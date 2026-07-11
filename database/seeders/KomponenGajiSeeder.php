<?php

namespace Database\Seeders;

use App\Models\KomponenGaji;
use Illuminate\Database\Seeder;

class KomponenGajiSeeder extends Seeder
{
    public function run(): void
    {
        $komponens = [
            ['nama' => 'Gaji Pokok', 'tipe' => 'pendapatan', 'jenis' => 'fixed', 'nilai_default' => 3000000, 'is_active' => true],
            ['nama' => 'Tunjangan Jabatan', 'tipe' => 'pendapatan', 'jenis' => 'fixed', 'nilai_default' => 1000000, 'is_active' => true],
            ['nama' => 'Tunjangan Transport', 'tipe' => 'pendapatan', 'jenis' => 'dinamis_kehadiran', 'nilai_default' => 50000, 'is_active' => true],
            ['nama' => 'Tunjangan Makan', 'tipe' => 'pendapatan', 'jenis' => 'dinamis_kehadiran', 'nilai_default' => 50000, 'is_active' => true],
            ['nama' => 'PPh 21', 'tipe' => 'potongan', 'jenis' => 'persentase', 'nilai_default' => 5, 'is_active' => true],
            ['nama' => 'BPJS Kesehatan', 'tipe' => 'potongan', 'jenis' => 'persentase', 'nilai_default' => 1, 'is_active' => true],
            ['nama' => 'BPJS Ketenagakerjaan', 'tipe' => 'potongan', 'jenis' => 'persentase', 'nilai_default' => 2, 'is_active' => true],
            ['nama' => 'Tunjangan Lembur', 'tipe' => 'pendapatan', 'jenis' => 'dinamis_lembur', 'nilai_default' => 25000, 'kode' => 'lembur', 'is_taxable' => true, 'is_active' => true],
        ];

        foreach ($komponens as $komponen) {
            KomponenGaji::firstOrCreate(['nama' => $komponen['nama']], $komponen);
        }
    }
}
