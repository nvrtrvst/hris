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

    public function generate(Request $request)
    {
        $user = auth()->user();
        $isAdmin = $user && in_array($user->role, ['superadmin', 'admin_unit']);
        if (!$isAdmin) abort(403, 'Akses ditolak.');
        
        if ($user->role === 'superadmin') {
            abort(403, 'Hanya Admin Unit yang berhak melakukan Generate Payroll.');
        }

        $request->validate([
            'periode_bulan' => 'required|date_format:m-Y',
        ]);
        
        $periode = $request->periode_bulan;
        $month = substr($periode, 0, 2);
        $year = substr($periode, 3, 4);

        $pegawais = Pegawai::where('status_aktif', 'aktif')
            ->whereHas('units', function($q) use ($user) {
                $q->where('unit_sekolah.id', $user->unit_sekolah_id);
            })
            ->with('komponenGaji')->get();
        $globalKomponens = KomponenGaji::where('is_active', true)->get();

        DB::beginTransaction();
        try {
            foreach ($pegawais as $pegawai) {
                // Prevent duplicate generation
                $existing = Penggajian::where('pegawai_id', $pegawai->id)
                                      ->where('periode_bulan', $periode)
                                      ->lockForUpdate()
                                      ->first();
                if ($existing) continue;

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
                        // Calculate based on attendance
                        $countHadir = Presensi::where('pegawai_id', $pegawai->id)
                            ->whereMonth('tanggal', $month)
                            ->whereYear('tanggal', $year)
                            ->where('status', 'hadir')
                            ->count();
                            
                        $countTelat = Presensi::where('pegawai_id', $pegawai->id)
                            ->whereMonth('tanggal', $month)
                            ->whereYear('tanggal', $year)
                            ->where('status', 'telat')
                            ->count();
                            
                        $countAlpa = Presensi::where('pegawai_id', $pegawai->id)
                            ->whereMonth('tanggal', $month)
                            ->whereYear('tanggal', $year)
                            ->where('status', 'alpa')
                            ->count();
                            
                        $countSakit = Presensi::where('pegawai_id', $pegawai->id)
                            ->whereMonth('tanggal', $month)
                            ->whereYear('tanggal', $year)
                            ->where('status', 'sakit')
                            ->count();
                            
                        $countIzin = Presensi::where('pegawai_id', $pegawai->id)
                            ->whereMonth('tanggal', $month)
                            ->whereYear('tanggal', $year)
                            ->where('status', 'izin')
                            ->count();
                            
                        $countCuti = Presensi::where('pegawai_id', $pegawai->id)
                            ->whereMonth('tanggal', $month)
                            ->whereYear('tanggal', $year)
                            ->where('status', 'cuti')
                            ->count();

                        // Example logic: if nama contains 'Telat', multiply by countTelat
                        if (stripos($komponen->nama, 'telat') !== false) {
                            $nominal = ($komponen->nilai_default ?? 0) * $countTelat;
                        } elseif (stripos($komponen->nama, 'alpa') !== false) {
                            // [ATURAN IZIN/ALPA]
                            // Jika Izin memotong gaji seperti alpa, tambahkan $countIzin ke $countAlpa
                            // Misal: $totalMangkir = $countAlpa + $countIzin;
                            // Jika jatah cuti habis dan ingin memotong, bisa tambahkan logicnya di sini.
                            $nominal = ($komponen->nilai_default ?? 0) * $countAlpa;
                        } elseif (stripos($komponen->nama, 'makan') !== false || stripos($komponen->nama, 'transport') !== false) {
                            // [ATURAN MAKAN & TRANSPORT]
                            // Uang Makan / Transport hanya diberikan saat benar-benar Hadir Fisik (+ Telat)
                            $nominal = ($komponen->nilai_default ?? 0) * ($countHadir + $countTelat);
                        }
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
            return redirect()->back()->with('message', "Penggajian periode {$periode} berhasil digenerate.");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => 'Gagal generate: ' . $e->getMessage()]);
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
