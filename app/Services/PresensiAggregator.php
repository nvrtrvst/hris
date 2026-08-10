<?php

namespace App\Services;

use App\Models\Presensi;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Agregasi data presensi untuk kebutuhan payroll.
 *
 * Sebelumnya createDraft menjalankan 5 query scan presensi dengan filter tumpang
 * tindih (kehadiran per status, sakit prorata, hari present, lembur disetujui,
 * jumlah hadir per jadwal). Kelas ini menggabungkan semuanya menjadi 1 query
 * + agregasi di PHP — hasilnya identik dengan query lama.
 */
class PresensiAggregator
{
    /**
     * Fetch & agregasi presensi dalam satu periode.
     *
     * @param  Collection<int>  $pegawaiIds  ID pegawai yang diproses (sudah scoped unit)
     * @param  Carbon  $periodeStart  Awal periode (untuk fetch semua record)
     * @param  Carbon  $periodeEnd  Akhir periode (untuk fetch semua record)
     * @param  Carbon  $attendanceCutoff  Batas presensi yang dihitung (lembur & jam mengajar)
     * @param  int|null  $unitScope  Unit admin unit, atau null untuk superadmin
     * @return array{
     *     attendance: Collection,
     *     sakit_prorata: Collection,
     *     present_days: Collection,
     *     lembur: Collection,
     *     attended_jadwal: Collection,
     * }
     */
    public function aggregate(Collection $pegawaiIds, Carbon $periodeStart, Carbon $periodeEnd, Carbon $attendanceCutoff, ?int $unitScope = null): array
    {
        $presensiRows = Presensi::whereBetween('tanggal', [
            $periodeStart->format('Y-m-d'),
            $periodeEnd->format('Y-m-d'),
        ])
            ->whereIn('pegawai_id', $pegawaiIds)
            ->get(['pegawai_id', 'jadwal_id', 'tanggal', 'status', 'is_lembur', 'lembur_status', 'jam_masuk', 'jam_keluar', 'persentase_bayar_jam', 'unit_sekolah_id']);

        return [
            'attendance' => $this->attendanceByPegawai($presensiRows, $unitScope),
            'sakit_prorata' => $this->sakitProrata($presensiRows),
            'present_days' => $this->presentDaysByPegawai($presensiRows, $unitScope),
            'lembur' => $this->lemburByPegawai($presensiRows, $attendanceCutoff),
            'attended_jadwal' => $this->attendedJadwalByPegawai($presensiRows, $attendanceCutoff),
        ];
    }

    /**
     * Kehadiran per status — skip lembur + scope unit (semantik query lama).
     * Bentuk: pegawai_id => [ {status, total}, ... ] — dikonsumsi computeAttendance.
     */
    private function attendanceByPegawai(Collection $rows, ?int $unitScope): Collection
    {
        return $rows
            ->filter(fn ($p) => ! $p->is_lembur
                && ($unitScope === null || $p->unit_sekolah_id == $unitScope || $p->unit_sekolah_id === null))
            ->groupBy('pegawai_id')
            ->map(fn ($pegawaiRows) => $pegawaiRows->groupBy('status')
                ->map(fn ($statusRows) => (object) ['status' => $statusRows->first()->status, 'total' => $statusRows->count()])
                ->values());
    }

    /**
     * Sakit prorata — SUM(persentase_bayar_jam) per pegawai (tanpa scope unit, semantik lama).
     */
    private function sakitProrata(Collection $rows): Collection
    {
        return $rows
            ->where('status', 'sakit')
            ->groupBy('pegawai_id')
            ->mapWithKeys(fn ($pegawaiRows, $id) => [$id => (float) $pegawaiRows->sum('persentase_bayar_jam')]);
    }

    /**
     * Hari hadir/telat dedup per tanggal — tunjangan kehadiran dibayar 1x/hari.
     */
    private function presentDaysByPegawai(Collection $rows, ?int $unitScope): Collection
    {
        return $rows
            ->filter(fn ($p) => ! $p->is_lembur
                && in_array($p->status, ['hadir', 'telat'], true)
                && ($unitScope === null || $p->unit_sekolah_id == $unitScope || $p->unit_sekolah_id === null))
            ->groupBy('pegawai_id')
            ->map(fn ($pegawaiRows) => $pegawaiRows->pluck('tanggal')
                ->map(fn ($tanggal) => $tanggal->format('Y-m-d'))
                ->unique()
                ->count());
    }

    /**
     * Lembur disetujui — jam_masuk/jam_keluar untuk hitung durasi.
     */
    private function lemburByPegawai(Collection $rows, Carbon $attendanceCutoff): Collection
    {
        return $rows
            ->filter(fn ($p) => $p->is_lembur
                && $p->lembur_status === 'disetujui'
                && $p->jam_masuk !== null
                && $p->jam_keluar !== null
                && $p->tanggal->lte($attendanceCutoff))
            ->groupBy('pegawai_id');
    }

    /**
     * Jumlah hadir per jadwal — dinamis_jam_mengajar 'hanya_hadir'.
     */
    private function attendedJadwalByPegawai(Collection $rows, Carbon $attendanceCutoff): Collection
    {
        return $rows
            ->filter(fn ($p) => ! $p->is_lembur
                && in_array($p->status, ['hadir', 'telat'], true)
                && $p->jadwal_id !== null
                && $p->tanggal->lte($attendanceCutoff))
            ->groupBy('pegawai_id')
            ->map(fn ($pegawaiRows) => $pegawaiRows->groupBy('jadwal_id')->map->count());
    }
}
