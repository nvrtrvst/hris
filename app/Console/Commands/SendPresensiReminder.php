<?php

namespace App\Console\Commands;

use App\Helpers\NotificationHelper;
use App\Models\Pegawai;
use App\Models\Presensi;
use App\Notifications\PresensiReminder;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendPresensiReminder extends Command
{
    protected $signature = 'presensi:reminder {--date= : Tanggal target YYYY-MM-DD (default: hari ini)}';

    protected $description = 'Kirim push reminder ke pegawai yang belum absen masuk hari ini (pagi)';

    public function handle(): int
    {
        $target = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::today();

        $targetDate = $target->format('Y-m-d');
        $hariIndo = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
        $hariTarget = $hariIndo[$target->format('l')];

        $pegawais = Pegawai::with(['user', 'jadwals.unitSekolah', 'jadwals.mataPelajaran'])
            ->where('status_aktif', 'aktif')
            ->whereHas('jadwals', fn ($q) => $q->where('hari', $hariTarget))
            ->get();

        // Prefetch: id pegawai yang sudah absen masuk target date (1 query, bukan N+1)
        $sudahMasukPegawaiIds = Presensi::where('tanggal', $targetDate)
            ->whereNotNull('jam_masuk')
            ->pluck('pegawai_id')
            ->flip();

        $notified = 0;
        $skipped = 0;

        foreach ($pegawais as $pegawai) {
            // Harus punya akun user + jadwal hari ini
            if (! $pegawai->user) {
                $skipped++;

                continue;
            }

            $jadwalHariIni = $pegawai->jadwals->filter(fn ($j) => $j->hari === $hariTarget);

            if ($jadwalHariIni->isEmpty()) {
                $skipped++;

                continue;
            }

            // Skip kalau sudah absen masuk hari ini (dari prefetch)
            if (isset($sudahMasukPegawaiIds[$pegawai->id])) {
                $skipped++;

                continue;
            }

            // Kirim via push — sendSafely agar 1 user gagal tidak membatalkan sisa loop.
            NotificationHelper::sendSafely($pegawai->user, new PresensiReminder($jadwalHariIni->first()));
            $notified++;
        }

        $this->info("Selesai. {$notified} reminder terkirim, {$skipped} dilewati.");

        return self::SUCCESS;
    }
}
