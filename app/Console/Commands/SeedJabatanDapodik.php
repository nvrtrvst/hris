<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedJabatanDapodik extends Command
{
    protected $signature = 'jabatan:seed-dapodik';
    protected $description = 'Seed jabatan referensi Dapodik yang belum ada di tabel jabatan';

    public function handle(): int
    {
        $jabatans = [
            ['id' => 1, 'nama' => 'Kepala Sekolah'],
            ['id' => 2, 'nama' => 'Wakil Kepala Sekolah'],
            ['id' => 3, 'nama' => 'Guru Mata Pelajaran'],
            ['id' => 4, 'nama' => 'Guru Kelas'],
            ['id' => 5, 'nama' => 'Guru BK'],
            ['id' => 6, 'nama' => 'Wali Kelas'],
            ['id' => 7, 'nama' => 'Tenaga Administrasi'],
            ['id' => 8, 'nama' => 'Operator / Pranata Komputer'],
            ['id' => 9, 'nama' => 'Pustakawan'],
            ['id' => 10, 'nama' => 'Satpam'],
            ['id' => 11, 'nama' => 'Petugas Kebersihan'],
            ['id' => 12, 'nama' => 'Tenaga Administrasi (TU)'],
            ['id' => 13, 'nama' => 'Bendahara'],
            ['id' => 14, 'nama' => 'Laboran'],
            ['id' => 15, 'nama' => 'Satpam / Petugas Keamanan'],
            ['id' => 16, 'nama' => 'Pesuruh / Office Boy'],
            ['id' => 17, 'nama' => 'Kepala Perpustakaan'],
            ['id' => 18, 'nama' => 'Tukang Kebun'],
            ['id' => 19, 'nama' => 'Kepala Laboratorium'],
            ['id' => 20, 'nama' => 'Kasir'],
            ['id' => 21, 'nama' => 'Terapis'],
            ['id' => 22, 'nama' => 'Guru Mata Pelajaran'],
            ['id' => 23, 'nama' => 'Ketua Yayasan'],
            ['id' => 24, 'nama' => 'Kepala Tata Usaha'],
        ];

        $inserted = 0;
        $skipped = 0;

        foreach ($jabatans as $j) {
            $exists = DB::table('jabatan')
                ->where('nama', $j['nama'])
                ->exists();

            if (! $exists) {
                DB::table('jabatan')->insert([
                    'id' => $j['id'],
                    'nama' => $j['nama'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->info("INSERTED: {$j['nama']}");
                $inserted++;
            } else {
                $skipped++;
            }
        }

        $this->info("Done. Inserted: {$inserted}, Skipped: {$skipped}");

        return Command::SUCCESS;
    }
}
