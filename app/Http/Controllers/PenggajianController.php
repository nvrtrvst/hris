<?php

namespace App\Http\Controllers;

use App\Models\Penggajian;
use App\Models\PenggajianDetail;
use App\Models\Pegawai;
use App\Models\KomponenGaji;
use App\Models\Presensi;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PenggajianController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $isAdmin = $user && $user->can('view_payroll');
        $query = Penggajian::with('pegawai');
        
        if ($user && $user->unit_sekolah_id && !$user->can('view_all_units')) {
            $query->whereHas('pegawai', function($q) use ($user) {
                $q->where('unit_sekolah_id', $user->unit_sekolah_id);
            });
        } elseif (!$isAdmin) {
            $pegawai = Pegawai::where('user_id', auth()->id())->first();
            if ($pegawai) {
                $query->where('pegawai_id', $pegawai->id);
            } else {
                $query->where('id', -1); // Tidak ada data
            }
        }
        
        if ($request->filled('periode_bulan')) {
            $query->where('periode_bulan', $request->periode_bulan);
        }
        
        $penggajians = $query->orderBy('periode_bulan', 'desc')->paginate(10);
        
        return inertia('Payroll/Index', [
            'penggajians' => $penggajians,
            'filters' => $request->only(['periode_bulan'])
        ]);
    }

    public function indexRun(Request $request)
    {
        // Step 1: Layar Pilih Periode
        return inertia('Payroll/Run/Index');
    }

        public function createDraft(Request $request)
    {
        $user = auth()->user();
        $isAdmin = $user && $user->can('view_payroll');
        if (!$isAdmin) abort(403, 'Akses ditolak.');
        
        $request->validate([
            'month' => 'required|string',
            'year' => 'required|string',
        ]);
        
        $periode = $request->month . '-' . $request->year;
        $month = $request->month;
        $year = $request->year;

        // [FIX] N+1: Eager load komponenGaji dan jadwals.unitSekolah
        $query = Pegawai::where('status_aktif', 'aktif')->with(['komponenGaji', 'jadwals.unitSekolah']);
        
        if ($user && $user->unit_sekolah_id && !$user->can('view_all_units')) {
            $query->whereHas('units', function($q) use ($user) {
                $q->where('unit_sekolah.id', $user->unit_sekolah_id);
            });
        }
        
        $pegawais = $query->get();

        // [FIX] N+1: Fetch data referensi di luar loop
        $globalKomponens = KomponenGaji::where('is_active', true)->get();
        $skalas = \App\Models\SkalaMasaBakti::orderBy('masa_kerja_tahun', 'desc')->get();
        $allUnits = \App\Models\UnitSekolah::all();

        // [FIX] N+1: Group Presensi untuk semua pegawai sekaligus
        $attendanceRaw = Presensi::whereMonth('tanggal', $month)
            ->whereYear('tanggal', $year)
            ->selectRaw('pegawai_id, status, count(*) as total')
            ->groupBy('pegawai_id', 'status')
            ->get();
        
        $attendanceByPegawai = $attendanceRaw->groupBy('pegawai_id');

        DB::beginTransaction();
        try {
            foreach ($pegawais as $pegawai) {
                // Prevent duplicate generation for finalized payroll
                $existing = Penggajian::where('pegawai_id', $pegawai->id)
                                      ->where('periode_bulan', $periode)
                                      ->lockForUpdate()
                                      ->first();
                
                if ($existing) {
                    if ($existing->status === 'final') {
                        continue;
                    }
                    // Jika masih draft, kita hapus yang lama agar bisa direkalkulasi (overwrite)
                    $existing->details()->delete();
                    $existing->delete();
                }

                $totalPendapatan = 0;
                $totalPotongan = 0;
                $details = [];

                $pegawaiKomponens = $pegawai->komponenGaji->keyBy('id');

                // Extract Pegawai Attendance once
                $pAtt = $attendanceByPegawai->get($pegawai->id, collect());
                $countHadir = $pAtt->where('status', 'hadir')->sum('total');
                $countTelat = $pAtt->where('status', 'telat')->sum('total');
                $countAlpa = $pAtt->where('status', 'alpa')->sum('total');
                $countSakit = $pAtt->where('status', 'sakit')->sum('total');
                $countIzin = $pAtt->where('status', 'izin')->sum('total');
                $countCuti = $pAtt->where('status', 'cuti')->sum('total');

                foreach ($globalKomponens as $komponen) {
                    $nominal = 0;

                    if ($komponen->jenis === 'fixed') {
                        if ($pegawaiKomponens->has($komponen->id) && $pegawaiKomponens[$komponen->id]->pivot->nominal !== null) {
                            $nominal = $pegawaiKomponens[$komponen->id]->pivot->nominal;
                        } else {
                            $nominal = $komponen->nilai_default ?? 0;
                        }
                    } elseif ($komponen->jenis === 'persentase') {
                        $gajiPokok = $globalKomponens->first(function ($k) {
                            return stripos($k->nama, 'Gaji Pokok') !== false || stripos($k->nama, 'Basic Salary') !== false;
                        });
                        $gajiPokokId = $gajiPokok ? $gajiPokok->id : null;

                        $baseSalary = 0;
                        if ($gajiPokokId && $pegawaiKomponens->has($gajiPokokId) && $pegawaiKomponens[$gajiPokokId]->pivot->nominal !== null) {
                            $baseSalary = $pegawaiKomponens[$gajiPokokId]->pivot->nominal;
                        } elseif ($gajiPokok) {
                            $baseSalary = $gajiPokok->nilai_default ?? 0;
                        }
                        $nominal = ($komponen->nilai_default / 100) * $baseSalary;
                    } elseif ($komponen->jenis === 'dinamis_kehadiran') {
                        $rate = $pegawaiKomponens->has($komponen->id) && $pegawaiKomponens[$komponen->id]->pivot->nominal !== null
                            ? $pegawaiKomponens[$komponen->id]->pivot->nominal
                            : ($komponen->nilai_default ?? 0);

                        if (stripos($komponen->nama, 'telat') !== false) {
                            $nominal = $rate * $countTelat;
                        } elseif (stripos($komponen->nama, 'alpa') !== false) {
                            $nominal = $rate * $countAlpa;
                        } elseif (stripos($komponen->nama, 'makan') !== false || stripos($komponen->nama, 'transport') !== false || stripos($komponen->nama, 'hadir') !== false) {
                            $nominal = $rate * ($countHadir + $countTelat);
                        } else {
                            $nominal = 0;
                        }
                    } elseif ($komponen->jenis === 'dinamis_masa_bakti') {
                        if ($pegawaiKomponens->has($komponen->id) && $pegawaiKomponens[$komponen->id]->pivot->nominal !== null) {
                            $nominal = $pegawaiKomponens[$komponen->id]->pivot->nominal;
                        } else {
                            if ($pegawai->tanggal_mulai_kerja) {
                                $joinDate = \Carbon\Carbon::parse($pegawai->tanggal_mulai_kerja);
                                $yearsOfService = $joinDate->diffInYears(\Carbon\Carbon::now());
                                
                                $skala = $skalas->first(function($item) use ($yearsOfService) {
                                    return $item->masa_kerja_tahun <= $yearsOfService;
                                });
                                $nominal = $skala ? $skala->nominal_gaji : 0;
                            } else {
                                $nominal = 0;
                            }
                        }
                    } elseif ($komponen->jenis === 'dinamis_jam_mengajar') {
                        $rate = $pegawaiKomponens->has($komponen->id) && $pegawaiKomponens[$komponen->id]->pivot->nominal !== null
                            ? $pegawaiKomponens[$komponen->id]->pivot->nominal
                            : ($komponen->nilai_default ?? 0);

                        $totalHoursWeekly = 0;
                        
                        $isSpecific = $allUnits->contains(function($u) use ($komponen) {
                            return stripos($komponen->nama, $u->singkatan) !== false || stripos($komponen->nama, $u->nama) !== false;
                        });

                        if ($isSpecific) {
                            foreach ($pegawai->jadwals as $jadwal) {
                                $unit = $jadwal->unitSekolah;
                                if ($unit && (stripos($komponen->nama, $unit->singkatan) !== false || stripos($komponen->nama, $unit->nama) !== false)) {
                                    $mulai = \Carbon\Carbon::parse($jadwal->jam_mulai);
                                    $selesai = \Carbon\Carbon::parse($jadwal->jam_selesai);
                                    $totalHoursWeekly += $mulai->diffInMinutes($selesai) / 60;
                                }
                            }
                        }
                        
                        $totalHoursMonthly = $totalHoursWeekly * 4;
                        $nominal = $rate * $totalHoursMonthly;
                    }

                    if ($nominal > 0) {
                        if ($komponen->tipe === 'pendapatan') {
                            $totalPendapatan += $nominal;
                        } else {
                            $totalPotongan += $nominal;
                        }

                        $details[] = [
                            'komponen_gaji_id' => $komponen->id,
                            'nama_komponen' => $komponen->nama,
                            'tipe' => $komponen->tipe,
                            'nominal' => $nominal,
                        ];
                    }
                }

                $penggajian = Penggajian::create([
                    'pegawai_id' => $pegawai->id,
                    'periode_bulan' => $periode,
                    'tanggal_generate' => Carbon::today(),
                    'total_pendapatan' => $totalPendapatan,
                    'total_potongan' => $totalPotongan,
                    'gaji_bersih' => $totalPendapatan - $totalPotongan,
                    'status' => 'draft',
                ]);

                foreach ($details as $d) {
                    $d['penggajian_id'] = $penggajian->id;
                    PenggajianDetail::create($d);
                }
            }
            DB::commit();
            return redirect()->route('penggajian.run.worksheet', ['month' => $month, 'year' => $year]);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => 'Gagal generate: ' . $e->getMessage()]);
        }
    }

    public function worksheet($month, $year)
    {
        $periode = $month . '-' . $year;
        
        // Cek apakah ada draft untuk periode ini
        $drafts = Penggajian::where('periode_bulan', $periode)->where('status', 'draft')->count();
        if ($drafts === 0) {
            return redirect()->route('penggajian.run')->withErrors(['error' => 'Draft belum di-generate untuk periode ini.']);
        }
        
        return inertia('Payroll/Run/Worksheet', [
            'month' => $month,
            'year' => $year,
            'periode' => $periode
        ]);
    }
    
    public function getWorksheetData($month, $year)
    {
        $periode = $month . '-' . $year;
        
        $penggajians = Penggajian::with(['pegawai', 'details'])
            ->where('periode_bulan', $periode)
            ->where('status', 'draft')
            ->get();
            
        return response()->json($penggajians);
    }
    
    public function saveWorksheet(Request $request, $month, $year)
    {
        $periode = $month . '-' . $year;
        
        $request->validate([
            'penggajian_id' => 'required|exists:penggajian,id',
            'details' => 'required|array',
        ]);
        
        DB::beginTransaction();
        try {
            $penggajian = Penggajian::findOrFail($request->penggajian_id);
            if ($penggajian->status !== 'draft') {
                throw new \Exception('Hanya status draft yang bisa diubah.');
            }
            
            // Hapus semua detail lama
            $penggajian->details()->delete();
            
            $totalPendapatan = 0;
            $totalPotongan = 0;
            
            foreach ($request->details as $d) {
                // Konversi dari data array
                $nominal = (float) $d['nominal'];
                if ($nominal > 0) {
                    if ($d['tipe'] === 'pendapatan') {
                        $totalPendapatan += $nominal;
                    } else {
                        $totalPotongan += $nominal;
                    }
                    
                    PenggajianDetail::create([
                        'penggajian_id' => $penggajian->id,
                        'komponen_gaji_id' => $d['komponen_gaji_id'] ?? null,
                        'nama_komponen' => $d['nama_komponen'],
                        'tipe' => $d['tipe'],
                        'nominal' => $nominal,
                    ]);
                }
            }
            
            // Update Gaji Bersih
            $penggajian->update([
                'total_pendapatan' => $totalPendapatan,
                'total_potongan' => $totalPotongan,
                'gaji_bersih' => $totalPendapatan - $totalPotongan,
            ]);
            
            DB::commit();
            return response()->json(['message' => 'Berhasil disimpan', 'penggajian' => $penggajian->load('details')]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
    
    public function finalizeWorksheet(Request $request, $month, $year)
    {
        $periode = $month . '-' . $year;
        
        DB::beginTransaction();
        try {
            Penggajian::where('periode_bulan', $periode)
                ->where('status', 'draft')
                ->lockForUpdate()
                ->update(['status' => 'final']);
                
            DB::commit();
            return redirect()->route('penggajian.index')->with('message', 'Payroll berhasil dikunci (Finalize).');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => 'Gagal finalize: ' . $e->getMessage()]);
        }
    }

    public function finalizePeriod(Request $request)
    {
        $user = auth()->user();
        $isAdmin = $user && $user->can('view_payroll');
        if (!$isAdmin) abort(403, 'Akses ditolak.');
        
        if ($user->role === 'superadmin') {
            abort(403, 'Hanya Admin Unit yang berhak.');
        }

        $request->validate(['periode_bulan' => 'required|string']);
        
        $query = Penggajian::where('periode_bulan', $request->periode_bulan)->where('status', 'draft');
        
        if ($user && $user->unit_sekolah_id && !$user->can('view_all_units')) {
            $query->whereHas('pegawai', function($q) use ($user) {
                $q->where('unit_sekolah_id', $user->unit_sekolah_id);
            });
        }
        
        $updated = $query->update(['status' => 'finalized']);
            
        return redirect()->back()->with('message', "Sebanyak {$updated} data slip gaji periode {$request->periode_bulan} berhasil di-Finalisasi.");
    }

    public function finalize($id)
    {
        $user = auth()->user();
        $isAdmin = $user && $user->can('view_payroll');
        if (!$isAdmin) abort(403, 'Akses ditolak.');
        
        if ($user->role === 'superadmin') {
            abort(403, 'Hanya Admin Unit yang berhak.');
        }

        $penggajian = Penggajian::with('pegawai')->findOrFail($id);
        
        if ($user && $user->unit_sekolah_id && !$user->can('view_all_units') && $penggajian->pegawai->unit_sekolah_id !== $user->unit_sekolah_id) {
            abort(403, 'Akses ditolak.');
        }
        
        $penggajian->update(['status' => 'finalized']);
        
        return redirect()->back()->with('message', 'Satu data penggajian berhasil di-Finalisasi.');
    }

    public function destroyPeriod(Request $request)
    {
        $user = auth()->user();
        $isAdmin = $user && $user->can('view_payroll');
        if (!$isAdmin) abort(403, 'Akses ditolak.');
        
        if ($user->role === 'superadmin') {
            abort(403, 'Hanya Admin Unit yang berhak.');
        }

        $request->validate(['periode_bulan' => 'required|string']);
        
        $query = Penggajian::where('periode_bulan', $request->periode_bulan)->where('status', 'draft');
        
        if ($user && $user->unit_sekolah_id && !$user->can('view_all_units')) {
            $query->whereHas('pegawai', function($q) use ($user) {
                $q->where('unit_sekolah_id', $user->unit_sekolah_id);
            });
        }
        
        $deleted = $query->delete();
            
        return redirect()->back()->with('message', "Sebanyak {$deleted} data draft slip gaji periode {$request->periode_bulan} berhasil dihapus/dibatalkan.");
    }

    public function destroy($id)
    {
        $user = auth()->user();
        $isAdmin = $user && $user->can('view_payroll');
        if (!$isAdmin) abort(403, 'Akses ditolak.');
        
        if ($user->role === 'superadmin') {
            abort(403, 'Hanya Admin Unit yang berhak.');
        }

        $penggajian = Penggajian::with('pegawai')->findOrFail($id);
        
        if ($user && $user->unit_sekolah_id && !$user->can('view_all_units') && $penggajian->pegawai->unit_sekolah_id !== $user->unit_sekolah_id) {
            abort(403, 'Akses ditolak.');
        }
        
        if ($penggajian->status !== 'draft') {
            return redirect()->back()->withErrors(['error' => 'Hanya slip gaji berstatus DRAFT yang bisa dihapus.']);
        }
        $penggajian->delete();
        
        return redirect()->back()->with('message', 'Draft slip gaji berhasil dihapus.');
    }

    public function show($id)
    {
        $user = auth()->user();
        $isSuperadmin = $user && $user->role === 'superadmin';
        $isAdminUnit = $user && $user->unit_sekolah_id && !$user->can('view_all_units');
        $penggajian = Penggajian::with(['pegawai.jabatans', 'pegawai.units', 'details'])->findOrFail($id);

        if ($isAdminUnit) {
            if (!$penggajian->pegawai->units->pluck('id')->contains($user->unit_sekolah_id)) {
                abort(403, 'Akses ditolak.');
            }
        } elseif (!$isSuperadmin) {
            $pegawai = Pegawai::where('user_id', $user->id)->first();
            if (!$pegawai || $penggajian->pegawai_id !== $pegawai->id) {
                abort(403, 'Akses ditolak.');
            }
        }

        return inertia('Payroll/Show', ['penggajian' => $penggajian]);
    }
}
