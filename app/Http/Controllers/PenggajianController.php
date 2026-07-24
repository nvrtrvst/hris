<?php

namespace App\Http\Controllers;

use App\Models\KomponenGaji;
use App\Models\Pegawai;
use App\Models\Penggajian;
use App\Models\PenggajianDetail;
use App\Models\Presensi;
use App\Models\SkalaMasaBakti;
use App\Models\UnitSekolah;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PenggajianController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $isAdmin = $user && $user->can('view_payroll');
        $query = Penggajian::with('pegawai');

        if (! $isAdmin) {
            $pegawai = Pegawai::where('user_id', auth()->id())->first();
            if ($pegawai) {
                $query->where('pegawai_id', $pegawai->id);
            } else {
                $query->where('id', -1);
            }
        } elseif ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $query->whereHas('pegawai', function ($q) use ($user) {
                $q->forUnit($user->unit_sekolah_id);
            });
        }

        if ($request->filled('periode_bulan')) {
            $query->where('periode_bulan', $request->periode_bulan);
        }

        $penggajians = $query->orderBy('periode_bulan', 'desc')->paginate(10);

        return inertia('Payroll/Index', [
            'penggajians' => $penggajians,
            'filters' => $request->only(['periode_bulan']),
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
        if (! $isAdmin) {
            abort(403, 'Akses ditolak.');
        }

        $request->validate([
            'month' => 'required|string',
            'year' => 'required|string',
        ]);

        $periode = $request->month.'-'.$request->year;
        $month = $request->month;
        $year = $request->year;

        // [FIX] N+1: Eager load komponenGaji dan jadwals.unitSekolah
        $query = Pegawai::where('status_aktif', 'aktif')->with(['komponenGaji', 'jadwals.unitSekolah']);

        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $query->whereHas('units', function ($q) use ($user) {
                $q->where('unit_sekolah.id', $user->unit_sekolah_id);
            });
        }

        $pegawais = $query->get();

        // [FIX] N+1: Fetch data referensi di luar loop
        $globalKomponens = KomponenGaji::where('is_active', true)->get();
        $skalas = SkalaMasaBakti::orderBy('masa_kerja_tahun', 'desc')->get();

        $periodeStart = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $periodeEnd = Carbon::createFromDate($year, $month, 1)->endOfMonth();
        $today = Carbon::today();
        $isCurrentMonth = ((int) $today->year === (int) $year && (int) $today->month === (int) $month);
        $attendanceCutoff = $isCurrentMonth ? $today : $periodeEnd;

        $unitScope = ($user && $user->unit_sekolah_id && ! $user->can('view_all_units'))
            ? $user->unit_sekolah_id
            : null;

        // [FIX] N+1: Group Presensi untuk semua pegawai sekaligus (dengan scope unit)
        $presensiQuery = Presensi::whereBetween('tanggal', [
            $periodeStart->format('Y-m-d'),
            $periodeEnd->format('Y-m-d'),
        ]);
        if ($unitScope) {
            $presensiQuery->where(function ($q) use ($unitScope) {
                $q->where('unit_sekolah_id', $unitScope)->orWhereNull('unit_sekolah_id');
            });
        }
        $attendanceRaw = $presensiQuery
            ->selectRaw('pegawai_id, status, count(*) as total')
            ->groupBy('pegawai_id', 'status')
            ->get();

        $attendanceByPegawai = $attendanceRaw->groupBy('pegawai_id');

        // [FIX] N+1: Prefetch presensi lembur untuk semua pegawai sekaligus
        $lemburQuery = Presensi::whereIn('pegawai_id', $pegawais->pluck('id'))
            ->where('is_lembur', true)
            ->where('lembur_status', 'disetujui')
            ->whereNotNull('jam_masuk')
            ->whereNotNull('jam_keluar')
            ->whereBetween('tanggal', [$periodeStart, $attendanceCutoff])
            ->get();
        $lemburByPegawai = $lemburQuery->groupBy('pegawai_id');

        DB::beginTransaction();
        try {
            foreach ($pegawais as $pegawai) {
                // Prevent duplicate generation for finalized payroll
                $existing = Penggajian::where('pegawai_id', $pegawai->id)
                    ->where('periode_bulan', $periode)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    if ($existing->status !== 'draft') {
                        continue;
                    }
                    // Jika masih draft, kita hapus yang lama agar bisa direkalkulasi (overwrite)
                    $existing->details()->delete();
                    $existing->delete();
                }

                $pegawaiKomponens = $pegawai->komponenGaji->keyBy('id');
                $counts = $this->computeAttendance($pegawai, $attendanceByPegawai, $periodeStart, $attendanceCutoff);

                $totalPendapatan = '0.00';
                $totalPotongan = '0.00';
                $totalTaxable = '0.00';
                $details = [];

                foreach ($globalKomponens as $komponen) {
                    $nominal = round((float) $this->computeComponentNominal(
                        $komponen, $pegawai, $pegawaiKomponens, $globalKomponens, $counts, $skalas, $periodeEnd, $periodeStart, $attendanceCutoff, $lemburByPegawai
                    ), 2);

                    if ($nominal > 0) {
                        if ($komponen->tipe === 'pendapatan') {
                            $totalPendapatan = bcadd($totalPendapatan, (string) $nominal, 2);
                        } else {
                            $totalPotongan = bcadd($totalPotongan, (string) $nominal, 2);
                        }

                        if ($komponen->is_taxable && $komponen->tipe === 'pendapatan') {
                            $totalTaxable = bcadd($totalTaxable, (string) $nominal, 2);
                        }

                        $details[] = [
                            'komponen_gaji_id' => $komponen->id,
                            'nama_komponen' => $komponen->nama,
                            'tipe' => $komponen->tipe,
                            'nominal' => $nominal,
                            'is_taxable' => (bool) $komponen->is_taxable,
                        ];
                    }
                }

                $penggajian = Penggajian::create([
                    'pegawai_id' => $pegawai->id,
                    'periode_bulan' => $periode,
                    'tanggal_generate' => Carbon::today(),
                    'total_pendapatan' => (float) $totalPendapatan,
                    'total_potongan' => (float) $totalPotongan,
                    'gaji_bersih' => (float) bcsub($totalPendapatan, $totalPotongan, 2),
                    'total_taxable' => (float) $totalTaxable,
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

            return redirect()->back()->withErrors(['error' => 'Gagal generate: '.$e->getMessage()]);
        }
    }

    public function worksheet($month, $year)
    {
        $periode = $month.'-'.$year;

        // Cek apakah ada draft untuk periode ini
        $drafts = Penggajian::where('periode_bulan', $periode)->where('status', 'draft')->count();
        if ($drafts === 0) {
            return redirect()->route('penggajian.run')->withErrors(['error' => 'Draft belum di-generate untuk periode ini.']);
        }

        return inertia('Payroll/Run/Worksheet', [
            'month' => $month,
            'year' => $year,
            'periode' => $periode,
        ]);
    }

    public function getWorksheetData($month, $year)
    {
        $periode = $month.'-'.$year;

        $penggajians = Penggajian::with(['pegawai.pengajuanIzins', 'details'])
            ->where('periode_bulan', $periode)
            ->where('status', 'draft')
            ->get();

        return response()->json($penggajians);
    }

    public function saveWorksheet(Request $request, $month, $year)
    {
        $periode = $month.'-'.$year;

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

            $totalPendapatan = '0.00';
            $totalPotongan = '0.00';
            $totalTaxable = '0.00';

            foreach ($request->details as $d) {
                // Konversi dari data array
                $nominal = round((float) ($d['nominal'] ?? 0), 2);
                if ($nominal > 0) {
                    $tipe = $d['tipe'];
                    if ($tipe === 'pendapatan') {
                        $totalPendapatan = bcadd($totalPendapatan, (string) $nominal, 2);
                    } else {
                        $totalPotongan = bcadd($totalPotongan, (string) $nominal, 2);
                    }

                    $isTaxable = false;
                    if (! empty($d['komponen_gaji_id'])) {
                        $komponen = KomponenGaji::find($d['komponen_gaji_id']);
                        $isTaxable = $komponen ? (bool) $komponen->is_taxable : false;
                    }
                    if ($isTaxable && $tipe === 'pendapatan') {
                        $totalTaxable = bcadd($totalTaxable, (string) $nominal, 2);
                    }

                    PenggajianDetail::create([
                        'penggajian_id' => $penggajian->id,
                        'komponen_gaji_id' => $d['komponen_gaji_id'] ?? null,
                        'nama_komponen' => $d['nama_komponen'],
                        'tipe' => $tipe,
                        'nominal' => $nominal,
                        'is_taxable' => $isTaxable,
                    ]);
                }
            }

            // Update Gaji Bersih
            $penggajian->update([
                'total_pendapatan' => (float) $totalPendapatan,
                'total_potongan' => (float) $totalPotongan,
                'gaji_bersih' => (float) bcsub($totalPendapatan, $totalPotongan, 2),
                'total_taxable' => (float) $totalTaxable,
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
        $this->authorizePayrollModification();

        $periode = $month.'-'.$year;

        DB::beginTransaction();
        try {
            Penggajian::where('periode_bulan', $periode)
                ->where('status', 'draft')
                ->lockForUpdate()
                ->update(['status' => 'finalized']);

            DB::commit();

            return redirect()->route('penggajian.index')->with('message', 'Payroll berhasil dikunci (Finalize).');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->withErrors(['error' => 'Gagal finalize: '.$e->getMessage()]);
        }
    }

    public function finalizePeriod(Request $request)
    {
        $user = auth()->user();
        $isAdmin = $user && $user->can('view_payroll');
        if (! $isAdmin) {
            abort(403, 'Akses ditolak.');
        }

        if ($user->can('view_all_units')) {
            abort(403, 'Hanya Admin Unit yang berhak.');
        }

        $request->validate(['periode_bulan' => 'required|string']);

        $query = Penggajian::where('periode_bulan', $request->periode_bulan)->where('status', 'draft');

        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $query->whereHas('pegawai', function ($q) use ($user) {
                $q->forUnit($user->unit_sekolah_id);
            });
        }

        $updated = $query->update(['status' => 'finalized']);

        return redirect()->back()->with('message', "Sebanyak {$updated} data slip gaji periode {$request->periode_bulan} berhasil di-Finalisasi.");
    }

    public function finalize($id)
    {
        $this->authorizePayrollModification();

        $penggajian = Penggajian::with('pegawai')->findOrFail($id);

        if (! $this->userCanAccessPegawai($penggajian->pegawai_id)) {
            abort(403, 'Akses ditolak.');
        }

        $penggajian->update(['status' => 'finalized']);

        return redirect()->back()->with('message', 'Satu data penggajian berhasil di-Finalisasi.');
    }

    public function destroyPeriod(Request $request)
    {
        $this->authorizePayrollModification();

        $request->validate(['periode_bulan' => 'required|string']);

        $user = auth()->user();
        $query = Penggajian::where('periode_bulan', $request->periode_bulan)->where('status', 'draft');

        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $query->whereHas('pegawai', function ($q) use ($user) {
                $q->forUnit($user->unit_sekolah_id);
            });
        }

        $deleted = $query->delete();

        return redirect()->back()->with('message', "Sebanyak {$deleted} data draft slip gaji periode {$request->periode_bulan} berhasil dihapus/dibatalkan.");
    }

    public function destroy($id)
    {
        $this->authorizePayrollModification();

        $penggajian = Penggajian::with('pegawai')->findOrFail($id);

        if (! $this->userCanAccessPegawai($penggajian->pegawai_id)) {
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
        $isSuperadmin = $user && $user->can('view_all_units');
        $isAdminUnit = $user && $user->unit_sekolah_id && ! $user->can('view_all_units');
        $penggajian = Penggajian::with(['pegawai.jabatans', 'pegawai.units', 'details'])->findOrFail($id);

        if ($isAdminUnit) {
            if (! $penggajian->pegawai->units->pluck('id')->contains($user->unit_sekolah_id)) {
                abort(403, 'Akses ditolak.');
            }
        } elseif (! $isSuperadmin) {
            $pegawai = Pegawai::where('user_id', $user->id)->first();
            if (! $pegawai || $penggajian->pegawai_id !== $pegawai->id) {
                abort(403, 'Akses ditolak.');
            }
        }

        return inertia('Payroll/Show', ['penggajian' => $penggajian]);
    }

    public function markPaid($id)
    {
        $this->authorizePayrollModification();

        $penggajian = Penggajian::with('pegawai')->findOrFail($id);

        if (! $this->userCanAccessPegawai($penggajian->pegawai_id)) {
            abort(403, 'Akses ditolak.');
        }

        if ($penggajian->status !== 'finalized') {
            return redirect()->back()->withErrors(['error' => 'Hanya slip gaji berstatus FINALIZED yang bisa ditandai dibayar.']);
        }

        $penggajian->update(['status' => 'paid']);

        return redirect()->back()->with('message', 'Slip gaji berhasil ditandai DIBAYAR.');
    }

    /**
     * Hitung jumlah hari kerja dan kehadiran per status untuk pegawai.
     *
     * Logic:
     * 1. Count kehadiran berdasarkan status dari Presensi (hadir/telat/sakit/izin/cuti/alpa_manual)
     * 2. Calculate working days berdasarkan jadwal reguler ( jenis_jadwal != 'lembur')
     * 3. Auto-fill alpa: working days - (hadir + telat + sakit + izin + cuti)
     *
     * @param  Pegawai  $pegawai  Pegawai yang dihitung kehadiran
     * @param  Collection  $attendanceByPegawai  Data presensi grouping
     * @param  Carbon  $periodeStart  Awal periode penggajian
     * @param  Carbon  $attendanceCutoff  Batas cut-off presensi
     * @return array<string,int> ['hadir'=>X, 'telat'=>Y, 'alpa'=>Z, 'sakit'=>A, 'izin'=>B, 'cuti'=>C]
     */
    protected function computeAttendance(Pegawai $pegawai, $attendanceByPegawai, Carbon $periodeStart, Carbon $attendanceCutoff): array
    {
        $pAtt = $attendanceByPegawai->get($pegawai->id, collect());
        $countHadir = (int) $pAtt->where('status', 'hadir')->sum('total');
        $countTelat = (int) $pAtt->where('status', 'telat')->sum('total');
        $countAlpaManual = (int) $pAtt->where('status', 'alpa')->sum('total');
        $countSakit = (int) $pAtt->where('status', 'sakit')->sum('total');
        $countIzin = (int) $pAtt->where('status', 'izin')->sum('total');
        $countCuti = (int) $pAtt->where('status', 'cuti')->sum('total');

        // [FIX] Auto-alpha: hari kerja (dari jadwal, kecuali lembur) - (hadir/telat/izin/cuti disetujui)
        $workingDays = 0;
        foreach ($pegawai->jadwals as $jadwal) {
            if ($jadwal->jenis_jadwal === 'lembur') {
                continue;
            }
            $workingDays += $this->countWeekdayInRange($jadwal->hari, $periodeStart, $attendanceCutoff);
        }
        $presentOrLeave = $countHadir + $countTelat + $countSakit + $countIzin + $countCuti;
        $countAlpa = $countAlpaManual + max(0, $workingDays - $presentOrLeave);

        return [
            'hadir' => $countHadir,
            'telat' => $countTelat,
            'alpa' => $countAlpa,
            'sakit' => $countSakit,
            'izin' => $countIzin,
            'cuti' => $countCuti,
        ];
    }

    /**
     * Hitung nominal satu komponen gaji untuk pegawai pada periode tertentu.
     *
     * Mendukung beberapa jenis komponen:
     * - fixed: Override pegawai komponen nominal → nilai_default
     * - persentase: Hitung dari gaji pokok (base salary)
     * - dinamis_kehadiran: Rate × count (hadir/telat/alpa/sakit/izin/cuti/tunjangan)
     * - dinamis_masa_bakti: Lookup pada SkalaMasaBakti berdasarkan masa kerja
     * - dinamis_jam_mengajar: Rate × total jam jadwal dalam periode
     * - dinamis_lembur: Rate × total jam lembur disetujui
     *
     * @param  KomponenGaji  $komponen  Komponen gaji yang dihitung
     * @param  Pegawai  $pegawai  Pegawai yang dihitung gajinya
     * @param  Collection  $pegawaiKomponens  Komponen spesifik pegawai (pivot data)
     * @param  Collection  $globalKomponens  Semua komponen gaji aktif
     * @param  array  $counts  Array kehadiran per status dari computeAttendance
     * @param  Collection  $skalas  Skala masa bakti diurut descending
     * @param  Carbon  $periodeEnd  Akhir periode penggajian
     * @param  Carbon  $periodeStart  Awal periode penggajian
     * @param  Carbon  $attendanceCutoff  Batas cut-off presensi (untuk periode current)
     * @param  Collection|null  $lemburByPegawai  Data presensi lembur (prefetched)
     * @return float nominal komponen gaji
     */
    protected function computeComponentNominal(KomponenGaji $komponen, Pegawai $pegawai, $pegawaiKomponens, $globalKomponens, array $counts, $skalas, Carbon $periodeEnd, Carbon $periodeStart, Carbon $attendanceCutoff, $lemburByPegawai = null): float
    {
        $nominal = 0;

        if ($komponen->jenis === 'fixed') {
            if ($pegawaiKomponens->has($komponen->id) && $pegawaiKomponens[$komponen->id]->pivot->nominal !== null) {
                $nominal = $pegawaiKomponens[$komponen->id]->pivot->nominal;
            } else {
                $nominal = $komponen->nilai_default ?? 0;
            }
        } elseif ($komponen->jenis === 'persentase') {
            $gajiPokok = $this->findKomponenByKode($globalKomponens, 'gaji_pokok', ['Gaji Pokok', 'Basic Salary']);
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

            if ($this->isKehadiranType($komponen, 'kehadiran_telat', ['telat'])) {
                $nominal = $rate * $counts['telat'];
            } elseif ($this->isKehadiranType($komponen, 'kehadiran_alpa', ['alpa'])) {
                $nominal = $rate * $counts['alpa'];
            } elseif ($this->isKehadiranType($komponen, 'kehadiran_sakit', ['sakit'])) {
                $nominal = $rate * $counts['sakit'];
            } elseif ($this->isKehadiranType($komponen, 'kehadiran_izin', ['izin'])) {
                $nominal = $rate * $counts['izin'];
            } elseif ($this->isKehadiranType($komponen, 'kehadiran_cuti', ['cuti'])) {
                $nominal = $rate * $counts['cuti'];
            } elseif ($this->isKehadiranType($komponen, 'tunjangan_kehadiran', ['makan', 'transport', 'hadir'])) {
                $nominal = $rate * ($counts['hadir'] + $counts['telat']);
            } else {
                $nominal = 0;
            }
        } elseif ($komponen->jenis === 'dinamis_masa_bakti') {
            if ($pegawaiKomponens->has($komponen->id) && $pegawaiKomponens[$komponen->id]->pivot->nominal !== null) {
                $nominal = $pegawaiKomponens[$komponen->id]->pivot->nominal;
            } else {
                if ($pegawai->tanggal_mulai_kerja) {
                    $joinDate = Carbon::parse($pegawai->tanggal_mulai_kerja);
                    $yearsOfService = $joinDate->diffInYears($periodeEnd);

                    $skala = $skalas->first(function ($item) use ($yearsOfService) {
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

            $totalHoursMonthly = 0;
            foreach ($pegawai->jadwals as $jadwal) {
                $unit = $jadwal->unitSekolah;
                if ($komponen->unit_sekolah_id && (! $unit || $unit->id !== $komponen->unit_sekolah_id)) {
                    continue;
                }
                $mulai = Carbon::parse($jadwal->jam_mulai);
                $selesai = Carbon::parse($jadwal->jam_selesai);
                $sessionHours = $mulai->diffInMinutes($selesai) / 60;
                $occurrences = $this->countWeekdayInRange($jadwal->hari, $periodeStart, $attendanceCutoff);
                $totalHoursMonthly += $sessionHours * $occurrences;
            }
            $nominal = $rate * $totalHoursMonthly;
        } elseif ($komponen->jenis === 'dinamis_lembur') {
            $rate = $pegawaiKomponens->has($komponen->id) && $pegawaiKomponens[$komponen->id]->pivot->nominal !== null
                ? $pegawaiKomponens[$komponen->id]->pivot->nominal
                : ($komponen->nilai_default ?? 0);

            $totalMinutes = 0;
            if ($lemburByPegawai && $lemburByPegawai->has($pegawai->id)) {
                $totalMinutes = $lemburByPegawai[$pegawai->id]
                    ->sum(fn ($p) => Carbon::parse($p->jam_masuk)->diffInMinutes(Carbon::parse($p->jam_keluar)));
            }

            $nominal = $rate * ($totalMinutes / 60);
        }

        return (float) $nominal;
    }

    /**
     * Helper untuk mencari komponen berdasarkan kode dengan fallback ke pattern matching nama.
     * Ini backward compatibility untuk komponen yang sudah ada sebelum migration.
     *
     * @param  Collection  $komponens  Collection komponen yang dicari
     * @param  string  $targetKode  Kode target (primary lookup)
     * @param  array  $namePatterns  Array pattern untuk stripos fallback (opsional)
     * @return Komponen|null Komponen yang ditemukan atau null
     */
    private function findKomponenByKode($komponens, string $targetKode, array $namePatterns = []): ?KomponenGaji
    {
        // Priority 1: Exact kode match
        $komponen = $komponens->first(function ($k) use ($targetKode) {
            return $k->kode === $targetKode;
        });

        // Priority 2: Pattern matching nama (backward compatibility)
        if (! $komponen && ! empty($namePatterns)) {
            $komponen = $komponens->first(function ($k) use ($namePatterns) {
                foreach ($namePatterns as $pattern) {
                    if (stripos($k->nama, $pattern) !== false) {
                        return true;
                    }
                }

                return false;
            });
        }

        return $komponen;
    }

    /**
     * Helper untuk mengecek apakah komponen adalah jenis kehadiran tertentu.
     * Prioritas kode > pattern nama untuk backward compatibility.
     *
     * @param  KomponenGaji  $komponen  Komponen yang dicek
     * @param  string  $targetKode  Kode target (primary)
     * @param  array  $namePatterns  Array pattern untuk stripos fallback (opsional)
     * @return bool true jika komponen match target type
     */
    private function isKehadiranType(KomponenGaji $komponen, string $targetKode, array $namePatterns = []): bool
    {
        // Priority 1: Kode exact match
        if ($komponen->kode === $targetKode) {
            return true;
        }

        // Priority 2: Pattern matching nama (backward compatibility)
        if (! empty($namePatterns)) {
            foreach ($namePatterns as $pattern) {
                if (stripos($komponen->nama, $pattern) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Hitung jumlah hari tertentu (nama Indonesia) dalam rentang tanggal [start, end].
     * Optimized: O(1) formula daripada loop O(n).
     */
    protected function countWeekdayInRange(string $hari, Carbon $start, Carbon $end): int
    {
        $map = [
            'Minggu' => 0, 'Senin' => 1, 'Selasa' => 2, 'Rabu' => 3,
            'Kamis' => 4, 'Jumat' => 5, 'Sabtu' => 6,
        ];
        $target = $map[$hari] ?? null;
        if ($target === null || $start->gt($end)) {
            return 0;
        }

        $totalDays = $start->diffInDays($end) + 1;
        $fullWeeks = intdiv($totalDays, 7);
        $remainderDays = $totalDays % 7;

        $count = $fullWeeks;

        $startDayOfWeek = $start->dayOfWeek;
        for ($i = 0; $i < $remainderDays; $i++) {
            if ((($startDayOfWeek + $i) % 7) === $target) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Authorize user untuk aksi payroll modification (finalize, destroy, markPaid).
     * Harus punya permission 'view_payroll' dan BUKAN superadmin (view_all_units).
     *
     * @throws AuthorizationException
     */
    private function authorizePayrollModification(): void
    {
        $user = auth()->user();
        if (! $user || ! $user->can('view_payroll')) {
            abort(403, 'Akses ditolak.');
        }

        if ($user->can('view_all_units')) {
            abort(403, 'Hanya Admin Unit yang berhak.');
        }
    }

    /**
     * Check apakah user punya akses ke pegawai tertentu.
     *
     * @param  mixed  $pegawaiId  ID Pegawai atau object Pegawai
     */
    private function userCanAccessPegawai($pegawaiId): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->can('view_all_units')) {
            return true;
        }

        if (! $user->unit_sekolah_id) {
            return false;
        }

        $pegawai = $pegawaiId instanceof Pegawai
            ? $pegawaiId
            : Pegawai::find($pegawaiId);

        return $pegawai && $pegawai->belongsToUnit($user->unit_sekolah_id);
    }
}
