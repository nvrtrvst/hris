<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class KepalaSekolahSnapshotService
{
    public function __construct(private AbsenSiswaReportService $absenSiswa) {}

    /**
     * Snapshot harian Kepala Sekolah.
     *
     * Data guru/JP/piket memakai hasil live yang sudah dihitung controller agar
     * polling tetap akurat. Hanya ringkasan siswa yang di-cache karena query
     * sesi + detail absensi jauh lebih mahal dan berubah lebih lambat.
     */
    public function build(
        User $user,
        array $live,
        array $cached,
        ?int $unitId,
        ?string $unitNama,
        Carbon $today,
        bool $canViewSiswa,
    ): array {
        $jpSummary = $live['kelasKosongSummary'] ?? [];
        $piketRows = collect($live['piketHariIni'] ?? []);
        $laporan = $piketRows->pluck('laporan')->filter();

        $jpTotal = (float) ($jpSummary['jp_total'] ?? 0);
        $jpKosong = (float) ($jpSummary['jp_kosong'] ?? 0);
        $jpPenugasan = (float) ($jpSummary['jp_penugasan'] ?? 0);

        return [
            'unit_nama' => $unitNama,
            'guru' => [
                'total' => (int) ($live['pegawaiTotal'] ?? 0),
                'dijadwalkan' => (int) ($live['pegawaiDijadwalkan'] ?? 0),
                'hadir' => (int) ($live['hadirHariIniCount'] ?? 0),
                'telat' => (int) ($live['telatCount'] ?? 0),
                'izin' => (int) ($live['izinCount'] ?? 0),
                'sakit' => (int) ($live['sakitCount'] ?? 0),
                'alpa' => (int) ($live['alpaCount'] ?? 0),
            ],
            'jp' => [
                'total' => $jpTotal,
                'tercatat' => max(0, $jpTotal - $jpKosong),
                'kosong' => $jpKosong,
                'penugasan' => $jpPenugasan,
                'kelas_kosong' => $jpKosong + $jpPenugasan,
                'lewat' => (float) ($jpSummary['jp_lewat'] ?? 0),
                'berjalan' => (float) ($jpSummary['jp_berjalan'] ?? 0),
                'belum_mulai' => (float) ($jpSummary['jp_belum_mulai'] ?? 0),
                'guru_hadir' => (float) ($jpSummary['jp_guru_hadir'] ?? 0),
                'rows' => $live['kelasKosongMengajar'] ?? [],
                'per_unit' => $jpSummary['per_unit'] ?? [],
                'hari_libur' => $jpSummary['hari_libur'] ?? null,
            ],
            'piket' => [
                'jadwal' => $piketRows->count(),
                'laporan' => $laporan->count(),
                'lengkap' => $laporan->where('status', 'submitted')->count(),
                'belum' => $laporan->where('status', '!=', 'submitted')->count(),
                'perlu_cek' => (int) $laporan->sum(fn ($row) => (int) ($row['perlu_cek'] ?? 0)),
            ],
            'siswa' => $canViewSiswa ? $this->siswaSummary($user, $unitId, $today) : null,
            'pengajuan_pending' => (int) ($cached['pengajuanPending'] ?? 0),
        ];
    }

    private function siswaSummary(User $user, ?int $unitId, Carbon $today): ?array
    {
        if (! $unitId) {
            return null;
        }

        $key = "dashboard:kepsek:siswa:{$unitId}:{$today->toDateString()}";

        return Cache::remember($key, now()->addSeconds(90), function () use ($user, $unitId, $today) {
            $report = $this->absenSiswa->report($user, [
                'mode' => 'daily',
                'tanggal' => $today->toDateString(),
                'unit_id' => $unitId,
            ]);

            return $report['summary'];
        });
    }
}
