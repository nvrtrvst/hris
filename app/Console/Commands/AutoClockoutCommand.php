<?php

namespace App\Console\Commands;

use App\Models\Presensi;
use App\Models\UnitSekolah;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AutoClockoutCommand extends Command
{
    /**
     * Otomatis tutup record presensi kantor yang belum punya jam_keluar.
     * Dijalankan setelah jam_pulang_kantor semua unit lewat.
     * Hanya mempengaruhi record tipe kantor (jadwal_id=null) yang punya jam_masuk tapi jam_keluar kosong.
     */
    protected $signature = 'presensi:auto-clockout
        {--date= : Tanggal YYYY-MM-DD (default: hari ini)}';

    protected $description = 'Tutup otomatis presensi kantor yang belum absen pulang';

    public function handle(): int
    {
        $targetDate = $this->option('date')
            ? Carbon::parse($this->option('date'))->toDateString()
            : Carbon::today()->toDateString();

        // Ambil semua unit dengan jam_pulang_kantor-nya
        $units = UnitSekolah::pluck('jam_pulang_kantor', 'id');

        // Cari record kantor yang punya jam_masuk tapi jam_keluar kosong
        $openRecords = Presensi::where('tanggal', $targetDate)
            ->whereNull('jadwal_id')
            ->where('tipe_presensi', 'kantor')
            ->whereNotNull('jam_masuk')
            ->whereNull('jam_keluar')
            ->where('is_lembur', false)
            ->get();

        $closed = 0;
        $skipped = 0;

        foreach ($openRecords as $record) {
            $jamPulang = $units->get($record->unit_sekolah_id) ?? '15:00';

            // Jangan tutup jika sekarang masih belum lewat jam_pulang_kantor
            $jamPulangToday = Carbon::parse($targetDate.' '.$jamPulang);
            if (Carbon::now()->lt($jamPulangToday)) {
                $skipped++;
                continue;
            }

            $record->jam_keluar = $jamPulang;
            $record->save();
            $closed++;
        }

        $this->info("Auto clock-out {$targetDate}: {$closed} record ditutup, {$skipped} dilewati (belum lewat jam pulang).");

        return self::SUCCESS;
    }
}
