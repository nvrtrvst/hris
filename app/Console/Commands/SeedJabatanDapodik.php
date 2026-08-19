<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedJabatanDapodik extends Command
{
    protected $signature = 'jabatan:seed-dapodik';
    protected $description = 'Seed jabatan referensi Dapodik + hierarchy_level';

    public function handle(): int
    {
        // hierarchy_level: 0=paling tinggi, 99=default
        $jabatans = [
            ['nama' => 'Kepala Sekolah',               'hierarchy_level' => 1],
            ['nama' => 'Wakil Kepala Sekolah',          'hierarchy_level' => 2],
            ['nama' => 'Ketua Yayasan',                 'hierarchy_level' => 0],
            ['nama' => 'Kepala Tata Usaha',             'hierarchy_level' => 3],
            ['nama' => 'Kepala Perpustakaan',           'hierarchy_level' => 3],
            ['nama' => 'Kepala Laboratorium',           'hierarchy_level' => 3],
            ['nama' => 'Bendahara',                     'hierarchy_level' => 4],
            ['nama' => 'Operator / Pranata Komputer',   'hierarchy_level' => 4],
            ['nama' => 'Wali Kelas',                    'hierarchy_level' => 5],
            ['nama' => 'Guru Mata Pelajaran',           'hierarchy_level' => 5],
            ['nama' => 'Guru Kelas',                    'hierarchy_level' => 5],
            ['nama' => 'Guru BK',                       'hierarchy_level' => 5],
            ['nama' => 'Pustakawan',                    'hierarchy_level' => 6],
            ['nama' => 'Laboran',                       'hierarchy_level' => 6],
            ['nama' => 'Tenaga Administrasi',           'hierarchy_level' => 6],
            ['nama' => 'Tenaga Administrasi (TU)',      'hierarchy_level' => 6],
            ['nama' => 'Kasir',                         'hierarchy_level' => 6],
            ['nama' => 'Terapis',                       'hierarchy_level' => 6],
            ['nama' => 'Satpam',                        'hierarchy_level' => 7],
            ['nama' => 'Satpam / Petugas Keamanan',     'hierarchy_level' => 7],
            ['nama' => 'Petugas Kebersihan',            'hierarchy_level' => 7],
            ['nama' => 'Pesuruh / Office Boy',          'hierarchy_level' => 7],
            ['nama' => 'Tukang Kebun',                  'hierarchy_level' => 7],
        ];

        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($jabatans as $j) {
            $existing = DB::table('jabatan')->where('nama', $j['nama'])->first();

            if (! $existing) {
                DB::table('jabatan')->insert([
                    'nama' => $j['nama'],
                    'hierarchy_level' => $j['hierarchy_level'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->info("INSERTED: {$j['nama']} (level {$j['hierarchy_level']})");
                $inserted++;
            } elseif ($existing->hierarchy_level != $j['hierarchy_level']) {
                DB::table('jabatan')->where('id', $existing->id)->update([
                    'hierarchy_level' => $j['hierarchy_level'],
                    'updated_at' => now(),
                ]);
                $this->info("UPDATED: {$j['nama']} level {$existing->hierarchy_level} -> {$j['hierarchy_level']}");
                $updated++;
            } else {
                $skipped++;
            }
        }

        $this->info("Done. Inserted: {$inserted}, Updated: {$updated}, Skipped: {$skipped}");

        return Command::SUCCESS;
    }
}
