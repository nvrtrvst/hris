<?php

namespace App\Http\Controllers;

use App\Exports\KpiReportExport;
use App\Models\Jabatan;
use App\Models\KpiEvaluation;
use App\Models\KpiTemplate;
use App\Models\PegawaiUnit;
use App\Models\UnitSekolah;
use App\Models\User;
use App\Services\KpiMetricsService;
use App\Services\KpiScoreCalculator;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class KpiController extends Controller
{
    public function templates(Request $request)
    {
        abort_unless($request->user()?->can('manage_kpi_templates'), 403);

        return Inertia::render('Kpi/Templates', [
            'templates' => KpiTemplate::with(['jabatan:id,nama', 'unitSekolah:id,nama', 'indicators'])
                ->orderBy('jabatan_id')
                ->orderBy('unit_sekolah_id')
                ->get(),
            'units' => UnitSekolah::orderBy('nama')->get(['id', 'nama']),
            'jabatans' => Jabatan::orderBy('nama')->get(['id', 'nama']),
        ]);
    }

    public function updateTemplate(Request $request, KpiTemplate $template)
    {
        abort_unless($request->user()?->can('manage_kpi_templates'), 403);

        $validated = $request->validate([
            'nama' => 'required|string|max:150',
            'indicators' => 'required|array|min:1',
            'indicators.*.id' => 'required|integer|distinct|exists:kpi_indicators,id',
            'indicators.*.nama' => 'required|string|max:150',
            'indicators.*.deskripsi' => 'nullable|string|max:1000',
            'indicators.*.bobot' => 'required|numeric|min:0|max:100',
            'indicators.*.target' => 'nullable|numeric|min:0',
            'indicators.*.arah' => 'nullable|in:higher,lower',
            'indicators.*.wajib_bukti' => 'required|boolean',
            'indicators.*.rubrik' => 'nullable|array',
            'indicators.*.rubrik.1' => 'nullable|string|max:300',
            'indicators.*.rubrik.2' => 'nullable|string|max:300',
            'indicators.*.rubrik.3' => 'nullable|string|max:300',
            'indicators.*.rubrik.4' => 'nullable|string|max:300',
        ]);

        $indicators = $template->indicators()->where('is_active', true)->get();
        $submittedIds = collect($validated['indicators'])->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();
        if ($submittedIds->all() !== $indicators->pluck('id')->sort()->values()->all()) {
            throw ValidationException::withMessages(['indicators' => 'Semua indikator aktif harus dikirim bersama template.']);
        }
        foreach ($validated['indicators'] as $data) {
            $indicator = $indicators->firstWhere('id', (int) $data['id']);
            if ($indicator?->jenis === 'numeric'
                && (($data['target'] ?? null) === null || ($data['arah'] ?? null) === null || (($data['arah'] ?? null) === 'higher' && (float) $data['target'] <= 0))) {
                throw ValidationException::withMessages(['indicators' => 'Indikator numerik harus memiliki target dan arah hitung yang valid.']);
            }
            if ($indicator?->jenis === 'rubric' && isset($data['rubrik'])
                && collect([1, 2, 3, 4])->contains(fn ($level) => trim((string) ($data['rubrik'][$level] ?? '')) === '')) {
                throw ValidationException::withMessages(['indicators' => 'Setiap rubrik harus memiliki deskripsi untuk tingkat 1 sampai 4.']);
            }
        }
        if (round((float) collect($validated['indicators'])->sum('bobot'), 2) !== 100.0) {
            throw ValidationException::withMessages(['indicators' => 'Total bobot indikator harus tepat 100%.']);
        }

        DB::transaction(function () use ($template, $validated, $indicators) {
            $template->update(['nama' => $validated['nama']]);
            foreach ($validated['indicators'] as $data) {
                $indicators->firstWhere('id', (int) $data['id'])->update([
                    'nama' => $data['nama'],
                    'deskripsi' => $data['deskripsi'] ?? null,
                    'bobot' => $data['bobot'],
                    'target' => $data['target'] ?? null,
                    'arah' => $data['arah'] ?? null,
                    'wajib_bukti' => $data['wajib_bukti'],
                    'rubrik' => $data['rubrik'] ?? $indicators->firstWhere('id', (int) $data['id'])->rubrik,
                ]);
            }
        });

        return back()->with('message', 'Template KPI diperbarui. Perubahan berlaku untuk evaluasi berikutnya.');
    }

    public function createUnitOverride(Request $request)
    {
        abort_unless($request->user()?->can('manage_kpi_templates'), 403);
        $validated = $request->validate([
            'template_id' => 'required|integer|exists:kpi_templates,id',
            'unit_sekolah_id' => 'required|integer|exists:unit_sekolah,id',
        ]);

        $source = KpiTemplate::with('indicators')->findOrFail($validated['template_id']);
        if ($source->unit_sekolah_id !== null) {
            throw ValidationException::withMessages(['template_id' => 'Pilih template dasar, bukan template unit.']);
        }
        if (KpiTemplate::where('jabatan_id', $source->jabatan_id)->where('unit_sekolah_id', $validated['unit_sekolah_id'])->exists()) {
            throw ValidationException::withMessages(['unit_sekolah_id' => 'Penyesuaian template untuk jabatan dan unit ini sudah ada.']);
        }

        DB::transaction(function () use ($source, $validated) {
            $template = KpiTemplate::create([
                'jabatan_id' => $source->jabatan_id,
                'unit_sekolah_id' => $validated['unit_sekolah_id'],
                'nama' => $source->nama.' — '.UnitSekolah::find($validated['unit_sekolah_id'])->nama,
                'is_active' => true,
            ]);
            foreach ($source->indicators as $indicator) {
                $template->indicators()->create($indicator->only([
                    'kode', 'nama', 'deskripsi', 'jenis', 'sumber', 'source_key', 'target', 'arah',
                    'bobot', 'satuan', 'rubrik', 'wajib_bukti', 'urutan', 'is_active',
                ]));
            }
        });

        return back()->with('message', 'Template khusus unit dibuat dari template dasar.');
    }

    public function editEvaluation(Request $request, PegawaiUnit $assignment)
    {
        $user = $request->user();
        $assignment->load(['pegawai', 'unitSekolah', 'jabatan']);
        $canEdit = $user?->can('evaluate_kpi') && $this->canEvaluate($user, $assignment);
        $selfView = $user?->can('view_kpi_self') && (int) $user->pegawai?->id === (int) $assignment->pegawai_id;
        $canView = $user && $this->canViewAssignment($user, $assignment);
        abort_unless($canEdit || $selfView || $canView, 403);

        $validated = $request->validate(['tanggal' => 'nullable|date_format:Y-m-d']);
        $anchor = Carbon::parse($validated['tanggal'] ?? Carbon::today('Asia/Jakarta')->toDateString());
        $start = $anchor->copy()->startOfMonth();
        $end = $anchor->copy()->endOfMonth();
        $evaluation = KpiEvaluation::with(['items', 'template'])
            ->where('pegawai_id', $assignment->pegawai_id)
            ->where('unit_sekolah_id', $assignment->unit_sekolah_id)
            ->where('jabatan_id', $assignment->jabatan_id)
            ->whereDate('periode_mulai', $start->toDateString())
            ->whereDate('periode_selesai', $end->toDateString())
            ->first();
        if ($selfView && $evaluation?->status !== 'finalized') {
            abort(404);
        }

        $template = $evaluation?->status === 'finalized' ? $evaluation->template : null;
        if (! $template) {
            $template = KpiTemplate::with(['indicators' => fn ($q) => $q->where('is_active', true)])
                ->where('jabatan_id', $assignment->jabatan_id)
                ->where('is_active', true)
                ->where('unit_sekolah_id', $assignment->unit_sekolah_id)
                ->first();
            $template ??= KpiTemplate::with(['indicators' => fn ($q) => $q->where('is_active', true)])
                ->where('jabatan_id', $assignment->jabatan_id)
                ->where('is_active', true)
                ->whereNull('unit_sekolah_id')
                ->first();
        }
        abort_unless(($evaluation?->status === 'finalized' && $evaluation->items->isNotEmpty()) || ($template && $template->indicators->isNotEmpty()), 404, 'Template KPI belum tersedia.');

        $systemMetrics = $evaluation?->status === 'finalized'
            ? []
            : app(KpiMetricsService::class)->forAssignments(
                collect([$assignment]),
                $start,
                $end->greaterThan(Carbon::today('Asia/Jakarta')) ? Carbon::today('Asia/Jakarta') : $end,
            )[$assignment->id] ?? [];
        $evaluationItems = $evaluation?->items ?? collect();
        $indicators = $evaluation?->status === 'finalized'
            ? $evaluationItems->map(fn ($item) => [
                'id' => $item->kpi_indicator_id ?? 'snapshot-'.$item->id,
                'kode' => $item->source_key_snapshot,
                'nama' => $item->nama_snapshot,
                'deskripsi' => $item->deskripsi_snapshot,
                'jenis' => $item->jenis_snapshot,
                'sumber' => $item->sumber_snapshot,
                'source_key' => $item->source_key_snapshot,
                'target' => $item->target_snapshot,
                'arah' => $item->arah_snapshot,
                'bobot' => $item->bobot_snapshot,
                'satuan' => $item->satuan_snapshot,
                'rubrik' => $item->rubrik_snapshot,
                'wajib_bukti' => $item->wajib_bukti_snapshot,
                'nilai_sistem' => $item->sumber_snapshot === 'system' ? $item->nilai_aktual : null,
            ])->values()
            : $template->indicators->map(fn ($indicator) => [
                'id' => $indicator->id,
                'kode' => $indicator->kode,
                'nama' => $indicator->nama,
                'deskripsi' => $indicator->deskripsi,
                'jenis' => $indicator->jenis,
                'sumber' => $indicator->sumber,
                'source_key' => $indicator->source_key,
                'target' => $indicator->target,
                'arah' => $indicator->arah,
                'bobot' => $indicator->bobot,
                'satuan' => $indicator->satuan,
                'rubrik' => $indicator->rubrik,
                'wajib_bukti' => $indicator->wajib_bukti,
                'nilai_sistem' => $indicator->sumber === 'system' ? ($systemMetrics[$indicator->source_key] ?? null) : null,
            ])->values();
        $evaluationItemsById = $evaluationItems->mapWithKeys(fn ($item) => [
            (string) ($item->kpi_indicator_id ?? 'snapshot-'.$item->id) => [
                'nilai_aktual' => $item->nilai_aktual,
                'level_rubrik' => $item->level_rubrik,
                'rubrik_snapshot' => $item->rubrik_snapshot,
                'bukti' => $item->bukti,
                'catatan' => $item->catatan,
                'tidak_berlaku' => $item->tidak_berlaku,
                'alasan_tidak_berlaku' => $item->alasan_tidak_berlaku,
            ],
        ]);

        return Inertia::render('Kpi/Evaluate', [
            'assignment' => [
                'id' => $assignment->id,
                'pegawai_id' => $assignment->pegawai_id,
                'pegawai' => $assignment->pegawai->nama_lengkap,
                'unit_sekolah_id' => $assignment->unit_sekolah_id,
                'unit' => $assignment->unitSekolah->nama,
                'jabatan_id' => $assignment->jabatan_id,
                'jabatan' => $assignment->jabatan->nama,
            ],
            'periode' => ['mulai' => $start->toDateString(), 'selesai' => $end->toDateString()],
            'template' => ['id' => $template?->id ?? $evaluation?->kpi_template_id, 'nama' => $template?->nama ?? 'Snapshot KPI'],
            'indicators' => $indicators,
            'evaluation' => $evaluation ? [
                'id' => $evaluation->id,
                'status' => $evaluation->status,
                'nilai_akhir' => $evaluation->nilai_akhir,
                'ringkasan' => $evaluation->ringkasan,
                'tanggapan_pegawai' => $evaluation->tanggapan_pegawai,
                'ditanggapi_pada' => $evaluation->ditanggapi_pada?->format('Y-m-d H:i'),
                'items' => $evaluationItemsById,
            ] : null,
            'can_edit' => (bool) $canEdit,
            'can_respond' => (bool) ($selfView && $evaluation?->status === 'finalized'),
        ]);
    }

    public function index(Request $request, KpiMetricsService $metrics)
    {
        $user = $request->user();
        $selfOnly = $user?->can('view_kpi_self') && ! $user->can('view_kpi') && ! $user->can('evaluate_kpi');
        abort_unless($user && ($user->can('view_kpi') || $selfOnly), 403);

        $filters = $request->validate([
            'periode' => 'nullable|in:daily,weekly,monthly',
            'tanggal' => 'nullable|date_format:Y-m-d',
            'unit_sekolah_id' => 'nullable|integer|exists:unit_sekolah,id',
            'jabatan_id' => 'nullable|integer|exists:jabatan,id',
            'search' => 'nullable|string|max:100',
            'export' => 'nullable|in:excel,pdf',
        ]);
        $periode = $filters['periode'] ?? 'monthly';
        $anchor = Carbon::parse($filters['tanggal'] ?? Carbon::today('Asia/Jakarta')->toDateString());
        [$start, $end] = match ($periode) {
            'daily' => [$anchor->copy(), $anchor->copy()],
            'weekly' => [$anchor->copy()->startOfWeek(Carbon::MONDAY), $anchor->copy()->endOfWeek(Carbon::SUNDAY)],
            default => [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth()],
        };
        $metricsEnd = $end->greaterThan(Carbon::today('Asia/Jakarta'))
            ? Carbon::today('Asia/Jakarta')
            : $end->copy();

        $unitId = $filters['unit_sekolah_id'] ?? null;
        $query = PegawaiUnit::with(['pegawai', 'unitSekolah', 'jabatan'])
            ->whereHas('pegawai', fn ($q) => $q->where('status_aktif', 'aktif'));

        if ($selfOnly && $user->pegawai) {
            $query->where('pegawai_id', $user->pegawai->id)
                ->when($unitId, fn ($q) => $q->where('unit_sekolah_id', $unitId));
        } elseif ($user->can('view_all_units')) {
            $query->when($unitId, fn ($q) => $q->where('unit_sekolah_id', $unitId));
        } elseif ($user->hasRole('admin_unit') && $user->unit_sekolah_id) {
            $unitId = (int) $user->unit_sekolah_id;
            $query->where('unit_sekolah_id', $unitId);
        } elseif ($user->hasRole('pimpinan') && ! $user->hasRole('admin_unit') && $user->pegawai) {
            $manager = $user->pegawai;
            $jabatanPrimer = $manager->jabatanPrimer();
            if ($jabatanPrimer?->nama === 'Kepala Sekolah') {
                $managerUnitId = $manager->primaryUnit()?->id;
                $unitId = $unitId ?: $managerUnitId;
                $query->where('unit_sekolah_id', $unitId && (int) $unitId === (int) $managerUnitId ? $unitId : 0);
            } else {
                $query->whereHas('pegawai', fn ($q) => $q->where('atasan_langsung_id', $manager->id));
                $query->when($unitId, fn ($q) => $q->where('unit_sekolah_id', $unitId));
            }
        } elseif ($user->unit_sekolah_id) {
            $unitId = (int) $user->unit_sekolah_id;
            $query->where('unit_sekolah_id', $unitId);
        } else {
            abort(403);
        }

        $query->when($filters['jabatan_id'] ?? null, fn ($q, $jabatanId) => $q->where('jabatan_id', $jabatanId));
        $query->when($filters['search'] ?? null, fn ($q, $search) => $q->whereHas('pegawai', fn ($p) => $p->where('nama_lengkap', 'like', '%'.$search.'%')));
        $assignments = $query->orderBy('unit_sekolah_id')->orderBy('jabatan_id')->get();
        $metricRows = $metrics->forAssignments($assignments, $start, $metricsEnd);

        $evaluations = $periode === 'monthly'
            ? KpiEvaluation::whereIn('pegawai_id', $assignments->pluck('pegawai_id'))
                ->with('items')
                ->whereIn('unit_sekolah_id', $assignments->pluck('unit_sekolah_id'))
                ->when($selfOnly, fn ($q) => $q->where('status', 'finalized'))
                ->whereDate('periode_mulai', $start->toDateString())
                ->whereDate('periode_selesai', $end->toDateString())
                ->get()
                ->keyBy(fn ($evaluation) => $evaluation->pegawai_id.'|'.$evaluation->unit_sekolah_id.'|'.$evaluation->jabatan_id)
            : collect();

        $rows = $assignments->map(function (PegawaiUnit $assignment) use ($metricRows, $evaluations, $user, $selfOnly) {
            $key = $assignment->pegawai_id.'|'.$assignment->unit_sekolah_id.'|'.$assignment->jabatan_id;
            $metrics = $metricRows[$assignment->id] ?? [];
            $evaluation = $evaluations->get($key);

            return [
                'assignment_id' => $assignment->id,
                'pegawai_id' => $assignment->pegawai_id,
                'pegawai' => $assignment->pegawai?->nama_lengkap,
                'jabatan_id' => $assignment->jabatan_id,
                'jabatan' => $assignment->jabatan?->nama,
                'unit_sekolah_id' => $assignment->unit_sekolah_id,
                'unit' => $assignment->unitSekolah?->nama,
                'attendance_rate' => $metrics['attendance_rate'] ?? null,
                'punctuality_rate' => $metrics['punctuality_rate'] ?? null,
                'hadir_hari' => $metrics['hadir_hari'] ?? 0,
                'hari_kerja' => $metrics['hari_kerja'] ?? 0,
                'teaching_jp_rate' => $metrics['teaching_jp_rate'] ?? null,
                'teaching_jp_hadir' => $metrics['teaching_jp_hadir'] ?? 0,
                'teaching_jp_terjadwal' => $metrics['teaching_jp_terjadwal'] ?? 0,
                'evaluation_id' => $evaluation?->id,
                'evaluation_status' => $evaluation?->status,
                'score' => $evaluation?->nilai_akhir,
                'ringkasan' => $evaluation?->ringkasan,
                'tanggapan_pegawai' => $evaluation?->tanggapan_pegawai,
                'evaluation_items' => $evaluation?->items->map(fn ($item) => [
                    'nama' => $item->nama_snapshot,
                    'target' => $item->target_snapshot,
                    'aktual' => $item->nilai_aktual,
                    'bobot' => $item->bobot_snapshot,
                    'nilai' => $item->nilai,
                    'bukti' => $item->bukti,
                    'catatan' => $item->catatan,
                    'tidak_berlaku' => $item->tidak_berlaku,
                    'alasan_tidak_berlaku' => $item->alasan_tidak_berlaku,
                    'level_rubrik' => $item->level_rubrik,
                    'rubrik_snapshot' => $item->rubrik_snapshot,
                ])->values() ?? [],
                'can_evaluate' => ! $selfOnly && $this->canEvaluate($user, $assignment),
            ];
        })->values();

        $units = $user->can('view_all_units')
            ? UnitSekolah::orderBy('nama')->get(['id', 'nama'])
            : $assignments->pluck('unitSekolah')->filter()->unique('id')->values()->map->only(['id', 'nama']);
        $jabatans = $assignments->pluck('jabatan')->filter()->unique('id')->values()->map->only(['id', 'nama']);
        $scored = $rows->where('evaluation_status', 'finalized')->where('score', '!=', null);
        $jpScored = $rows->where('teaching_jp_rate', '!=', null);
        $punctualityScored = $rows->where('punctuality_rate', '!=', null);
        $roleCount = $rows->pluck('jabatan_id')->unique()->count();
        $summary = [
            'pegawai' => $rows->count(),
            'evaluasi_final' => $rows->where('evaluation_status', 'finalized')->count(),
            'rata_rata_nilai' => $roleCount <= 1 && $scored->isNotEmpty() ? round((float) $scored->avg('score'), 2) : null,
            'rata_rata_hadir' => $rows->where('attendance_rate', '!=', null)->isNotEmpty()
                ? round((float) $rows->where('attendance_rate', '!=', null)->avg('attendance_rate'), 2)
                : null,
            'rata_rata_jp' => $jpScored->isNotEmpty() ? round((float) $jpScored->avg('teaching_jp_rate'), 2) : null,
            'rata_rata_tepat_waktu' => $punctualityScored->isNotEmpty() ? round((float) $punctualityScored->avg('punctuality_rate'), 2) : null,
        ];

        if (($filters['export'] ?? null) === 'excel') {
            return Excel::download(
                new KpiReportExport($rows),
                'Laporan_KPI_'.$start->format('Ymd').'_to_'.$metricsEnd->format('Ymd').'.xlsx'
            );
        }
        if (($filters['export'] ?? null) === 'pdf') {
            if ($rows->count() > 500) {
                throw ValidationException::withMessages(['export' => 'PDF dibatasi 500 baris; gunakan Excel untuk laporan lebih besar.']);
            }

            return Pdf::loadView('pdf.kpi-report', [
                'rows' => $rows,
                'periode' => $periode,
                'start' => $start->toDateString(),
                'end' => $metricsEnd->toDateString(),
                'unitNama' => $unitId ? UnitSekolah::find($unitId)?->nama : 'Sesuai cakupan akses',
            ])->setPaper('a4', 'landscape')->download('Laporan_KPI_'.$start->format('Ymd').'_to_'.$metricsEnd->format('Ymd').'.pdf');
        }

        return Inertia::render('Kpi/Index', [
            'rows' => $rows,
            'units' => $units,
            'jabatans' => $jabatans,
            'filters' => [
                'periode' => $periode,
                'tanggal' => $anchor->toDateString(),
                'unit_sekolah_id' => $unitId,
                'jabatan_id' => $filters['jabatan_id'] ?? null,
                'search' => $filters['search'] ?? '',
            ],
            'periode' => ['start' => $start->toDateString(), 'end' => $metricsEnd->toDateString(), 'kalender_selesai' => $end->toDateString()],
            'summary' => $summary,
            'can_manage_templates' => $user->can('manage_kpi_templates'),
        ]);
    }

    public function myKpi(Request $request, KpiMetricsService $metrics)
    {
        $user = $request->user();
        abort_unless($user?->can('view_kpi_self') && $user->pegawai, 403);

        $pegawai = $user->pegawai;
        $assignments = PegawaiUnit::with(['pegawai', 'unitSekolah', 'jabatan'])
            ->where('pegawai_id', $pegawai->id)
            ->whereHas('unitSekolah')
            ->get();
        $today = Carbon::today('Asia/Jakarta');
        $start = $today->copy()->startOfMonth();
        $metricsByAssignment = $metrics->forAssignments($assignments, $start, $today);
        $evaluations = KpiEvaluation::with('items')
            ->where('pegawai_id', $pegawai->id)
            ->where('status', 'finalized')
            ->orderByDesc('periode_mulai')
            ->get();

        return Inertia::render('Mobile/Kpi', [
            'pegawai' => ['nama' => $pegawai->nama_lengkap],
            'bulan_ini' => ['mulai' => $start->toDateString(), 'sampai' => $today->toDateString()],
            'assignments' => $assignments->map(function (PegawaiUnit $assignment) use ($metricsByAssignment, $evaluations) {
                $current = $metricsByAssignment[$assignment->id] ?? [];
                $history = $evaluations
                    ->where('unit_sekolah_id', $assignment->unit_sekolah_id)
                    ->where('jabatan_id', $assignment->jabatan_id)
                    ->take(12)
                    ->map(fn (KpiEvaluation $evaluation) => [
                        'id' => $evaluation->id,
                        'periode_mulai' => $evaluation->periode_mulai->format('Y-m-d'),
                        'periode_selesai' => $evaluation->periode_selesai->format('Y-m-d'),
                        'nilai_akhir' => $evaluation->nilai_akhir,
                        'ringkasan' => $evaluation->ringkasan,
                        'tanggapan_pegawai' => $evaluation->tanggapan_pegawai,
                        'ditanggapi_pada' => $evaluation->ditanggapi_pada?->format('Y-m-d H:i'),
                        'items' => $evaluation->items->map(fn ($item) => [
                            'nama' => $item->nama_snapshot,
                            'target' => $item->target_snapshot,
                            'aktual' => $item->nilai_aktual,
                            'satuan' => $item->satuan_snapshot,
                            'bobot' => $item->bobot_snapshot,
                            'nilai' => $item->nilai,
                            'bukti' => $item->bukti,
                            'catatan' => $item->catatan,
                            'level_rubrik' => $item->level_rubrik,
                            'label_rubrik' => $item->rubrik_snapshot[$item->level_rubrik] ?? null,
                            'tidak_berlaku' => $item->tidak_berlaku,
                            'alasan_tidak_berlaku' => $item->alasan_tidak_berlaku,
                        ])->values(),
                    ])->values();

                return [
                    'unit' => $assignment->unitSekolah?->nama,
                    'jabatan' => $assignment->jabatan?->nama,
                    'attendance_rate' => $current['attendance_rate'] ?? null,
                    'punctuality_rate' => $current['punctuality_rate'] ?? null,
                    'hadir_hari' => $current['hadir_hari'] ?? 0,
                    'hari_kerja' => $current['hari_kerja'] ?? 0,
                    'teaching_jp_rate' => $current['teaching_jp_rate'] ?? null,
                    'teaching_jp_hadir' => $current['teaching_jp_hadir'] ?? 0,
                    'teaching_jp_terjadwal' => $current['teaching_jp_terjadwal'] ?? 0,
                    'evaluations' => $history,
                ];
            })->values(),
        ]);
    }

    public function storeEvaluation(Request $request, KpiMetricsService $metrics)
    {
        $user = $request->user();
        abort_unless($user?->can('evaluate_kpi'), 403);

        $validated = $request->validate([
            'pegawai_id' => 'required|integer|exists:pegawai,id',
            'unit_sekolah_id' => 'required|integer|exists:unit_sekolah,id',
            'jabatan_id' => 'required|integer|exists:jabatan,id',
            'periode_mulai' => 'required|date_format:Y-m-d',
            'periode_selesai' => 'required|date_format:Y-m-d|after_or_equal:periode_mulai',
            'ringkasan' => 'nullable|string|max:2000',
            'finalize' => 'nullable|boolean',
            'items' => 'required|array',
            'items.*.nilai_aktual' => 'nullable|numeric|min:0',
            'items.*.level_rubrik' => 'nullable|integer|between:1,4',
            'items.*.bukti' => 'nullable|string|max:4000',
            'items.*.catatan' => 'nullable|string|max:2000',
            'items.*.tidak_berlaku' => 'nullable|boolean',
            'items.*.alasan_tidak_berlaku' => 'nullable|string|max:500',
        ]);

        $start = Carbon::parse($validated['periode_mulai']);
        $end = Carbon::parse($validated['periode_selesai']);
        if (! $start->isStartOfMonth() || ! $end->isLastOfMonth() || ! $start->isSameMonth($end)) {
            throw ValidationException::withMessages(['periode_mulai' => 'Evaluasi KPI harus memakai satu bulan kalender penuh.']);
        }
        if (($validated['finalize'] ?? false) && $end->isAfter(Carbon::today('Asia/Jakarta'))) {
            throw ValidationException::withMessages(['periode_selesai' => 'Periode KPI belum selesai dan belum bisa difinalisasi.']);
        }

        $assignment = PegawaiUnit::with(['pegawai', 'unitSekolah', 'jabatan'])
            ->where('pegawai_id', $validated['pegawai_id'])
            ->where('unit_sekolah_id', $validated['unit_sekolah_id'])
            ->where('jabatan_id', $validated['jabatan_id'])
            ->firstOrFail();
        abort_unless($this->canEvaluate($user, $assignment), 403);

        $template = KpiTemplate::with(['indicators' => fn ($q) => $q->where('is_active', true)])
            ->where('jabatan_id', $assignment->jabatan_id)
            ->where('is_active', true)
            ->where('unit_sekolah_id', $assignment->unit_sekolah_id)
            ->first();
        $template ??= KpiTemplate::with(['indicators' => fn ($q) => $q->where('is_active', true)])
            ->where('jabatan_id', $assignment->jabatan_id)
            ->where('is_active', true)
            ->whereNull('unit_sekolah_id')
            ->first();

        if (! $template || $template->indicators->isEmpty()) {
            throw ValidationException::withMessages(['jabatan_id' => 'Template KPI untuk jabatan ini belum tersedia.']);
        }

        $evaluation = KpiEvaluation::where('pegawai_id', $assignment->pegawai_id)
            ->where('unit_sekolah_id', $assignment->unit_sekolah_id)
            ->where('jabatan_id', $assignment->jabatan_id)
            ->whereDate('periode_mulai', $start->toDateString())
            ->whereDate('periode_selesai', $end->toDateString())
            ->first();
        if ($evaluation?->status === 'finalized') {
            throw ValidationException::withMessages(['evaluasi' => 'Evaluasi sudah difinalisasi dan terkunci.']);
        }

        $itemRows = [];
        $scores = [];
        $incomplete = [];
        $missingEvidence = [];
        $finalize = (bool) ($validated['finalize'] ?? false);
        $metricsEnd = ! $finalize && $end->isAfter(Carbon::today('Asia/Jakarta'))
            ? Carbon::today('Asia/Jakarta')
            : $end;
        foreach ($template->indicators as $indicator) {
            $input = $validated['items'][$indicator->id] ?? [];
            $notApplicable = (bool) ($input['tidak_berlaku'] ?? false);
            $score = null;
            $actual = null;
            $level = null;
            $evidence = trim((string) ($input['bukti'] ?? ''));
            $reason = trim((string) ($input['alasan_tidak_berlaku'] ?? ''));

            if ($notApplicable) {
                if ($reason === '') {
                    throw ValidationException::withMessages([
                        "items.{$indicator->id}.alasan_tidak_berlaku" => 'Jelaskan alasan indikator tidak berlaku.',
                    ]);
                }
            } else {
                if ($indicator->sumber === 'system') {
                    $actual = $metrics->actual(
                        (string) $indicator->source_key,
                        $assignment->pegawai,
                        $assignment->unitSekolah,
                        $start,
                        $metricsEnd,
                    );
                } elseif ($indicator->jenis === 'rubric') {
                    $level = isset($input['level_rubrik']) ? (int) $input['level_rubrik'] : null;
                    if ($level !== null) {
                        $score = KpiScoreCalculator::rubricScore($level);
                    }
                } else {
                    $actual = isset($input['nilai_aktual']) ? (float) $input['nilai_aktual'] : null;
                }

                if ($indicator->jenis === 'numeric' && $actual !== null) {
                    $score = KpiScoreCalculator::score($actual, (float) $indicator->target, (string) $indicator->arah);
                }
                if ($finalize && $indicator->wajib_bukti && $indicator->sumber === 'manual' && $score !== null && $evidence === '') {
                    $missingEvidence[] = $indicator->nama;
                }
                if ($score === null) {
                    $incomplete[] = $indicator->nama;
                }
            }

            $scores[] = ['score' => $notApplicable ? null : $score, 'weight' => (float) $indicator->bobot];
            $itemRows[] = [
                'kpi_indicator_id' => $indicator->id,
                'nama_snapshot' => $indicator->nama,
                'deskripsi_snapshot' => $indicator->deskripsi,
                'jenis_snapshot' => $indicator->jenis,
                'sumber_snapshot' => $indicator->sumber,
                'source_key_snapshot' => $indicator->source_key,
                'bobot_snapshot' => $indicator->bobot,
                'target_snapshot' => $indicator->target,
                'arah_snapshot' => $indicator->arah,
                'satuan_snapshot' => $indicator->satuan,
                'wajib_bukti_snapshot' => $indicator->wajib_bukti,
                'nilai_aktual' => $actual,
                'level_rubrik' => $level,
                'rubrik_snapshot' => $indicator->rubrik,
                'nilai' => $score,
                'bukti' => $evidence !== '' ? $evidence : null,
                'catatan' => $input['catatan'] ?? null,
                'tidak_berlaku' => $notApplicable,
                'alasan_tidak_berlaku' => $notApplicable ? $reason : null,
            ];
        }

        if ($finalize && $incomplete !== []) {
            throw ValidationException::withMessages(['items' => 'Lengkapi indikator sebelum finalisasi: '.implode(', ', $incomplete)]);
        }
        if ($finalize && $missingEvidence !== []) {
            throw ValidationException::withMessages(['items' => 'Bukti wajib belum diisi: '.implode(', ', $missingEvidence)]);
        }
        if ($finalize && count(array_filter($itemRows, fn ($row) => ! $row['tidak_berlaku'])) === 0) {
            throw ValidationException::withMessages(['items' => 'Minimal satu indikator harus berlaku pada periode ini.']);
        }

        $weightedScore = KpiScoreCalculator::weightedScore($scores);
        if (count(array_filter($itemRows, fn ($row) => ! $row['tidak_berlaku'] && $row['nilai'] === null)) > 0) {
            $weightedScore = null;
        }

        DB::transaction(function () use ($evaluation, $assignment, $template, $start, $end, $validated, $user, $finalize, $weightedScore, $itemRows) {
            $evaluation ??= new KpiEvaluation;
            $evaluation->fill([
                'pegawai_id' => $assignment->pegawai_id,
                'unit_sekolah_id' => $assignment->unit_sekolah_id,
                'jabatan_id' => $assignment->jabatan_id,
                'kpi_template_id' => $template->id,
                'periode_mulai' => $start->toDateString(),
                'periode_selesai' => $end->toDateString(),
                'status' => $finalize ? 'finalized' : 'draft',
                'nilai_akhir' => $weightedScore,
                'ringkasan' => $validated['ringkasan'] ?? null,
                'dinilai_oleh' => $user->id,
                'difinalisasi_oleh' => $finalize ? $user->id : null,
                'difinalisasi_pada' => $finalize ? now() : null,
            ])->save();

            $evaluation->items()->get()->each->delete();
            $evaluation->items()->createMany($itemRows);
        });

        return back()->with('message', $finalize ? 'Evaluasi KPI difinalisasi.' : 'Draft evaluasi KPI tersimpan.');
    }

    public function reopenEvaluation(Request $request, KpiEvaluation $evaluation)
    {
        abort_unless($request->user()?->can('manage_kpi_templates'), 403);
        if ($evaluation->status !== 'finalized') {
            throw ValidationException::withMessages(['evaluasi' => 'Hanya evaluasi final yang dapat dibuka kembali.']);
        }

        $evaluation->update([
            'status' => 'draft',
            'difinalisasi_oleh' => null,
            'difinalisasi_pada' => null,
        ]);

        return back()->with('message', 'Evaluasi dibuka kembali untuk koreksi. Perubahan tercatat di audit log.');
    }

    public function respondToEvaluation(Request $request, KpiEvaluation $evaluation)
    {
        abort_unless(
            $request->user()?->can('view_kpi_self')
                && (int) $request->user()->pegawai?->id === (int) $evaluation->pegawai_id,
            403,
        );
        if ($evaluation->status !== 'finalized') {
            throw ValidationException::withMessages(['tanggapan_pegawai' => 'Tanggapan hanya dapat dikirim setelah evaluasi difinalisasi.']);
        }

        $validated = $request->validate([
            'tanggapan_pegawai' => 'required|string|min:3|max:2000',
        ]);
        $evaluation->update([
            'tanggapan_pegawai' => $validated['tanggapan_pegawai'],
            'ditanggapi_oleh' => $request->user()->id,
            'ditanggapi_pada' => now(),
        ]);

        return back()->with('message', 'Tanggapan Anda tersimpan.');
    }

    private function canEvaluate(User $user, PegawaiUnit $assignment): bool
    {
        if ($user->pegawai && (int) $user->pegawai->id === (int) $assignment->pegawai_id) {
            return false;
        }
        if ($user->can('view_all_units')) {
            return true;
        }
        if ($user->hasRole('admin_unit') && $user->unit_sekolah_id) {
            return (int) $user->unit_sekolah_id === (int) $assignment->unit_sekolah_id;
        }
        if ($user->hasRole('pimpinan') && ! $user->hasRole('admin_unit') && $user->pegawai) {
            $jabatan = $user->pegawai->jabatanPrimer();
            if ($jabatan?->nama === 'Kepala Sekolah') {
                return $user->pegawai->belongsToUnit($assignment->unit_sekolah_id);
            }

            return (int) $assignment->pegawai->atasan_langsung_id === (int) $user->pegawai->id;
        }
        if ($user->unit_sekolah_id) {
            return (int) $user->unit_sekolah_id === (int) $assignment->unit_sekolah_id;
        }

        return false;
    }

    private function canViewAssignment(User $user, PegawaiUnit $assignment): bool
    {
        if ($user->can('view_all_units')) {
            return true;
        }
        if ($user->hasRole('admin_unit') && $user->unit_sekolah_id) {
            return (int) $user->unit_sekolah_id === (int) $assignment->unit_sekolah_id;
        }
        if ($user->hasRole('pimpinan') && ! $user->hasRole('admin_unit') && $user->pegawai) {
            $jabatan = $user->pegawai->jabatanPrimer();
            if ($jabatan?->nama === 'Kepala Sekolah') {
                return $user->pegawai->belongsToUnit($assignment->unit_sekolah_id);
            }

            return (int) $assignment->pegawai->atasan_langsung_id === (int) $user->pegawai->id;
        }
        if ($user->can('view_kpi_self') && (int) $user->pegawai?->id === (int) $assignment->pegawai_id) {
            return true;
        }

        return (bool) ($user->unit_sekolah_id && (int) $user->unit_sekolah_id === (int) $assignment->unit_sekolah_id);
    }
}
