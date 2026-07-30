<?php

namespace Database\Seeders;

use App\Models\KomponenGaji;
use App\Models\UnitSekolah;
use Illuminate\Database\Seeder;

class KomponenGajiSeeder extends Seeder
{
    public function run(): void
    {
        $komponens = [
            ['nama' => 'Gaji Pokok', 'tipe' => 'pendapatan', 'jenis' => 'fixed', 'nilai_default' => 3000000, 'is_active' => true],
            ['nama' => 'Tunjangan Jabatan', 'tipe' => 'pendapatan', 'jenis' => 'fixed', 'nilai_default' => 1000000, 'is_active' => true],
            ['nama' => 'Tunjangan Transport', 'tipe' => 'pendapatan', 'jenis' => 'dinamis_kehadiran', 'nilai_default' => 50000, 'is_active' => true],
            ['nama' => 'PPh 21', 'tipe' => 'potongan', 'jenis' => 'persentase', 'nilai_default' => 5, 'is_active' => true],
            ['nama' => 'BPJS Kesehatan', 'tipe' => 'potongan', 'jenis' => 'persentase', 'nilai_default' => 1, 'is_active' => true],
            ['nama' => 'BPJS Ketenagakerjaan', 'tipe' => 'potongan', 'jenis' => 'persentase', 'nilai_default' => 2, 'is_active' => true],
            ['nama' => 'Tunjangan Lembur', 'tipe' => 'pendapatan', 'jenis' => 'dinamis_lembur', 'nilai_default' => 25000, 'kode' => 'lembur', 'is_taxable' => true, 'is_active' => true],
            ['nama' => 'Honor Mengajar (Tetap)', 'tipe' => 'pendapatan', 'jenis' => 'dinamis_jam_mengajar', 'nilai_default' => 30000, 'kode' => 'honor_mengajar_tetap', 'syarat_bayar_jam_mengajar' => 'hanya_hadir', 'applies_to_status_kepegawaian' => 'tetap', 'is_taxable' => true, 'is_active' => true, 'urutan' => 6, 'tampil_di_matrix' => true],
        ];

        foreach ($komponens as $komponen) {
            KomponenGaji::firstOrCreate(['nama' => $komponen['nama']], $komponen);
        }

        KomponenGaji::where('nama', 'Tunjangan Makan')->update(['is_active' => false]);

        $unitValues = ['SMK' => 5000000, 'SMP' => 2000000];
        foreach (UnitSekolah::all() as $unit) {
            $nilai = $unitValues[$unit->nama] ?? 3000000;
            KomponenGaji::updateOrCreate(
                ['kode' => 'gaji_pokok', 'unit_sekolah_id' => $unit->id],
                [
                    'nama' => 'Gaji Pokok '.$unit->nama,
                    'jenis' => 'fixed',
                    'tipe' => 'pendapatan',
                    'is_taxable' => false,
                    'applies_to_status_kepegawaian' => 'tetap',
                    'nilai_default' => $nilai,
                    'is_active' => true,
                    'urutan' => 1,
                    'tampil_di_matrix' => true,
                ]
            );
        }

        KomponenGaji::where('nama', 'Gaji Pokok')->whereNull('unit_sekolah_id')->update(['is_active' => false]);
    }
}
