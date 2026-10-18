<?php

namespace App\Http\Controllers;

use App\Exports\TransferBankExport;
use App\Http\Controllers\Concerns\ScopesPimpinan;
use App\Models\HariLibur;
use App\Models\KomponenGaji;
use App\Models\LaporanPiket;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\Penggajian;
use App\Models\PenggajianDetail;
use App\Models\SkalaMasaBakti;
use App\Models\StatusKepegawaian;
use App\Models\User;
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
    use ScopesPimpinan;

    /** Batas honor ekskul per ekskul per bulan (mis. 4 × Rp 60.000). */
    private const EKSKUL_CAP_NOMINAL = 240000;

    /**
     * Cache kode status tetap (ref table) per-request — dipakai loop payroll
     * agar tidak query berulang per komponen × pegawai. Injectable utk test.
     *
     * @var list<string>|null
     */
    protected ?array $tetapKodeCache = null;

    /**
     * Cache kode status grup guru (GTYS/GTY/GTT) per-request.
     *
     * @var list<string>|null
     */
    protected ?array $guruKodeCache = null;

    public function __construct(
        private readonly PresensiAggregator $presensiAggregator,
    ) {}

    /**
     * Kode status grup tetap (GTYS/PTY) — cache per-request.
     *
     * @return list<string>
     */
    protected function tetapKodes(): array
    {
        return $this->tetapKodeCache ??= StatusKepegawaian::activeTetapKodes();
    }

    /**
     * Kode status grup guru — cache per-request.
     *
     * @return list<string>
     */
    protected function guruKodes(): array
    {
        return $this->guruKodeCache ??= StatusKepegawaian::activeGuruKodes();
    }

    public function index(Request $request)
    {
        $request->validate(['status' => 'nullable|in:draft,finalized,paid']);

        $user = auth()->user();
        $isAdmin = $user && $this->isPayrollAdmin($user);
        $query = Penggajian::with(['pegawai.units:id,singkatan,nama']);

        if ($this->isPimpinanReadOnly($user)) {
            // Pimpinan (kepsek/kepala TU/ketua yayasan): HANYA penggajian bawahan langsung.
            $this->scopePimpinanBawahan($query, $user);
        } elseif (! $isAdmin) {
            $pegawai = Pegawai::where('user_id', auth()->id())->first();
            if ($pegawai) {
                $query->where('pegawai_id', $pegawai->id);
            } else {
                $query->where('id', -1);
            }
        } elseif ($user && ! $user->can('view_all_units')) {
            $this->scopePayrollQuery($query, $user);
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
        $isAdmin = $user && $this->isPayrollAdmin($user);
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

        // [FIX] N+1: Eager load komponenGaji, jadwal + mapel (honor ekskul), jabatan.
        $query = Pegawai::where('status_aktif', 'aktif')->with(['komponenGaji', 'units', 'jadwals.unitSekolah', 'jadwals.pegawaiMapel.mataPelajaran', 'jabatans:id']);

        if ($user && ! $user->can('view_all_units')) {
            $unitIds = $this->payrollUnitScope($user);
            if (empty($unitIds)) {
                abort(403, 'Akses ditolak.');
            }
            $query->whereHas('units', function ($q) use ($unitIds) {
                $q->whereIn('unit_sekolah.id', $unitIds);
            });
        }

        $pegawais = $query->get();

        // [FIX] N+1: Fetch data referensi di luar loop
        $globalKomponens = KomponenGaji::where('is_active', true)
            ->with(['referenceValues'])
            ->get();
        $skalas = SkalaMasaBakti::orderBy('masa_kerja_tahun', 'desc')->get();
        // [FIX] N+1: mapping tunjangan jabatan per komponen (dinamis_jabatan).
        $jabatanRates = DB::table('komponen_jabatan')->get(['komponen_gaji_id', 'jabatan_id', 'nominal'])
            ->groupBy('komponen_gaji_id');

        $periodeStart = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $periodeEnd = Carbon::createFromDate($year, $month, 1)->endOfMonth();
        $today = Carbon::today();
        $isCurrentMonth = ((int) $today->year === (int) $year && (int) $today->month === (int) $month);
        $attendanceCutoff = $isCurrentMonth ? $today : $periodeEnd;

        $unitScope = ($user && $user->unit_sekolah_id && ! $user->can('view_all_units') && ! $user->isPayrollOperator())
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
        $kantorDaysByPegawai = $presensiAgg['kantor_days'];
        $activeDatesByPegawai = $presensiAgg['active_dates'];
        $lemburByPegawai = $presensiAgg['lembur'];
        $attendedJadwalByPegawai = $presensiAgg['attended_jadwal'];

        // Hari ½ (izin + tugas, flag admin): 1 query untuk semua pegawai,
        // tanggal overlap dihitung di PHP, lewati tanggal berkehadiran fisik.
        $setengahCountByPegawai = $this->setengahCountByPegawai(
            $pegawais->pluck('id'), $periodeStart, $attendanceCutoff, $activeDatesByPegawai
        );

        // [OPT] Satu query ambil hari libur (nasional + unit pegawai) untuk seluruh run.
        // Dipetakan ke weekday→count agar exclude libur O(1) per jadwal (hindari N+1).
        $allUnitIds = $pegawais->flatMap->units->pluck('id')->filter()->unique()->toArray();
        $holidayMap = $this->buildHolidayWeekdayMap($allUnitIds, $periodeStart, $attendanceCutoff);

        // Honor piket: jumlah laporan piket submitted per pegawai dalam periode
        // (numerator). 1 query untuk semua pegawai.
        $piketSubmittedByPegawai = LaporanPiket::whereIn('pegawai_id', $pegawais->pluck('id'))
            ->where('status', LaporanPiket::STATUS_SUBMITTED)
            ->whereBetween('tanggal', [$periodeStart->toDateString(), $attendanceCutoff->toDateString()])
            ->get()
            ->groupBy('pegawai_id')
            ->map->count();

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
                $counts = $this->computeAttendance($pegawai, $attendanceByPegawai, $periodeStart, $attendanceCutoff, $holidayMap, $setengahCountByPegawai);

                $totalPendapatan = '0.00';
                $totalPotongan = '0.00';
                $totalTaxable = '0.00';
                $details = [];

                $pegawaiUnitIds = $pegawai->units->pluck('id')->toArray();

                // Dua-pass: pendapatan dulu (akumulasi taxable), lalu potongan —
                // agar persentase ber-dasar 'taxable'/'total_pendapatan' akurat.
                $ordered = $globalKomponens->sortBy(fn ($k) => $k->tipe === 'potongan' ? 1 : 0)->values();

                foreach ([0, 1] as $pass) {
                    foreach ($ordered as $komponen) {
                        if (($komponen->tipe === 'potongan') !== ($pass === 1)) {
                            continue;
                        }
                        if ($komponen->unit_sekolah_id && ! in_array($komponen->unit_sekolah_id, $pegawaiUnitIds, true)) {
                            continue;
                        }
                        $nominal = round((float) $this->computeComponentNominal(
                            $komponen, $pegawai, $pegawaiKomponens, $globalKomponens, $counts, $skalas, $periodeEnd, $periodeStart, $attendanceCutoff, $lemburByPegawai, $sakitProrata, $presentDaysByPegawai, $attendedJadwalByPegawai, $holidayMap, $jabatanRates, $kantorDaysByPegawai, (float) $totalTaxable, (float) $totalPendapatan, $piketSubmittedByPegawai
                        ), 2);

                        if ($nominal <= 0) {
                            continue;
                        }

                        if ($komponen->tipe === 'potongan') {
                            $totalPotongan = bcadd($totalPotongan, (string) $nominal, 2);
                        } else {
                            $totalPendapatan = bcadd($totalPendapatan, (string) $nominal, 2);
                            if ($komponen->is_taxable) {
                                $totalTaxable = bcadd($totalTaxable, (string) $nominal, 2);
                            }
                        }

                        $details[] = [
                            'komponen_gaji_id' => $komponen->id,
                            'nama_komponen' => $this->detailNama(
                                $komponen, $pegawai, $pegawaiKomponens,
                                $kantorDaysByPegawai, $attendedJadwalByPegawai, $piketSubmittedByPegawai
                            ),
                            'tipe' => $komponen->tipe,
                            'nominal' => $nominal,
                            'is_taxable' => (bool) $komponen->is_taxable,
                        ];
                    }
                }

                // Potongan kehadiran otomatis (baris virtual, komponen_gaji_id NULL):
                // tetap di bawah 90% → gaji × (1−rate); honorer → (honor ÷ hari) × hari_nol.
                $this->appendKehadiranAdjustments($pegawai, $pegawaiKomponens, $globalKomponens, $counts, $details, $totalPotongan);

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

        $query = Penggajian::with(['pegawai.units:id,nama,singkatan', 'details'])
            ->where('periode_bulan', $periode)
            ->where('status', 'draft');

        $user = auth()->user();
        if ($user && ! $user->can('view_all_units')) {
            $this->scopePayrollQuery($query, $user);
        }

        return response()->json($query->get());
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

            $user = auth()->user();
            if ($user && ! $user->can('view_all_units') && ! $this->userCanAccessPegawai($penggajian->pegawai_id)) {
                throw new \Exception('Akses ditolak.');
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
            $query = Penggajian::where('periode_bulan', $periode)
                ->where('status', 'draft')
                ->lockForUpdate();

            $user = auth()->user();
            if ($user && ! $user->can('view_all_units')) {
                $this->scopePayrollQuery($query, $user);
            }

            $query->update(['status' => 'finalized']);

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
        $isAdmin = $user && $this->isPayrollAdmin($user);
        if (! $isAdmin) {
            abort(403, 'Akses ditolak.');
        }

        if ($user->can('view_all_units')) {
            abort(403, 'Hanya Admin Unit yang berhak.');
        }

        $request->validate(['periode_bulan' => 'required|string']);

        $query = Penggajian::where('periode_bulan', $request->periode_bulan)->where('status', 'draft');

        $this->scopePayrollQuery($query, $user);

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

        $this->scopePayrollQuery($query, $user);

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
        $isPayrollAdmin = $user && $this->isPayrollAdmin($user);
        $penggajian = Penggajian::with(['pegawai.jabatans', 'pegawai.units', 'pegawai.statusRef', 'details'])->findOrFail($id);

        if ($isPayrollAdmin && ! $isSuperadmin) {
            if (! $this->userCanAccessPegawai($penggajian->pegawai_id)) {
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

        // Verifikasi akses: hanya payroll operator/admin unit/pegawai sendiri/superadmin
        $user = auth()->user();
        if (! $user) {
            abort(403);
        }
        if (! $user->can('view_all_units')) {
            if ($user->isPayrollOperator()) {
                if (! $this->userCanAccessPegawai($penggajian->pegawai_id)) {
                    abort(403);
                }
            } elseif ($user->unit_sekolah_id) {
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
     * @param  array  $holidayMap  Map hari libur (buildHolidayWeekdayMap) untuk exclude libur
     * @return array<string,int> ['hadir'=>X, 'telat'=>Y, 'alpa'=>Z, 'sakit'=>A, 'izin'=>B, 'cuti'=>C]
     */
    protected function computeAttendance(Pegawai $pegawai, $attendanceByPegawai, Carbon $periodeStart, Carbon $attendanceCutoff, array $holidayMap = [], $setengahCountByPegawai = null): array
    {
        $pAtt = $attendanceByPegawai->get($pegawai->id, collect());
        $countHadir = (int) $pAtt->where('status', 'hadir')->sum('total');
        $countTelat = (int) $pAtt->where('status', 'telat')->sum('total');
        $countAlpaManual = (int) $pAtt->where('status', 'alpa')->sum('total');
        $countSakit = (int) $pAtt->where('status', 'sakit')->sum('total');
        $countIzin = (int) $pAtt->where('status', 'izin')->sum('total');
        $countCuti = (int) $pAtt->where('status', 'cuti')->sum('total');

        // [FIX] Auto-alpha: hari kerja (dari jadwal, kecuali lembur) - (hadir/telat/izin/cuti/alpa terkonfirmasi)
        // Hari libur (nasional + unit) di-exclude agar pegawai tak dihitung alpa di kalender merah.
        $workingDays = 0;
        $seenHariUnit = [];
        foreach ($pegawai->jadwals as $jadwal) {
            // Piket bukan sesi presensi (akuntabilitas via laporan piket) —
            // samakan dengan FinalizeAlpa yang tak menandai alpa slot piket.
            if (in_array($jadwal->jenis_jadwal, ['lembur', 'piket'], true)) {
                continue;
            }
            // Dedup (hari, unit): 2 jadwal Senin di unit sama = 1 hari kerja,
            // bukan 2 (kalau tidak, alpa auto ter-overcount).
            $key = $jadwal->hari.'|'.$jadwal->unit_sekolah_id;
            if (isset($seenHariUnit[$key])) {
                continue;
            }
            $seenHariUnit[$key] = true;
            $workingDays += max(0, $this->countWeekdayInRange(
                $jadwal->hari, $periodeStart, $attendanceCutoff,
                $this->holidayCountFor($holidayMap, $jadwal->unit_sekolah_id, $jadwal->hari)
            ));
        }

        // Tanpa jadwal (mis. non-guru staf kantor) → hari kerja unit utama
        // (Sen–Jum, +Sabtu bila unit kerja Sabtu), minus libur.
        if ($workingDays === 0) {
            $unit = $pegawai->unitUtama();
            if ($unit) {
                foreach (Pegawai::hariKerjaUnit($unit) as $hari) {
                    $workingDays += max(0, $this->countWeekdayInRange(
                        $hari, $periodeStart, $attendanceCutoff,
                        $this->holidayCountFor($holidayMap, $unit->id, $hari)
                    ));
                }
            }
        }
        $presentOrLeave = $countHadir + $countTelat + $countSakit + $countIzin + $countCuti + $countAlpaManual;
        $countAlpa = $countAlpaManual + max(0, $workingDays - $presentOrLeave);
        $setengah = (int) ($setengahCountByPegawai[$pegawai->id] ?? 0);

        return [
            'hadir' => $countHadir,
            'telat' => $countTelat,
            'alpa' => $countAlpa,
            'sakit' => $countSakit,
            'izin' => $countIzin,
            'cuti' => $countCuti,
            'setengah' => $setengah,
            'working_days' => $workingDays,
        ];
    }

    /**
     * Rate kehadiran: hari penuh (hadir/telat/sakit/cuti) + ½ hari izin-tugas,
     * dibagi hari kerja. Tanpa hari kerja terjadwal → penuh (tak ada yang bisa alpa).
     */
    protected function attendanceRate(array $counts, int $setengah, int $workingDays): float
    {
        if ($workingDays <= 0) {
            return 1.0;
        }

        return ($counts['hadir'] + $counts['telat'] + $counts['sakit'] + $counts['cuti'] + 0.5 * $setengah) / $workingDays;
    }

    /**
     * Jumlah hari ½ per pegawai: izin disetujui berflag dalam periode
     * (dipotong tanggal cutoff + tanggal berkehadiran fisik).
     */
    protected function setengahCountByPegawai($pegawaiIds, Carbon $periodeStart, Carbon $attendanceCutoff, $activeDatesByPegawai): Collection
    {
        $rows = PengajuanIzin::whereIn('pegawai_id', $pegawaiIds instanceof Collection ? $pegawaiIds->all() : $pegawaiIds)
            ->where('status', 'disetujui')
            ->where('dihitung_setengah', true)
            ->whereDate('tanggal_mulai', '<=', $attendanceCutoff->toDateString())
            ->whereDate('tanggal_selesai', '>=', $periodeStart->toDateString())
            ->get(['pegawai_id', 'tanggal_mulai', 'tanggal_selesai']);

        return $rows->groupBy('pegawai_id')->map(function ($items, $pid) use ($periodeStart, $attendanceCutoff, $activeDatesByPegawai) {
            $active = $activeDatesByPegawai[$pid] ?? [];
            $days = [];
            foreach ($items as $r) {
                $s = max(Carbon::parse($r->tanggal_mulai)->startOfDay(), $periodeStart->copy()->startOfDay());
                $e = min(Carbon::parse($r->tanggal_selesai)->startOfDay(), $attendanceCutoff->copy()->startOfDay());
                for ($d = $s->copy(); $d->lte($e); $d->addDay()) {
                    $key = $d->format('Y-m-d');
                    if (! isset($active[$key])) {
                        $days[$key] = true;
                    }
                }
            }

            return count($days);
        });
    }

    /**
     * Potongan kehadiran harian — HANYA non-guru (baris virtual, komponen_gaji_id
     * NULL): (gaji_pokok ÷ hari kerja) × hari nol. Guru tidak dipotong kehadiran
     * (gajinya murni JP dihadiri × tarif).
     */
    protected function appendKehadiranAdjustments(Pegawai $pegawai, $pegawaiKomponens, $globalKomponens, array $counts, array &$details, string &$totalPotongan): void
    {
        if (in_array($pegawai->status_kepegawaian, $this->guruKodes(), true)) {
            return;
        }

        $setengah = (int) ($counts['setengah'] ?? 0);
        $workingDays = (int) ($counts['working_days'] ?? 0);
        $hadirEq = $counts['hadir'] + $counts['telat'] + $counts['sakit'] + $counts['cuti'] + 0.5 * $setengah;
        $hariNol = max(0, $workingDays - $hadirEq);
        if ($hariNol <= 0 || $workingDays <= 0) {
            return;
        }

        $gajiPokok = $this->findKomponenByKode($globalKomponens, 'gaji_pokok', [], $pegawai);
        if (! $gajiPokok) {
            return;
        }
        $base = $this->resolveFixedNominal($gajiPokok, $pegawaiKomponens);
        if ($base <= 0) {
            return;
        }

        $potongan = round($base / $workingDays * $hariNol, 2);
        if ($potongan <= 0) {
            return;
        }
        $details[] = [
            'komponen_gaji_id' => null,
            'nama_komponen' => sprintf(
                'Potongan Kehadiran — (Rp %s ÷ %d hari × %s hari)',
                number_format($base, 0, ',', '.'),
                $workingDays,
                $this->fmtId($hariNol)
            ),
            'tipe' => 'potongan',
            'nominal' => $potongan,
            'is_taxable' => false,
        ];
        $totalPotongan = bcadd($totalPotongan, (string) $potongan, 2);
    }

    /**
     * Nominal fixed: override pivot per pegawai menang, else nilai_default.
     * Pola yang sama dipakai semua cabang fixed/rate agar konsisten.
     */
    protected function resolveFixedNominal(KomponenGaji $komponen, $pegawaiKomponens): float
    {
        if ($pegawaiKomponens->has($komponen->id) && $pegawaiKomponens[$komponen->id]->pivot->nominal !== null) {
            return (float) $pegawaiKomponens[$komponen->id]->pivot->nominal;
        }

        return (float) ($komponen->nilai_default ?? 0);
    }

    /**
     * Nama baris detail. Transport diberi keterangan (N × Rp rate); sisanya
     * nama komponen apa adanya.
     */
    protected function detailNama(KomponenGaji $komponen, Pegawai $pegawai, $pegawaiKomponens, $kantorDaysByPegawai, $attendedJadwalByPegawai, $piketSubmittedByPegawai = null): string
    {
        if ($komponen->kode === 'transport_harian') {
            $hari = $kantorDaysByPegawai[$pegawai->id] ?? [];

            return sprintf(
                'Transport (%d × Rp %s)',
                count($hari),
                number_format($this->resolveFixedNominal($komponen, $pegawaiKomponens), 0, ',', '.')
            );
        }

        return $komponen->nama;
    }

    /** Format angka gaya Indonesia: 17,5 (bukan 17.5), bulat tanpa koma. */
    protected function fmtId(float $n): string
    {
        $s = number_format(round($n, 1), 1, ',', '.');

        return str_ends_with($s, ',0') ? substr($s, 0, -2) : $s;
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
     * - dinamis_jabatan: Jumlah nominal mapping komponen-jabatan utk semua jabatan pegawai
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
     * @param  array  $holidayMap  Map hari libur untuk exclude libur di sesi mengajar
     * @return float nominal komponen gaji
     */
    protected function computeComponentNominal(KomponenGaji $komponen, Pegawai $pegawai, $pegawaiKomponens, $globalKomponens, array $counts, $skalas, Carbon $periodeEnd, Carbon $periodeStart, Carbon $attendanceCutoff, $lemburByPegawai = null, $sakitProrata = null, $presentDays = null, $attendedJadwalByPegawai = null, array $holidayMap = [], $jabatanRatesByKomponen = null, $kantorDaysByPegawai = null, float $totalTaxable = 0, float $totalPendapatan = 0, $piketSubmittedByPegawai = null): float
    {
        $nominal = 0;

        // Filter by grup status kepegawaian (group-key, bukan kode individual):
        // 'tetap'/'honorer' = grup tetap/non-tetap; 'guru'/'non_guru' = grup guru.
        // Nilai legacy di DB tetap valid — match via flag tabel referensi.
        if ($komponen->applies_to_status_kepegawaian) {
            $kode = $pegawai->status_kepegawaian;
            $cocok = match ($komponen->applies_to_status_kepegawaian) {
                'tetap' => in_array($kode, $this->tetapKodes(), true),
                'honorer' => ! in_array($kode, $this->tetapKodes(), true),
                'guru' => in_array($kode, $this->guruKodes(), true),
                'non_guru' => ! in_array($kode, $this->guruKodes(), true),
                default => true,
            };
            if (! $cocok) {
                return 0;
            }
        }

        if ($komponen->jenis === 'fixed') {
            if ($pegawaiKomponens->has($komponen->id) && $pegawaiKomponens[$komponen->id]->pivot->nominal !== null) {
                $nominal = $pegawaiKomponens[$komponen->id]->pivot->nominal;
            } else {
                $nominal = $komponen->nilai_default ?? 0;
            }
        } elseif ($komponen->jenis === 'persentase') {
            // Dasar persentase: 'gaji_pokok' (default/legacy), 'taxable' (penghasilan
            // kena pajak — PPh 21), atau 'total_pendapatan' (bruto).
            $baseKind = $komponen->persentase_base ?: 'gaji_pokok';
            if ($baseKind === 'taxable') {
                $base = $totalTaxable;
            } elseif ($baseKind === 'total_pendapatan') {
                $base = $totalPendapatan;
            } else {
                $gajiPokok = $this->findKomponenByKode($globalKomponens, 'gaji_pokok', ['Gaji Pokok', 'Basic Salary'], $pegawai);
                $base = $gajiPokok ? $this->resolveFixedNominal($gajiPokok, $pegawaiKomponens) : 0;
            }
            $nominal = ($komponen->nilai_default / 100) * $base;
        } elseif ($komponen->jenis === 'dinamis_kehadiran') {
            // Transport harian: tarif × hari kantor-masuk (bukan semua hadir).
            // Override pivot menang sebagai TARIF (bukan nominal flat) — jenis
            // ini dibayar per hari, jadi tetap dikali hari.
            if ($komponen->kode === 'transport_harian') {
                $rateHarian = $this->resolveFixedNominal($komponen, $pegawaiKomponens);
                $hariKantor = $kantorDaysByPegawai && isset($kantorDaysByPegawai[$pegawai->id])
                    ? count($kantorDaysByPegawai[$pegawai->id])
                    : 0;

                return (float) ($rateHarian * $hariKantor);
            }

            $rate = $pegawaiKomponens->has($komponen->id) && $pegawaiKomponens[$komponen->id]->pivot->nominal !== null
                ? $pegawaiKomponens[$komponen->id]->pivot->nominal
                : ($komponen->nilai_default ?? 0);

            if ($this->isKehadiranType($komponen, 'kehadiran_telat', ['telat'])) {
                $nominal = $rate * $counts['telat'];
            } elseif ($this->isKehadiranType($komponen, 'kehadiran_alpa', ['alpa'])) {
                $nominal = $rate * $counts['alpa'];
            } elseif ($this->isKehadiranType($komponen, 'kehadiran_sakit', ['sakit'])) {
                $multiplier = ($sakitProrata && $counts['sakit'] > 0 && $sakitProrata->has($pegawai->id))
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
                if ($pegawai->tmt_mengajar) {
                    $joinDate = Carbon::parse($pegawai->tmt_mengajar);
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
                // Hanya JP mengajar. Piket/ekskul/shift punya komponen sendiri.
                if ($jadwal->jenis_jadwal !== 'mengajar') {
                    continue;
                }
                $unit = $jadwal->unitSekolah;
                if ($komponen->unit_sekolah_id && (! $unit || $unit->id !== $komponen->unit_sekolah_id)) {
                    continue;
                }
                $mulai = Carbon::parse($jadwal->jam_mulai);
                $selesai = Carbon::parse($jadwal->jam_selesai);
                $durasiJp = (int) ($unit->durasi_jp ?? 45);
                if ($durasiJp <= 0) {
                    $durasiJp = 45;
                }
                $sessionHours = $mulai->diffInMinutes($selesai) / $durasiJp;

                $count = $syarat === 'hanya_hadir'
                    ? ($attendedJadwalIds[$jadwal->id] ?? 0)
                    : max(0, $this->countWeekdayInRange(
                        $jadwal->hari, $periodeStart, $attendanceCutoff,
                        $this->holidayCountFor($holidayMap, $jadwal->unit_sekolah_id, $jadwal->hari)
                    ));

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
        } elseif ($komponen->jenis === 'dinamis_jabatan') {
            // Override manual per pegawai menang (pola komponen lain).
            if ($pegawaiKomponens->has($komponen->id) && $pegawaiKomponens[$komponen->id]->pivot->nominal !== null) {
                $nominal = $pegawaiKomponens[$komponen->id]->pivot->nominal;
            } else {
                // Rangkap jabatan = jumlahkan semua mapping yang cocok (full).
                $jabatanIds = $pegawai->jabatans->pluck('id')->unique()->all();
                $rates = $jabatanRatesByKomponen && isset($jabatanRatesByKomponen[$komponen->id])
                    ? $jabatanRatesByKomponen[$komponen->id]
                    : collect();
                $nominal = $rates->whereIn('jabatan_id', $jabatanIds)->sum('nominal');
            }
        } elseif ($komponen->jenis === 'dinamis_piket') {
            $rate = $this->resolveFixedNominal($komponen, $pegawaiKomponens);

            // Penyebut = pertemuan piket nyata bulan itu (mis. 5 Senin), minus
            // libur. Dibayar rasio, dibatasi 1 JP/bulan.
            $dijadwalkan = 0;
            $seenPiket = [];
            foreach ($pegawai->jadwals as $jadwal) {
                if ($jadwal->jenis_jadwal !== 'piket') {
                    continue;
                }
                if ($komponen->unit_sekolah_id && $jadwal->unit_sekolah_id !== $komponen->unit_sekolah_id) {
                    continue;
                }
                $key = $jadwal->hari.'|'.$jadwal->unit_sekolah_id;
                if (isset($seenPiket[$key])) {
                    continue;
                }
                $seenPiket[$key] = true;
                $dijadwalkan += max(0, $this->countWeekdayInRange(
                    $jadwal->hari, $periodeStart, $attendanceCutoff,
                    $this->holidayCountFor($holidayMap, $jadwal->unit_sekolah_id, $jadwal->hari)
                ));
            }
            if ($dijadwalkan <= 0) {
                return 0;
            }

            // Numerator = laporan piket submitted (akuntabilitas harian piket).
            $hadir = $piketSubmittedByPegawai ? ($piketSubmittedByPegawai[$pegawai->id] ?? 0) : 0;
            $nominal = $rate * min(1.0, $hadir / $dijadwalkan);
        } elseif ($komponen->jenis === 'dinamis_ekskul') {
            if (! $komponen->mata_pelajaran_id) {
                return 0;
            }
            $rate = $this->resolveFixedNominal($komponen, $pegawaiKomponens);
            $attendedJadwalIds = $attendedJadwalByPegawai && isset($attendedJadwalByPegawai[$pegawai->id])
                ? $attendedJadwalByPegawai[$pegawai->id]
                : collect();

            // Pertemuan ekskul dihadiri (identitas ekskul = mata pelajaran).
            $pertemuan = 0;
            foreach ($pegawai->jadwals as $jadwal) {
                if ($jadwal->jenis_jadwal !== 'ekskul') {
                    continue;
                }
                if ((int) ($jadwal->pegawaiMapel?->mata_pelajaran_id) !== (int) $komponen->mata_pelajaran_id) {
                    continue;
                }
                if ($komponen->unit_sekolah_id && $jadwal->unit_sekolah_id !== $komponen->unit_sekolah_id) {
                    continue;
                }
                $pertemuan += (int) ($attendedJadwalIds[$jadwal->id] ?? 0);
            }

            $nominal = min($rate * $pertemuan, self::EKSKUL_CAP_NOMINAL);
        } elseif ($komponen->jenis === 'lookup_reference') {
            $refType = $komponen->payrollReferenceType;
            if (! $refType) {
                return 0;
            }

            $refKey = $this->resolveReferenceKey($refType->source_field, $pegawai);
            if ($refKey === null || $refKey === '') {
                return 0;
            }

            $nominal = $komponen->referenceValues
                ->first(fn ($v) => (string) $v->reference_key === (string) $refKey && (int) $v->payroll_reference_type_id === (int) $refType->id)
                ?->nominal ?? 0;
        }

        return (float) $nominal;
    }

    /**
     * Whitelist kolom Pegawai yang boleh dipakai untuk lookup_reference.
     * Cegah akses kolom sensitif (password, email, dll) via FE.
     */
    public static function referenceSourceFields(): array
    {
        return [
            'pendidikan_terakhir',
            'jenis_kelamin',
            'status_pernikahan',
            'status_kepegawaian',
            'tmt_mengajar',
        ];
    }

    private function resolveReferenceKey(string $sourceField, Pegawai $pegawai): ?string
    {
        if (! in_array($sourceField, self::referenceSourceFields(), true)) {
            return null;
        }

        $value = $pegawai->{$sourceField};

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
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
     *
     * @param  int  $exclude  Jumlah hari libur di weekday yg sama dalam rentang (dikurangi).
     */
    protected function countWeekdayInRange(string $hari, Carbon $start, Carbon $end, int $exclude = 0): int
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

        return max(0, $count - $exclude);
    }

    /**
     * Bangun map hari libur → count per weekday, dipisah nasional & per-unit.
     * Satu query untuk seluruh run payroll (hindari N+1 per pegawai/jadwal).
     *
     * @param  array  $unitIds  Unit yang relevan (dari pegawai terpilih)
     * @return array{static: array<string,int>, units: array<int, array<string,int>>}
     */
    protected function buildHolidayWeekdayMap(array $unitIds, Carbon $start, Carbon $end): array
    {
        $rows = HariLibur::whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
            ->where(function ($q) use ($unitIds) {
                $q->whereNull('unit_sekolah_id');
                if ($unitIds) {
                    $q->orWhereIn('unit_sekolah_id', $unitIds);
                }
            })
            ->get(['tanggal', 'unit_sekolah_id']);

        $idMap = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $national = [];
        $units = [];
        foreach ($rows as $h) {
            $wd = $idMap[(int) Carbon::parse($h->tanggal)->dayOfWeek];
            if ($h->unit_sekolah_id === null) {
                $national[$wd] = ($national[$wd] ?? 0) + 1;
            } else {
                $units[$h->unit_sekolah_id][$wd] = ($units[$h->unit_sekolah_id][$wd] ?? 0) + 1;
            }
        }

        return ['national' => $national, 'units' => $units];
    }

    /**
     * Jumlah hari libur (nasional + unit terkait) yang jatuh pada weekday & unit tertentu.
     */
    protected function holidayCountFor(array $holidayMap, ?int $unitId, string $hari): int
    {
        return (int) ($holidayMap['national'][$hari] ?? 0)
            + (int) ($holidayMap['units'][$unitId][$hari] ?? 0);
    }

    /**
     * Authorize user untuk aksi payroll modification (finalize, destroy, markPaid).
     * Harus payroll admin (operator jabatan / admin unit lama) dan BUKAN superadmin
     * (view_all_units) — superadmin hanya memantau, tidak boleh mengunci/mengubah.
     *
     * @throws AuthorizationException
     */
    private function authorizePayrollModification(): void
    {
        $user = auth()->user();
        if (! $user || ! $this->isPayrollAdmin($user)) {
            abort(403, 'Akses ditolak.');
        }

        if ($user->can('view_all_units')) {
            abort(403, 'Hanya Admin Unit yang berhak.');
        }
    }

    /**
     * User adalah payroll admin: punya permission `view_payroll` (superadmin)
     * atau jabatan operator payroll (`is_payroll_operator`).
     */
    private function isPayrollAdmin(?User $user): bool
    {
        return $user && ($user->can('view_payroll') || $user->isPayrollOperator());
    }

    /**
     * Unit yang bisa dikelola user utk payroll.
     * - superadmin: [] (tanpa scope)
     * - operator payroll: unit dari pivot pegawai_unit (bisa multi-unit)
     * - admin unit legacy: unit_sekolah_id
     */
    private function payrollUnitScope(User $user): array
    {
        if ($user->can('view_all_units')) {
            return [];
        }

        if ($user->isPayrollOperator()) {
            return $user->payrollUnitIds();
        }

        return $user->unit_sekolah_id ? [$user->unit_sekolah_id] : [];
    }

    /**
     * Scope query Penggajian ke unit yang dikelola user (bukan superadmin).
     */
    private function scopePayrollQuery($query, User $user): void
    {
        $unitIds = $this->payrollUnitScope($user);

        if (empty($unitIds)) {
            $query->where('id', -1);

            return;
        }

        $query->whereHas('pegawai', function ($q) use ($unitIds) {
            $q->whereHas('units', fn ($q2) => $q2->whereIn('unit_sekolah.id', $unitIds));
        });
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

        $unitIds = $this->payrollUnitScope($user);
        if (empty($unitIds)) {
            return false;
        }

        $pegawai = $pegawaiId instanceof Pegawai
            ? $pegawaiId
            : Pegawai::find($pegawaiId);

        return $pegawai && $pegawai->units()->whereIn('unit_sekolah.id', $unitIds)->exists();
    }
}
