<?php

namespace App\Services;

use App\Constants\PresensiStatus;
use App\Models\HariLibur;
use App\Models\LaporanTindakLanjut;
use App\Models\PengajuanIzin;
use App\Models\PengajuanKoreksi;
use App\Models\Presensi;
use App\Models\User;
use App\Support\HariKerja;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LaporanPemenuhanService
{
    public function enrich(array $data, Collection $employees, Collection $units, User $user): array
    {
        $from = Carbon::parse($data['period']['from']);
        $to = Carbon::parse($data['period']['to']);
        $ids = $employees->pluck('id');
        $pendingIzin = PengajuanIzin::whereIn('pegawai_id', $ids)->where('status', 'pending')
            ->whereDate('tanggal_mulai', '<=', $to)->whereDate('tanggal_selesai', '>=', $from)->get();
        $pendingKoreksi = PengajuanKoreksi::whereIn('pegawai_id', $ids)->where('status', 'pending')
            ->where('tanggal', '>=', $from->toDateString())->where('tanggal', '<', $to->copy()->addDay()->toDateString())->get();
        $lembur = Presensi::whereIn('pegawai_id', $ids)->whereIn('unit_sekolah_id', $units->pluck('id'))
            ->where('is_lembur', true)->where('lembur_status', 'pending')
            ->where('tanggal', '>=', $from->toDateString())->where('tanggal', '<', $to->copy()->addDay()->toDateString())->get();
        $records = LaporanTindakLanjut::whereIn('pegawai_id', $ids)->whereIn('unit_sekolah_id', $units->pluck('id'))
            ->whereBetween('tanggal', [$from->copy()->startOfWeek()->toDateString(), $to->toDateString()])->get()->keyBy('finding_key');
        $access = app(LaporanTindakLanjutAccess::class);
        $canManage = $access->canManage($user);
        $candidates = [];
        foreach ($units as $unit) {
            $candidates[$unit->id] = $canManage ? $access->candidates($unit->id)->all() : [];
            if ($canManage && $user->can('view_all_units')) {
                $candidates[$unit->id][] = ['id' => $user->id, 'name' => $user->name];
            }
        }
        $findings = [];
        $add = function (array $row, string $kind, string $date, string $label, string $evidence, ?int $slot = null) use (&$findings) {
            $key = hash('sha256', implode('|', [$row['unit_id'], $row['pegawai_id'], $kind, $date, $slot ?? 0]));
            $findings[$key] = ['key' => $key, 'pegawai_id' => $row['pegawai_id'], 'pegawai' => $row['pegawai'],
                'unit_sekolah_id' => $row['unit_id'], 'unit' => $row['unit'], 'jenis' => $kind, 'tanggal' => $date,
                'label' => $label, 'evidence' => $evidence, 'source_changed' => false];
        };
        $holidayRows = HariLibur::where('tanggal', '>=', $from->copy()->startOfWeek()->toDateString())->where('tanggal', '<', $to->copy()->endOfWeek()->addDay()->toDateString())
            ->where(fn ($q) => $q->whereNull('unit_sekolah_id')->orWhereIn('unit_sekolah_id', $units->pluck('id')))->get();
        $approvedLeaves = PengajuanIzin::whereIn('pegawai_id', $ids)->where('status', 'disetujui')->whereDate('tanggal_mulai', '<=', $to->copy()->endOfWeek())->whereDate('tanggal_selesai', '>=', $from->copy()->startOfWeek())->get();
        $allAttendance = Presensi::whereIn('pegawai_id', $ids)->where('is_lembur', false)
            ->where('tanggal', '>=', $from->copy()->startOfWeek()->toDateString())->where('tanggal', '<', Carbon::parse(min($to->copy()->endOfWeek()->toDateString(), today()->toDateString()))->addDay()->toDateString())->get()
            ->groupBy(fn ($p) => $p->pegawai_id.'|'.$p->unit_sekolah_id);
        foreach ($data['matrix'] as &$row) {
            $pegawai = $employees->firstWhere('id', $row['pegawai_id']);
            $unit = $units->firstWhere('id', $row['unit_id']);
            $row['belum_tercatat'] = 0;
            $row['menunggu_keputusan'] = 0;
            $row['alpa_terkonfirmasi'] = 0;
            foreach ($row['temuan_detail'] as $finding) {
                foreach ($finding['items'] as $item) {
                    $date = $item['tanggal'];
                    $pending = $pendingIzin->contains(fn ($i) => $i->pegawai_id === $pegawai->id && $date >= Carbon::parse($i->tanggal_mulai)->toDateString() && $date <= Carbon::parse($i->tanggal_selesai)->toDateString())
                        || $pendingKoreksi->contains(fn ($i) => $i->pegawai_id === $pegawai->id && Carbon::parse($i->tanggal)->toDateString() === $date);
                    $kind = $finding['key'] === 'alpa' ? 'belum_tercatat' : $finding['key'];
                    $label = $finding['key'] === 'alpa' ? 'Belum tercatat' : $finding['label'];
                    if ($kind === 'belum_tercatat') {
                        if ($pending) {
                            $row['menunggu_keputusan']++;

                            continue;
                        }
                        $row['belum_tercatat']++;
                    }
                    $evidence = isset($item['scheduled_jp']) ? 'JP tercatat '.($item['actual_jp'] ?? 0).' / '.$item['scheduled_jp'] : 'Presensi dan izin pada '.$date;
                    $add($row, $kind, $date, $label, $evidence);
                }
            }
            foreach ($pendingIzin->where('pegawai_id', $pegawai->id) as $p) {
                $date = Carbon::parse($p->tanggal_mulai)->toDateString();
                if ($date <= today()->toDateString()) {
                    $add($row, 'izin_pending', $date, 'Izin menunggu keputusan', 'Pengajuan izin #'.$p->id, $p->id);
                }
            }
            foreach ($pendingKoreksi->where('pegawai_id', $pegawai->id) as $p) {
                $date = Carbon::parse($p->tanggal)->toDateString();
                if ($date <= today()->toDateString()) {
                    $add($row, 'koreksi_pending', $date, 'Koreksi menunggu keputusan', 'Pengajuan koreksi #'.$p->id, $p->id);
                }
            }
            foreach ($lembur->where('pegawai_id', $pegawai->id)->where('unit_sekolah_id', $unit->id) as $p) {
                $date = Carbon::parse($p->tanggal)->toDateString();
                if ($date <= today()->toDateString()) {
                    $add($row, 'lembur_pending', $date, 'Lembur menunggu keputusan', 'Presensi lembur #'.$p->id, $p->id);
                }
            }
            $row['weeks'] = [];
            $holidayMap = ['national_dates' => $holidayRows->filter(fn ($h) => ! $h->unit_sekolah_id || $h->unit_sekolah_id === $unit->id)
                ->mapWithKeys(fn ($h) => [Carbon::parse($h->tanggal)->toDateString() => true])->all()];
            $attendance = $allAttendance->get($pegawai->id.'|'.$unit->id, collect());
            for ($week = $from->copy()->startOfWeek(); $week->lte($to); $week->addWeek()) {
                $end = $week->copy()->endOfWeek()->startOfDay();
                $quota = HariKerja::mingguKerjaGuru($pegawai, $unit, $week, $end, $holidayMap)[0] ?? null;
                if (! $quota) {
                    continue;
                }
                $dates = collect($quota['segments'])->flatMap(fn ($s) => $s['quota_dates'])->unique()->all();
                $creditedDates = $attendance->filter(fn ($p) => in_array($p->status, PresensiStatus::TERHITUNG_HARI_KERJA, true))->map(fn ($p) => Carbon::parse($p->tanggal)->toDateString())->all();
                $realized = count(array_filter($dates, fn ($date) => $date <= today()->toDateString() && (in_array($date, $creditedDates, true) || $approvedLeaves->contains(fn ($leave) => $leave->pegawai_id === $pegawai->id && $date >= Carbon::parse($leave->tanggal_mulai)->toDateString() && $date <= Carbon::parse($leave->tanggal_selesai)->toDateString()))));
                $final = $end->lt(today()) && $end->lte($to);
                $row['weeks'][] = ['from' => $week->toDateString(), 'to' => $end->toDateString(), 'target' => $quota['target'],
                    'realized' => $realized, 'final' => $final, 'partial' => $week->lt($from) || $end->gt($to)];
                if ($final && $quota['target'] > $realized) {
                    $add($row, 'kuota_kurang', $end->toDateString(), 'Kuota mingguan belum terpenuhi',
                        $week->toDateString().' sampai '.$end->toDateString().': '.$realized.' / '.$quota['target'].' hari');
                }
            }
            $row['target_hari'] = $row['employee_days'];
            foreach ($row['weeks'] as $weekRow) {
                if ($weekRow['partial']) {
                    continue;
                }
                $scheduledDays = count(array_filter($row['counted_dates'], fn ($date) => $date >= $weekRow['from'] && $date <= $weekRow['to']));
                $row['target_hari'] += max(0, $weekRow['target'] - $scheduledDays);
            }
            $row['realisasi_hari'] = $attendance->filter(fn ($p) => Carbon::parse($p->tanggal)->toDateString() >= $from->toDateString() && Carbon::parse($p->tanggal)->toDateString() <= $to->toDateString() && in_array($p->status, ['hadir', 'telat'], true))->pluck('tanggal')->map(fn ($d) => Carbon::parse($d)->toDateString())->unique()->count();
            $row['hari_sah'] = $row['izin'] + $row['sakit'] + $row['cuti'] + $row['tugas'];
            $row['sementara'] = $to->gte(today());
        }
        unset($row);
        foreach ($data['class_issues'] as $issue) {
            if (in_array($issue['status'], ['digantikan', 'penugasan'], true)) {
                continue;
            }
            $row = collect($data['matrix'])->first(fn ($r) => $r['pegawai_id'] === $issue['pegawai_id'] && $r['unit_id'] === $issue['unit_id']);
            if ($row) {
                $add($row, 'kelas_'.$issue['status'], $issue['tanggal'], 'Kelas: '.str_replace('_', ' ', $issue['status']),
                    $issue['kelas'].' '.$issue['jam'].' | '.$issue['sumber'].' | '.($issue['catatan'] ?? ''), $issue['jadwal_id']);
            }
        }
        $records = $records->concat(LaporanTindakLanjut::whereIn('finding_key', array_keys($findings))->whereIn('pegawai_id', $ids)->whereIn('unit_sekolah_id', $units->pluck('id'))->get())->keyBy('finding_key');
        foreach ($records as $key => $record) {
            if (! isset($findings[$key])) {
                if ($record->tanggal < $from->toDateString()) {
                    continue;
                }
                $findings[$key] = $record->snapshot;
                $findings[$key]['source_changed'] = true;
            }
        }
        $names = User::whereIn('id', $records->pluck('penanggung_jawab_id')->filter())->pluck('name', 'id');
        foreach ($findings as $key => &$finding) {
            $record = $records->get($key);
            $finding['record'] = $record?->toArray();
            $finding['penanggung_jawab'] = $record ? $names->get($record->penanggung_jawab_id) : null;
            $finding['candidates'] = $candidates[$finding['unit_sekolah_id']] ?? [];
            if ($record?->status === 'selesai' && $record->hasil === 'alpa_terkonfirmasi' && ! $finding['source_changed']) {
                foreach ($data['matrix'] as &$row) {
                    if ($row['pegawai_id'] === $record->pegawai_id && $row['unit_id'] === $record->unit_sekolah_id) {
                        $row['alpa_terkonfirmasi']++;
                        $row['belum_tercatat'] = max(0, $row['belum_tercatat'] - 1);
                    }
                }
                unset($row);
            }
        }
        unset($finding);
        $data['followups'] = collect($findings)->sortBy(fn ($f) => [($f['record']['status'] ?? '') === 'selesai' ? 1 : 0, $f['tanggal'], $f['pegawai']])->values()->all();
        $open = collect($data['followups'])->filter(fn ($f) => ! $f['source_changed'] && ($f['record']['status'] ?? '') !== 'selesai');
        $data['decision_summary'] = [
            'pegawai_klarifikasi' => $open->pluck('pegawai_id')->unique()->count(),
            'kejadian' => $open->count(),
            'kewajiban_kurang' => $open->whereIn('jenis', ['jp_kurang', 'hadir_tanpa_jp', 'kuota_kurang'])->count(),
            'kelas_terdampak' => $open->filter(fn ($f) => str_starts_with($f['jenis'], 'kelas_'))->count(),
            'administrasi_pending' => $open->whereIn('jenis', ['izin_pending', 'koreksi_pending', 'lembur_pending'])->count(),
            'belum_tercatat' => array_sum(array_column($data['matrix'], 'belum_tercatat')),
            'alpa_terkonfirmasi' => array_sum(array_column($data['matrix'], 'alpa_terkonfirmasi')),
        ];
        $data['can_manage_followups'] = $canManage;
        $data['summary']['employees'] = collect($data['matrix'])->pluck('pegawai_id')->unique()->count();

        return $data;
    }
}
