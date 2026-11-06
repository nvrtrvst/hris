<?php

namespace App\Services;

use App\Models\Jadwal;
use App\Models\Presensi;
use App\Models\TugasLuar;
use Carbon\Carbon;

class TugasLuarCoverageService
{
    public function cover(Presensi $dayRow): void
    {
        if (! $dayRow->is_tugas_luar || $dayRow->tugas_luar_status !== 'disetujui' || ! $dayRow->jam_masuk) {
            return;
        }

        $tugasLuar = $dayRow->tugas_luar_id ? TugasLuar::find($dayRow->tugas_luar_id) : null;
        $tanggal = Carbon::parse($dayRow->tanggal)->startOfDay();
        $mulai = $tugasLuar?->jam_mulai ? Carbon::parse($tugasLuar->jam_mulai)->format('H:i:s') : null;
        $selesai = $tugasLuar?->jam_selesai ? Carbon::parse($tugasLuar->jam_selesai)->format('H:i:s') : null;

        $jadwals = Jadwal::query()
            ->where('pegawai_id', $dayRow->pegawai_id)
            ->where('hari', $this->hariIndo($tanggal))
            ->where('jenis_jadwal', 'mengajar')
            ->where('tahun_ajaran', $this->tahunAjaran($tanggal))
            ->where('semester', $tanggal->month >= 7 ? 1 : 2)
            ->whereNotNull('unit_sekolah_id')
            ->when($mulai && $selesai, fn ($query) => $query
                ->where('jam_mulai', '<', $selesai)
                ->where('jam_selesai', '>', $mulai))
            ->get();

        foreach ($jadwals as $jadwal) {
            $this->coverJadwal($dayRow, $jadwal, $tanggal, $tugasLuar);
        }
    }

    public function clear(TugasLuar $tugasLuar): void
    {
        Presensi::query()
            ->where('tugas_luar_id', $tugasLuar->id)
            ->whereNotNull('jadwal_id')
            ->delete();
    }

    private function coverJadwal(Presensi $dayRow, Jadwal $jadwal, Carbon $tanggal, ?TugasLuar $tugasLuar): void
    {
        $row = Presensi::query()
            ->where('pegawai_id', $dayRow->pegawai_id)
            ->where('jadwal_id', $jadwal->id)
            ->whereDate('tanggal', $tanggal->toDateString())
            ->first();

        if ($row && ($row->jam_masuk || in_array($row->status, ['hadir', 'telat', 'izin', 'sakit', 'cuti'], true))) {
            return;
        }

        if ($row && $row->status !== 'alpa') {
            return;
        }

        $row ??= new Presensi([
            'pegawai_id' => $dayRow->pegawai_id,
            'jadwal_id' => $jadwal->id,
            'tanggal' => $tanggal->toDateString(),
        ]);

        $row->unit_sekolah_id = $jadwal->unit_sekolah_id;
        $row->tipe_presensi = 'mengajar';
        $row->jam_masuk = Carbon::parse($jadwal->jam_mulai)->format('H:i:s');
        $row->jam_keluar = Carbon::parse($jadwal->jam_selesai)->format('H:i:s');
        $row->status = 'hadir';
        // Baris cover harus false: generated presensi_key memakai TL{tanggal}
        // saat is_tugas_luar true, sehingga semua JP akan bentrok di unique key.
        $row->is_tugas_luar = false;
        $row->tugas_luar_status = 'disetujui';
        $row->tugas_luar_id = $dayRow->tugas_luar_id;
        $row->tujuan = $tugasLuar?->tujuan ?: $dayRow->tujuan;
        $row->keterangan = 'Penugasan: '.($row->tujuan ?: 'Kegiatan sekolah');
        $row->save();
    }

    private function hariIndo(Carbon $tanggal): string
    {
        return [
            'Sunday' => 'Minggu',
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
        ][$tanggal->format('l')];
    }

    private function tahunAjaran(Carbon $tanggal): string
    {
        return $tanggal->month >= 7
            ? $tanggal->year.'/'.($tanggal->year + 1)
            : ($tanggal->year - 1).'/'.$tanggal->year;
    }
}
