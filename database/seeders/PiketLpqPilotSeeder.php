<?php

namespace Database\Seeders;

use App\Models\PiketArea;
use App\Models\PiketItemTemplate;
use App\Models\PiketPenugasan;
use App\Models\PiketPos;
use App\Models\UnitSekolah;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PiketLpqPilotSeeder extends Seeder
{
    public function run(): void
    {
        $unit = UnitSekolah::where('singkatan', 'LPQ')->first();
        $user = User::where('email', 'reza@yayasan.com')->first();
        $pegawai = $user?->pegawai;

        if (! $unit || ! $pegawai) {
            $this->command?->warn('LPQ atau Reza tidak ditemukan.');

            return;
        }

        $area = PiketArea::firstOrCreate(
            ['unit_sekolah_id' => $unit->id, 'nama' => 'LPQ'],
            ['urutan' => 1, 'aktif' => true],
        );

        foreach (['1 - Kelas 1', 'Ula - Halaqah Ula'] as $label) {
            DB::table('piket_area_kelas')->updateOrInsert(
                ['unit_sekolah_id' => $unit->id, 'kelas_label' => $label],
                ['area_id' => $area->id, 'updated_at' => now(), 'created_at' => now()],
            );
        }

        $pos = PiketPos::firstOrCreate(
            ['unit_sekolah_id' => $unit->id, 'nama' => 'Gerbang LPQ'],
            [
                'area_id' => $area->id,
                'jam_mulai' => '07:00',
                'jam_selesai' => '07:45',
                'is_wajib' => true,
                'aktif' => true,
            ],
        );

        $templates = [
            ['kode' => 'cek_absen', 'label' => 'Cek guru berhalangan', 'seksi' => 'pagi', 'scope' => 'area', 'urutan' => 10],
            ['kode' => 'absen_siswa', 'label' => 'Absen siswa area saya', 'seksi' => 'pagi', 'scope' => 'area', 'urutan' => 20],
            ['kode' => 'kelas_kosong', 'label' => 'Kelas kosong', 'seksi' => 'kbm', 'scope' => 'area', 'urutan' => 30],
            ['kode' => 'buku', 'label' => 'Buku piket terisi', 'seksi' => 'kbm', 'scope' => 'global', 'urutan' => 40],
            ['kode' => 'insiden', 'label' => 'Insiden', 'seksi' => 'kbm', 'scope' => 'area', 'urutan' => 50],
        ];

        foreach ($templates as $row) {
            PiketItemTemplate::updateOrCreate(
                ['unit_sekolah_id' => $unit->id, 'kode' => $row['kode']],
                $row + ['tipe' => 'centang', 'wajib' => true, 'butuh_foto' => false, 'aktif' => true],
            );
        }

        PiketPenugasan::updateOrCreate(
            [
                'unit_sekolah_id' => $unit->id,
                'tahun_ajaran' => '2026/2027',
                'semester' => '1',
                'pegawai_id' => $pegawai->id,
            ],
            [
                'area_id' => $area->id,
                'pos_id' => $pos->id,
                'peran' => 'gerbang',
                'aktif' => true,
            ],
        );

        $unit->forceFill(['piket_mode' => 'area'])->save();

        $this->command?->info('Pilot LPQ aktif: '.$pegawai->nama_lengkap.' · '.$area->nama.' · pos '.$pos->nama);
    }
}
