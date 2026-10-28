<?php

namespace App\Services;

use App\Models\AbsenSiswaSesi;
use App\Models\AuditLog;
use App\Models\Jadwal;
use App\Models\LaporanPiket;
use App\Models\Pegawai;
use App\Models\PiketArea;
use App\Models\PiketItemTemplate;
use App\Models\PiketPenugasan;
use App\Models\PiketPetugas;
use App\Models\PiketPetugasKelas;
use App\Models\PiketPos;
use App\Models\PiketPosLog;
use App\Models\PiketRound;
use App\Models\PiketSesi;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PiketService
{
    public const MODE_SIMPLE = 'simple';

    public const MODE_AREA = 'area';

    public static function isAreaMode(?UnitSekolah $unit): bool
    {
        return $unit?->piket_mode === self::MODE_AREA;
    }

    /**
     * Sesi hari ini untuk slot piket; dibuat sekali dan menautkan petugas.
     */
    public static function sessionFor(Pegawai $pegawai, Jadwal $slot): PiketSesi
    {
        $tanggal = Carbon::today()->toDateString();
        $mulai = substr((string) $slot->jam_mulai, 0, 5);
        $selesai = substr((string) $slot->jam_selesai, 0, 5);

        $sesi = PiketSesi::firstOrCreate(
            [
                'unit_sekolah_id' => $slot->unit_sekolah_id,
                'tanggal' => $tanggal,
                'jam_mulai' => $mulai,
                'jam_selesai' => $selesai,
            ],
            ['status' => 'open'],
        );

        $penugasan = PiketPenugasan::with('pegawai')
            ->where('unit_sekolah_id', $slot->unit_sekolah_id)
            ->where('aktif', true)
            ->where(fn ($q) => $q->whereNull('tahun_ajaran')->orWhere('tahun_ajaran', $slot->tahun_ajaran))
            ->where(fn ($q) => $q->whereNull('semester')->orWhere('semester', (string) $slot->semester))
            ->get();

        if ($penugasan->isEmpty()) {
            // Fallback: semua baris jadwal piket pada jam yang sama.
            $jadwalPetugas = Jadwal::where('unit_sekolah_id', $slot->unit_sekolah_id)
                ->where('jenis_jadwal', 'piket')
                ->where('hari', $slot->hari)
                ->where('jam_mulai', $slot->jam_mulai)
                ->pluck('pegawai_id');

            foreach ($jadwalPetugas as $pegawaiId) {
                self::linkPetugas($sesi, (int) $pegawaiId, null, null, 'area');
            }
        } else {
            foreach ($penugasan as $row) {
                self::linkPetugas($sesi, (int) $row->pegawai_id, $row->area_id, $row->pos_id, $row->peran ?: 'area');
            }
        }

        self::linkPetugas($sesi, $pegawai->id, null, null, 'area');

        return $sesi->fresh('petugas');
    }

    /** Bagi kelas area secara round-robin; pembagian yang sudah ada tidak diubah. */
    public static function ensureAbsenAssignments(PiketSesi $sesi): void
    {
        if (! self::isAreaMode($sesi->unitSekolah)) {
            return;
        }

        $petugas = PiketPetugas::where('sesi_id', $sesi->id)
            ->whereNotNull('area_id')
            ->where('status', '!=', 'tidak_hadir')
            ->orderBy('id')
            ->get();

        if ($petugas->isEmpty()) {
            return;
        }

        $existing = PiketPetugasKelas::where('sesi_id', $sesi->id)->pluck('kelas_label')->all();
        $existing = array_map(fn ($label) => AbsenSiswaService::normalizeLabel((string) $label), $existing);
        $offset = Carbon::parse($sesi->tanggal)->dayOfYear;

        foreach ($petugas->groupBy('area_id') as $areaId => $group) {
            $classes = self::kelasArea($sesi->unitSekolah, (int) $areaId);
            sort($classes, SORT_NATURAL | SORT_FLAG_CASE);
            $group = $group->values();
            $start = $offset % max(1, $group->count());

            foreach ($classes as $index => $classLabel) {
                $normalized = AbsenSiswaService::normalizeLabel($classLabel);
                if (in_array($normalized, $existing, true)) {
                    continue;
                }

                $owner = $group->get(($index + $start) % $group->count());
                PiketPetugasKelas::create([
                    'sesi_id' => $sesi->id,
                    'petugas_id' => $owner->pegawai_id,
                    'kelas_label' => $classLabel,
                    'status' => 'aktif',
                ]);
                $existing[] = $normalized;
            }
        }
    }

    public static function hasAssignments(PiketSesi $sesi): bool
    {
        return PiketPetugasKelas::where('sesi_id', $sesi->id)->exists();
    }

    /** @return list<string> */
    public static function assignedClasses(PiketSesi $sesi, PiketPetugas $petugas): array
    {
        return PiketPetugasKelas::where('sesi_id', $sesi->id)
            ->where('petugas_id', $petugas->pegawai_id)
            ->orderBy('kelas_label')
            ->pluck('kelas_label')
            ->all();
    }

    /**
     * @return array{assigned: list<string>, completed: list<string>, missing: list<string>, assigned_count: int, completed_count: int}
     */
    public static function absenCoverage(PiketSesi $sesi, PiketPetugas $petugas): array
    {
        $assigned = self::assignedClasses($sesi, $petugas);
        $saved = AbsenSiswaSesi::where('unit_sekolah_id', $sesi->unit_sekolah_id)
            ->whereDate('tanggal', $sesi->tanggal)
            ->pluck('class_label')
            ->all();
        $saved = array_map(fn ($label) => AbsenSiswaService::normalizeLabel((string) $label), $saved);

        $completed = array_values(array_filter(
            $assigned,
            fn ($label) => in_array(AbsenSiswaService::normalizeLabel($label), $saved, true),
        ));
        $missing = array_values(array_diff($assigned, $completed));

        return [
            'assigned' => $assigned,
            'completed' => $completed,
            'missing' => $missing,
            'assigned_count' => count($assigned),
            'completed_count' => count($completed),
        ];
    }

    public static function syncAbsenItem(LaporanPiket $laporan, PiketPetugas $petugas): void
    {
        if (! $laporan->sesi_id) {
            return;
        }

        $sesi = PiketSesi::find($laporan->sesi_id);
        if (! $sesi) {
            return;
        }

        $coverage = self::absenCoverage($sesi, $petugas);
        if ($coverage['assigned_count'] === 0 || $coverage['missing'] !== []) {
            return;
        }

        $items = $laporan->items ?? [];
        $items['absen_siswa'] = array_merge($items['absen_siswa'] ?? [], [
            'done_at' => Carbon::now()->format('H:i:s'),
            'sesi_count' => $coverage['completed_count'],
            'assigned_count' => $coverage['assigned_count'],
        ]);
        $laporan->items = $items;
        $laporan->save();
    }

    /**
     * Tandai petugas yang tidak hadir/abai dan alihkan kelasnya ke petugas
     * yang hadir. Dipanggil lazy, tanpa scheduler.
     */
    public static function reassignAbsenIfNeeded(PiketSesi $sesi): bool
    {
        if ($sesi->status !== 'open' || ! self::isAreaMode($sesi->unitSekolah)) {
            return false;
        }

        $now = Carbon::now();
        $presenceCutoff = self::presenceCutoff($sesi);
        if ($now->lt($presenceCutoff)) {
            return false;
        }

        $workCutoff = self::workCutoff($sesi);
        $petugas = PiketPetugas::where('sesi_id', $sesi->id)->orderBy('id')->get();
        $hasAssignment = PiketPetugasKelas::where('sesi_id', $sesi->id)->exists();

        $absent = collect();
        foreach ($petugas as $row) {
            $hasPresensi = self::hasPresensi($row->pegawai_id, $sesi->tanggal);
            $coverage = $hasAssignment ? self::absenCoverage($sesi, $row) : ['assigned_count' => 0, 'completed_count' => 0];

            $abai = $hasPresensi
                && $now->gte($workCutoff)
                && $coverage['assigned_count'] > 0
                && $coverage['completed_count'] === 0;

            if ($row->status === 'tidak_hadir' || ! $hasPresensi || $abai) {
                $absent->push($row);
            }
        }

        if ($absent->isEmpty()) {
            return false;
        }

        $present = $petugas->filter(
            fn ($row) => $row->status !== 'tidak_hadir'
                && self::hasPresensi($row->pegawai_id, $sesi->tanggal)
                && ! self::isAbsentByProgress($row, $sesi, $now, $workCutoff)
        )->values();

        if ($present->isEmpty()) {
            return false;
        }

        DB::transaction(function () use ($sesi, $absent, $present) {
            $reassigned = 0;
            $pointer = 0;

            foreach ($absent as $row) {
                $reason = self::hasPresensi($row->pegawai_id, $sesi->tanggal)
                    ? 'Tidak menyimpan kelas sampai batas KBM.'
                    : 'Tidak presensi sampai batas kehadiran.';

                $coverage = self::absenCoverage($sesi, $row);
                $missing = array_map(fn ($label) => AbsenSiswaService::normalizeLabel($label), $coverage['missing']);
                $assignments = PiketPetugasKelas::where('sesi_id', $sesi->id)
                    ->where('petugas_id', $row->pegawai_id)
                    ->get()
                    ->filter(fn ($item) => in_array(AbsenSiswaService::normalizeLabel($item->kelas_label), $missing, true));

                foreach ($assignments as $assignment) {
                    $newOwner = $present->get($pointer % $present->count());
                    $assignment->forceFill([
                        'petugas_id' => $newOwner->pegawai_id,
                        'dialihkan_dari' => $row->pegawai_id,
                        'status' => 'dialihkan',
                    ])->save();
                    $pointer++;
                    $reassigned++;
                }

                $row->forceFill(['status' => 'tidak_hadir', 'status_alasan' => $reason])->save();

                PiketRound::where('sesi_id', $sesi->id)
                    ->where('petugas_id', $row->pegawai_id)
                    ->whereNull('selesai_at')
                    ->update(['petugas_id' => $present->first()->pegawai_id]);
            }

            if ($reassigned > 0) {
                $sesi->forceFill(['assignment_version' => ((int) $sesi->assignment_version) + 1])->save();
                AuditLog::log($sesi, 'reassign_absen', [], [
                    'kelas_dialihkan' => $reassigned,
                    'petugas_tidak_hadir' => $absent->pluck('pegawai_id')->all(),
                ]);
            }
        });

        return true;
    }

    /** Petugas dianggap abai bila sudah presensi tapi nol kelas selesai. */
    private static function isAbsentByProgress(PiketPetugas $petugas, PiketSesi $sesi, Carbon $now, Carbon $workCutoff): bool
    {
        if (! self::hasPresensi($petugas->pegawai_id, $sesi->tanggal) || $now->lt($workCutoff)) {
            return false;
        }

        $coverage = self::absenCoverage($sesi, $petugas);

        return $coverage['assigned_count'] > 0 && $coverage['completed_count'] === 0;
    }

    private static function hasPresensi(int $pegawaiId, $tanggal): bool
    {
        return Presensi::where('pegawai_id', $pegawaiId)
            ->whereDate('tanggal', $tanggal)
            ->whereNotNull('jam_masuk')
            ->exists();
    }

    private static function presenceCutoff(PiketSesi $sesi): Carbon
    {
        $date = Carbon::parse($sesi->tanggal)->startOfDay();
        $sessionStart = $date->copy()->setTimeFromTimeString(substr((string) $sesi->jam_mulai, 0, 5));
        $unitStart = $date->copy()->setTimeFromTimeString(
            substr((string) ($sesi->unitSekolah?->jam_masuk_kantor ?: $sesi->jam_mulai), 0, 5)
        )->addMinutes((int) ($sesi->unitSekolah?->toleransi_menit ?? 0));

        return $sessionStart->gt($unitStart) ? $sessionStart : $unitStart;
    }

    private static function workCutoff(PiketSesi $sesi): Carbon
    {
        $windows = self::windowsForTimes(
            (string) $sesi->jam_mulai,
            (string) $sesi->jam_selesai,
            $sesi->unitSekolah,
        );

        return Carbon::parse($sesi->tanggal)->startOfDay()
            ->setTimeFromTimeString($windows['kbm'][0])
            ->addMinutes(30);
    }

    public static function linkPetugas(PiketSesi $sesi, int $pegawaiId, ?int $areaId, ?int $posId, string $peran): PiketPetugas
    {
        $tanggal = $sesi->tanggal instanceof Carbon ? $sesi->tanggal->toDateString() : (string) $sesi->tanggal;

        $laporan = LaporanPiket::firstOrCreate(
            ['pegawai_id' => $pegawaiId, 'tanggal' => $tanggal],
            [
                'jadwal_id' => null,
                'unit_sekolah_id' => $sesi->unit_sekolah_id,
                'status' => LaporanPiket::STATUS_DRAFT,
                'items' => [],
            ],
        );

        if ($laporan->sesi_id === null) {
            $laporan->forceFill([
                'sesi_id' => $sesi->id,
                'area_id' => $areaId,
                'peran' => $peran,
            ])->save();
        }

        return PiketPetugas::firstOrCreate(
            ['sesi_id' => $sesi->id, 'pegawai_id' => $pegawaiId],
            ['area_id' => $areaId, 'pos_id' => $posId, 'peran' => $peran, 'laporan_piket_id' => $laporan->id],
        );
    }

    public static function areaIdUntukPegawai(Pegawai $pegawai, UnitSekolah $unit): ?int
    {
        $penugasan = PiketPenugasan::where('unit_sekolah_id', $unit->id)
            ->where('pegawai_id', $pegawai->id)
            ->where('aktif', true)
            ->orderByDesc('id')
            ->first();

        if ($penugasan?->area_id) {
            return (int) $penugasan->area_id;
        }

        return self::petugasHariIni($pegawai)?->area_id;
    }

    public static function petugasHariIni(Pegawai $pegawai): ?PiketPetugas
    {
        return PiketPetugas::where('pegawai_id', $pegawai->id)
            ->whereHas('sesi', fn ($q) => $q->whereDate('tanggal', Carbon::today()))
            ->latest('id')
            ->first();
    }

    public static function petugasFor(PiketSesi $sesi, int $pegawaiId): ?PiketPetugas
    {
        return PiketPetugas::where('sesi_id', $sesi->id)
            ->where('pegawai_id', $pegawaiId)
            ->first();
    }

    /** @return array<int, array<string, mixed>> */
    public static function activeTemplates(?UnitSekolah $unit, ?PiketPetugas $petugas): array
    {
        if (! $unit) {
            return [];
        }

        return PiketItemTemplate::where('unit_sekolah_id', $unit->id)
            ->where('aktif', true)
            ->orderBy('urutan')
            ->get()
            ->filter(function (PiketItemTemplate $t) use ($petugas) {
                return match ($t->scope) {
                    'area' => $petugas?->area_id !== null,
                    'pos' => $petugas?->pos_id !== null,
                    default => true,
                };
            })
            ->map(fn (PiketItemTemplate $t) => [
                'kode' => $t->kode,
                'label' => $t->label,
                'seksi' => $t->seksi,
                'scope' => $t->scope,
                'tipe' => $t->tipe,
                'wajib' => $t->wajib,
                'butuh_foto' => $t->butuh_foto,
                'urutan' => $t->urutan,
            ])
            ->values()
            ->all();
    }

    /** @return list<string> */
    public static function requiredCodes(?UnitSekolah $unit, ?PiketPetugas $petugas): array
    {
        $codes = collect(self::activeTemplates($unit, $petugas))
            ->where('wajib', true)
            ->pluck('kode')
            ->values();

        if ($petugas?->sesi_id && $petugas->sesi && PiketPetugasKelas::where('sesi_id', $petugas->sesi_id)->exists()) {
            if (self::assignedClasses($petugas->sesi, $petugas) === []) {
                $codes = $codes->reject(fn ($kode) => $kode === 'absen_siswa');
            }
        }

        return $codes->values()->all();
    }

    public static function posFor(?UnitSekolah $unit, ?PiketPetugas $petugas): array
    {
        if (! $unit || ! $petugas) {
            return [];
        }

        return PiketPos::where('unit_sekolah_id', $unit->id)
            ->where('aktif', true)
            ->where(function ($q) use ($petugas) {
                $q->whereNull('area_id');
                if ($petugas->area_id) {
                    $q->orWhere('area_id', $petugas->area_id);
                }
            })
            ->orderBy('jam_mulai')
            ->get()
            ->map(fn (PiketPos $pos) => [
                'id' => $pos->id,
                'nama' => $pos->nama,
                'jam_mulai' => $pos->jam_mulai,
                'jam_selesai' => $pos->jam_selesai,
                'is_wajib' => $pos->is_wajib,
            ])
            ->all();
    }

    public static function posLogs(PiketSesi $sesi): array
    {
        return PiketPosLog::where('sesi_id', $sesi->id)
            ->get()
            ->mapWithKeys(fn (PiketPosLog $log) => [$log->pos_id => [
                'done_at' => $log->done_at?->format('H:i'),
                'nihil' => (bool) $log->nihil,
                'catatan' => $log->catatan,
                'sumber' => $log->sumber,
                'alasan_override' => $log->alasan_override,
            ]])
            ->all();
    }

    /** Posisi wajib yang belum punya catatan. */
    public static function unservedRequiredPos(PiketSesi $sesi): array
    {
        $served = PiketPosLog::where('sesi_id', $sesi->id)->pluck('pos_id')->all();

        return PiketPos::where('unit_sekolah_id', $sesi->unit_sekolah_id)
            ->where('aktif', true)
            ->where('is_wajib', true)
            ->whereNotIn('id', $served)
            ->pluck('nama')
            ->all();
    }

    public static function recordPos(PiketSesi $sesi, PiketPos $pos, int $pegawaiId, bool $nihil, ?string $catatan, string $sumber = 'manual', ?int $overrideBy = null, ?string $alasan = null): PiketPosLog
    {
        return PiketPosLog::updateOrCreate(
            ['sesi_id' => $sesi->id, 'pos_id' => $pos->id],
            [
                'petugas_id' => $pegawaiId,
                'done_at' => now(),
                'nihil' => $nihil,
                'catatan' => $catatan,
                'sumber' => $sumber,
                'override_by' => $overrideBy,
                'alasan_override' => $alasan,
            ],
        );
    }

    public static function markPosUnavailable(PiketSesi $sesi, PiketPos $pos, int $userId, string $alasan): PiketPosLog
    {
        return PiketPosLog::updateOrCreate(
            ['sesi_id' => $sesi->id, 'pos_id' => $pos->id],
            [
                'petugas_id' => null,
                'done_at' => now(),
                'nihil' => true,
                'catatan' => 'Pos dinonaktifkan hari ini.',
                'sumber' => 'nonaktif',
                'override_by' => $userId,
                'alasan_override' => $alasan,
            ],
        );
    }

    /** @return array{pagi:array{0:string,1:string},kbm:array{0:string,1:string},sore:array{0:string,1:string}} */
    public static function windows(Jadwal $slot): array
    {
        return self::windowsForTimes(
            (string) ($slot->jam_mulai ?: ''),
            (string) ($slot->jam_selesai ?: ''),
            $slot->unitSekolah,
        );
    }

    private static function windowsForTimes(string $mulai, string $selesai, ?UnitSekolah $unit): array
    {
        $mulai = substr($mulai, 0, 5);
        $selesai = substr($selesai, 0, 5);

        if (! $mulai || ! $selesai || $selesai <= $mulai) {
            $mulai = substr((string) ($unit?->jam_masuk_kantor ?: '07:00'), 0, 5);
            $selesai = substr((string) ($unit?->jam_pulang_kantor ?: '15:00'), 0, 5);
        }

        $awal = (int) substr($mulai, 0, 2) * 60 + (int) substr($mulai, 3, 2);
        $akhir = (int) substr($selesai, 0, 2) * 60 + (int) substr($selesai, 3, 2);
        $total = max(60, $akhir - $awal);
        $fmt = fn (int $m): string => sprintf('%02d:%02d', intdiv($m, 60), $m % 60);

        $p1 = $awal + (int) ($total / 3);
        $p2 = $awal + (int) ($total * 2 / 3);

        return ['pagi' => [$mulai, $fmt($p1)], 'kbm' => [$fmt($p1), $fmt($p2)], 'sore' => [$fmt($p2), $selesai]];
    }

    /** @return list<array{mulai:string,selesai:string}> */
    public static function teachingWindows(Pegawai $pegawai, string $hari): array
    {
        return Jadwal::where('pegawai_id', $pegawai->id)
            ->where('hari', $hari)
            ->where('jenis_jadwal', 'mengajar')
            ->orderBy('jam_mulai')
            ->get(['jam_mulai', 'jam_selesai'])
            ->map(fn ($j) => [
                'mulai' => substr((string) $j->jam_mulai, 0, 5),
                'selesai' => substr((string) $j->jam_selesai, 0, 5),
            ])
            ->all();
    }

    public static function overlapsTeaching(Pegawai $pegawai, string $hari, string $mulai, string $selesai): bool
    {
        $toMin = fn (string $t): int => (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2);
        $a = $toMin($mulai);
        $b = $toMin($selesai);

        foreach (self::teachingWindows($pegawai, $hari) as $w) {
            if ($a < $toMin($w['selesai']) && $b > $toMin($w['mulai'])) {
                return true;
            }
        }

        return false;
    }

    public static function lastTeachingEnd(Pegawai $pegawai, string $hari): ?string
    {
        $windows = self::teachingWindows($pegawai, $hari);

        return $windows === [] ? null : max(array_column($windows, 'selesai'));
    }

    public static function ensureRounds(PiketSesi $sesi, Jadwal $slot): void
    {
        if (! self::isAreaMode($slot->unitSekolah)) {
            return;
        }

        $windows = self::windows($slot);
        [$kbmMulai, $kbmSelesai] = $windows['kbm'];
        $awal = (int) substr($kbmMulai, 0, 2) * 60 + (int) substr($kbmMulai, 3, 2);
        $akhir = (int) substr($kbmSelesai, 0, 2) * 60 + (int) substr($kbmSelesai, 3, 2);
        $durasi = max(30, $akhir - $awal);
        $fmt = fn (int $m): string => sprintf('%02d:%02d', intdiv($m, 60), $m % 60);

        $petugas = PiketPetugas::where('sesi_id', $sesi->id)->whereNotNull('area_id')->get()->keyBy('area_id');

        foreach ($petugas as $areaId => $row) {
            foreach ([1 => 0.25, 2 => 0.75] as $putaran => $porsi) {
                $titik = $awal + (int) ($durasi * $porsi);
                $mulai = max($awal, $titik - 10);
                $selesai = min($akhir, $titik + 10);

                $pegawai = Pegawai::find($row->pegawai_id);
                if ($pegawai && self::overlapsTeaching($pegawai, $slot->hari, $fmt($mulai), $fmt($selesai))) {
                    continue;
                }

                PiketRound::updateOrCreate(
                    ['sesi_id' => $sesi->id, 'area_id' => $areaId, 'putaran' => $putaran],
                    [
                        'petugas_id' => $row->pegawai_id,
                        'jendela_mulai' => $fmt($mulai),
                        'jendela_selesai' => $fmt($selesai),
                    ],
                );
            }
        }
    }

    public static function waiveRound(PiketRound $round, int $userId, string $alasan): void
    {
        $lama = ['status' => $round->status, 'selesai_at' => $round->selesai_at?->toDateTimeString()];

        $round->forceFill([
            'selesai_at' => now(),
            'status' => 'dikecualikan',
            'alasan' => $alasan,
        ])->save();

        AuditLog::log($round, 'waive_piket_round', $lama, [
            'alasan' => $alasan,
            'oleh' => $userId,
        ]);

        $sesi = $round->sesi;
        if (! $sesi) {
            return;
        }

        $petugas = PiketPetugas::where('sesi_id', $sesi->id)->where('status', '!=', 'tidak_hadir')->count();
        $terkirim = LaporanPiket::where('sesi_id', $sesi->id)
            ->where('status', LaporanPiket::STATUS_SUBMITTED)
            ->count();

        if ($petugas > 0 && $terkirim >= $petugas && self::unservedRequiredPos($sesi) === []) {
            $sesi->forceFill([
                'status' => 'complete',
                'ditutup_oleh' => $userId,
                'ditutup_at' => now(),
            ])->save();
        }
    }

    public static function rounds(PiketSesi $sesi): array
    {
        return PiketRound::with('area:id,nama')
            ->where('sesi_id', $sesi->id)
            ->orderBy('area_id')
            ->orderBy('putaran')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'putaran' => $r->putaran,
                'area' => $r->area?->nama,
                'area_id' => $r->area_id,
                'petugas_id' => $r->petugas_id,
                'jendela_mulai' => $r->jendela_mulai,
                'jendela_selesai' => $r->jendela_selesai,
                'selesai_at' => $r->selesai_at?->format('H:i'),
                'status' => $r->status,
                'alasan' => $r->alasan,
            ])
            ->values()
            ->all();
    }

    public static function roundBlockReason(?LaporanPiket $laporan): ?string
    {
        if (! $laporan?->sesi_id) {
            return null;
        }

        $belum = PiketRound::where('sesi_id', $laporan->sesi_id)
            ->whereNull('selesai_at')
            ->get();

        if ($belum->isEmpty()) {
            return null;
        }

        $now = Carbon::now()->format('H:i');
        $label = $belum->map(function ($round) use ($now) {
            $window = $round->jendela_mulai.'-'.$round->jendela_selesai;

            return $now < $round->jendela_mulai
                ? 'Fase '.$round->putaran.' belum dibuka ('.$window.')'
                : 'Fase '.$round->putaran.' belum diselesaikan ('.$window.')';
        })->implode('; ');

        return $label.'.';
    }

    public static function sessionBlockReason(?LaporanPiket $laporan): ?string
    {
        if (! $laporan?->sesi_id) {
            return null;
        }

        $sesi = PiketSesi::find($laporan->sesi_id);
        if (! $sesi) {
            return null;
        }

        $unserved = self::unservedRequiredPos($sesi);

        if ($unserved !== []) {
            return 'Pos belum tercatat: '.implode(', ', $unserved).'.';
        }

        return self::roundBlockReason($laporan);
    }

    public static function teamProgress(PiketSesi $sesi): array
    {
        return PiketPetugas::with(['pegawai:id,nama_lengkap', 'laporan', 'area:id,nama'])
            ->where('sesi_id', $sesi->id)
            ->get()
            ->map(fn (PiketPetugas $p) => [
                'pegawai_id' => $p->pegawai_id,
                'nama' => $p->pegawai?->nama_lengkap,
                'area' => $p->area?->nama,
                'peran' => $p->peran,
                'status' => $p->status,
                'status_alasan' => $p->status_alasan,
                'selesai' => $p->laporan ? $p->laporan->doneCount() : 0,
                'submitted' => $p->laporan?->status === LaporanPiket::STATUS_SUBMITTED,
            ])
            ->values()
            ->all();
    }

    /** Label kelas yang dipetakan ke area petugas. */
    public static function kelasArea(?UnitSekolah $unit, ?int $areaId): array
    {
        if (! $unit || ! $areaId) {
            return [];
        }

        return DB::table('piket_area_kelas')
            ->where('unit_sekolah_id', $unit->id)
            ->where('area_id', $areaId)
            ->pluck('kelas_label')
            ->all();
    }

    public static function areaBagi(Pegawai $pegawai, ?Jadwal $slot = null): ?PiketArea
    {
        if (! $slot) {
            return null;
        }

        $sesi = PiketSesi::where('unit_sekolah_id', $slot->unit_sekolah_id)
            ->where('tanggal', Carbon::today()->toDateString())
            ->where('jam_mulai', substr((string) $slot->jam_mulai, 0, 5))
            ->first();

        if (! $sesi) {
            return null;
        }

        $petugas = self::petugasFor($sesi, $pegawai->id);

        return $petugas?->area_id ? PiketArea::find($petugas->area_id) : null;
    }
}
