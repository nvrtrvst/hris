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
        $isAdmin = $user && in_array($user->role, ['superadmin', 'admin_unit']);
        $query = Penggajian::with('pegawai');
        
        if ($user && $user->role === 'admin_unit') {
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
        $isAdmin = $user && in_array($user->role, ['superadmin', 'admin_unit']);
        if (!$isAdmin) abort(403, 'Akses ditolak.');
        
        $request->validate([
            'month' => 'required|string',
            'year' => 'required|string',
        ]);
        
        $periode = $request->month . '-' . $request->year;
        $month = $request->month;
        $year = $request->year;

        $query = Pegawai::where('status_aktif', 'aktif')->with('komponenGaji');
        
        if ($user->role === 'admin_unit') {
            $query->whereHas('units', function($q) use ($user) {
                $q->where('unit_sekolah.id', $user->unit_sekolah_id);
            });
        }
        
        $pegawais = $query->get();
        $globalKomponens = KomponenGaji::where('is_active', true)->get();

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

                // 1. Calculate Fixed & Percentage components from Pivot / Global
                // To keep it simple, we assume global components apply, but if pegawais have it in pivot, use pivot nominal.
                $pegawaiKomponens = $pegawai->komponenGaji->keyBy('id');

                foreach ($globalKomponens as $komponen) {
                    $nominal = 0;

                    if ($komponen->jenis === 'fixed') {
                        if ($pegawaiKomponens->has($komponen->id) && $pegawaiKomponens[$komponen->id]->pivot->nominal !== null) {
                            $nominal = $pegawaiKomponens[$komponen->id]->pivot->nominal;
                        } else {
                            $nominal = $komponen->nilai_default ?? 0;
                        }
                    } elseif ($komponen->jenis === 'persentase') {
                        // Persentase is calculated against totalPendapatan so far (e.g. Basic Salary)
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

                        // [FIX] Optimasi: 6 query → 1 query menggunakan groupBy
                        $attendanceCounts = Presensi::where('pegawai_id', $pegawai->id)
                            ->whereMonth('tanggal', $month)
                            ->whereYear('tanggal', $year)
                            ->selectRaw('status, count(*) as total')
                            ->groupBy('status')
                            ->pluck('total', 'status');

                        $countHadir = $attendanceCounts->get('hadir', 0);
                        $countTelat = $attendanceCounts->get('telat', 0);
                        $countAlpa = $attendanceCounts->get('alpa', 0);
                        $countSakit = $attendanceCounts->get('sakit', 0);
                        $countIzin = $attendanceCounts->get('izin', 0);
                        $countCuti = $attendanceCounts->get('cuti', 0);

                        // Example logic: if nama contains 'Telat', multiply by countTelat
                        if (stripos($komponen->nama, 'telat') !== false) {
                            $nominal = $rate * $countTelat;
                        } elseif (stripos($komponen->nama, 'alpa') !== false) {
                            $nominal = $rate * $countAlpa;
                        } elseif (stripos($komponen->nama, 'makan') !== false || stripos($komponen->nama, 'transport') !== false || stripos($komponen->nama, 'hadir') !== false) {
                            // Uang Makan / Transport / Hadir diberikan saat benar-benar Hadir Fisik (+ Telat)
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
                                
                                // Ambil nominal dari skala masa bakti sesuai tahun. Jika > skala maksimal, gunakan skala tertinggi.
                                $skala = \App\Models\SkalaMasaBakti::where('masa_kerja_tahun', '<=', $yearsOfService)
                                            ->orderBy('masa_kerja_tahun', 'desc')
                                            ->first();
                                
                                $nominal = $skala ? $skala->nominal_gaji : 0;
                            } else {
                                $nominal = 0;
                            }
                        }
                    } elseif ($komponen->jenis === 'dinamis_jam_mengajar') {
                        $rate = $pegawaiKomponens->has($komponen->id) && $pegawaiKomponens[$komponen->id]->pivot->nominal !== null
                            ? $pegawaiKomponens[$komponen->id]->pivot->nominal
                            : ($komponen->nilai_default ?? 0);

                        $jadwals = \App\Models\Jadwal::where('pegawai_id', $pegawai->id)
                            ->with('unitSekolah') // Load relasi unit sekolah
                            ->get();
                        
                        $totalHoursWeekly = 0;
                        foreach ($jadwals as $jadwal) {
                            $unit = $jadwal->unitSekolah;
                            $matchUnit = false;
                            
                            // Jika komponen bernama "Honor SMK", mesin akan mengecek apakah jadwal ini milik unit SMK
                            if ($unit) {
                                if (stripos($komponen->nama, $unit->singkatan) !== false || stripos($komponen->nama, $unit->nama) !== false) {
                                    $matchUnit = true;
                                }
                            }
                            
                            // Cek apakah komponen menyebut nama unit spesifik
                            $isSpecific = \App\Models\UnitSekolah::all()->contains(function($u) use ($komponen) {
                                return stripos($komponen->nama, $u->singkatan) !== false || stripos($komponen->nama, $u->nama) !== false;
                            });

                            if (!$isSpecific) {
                                // Jika komponen tidak spesifik menyebutkan unit, tolak (jangan hitung) untuk menghindari double count
                                break;
                            }

                            if (!$matchUnit) {
                                continue; // Skip jadwal ini karena bukan untuk unit yang dimaksud komponen
                            }

                            $mulai = \Carbon\Carbon::parse($jadwal->jam_mulai);
                            $selesai = \Carbon\Carbon::parse($jadwal->jam_selesai);
                            $totalHoursWeekly += $mulai->diffInMinutes($selesai) / 60;
                        }
                        
                        // Asumsi 1 bulan = 4 minggu pelajaran efektif
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

                // Create Penggajian Record
                $penggajian = Penggajian::create([
                    'pegawai_id' => $pegawai->id,
                    'periode_bulan' => $periode,
                    'tanggal_generate' => Carbon::today(),
                    'total_pendapatan' => $totalPendapatan,
                    'total_potongan' => $totalPotongan,
                    'gaji_bersih' => $totalPendapatan - $totalPotongan,
                    'status' => 'draft',
                ]);

                // Create Details
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
        $isAdmin = $user && in_array($user->role, ['superadmin', 'admin_unit']);
        if (!$isAdmin) abort(403, 'Akses ditolak.');
        
        if ($user->role === 'superadmin') {
            abort(403, 'Hanya Admin Unit yang berhak.');
        }

        $request->validate(['periode_bulan' => 'required|string']);
        
        $query = Penggajian::where('periode_bulan', $request->periode_bulan)->where('status', 'draft');
        
        if ($user->role === 'admin_unit') {
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
        $isAdmin = $user && in_array($user->role, ['superadmin', 'admin_unit']);
        if (!$isAdmin) abort(403, 'Akses ditolak.');
        
        if ($user->role === 'superadmin') {
            abort(403, 'Hanya Admin Unit yang berhak.');
        }

        $penggajian = Penggajian::with('pegawai')->findOrFail($id);
        
        if ($user->role === 'admin_unit' && $penggajian->pegawai->unit_sekolah_id !== $user->unit_sekolah_id) {
            abort(403, 'Akses ditolak.');
        }
        
        $penggajian->update(['status' => 'finalized']);
        
        return redirect()->back()->with('message', 'Satu data penggajian berhasil di-Finalisasi.');
    }

    public function destroyPeriod(Request $request)
    {
        $user = auth()->user();
        $isAdmin = $user && in_array($user->role, ['superadmin', 'admin_unit']);
        if (!$isAdmin) abort(403, 'Akses ditolak.');
        
        if ($user->role === 'superadmin') {
            abort(403, 'Hanya Admin Unit yang berhak.');
        }

        $request->validate(['periode_bulan' => 'required|string']);
        
        $query = Penggajian::where('periode_bulan', $request->periode_bulan)->where('status', 'draft');
        
        if ($user->role === 'admin_unit') {
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
        $isAdmin = $user && in_array($user->role, ['superadmin', 'admin_unit']);
        if (!$isAdmin) abort(403, 'Akses ditolak.');
        
        if ($user->role === 'superadmin') {
            abort(403, 'Hanya Admin Unit yang berhak.');
        }

        $penggajian = Penggajian::with('pegawai')->findOrFail($id);
        
        if ($user->role === 'admin_unit' && $penggajian->pegawai->unit_sekolah_id !== $user->unit_sekolah_id) {
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
        $isAdminUnit = $user && $user->role === 'admin_unit';
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
