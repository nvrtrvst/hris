<?php

namespace Database\Seeders;

use App\Models\Pegawai;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BulkPresensiSeeder extends Seeder
{
    public function run(): void
    {
        $daysBack = (int) $this->command?->ask('Berapa hari ke belakang?', 90) ?? 90;
        $units = UnitSekolah::pluck('id')->all();
        $pegawaiIds = Pegawai::query()->where('status_aktif', 'aktif')->pluck('id')->all();
        if (empty($pegawaiIds)) {
            $this->command->warn('Tidak ada pegawai aktif. Seed pegawai dulu.');

            return;
        }

        $rows = [];
        $today = Carbon::today();
        $statusPool = ['hadir', 'hadir', 'hadir', 'hadir', 'telat', 'telat', 'sakit', 'izin', 'alpa'];
        $unitCount = count($units);
        $pegawaiCount = count($pegawaiIds);

        $this->command->info('Sebelum: presensi = '.Presensi::count().' rows');

        for ($d = $daysBack; $d >= 0; $d--) {
            $date = Carbon::today()->subDays($d);
            if ($date->isWeekend()) {
                continue;
            }
            $dateStr = $date->format('Y-m-d');

            // 1 record per pegawai per hari kerja
            foreach ($pegawaiIds as $index => $pegawaiId) {
                $status = $statusPool[($index + $d) % count($statusPool)];
                $jamMasuk = $status === 'alpa' ? null : ($status === 'telat' ? '07:15:00' : '06:55:00');
                $rows[] = [
                    'pegawai_id' => $pegawaiId,
                    'unit_sekolah_id' => $units[$index % $unitCount],
                    'jadwal_id' => null,
                    'tanggal' => $dateStr,
                    'jam_masuk' => $jamMasuk,
                    'jam_keluar' => $status === 'alpa' ? null : '15:10:00',
                    'status' => $status,
                    'jarak_masuk_meter' => rand(5, 45),
                    'jarak_keluar_meter' => rand(5, 45),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (count($rows) >= 5000) {
                DB::table('presensi')->insert($rows);
                $rows = [];
            }
        }

        if ($rows) {
            DB::table('presensi')->insert($rows);
        }
        $this->command->info('Selesai. Total presensi skarang = '.DB::table('presensi')->count());
    }
}
