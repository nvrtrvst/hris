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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PenggajianController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $isAdmin = $user && $user->can('view_payroll');
        $query = Penggajian::with('pegawai');

        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $query->whereHas('pegawai', function ($q) use ($user) {
                $q->forUnit($user->unit_sekolah_id);
            });
        } elseif (! $isAdmin) {
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
                        $komponen, $pegawai, $pegawaiKomponens, $globalKomponens, $counts, $skalas, $periodeEnd, $periodeStart, $attendanceCutoff
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

        $komponenMap = KomponenGaji::all()->keyBy('id');

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
                    if (! empty($d['komponen_gaji_id']) && isset($komponenMap[$d['komponen_gaji_id']])) {
                        $isTaxable = (bool) $komponenMap[$d['komponen_gaji_id']]->is_taxable;
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
        $user = auth()->user();
        $isAdmin = $user && $user->can('view_payroll');
        if (! $isAdmin) {
            abort(403, 'Akses ditolak.');
        }

        if ($user->can('view_all_units')) {
            abort(403, 'Hanya Admin Unit yang berhak.');
        }

        $penggajian = Penggajian::with('pegawai')->findOrFail($id);

        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units') && ! $penggajian->pegawai->belongsToUnit($user->unit_sekolah_id)) {
            abort(403, 'Akses ditolak.');
        }

        $penggajian->update(['status' => 'finalized']);

        return redirect()->back()->with('message', 'Satu data penggajian berhasil di-Finalisasi.');
    }

    public function destroyPeriod(Request $request)
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

        $deleted = $query->delete();

        return redirect()->back()->with('message', "Sebanyak {$deleted} data draft slip gaji periode {$request->periode_bulan} berhasil dihapus/dibatalkan.");
    }

    public function destroy($id)
    {
        $user = auth()->user();
        $isAdmin = $user && $user->can('view_payroll');
        if (! $isAdmin) {
            abort(403, 'Akses ditolak.');
        }

        if ($user->can('view_all_units')) {
            abort(403, 'Hanya Admin Unit yang berhak.');
        }

        $penggajian = Penggajian::with('pegawai')->findOrFail($id);

        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units') && ! $penggajian->pegawai->belongsToUnit($user->unit_sekolah_id)) {
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
        $user = auth()->user();
        $isAdmin = $user && $user->can('view_payroll');
        if (! $isAdmin) {
            abort(403, 'Akses ditolak.');
        }

        if ($user->can('view_all_units')) {
            abort(403, 'Hanya Admin Unit yang berhak.');
        }

        $penggajian = Penggajian::with('pegawai')->findOrFail($id);

        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units') && ! $penggajian->pegawai->belongsToUnit($user->unit_sekolah_id)) {
            abort(403, 'Akses ditolak.');
        }

        if ($penggajian->status !== 'finalized') {
            return redirect()->back()->withErrors(['error' => 'Hanya slip gaji berstatus FINALIZED yang bisa ditandai dibayar.']);
        }

        $penggajian->update(['status' => 'paid']);

        return redirect()->back()->with('message', 'Slip gaji berhasil ditandai DIBAYAR.');
    }

    /**
     * Hitung jumlah kehadiran per status + auto-alpha dari jadwal pegawai.
     *
     * @return array{hadir:int,telat:int,alpa:int,sakit:int,izin:int,cuti:int}
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

        // [FIX] Auto-alpha: hari kerja (dari jadwal) - (hadir/telat/izin/cuti disetujui)
        $workingDays = 0;
        foreach ($pegawai->jadwals as $jadwal) {
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
     */
    protected function computeComponentNominal(KomponenGaji $komponen, Pegawai $pegawai, $pegawaiKomponens, $globalKomponens, array $counts, $skalas, Carbon $periodeEnd, Carbon $periodeStart, Carbon $attendanceCutoff): float
    {
        $nominal = 0;

        if ($komponen->jenis === 'fixed') {
            if ($pegawaiKomponens->has($komponen->id) && $pegawaiKomponens[$komponen->id]->pivot->nominal !== null) {
                $nominal = $pegawaiKomponens[$komponen->id]->pivot->nominal;
            } else {
                $nominal = $komponen->nilai_default ?? 0;
            }
        } elseif ($komponen->jenis === 'persentase') {
            $gajiPokok = $globalKomponens->first(function ($k) {
                return $k->kode === 'gaji_pokok'
                    || stripos($k->nama, 'Gaji Pokok') !== false
                    || stripos($k->nama, 'Basic Salary') !== false;
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

            $kode = $komponen->kode;
            if ($kode === 'kehadiran_telat' || stripos($komponen->nama, 'telat') !== false) {
                $nominal = $rate * $counts['telat'];
            } elseif ($kode === 'kehadiran_alpa' || stripos($komponen->nama, 'alpa') !== false) {
                $nominal = $rate * $counts['alpa'];
            } elseif ($kode === 'kehadiran_sakit' || stripos($komponen->nama, 'sakit') !== false) {
                $nominal = $rate * $counts['sakit'];
            } elseif ($kode === 'kehadiran_izin' || stripos($komponen->nama, 'izin') !== false) {
                $nominal = $rate * $counts['izin'];
            } elseif ($kode === 'kehadiran_cuti' || stripos($komponen->nama, 'cuti') !== false) {
                $nominal = $rate * $counts['cuti'];
            } elseif ($kode === 'tunjangan_kehadiran' || stripos($komponen->nama, 'makan') !== false || stripos($komponen->nama, 'transport') !== false || stripos($komponen->nama, 'hadir') !== false) {
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
        }

        return (float) $nominal;
    }

    /**
     * Hitung jumlah hari tertentu (nama Indonesia) dalam rentang tanggal [start, end].
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

        $count = 0;
        $d = $start->copy();
        while ($d->lte($end)) {
            if ((int) $d->dayOfWeek === $target) {
                $count++;
            }
            $d->addDay();
        }

        return $count;
    }
}
