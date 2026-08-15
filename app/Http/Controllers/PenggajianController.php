<?php

namespace App\Http\Controllers;

use App\Exports\TransferBankExport;
use App\Models\KomponenGaji;
use App\Models\Pegawai;
use App\Models\Penggajian;
use App\Models\PenggajianDetail;
use App\Models\SkalaMasaBakti;
use App\Models\UnitSekolah;
use App\Services\PresensiAggregator;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class PenggajianController extends Controller
{
    public function __construct(
        private readonly PresensiAggregator $presensiAggregator,
    ) {}

    public function index(Request $request)
    {
        $request->validate(['status' => 'nullable|in:draft,finalized,paid']);

        $user = auth()->user();
        $isAdmin = $user && $user->can('view_payroll');
        $query = Penggajian::with(['pegawai.units:id,singkatan,nama']);

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

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Stats ringkasan: 1 query agregat (tanpa memuat baris)
        $stats = (clone $query)->selectRaw("
            COUNT(*) as total,
            COALESCE(SUM(gaji_bersih), 0) as total_bersih,
            COALESCE(SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END), 0) as draft,
            COALESCE(SUM(CASE WHEN status = 'finalized' THEN 1 ELSE 0 END), 0) as finalized,
            COALESCE(SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END), 0) as paid
        ")->first();

        // Daftar periode unik untuk dropdown filter
        $periodeOptions = (clone $query)->select('periode_bulan')
            ->distinct()
            ->orderByDesc('periode_bulan')
            ->pluck('periode_bulan');

        $penggajians = $query->orderByDesc('periode_bulan')->orderByDesc('id')->paginate(10)->withQueryString();

        return inertia('Payroll/Index', [
            'penggajians' => $penggajians,
            'stats' => $stats,
            'periodeOptions' => $periodeOptions,
            'filters' => $request->only(['periode_bulan', 'status']),
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
        $query = Pegawai::where('status_aktif', 'aktif')->with(['komponenGaji', 'units', 'jadwals.unitSekolah']);

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

        // [PERF] Satu query scan presensi untuk SEMUA kebutuhan payroll,
        // agregasi di PHP (PresensiAggregator) — hemat 4 query scan.
        $presensiAgg = $this->presensiAggregator->aggregate(
            $pegawais->pluck('id'),
            $periodeStart,
            $periodeEnd,
            $attendanceCutoff,
            $unitScope,
        );

        $attendanceByPegawai = $presensiAgg['attendance'];
        $sakitProrata = $presensiAgg['sakit_prorata'];
        $presentDaysByPegawai = $presensiAgg['present_days'];
        $lemburByPegawai = $presensiAgg['lembur'];
        $attendedJadwalByPegawai = $presensiAgg['attended_jadwal'];

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

                $pegawaiUnitIds = $pegawai->units->pluck('id')->toArray();

                foreach ($globalKomponens as $komponen) {
                    if ($komponen->unit_sekolah_id && ! in_array($komponen->unit_sekolah_id, $pegawaiUnitIds, true)) {
                        continue;
                    }
                    $nominal = round((float) $this->computeComponentNominal(
                        $komponen, $pegawai, $pegawaiKomponens, $globalKomponens, $counts, $skalas, $periodeEnd, $periodeStart, $attendanceCutoff, $lemburByPegawai, $sakitProrata, $presentDaysByPegawai, $attendedJadwalByPegawai
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

        $penggajians = Penggajian::with(['pegawai.units:id,nama,singkatan', 'details'])
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

            // [PERF] Hindari N+1: KomponenGaji::find() per baris detail (dulu bisa
            // 1000+ query untuk 100 pegawai × 10 komponen). Prefetch 1 query;
            // jika semua detail ad-hoc (tanpa komponen id) langsung lewati.
            $komponenIds = collect($request->details)->pluck('komponen_gaji_id')->filter()->unique()->all();
            $komponenById = $komponenIds
                ? KomponenGaji::whereIn('id', $komponenIds)->get()->keyBy('id')
                : collect();

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
                    if (! empty($d['komponen_gaji_id']) && isset($komponenById[$d['komponen_gaji_id']])) {
                        $isTaxable = (bool) $komponenById[$d['komponen_gaji_id']]->is_taxable;
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

    /**
     * PDF slip gaji (F3).
     */
    public function pdf($id)
    {
        $penggajian = Penggajian::with(['pegawai.units', 'pegawai.jabatans', 'details'])->findOrFail($id);

        // Verifikasi akses: hanya admin unit/pegawai sendiri/superadmin
        $user = auth()->user();
        if (! $user) {
            abort(403);
        }
        if (! $user->can('view_all_units')) {
            if ($user->unit_sekolah_id) {
                if (! $penggajian->pegawai?->belongsToUnit($user->unit_sekolah_id)) {
                    abort(403);
                }
            } else {
                $peg = Pegawai::where('user_id', $user->id)->first();
                if (! $peg || $penggajian->pegawai_id !== $peg->id) {
                    abort(403);
                }
            }
        }

        $pdf = Pdf::loadView('exports.pdf-slip', [
            'penggajian' => $penggajian,
        ])->setPaper('A4', 'portrait');

        $filename = 'slip-gaji-'.$penggajian->pegawai->nama_lengkap.'-'.$penggajian->periode_bulan.'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Export daftar gaji untuk transfer bank (Excel) per periode (F3).
     */
    public function exportBank(Request $request)
    {
        $this->authorizePayrollModification();

        $request->validate([
            'periode_bulan' => 'required|string',
        ]);

        return Excel::download(
            new TransferBankExport($request->periode_bulan),
            'transfer-bank-'.$request->periode_bulan.'.xlsx'
        );
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
     * @param  Collection|null  $sakitProrata  Total persen bayar per pegawai utk status sakit
     * @param  Collection|null  $presentDays  Jumlah hari kalender hadir/telat per pegawai (dedup per tanggal)
     * @param  Collection|null  $attendedJadwalByPegawai  Map pegawai_id => [jadwal_id => jumlah hadir]
     * @return float nominal komponen gaji
     */
    protected function computeComponentNominal(KomponenGaji $komponen, Pegawai $pegawai, $pegawaiKomponens, $globalKomponens, array $counts, $skalas, Carbon $periodeEnd, Carbon $periodeStart, Carbon $attendanceCutoff, $lemburByPegawai = null, $sakitProrata = null, $presentDays = null, $attendedJadwalByPegawai = null): float
    {
        $nominal = 0;

        // Filter by status kepegawaian
        if ($komponen->applies_to_status_kepegawaian
            && $komponen->applies_to_status_kepegawaian !== $pegawai->status_kepegawaian) {
            return 0;
        }

        if ($komponen->jenis === 'fixed') {
            if ($pegawaiKomponens->has($komponen->id) && $pegawaiKomponens[$komponen->id]->pivot->nominal !== null) {
                $nominal = $pegawaiKomponens[$komponen->id]->pivot->nominal;
            } else {
                $nominal = $komponen->nilai_default ?? 0;
            }
        } elseif ($komponen->jenis === 'persentase') {
            $gajiPokok = $this->findKomponenByKode($globalKomponens, 'gaji_pokok', ['Gaji Pokok', 'Basic Salary'], $pegawai);
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
                $multiplier = $sakitProrata && $sakitProrata->has($pegawai->id)
                    ? min(1, (int) $sakitProrata[$pegawai->id] / ($counts['sakit'] * 100))
                    : 1;
                $nominal = $rate * $counts['sakit'] * $multiplier;
            } elseif ($this->isKehadiranType($komponen, 'kehadiran_izin', ['izin'])) {
                $nominal = $rate * $counts['izin'];
            } elseif ($this->isKehadiranType($komponen, 'kehadiran_cuti', ['cuti'])) {
                $nominal = $rate * $counts['cuti'];
            } elseif ($this->isKehadiranType($komponen, 'tunjangan_kehadiran', ['makan', 'transport', 'hadir'])) {
                // Dibayar 1x per hari kalender (dedup per tanggal), bukan per record jadwal.
                $present = $presentDays && isset($presentDays[$pegawai->id])
                    ? (int) $presentDays[$pegawai->id]
                    : $counts['hadir'] + $counts['telat'];
                $nominal = $rate * $present;
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

            $syarat = $komponen->syarat_bayar_jam_mengajar ?: 'hanya_hadir';

            $attendedJadwalIds = $attendedJadwalByPegawai && isset($attendedJadwalByPegawai[$pegawai->id])
                ? $attendedJadwalByPegawai[$pegawai->id]
                : collect();

            $totalHoursMonthly = 0;
            foreach ($pegawai->jadwals as $jadwal) {
                $unit = $jadwal->unitSekolah;
                if ($komponen->unit_sekolah_id && (! $unit || $unit->id !== $komponen->unit_sekolah_id)) {
                    continue;
                }
                $mulai = Carbon::parse($jadwal->jam_mulai);
                $selesai = Carbon::parse($jadwal->jam_selesai);
                $durasiJp = (int) ($unit->durasi_jp ?? 45);
                $sessionHours = $mulai->diffInMinutes($selesai) / $durasiJp;

                $count = $syarat === 'hanya_hadir'
                    ? ($attendedJadwalIds[$jadwal->id] ?? 0)
                    : $this->countWeekdayInRange($jadwal->hari, $periodeStart, $attendanceCutoff);

                $totalHoursMonthly += $sessionHours * $count;
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
     * @param  Pegawai|null  $pegawai  Pegawai (untuk filter unit_sekolah_id)
     * @return Komponen|null Komponen yang ditemukan atau null
     */
    private function findKomponenByKode($komponens, string $targetKode, array $namePatterns = [], $pegawai = null): ?KomponenGaji
    {
        if ($pegawai && $pegawai->units->isNotEmpty()) {
            $unitIds = $pegawai->units->sortByDesc('pivot.is_primary')->pluck('id')->toArray();

            foreach ($unitIds as $uid) {
                $komponen = $komponens->first(fn ($k) => $k->kode === $targetKode && $k->unit_sekolah_id === $uid);
                if ($komponen) {
                    return $komponen;
                }
            }
        }

        $komponen = $komponens->first(fn ($k) => $k->kode === $targetKode && is_null($k->unit_sekolah_id));
        if ($komponen) {
            return $komponen;
        }

        if (! empty($namePatterns)) {
            if ($pegawai && $pegawai->units->isNotEmpty()) {
                $unitIds = $pegawai->units->sortByDesc('pivot.is_primary')->pluck('id')->toArray();
                foreach ($unitIds as $uid) {
                    foreach ($namePatterns as $pattern) {
                        $komponen = $komponens->first(fn ($k) => $k->unit_sekolah_id === $uid && stripos($k->nama, $pattern) !== false);
                        if ($komponen) {
                            return $komponen;
                        }
                    }
                }
            }

            foreach ($namePatterns as $pattern) {
                $komponen = $komponens->first(fn ($k) => is_null($k->unit_sekolah_id) && stripos($k->nama, $pattern) !== false);
                if ($komponen) {
                    return $komponen;
                }
            }
        }

        return null;
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
