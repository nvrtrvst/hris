<?php

namespace App\Console\Commands;

use App\Models\HariLibur;
use App\Models\Pegawai;
use App\Models\Presensi;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FinalizeAlpa extends Command
{
    protected $signature = 'presensi:finalize-alpa {--date= : Target date YYYY-MM-DD (default: yesterday)}';

    protected $description = 'Tandai pegawai yang tidak hadir sebagai alpa';

    public function handle(): int
    {
        $target = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::yesterday();

        $targetDate = $target->format('Y-m-d');
        $hariIndo = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
        ];
        $hariTarget = $hariIndo[$target->format('l')];

        // Cek apakah hari libur nasional (berlaku semua unit)
        $liburNasional = HariLibur::where('tanggal', $targetDate)
            ->whereNull('unit_sekolah_id')
            ->exists();

        // Cek semua hari libur per unit untuk filtering nanti
        $liburUnitIds = HariLibur::where('tanggal', $targetDate)
            ->whereNotNull('unit_sekolah_id')
            ->pluck('unit_sekolah_id')
            ->toArray();

        $pegawais = Pegawai::with('jadwals')->where('status_aktif', 'aktif')->get();

        $marked = 0;
        $skipped = 0;

        foreach ($pegawais as $pegawai) {
            if ($liburNasional) {
                $skipped++;

                continue;
            }

            $jadwalHariIni = $pegawai->jadwals->filter(fn ($j) => $j->hari === $hariTarget);

            if ($jadwalHariIni->isEmpty()) {
                $skipped++;

                continue;
            }

            $unitIdsHariIni = $jadwalHariIni->pluck('unit_sekolah_id')->unique()->toArray();
            $semuaUnitLibur = ! empty($unitIdsHariIni) && empty(array_diff($unitIdsHariIni, $liburUnitIds));
            if ($semuaUnitLibur) {
                $skipped++;

                continue;
            }

            $unitId = $jadwalHariIni->first()->unit_sekolah_id;

            $sudahAbsen = Presensi::where('pegawai_id', $pegawai->id)
                ->where('tanggal', $targetDate)
                ->exists();

            if ($sudahAbsen) {
                $skipped++;

                continue;
            }

            Presensi::create([
                'pegawai_id' => $pegawai->id,
                'unit_sekolah_id' => $unitId,
                'tanggal' => $targetDate,
                'status' => 'alpa',
                'keterangan' => 'Auto-mark alpa',
            ]);

            $marked++;
        }

        $this->info("Selesai. {$marked} alpa di-mark, {$skipped} dilewati.");

        return self::SUCCESS;
    }
}
