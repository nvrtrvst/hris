<?php

namespace Database\Seeders;

use App\Models\PiketArea;
use App\Models\PiketItemTemplate;
use App\Models\PiketPos;
use App\Models\UnitSekolah;
use Illuminate\Database\Seeder;

class PiketSeeder extends Seeder
{
    public function run(): void
    {
        $smk = UnitSekolah::where('singkatan', 'SMK')->first();
        if (! $smk) {
            return;
        }

        $kampus1 = PiketArea::firstOrCreate(
            ['unit_sekolah_id' => $smk->id, 'nama' => 'Kampus 1'],
            ['urutan' => 1, 'aktif' => true],
        );
        PiketArea::firstOrCreate(
            ['unit_sekolah_id' => $smk->id, 'nama' => 'Kampus 2'],
            ['urutan' => 2, 'aktif' => true],
        );

        PiketPos::firstOrCreate(
            ['unit_sekolah_id' => $smk->id, 'nama' => 'Gerbang Kampus 1'],
            [
                'area_id' => $kampus1->id,
                'jam_mulai' => '06:30',
                'jam_selesai' => '07:15',
                'is_wajib' => true,
                'aktif' => true,
            ],
        );
        PiketPos::firstOrCreate(
            ['unit_sekolah_id' => $smk->id, 'nama' => 'Kantin Kampus 1'],
            [
                'area_id' => $kampus1->id,
                'jam_mulai' => '09:30',
                'jam_selesai' => '09:50',
                'is_wajib' => false,
                'aktif' => true,
            ],
        );

        $templates = [
            ['kode' => 'cek_absen', 'label' => 'Cek guru berhalangan', 'seksi' => 'pagi', 'scope' => 'area', 'tipe' => 'centang', 'urutan' => 10],
            ['kode' => 'absen_siswa', 'label' => 'Absen siswa area saya', 'seksi' => 'pagi', 'scope' => 'area', 'tipe' => 'centang', 'urutan' => 20],
            ['kode' => 'kelas_kosong', 'label' => 'Kelas kosong', 'seksi' => 'kbm', 'scope' => 'area', 'tipe' => 'centang', 'urutan' => 30],
            ['kode' => 'buku', 'label' => 'Buku piket terisi', 'seksi' => 'kbm', 'scope' => 'global', 'tipe' => 'centang', 'urutan' => 40],
            ['kode' => 'insiden', 'label' => 'Insiden', 'seksi' => 'kbm', 'scope' => 'area', 'tipe' => 'teks', 'urutan' => 50],
        ];

        foreach ($templates as $row) {
            PiketItemTemplate::updateOrCreate(
                ['unit_sekolah_id' => $smk->id, 'kode' => $row['kode']],
                $row + ['wajib' => true, 'butuh_foto' => in_array($row['kode'], ['kelas_kosong', 'insiden'], true), 'aktif' => true],
            );
        }
    }
}
