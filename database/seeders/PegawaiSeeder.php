<?php

namespace Database\Seeders;

use App\Models\Jabatan;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\UnitSekolah;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PegawaiSeeder extends Seeder
{
    public function run(): void
    {
        $guruJabatan = Jabatan::where('nama', 'Guru')->first();
        $smpUnit = UnitSekolah::where('nama', 'SMP Yayasan')->first();
        $mapelMtk = MataPelajaran::where('nama', 'Matematika')->first();

        if ($guruJabatan && $smpUnit && $mapelMtk) {
            $user = User::firstOrCreate(
                ['email' => '1234567890123456@yayasan.com'],
                [
                    'name' => 'Budi Santoso',
                    'password' => Hash::make('password'),
                    'role' => 'pegawai',
                    'unit_sekolah_id' => $smpUnit->id,
                ]
            );
            $user->assignRole('pegawai');

            $pegawai = Pegawai::firstOrCreate(
                ['nik' => '1234567890123456'],
                [
                    'user_id' => $user->id,
                    'nip' => '199001012020121001',
                    'nama_lengkap' => 'Budi Santoso, S.Pd',
                    'tempat_lahir' => 'Jakarta',
                    'tanggal_lahir' => '1990-01-01',
                    'jenis_kelamin' => 'L',
                    'agama' => 'Islam',
                    'status_pernikahan' => 'Menikah',
                    'alamat_ktp' => 'Jl. Merdeka No. 10, Jakarta',
                    'no_hp' => '081234567890',
                    'status_kepegawaian' => 'tetap',
                    'status_aktif' => 'aktif',
                    'tanggal_mulai_kerja' => '2020-01-01',
                    'pendidikan_terakhir' => 'S1 Pendidikan Matematika',
                    'jumlah_tanggungan' => 2,
                ]
            );

            // Attach to Unit and Jabatan
            if (! $pegawai->units()->where('unit_sekolah.id', $smpUnit->id)->exists()) {
                $pegawai->units()->attach($smpUnit->id, ['jabatan_id' => $guruJabatan->id, 'is_primary' => true]);
            }

            // Attach Mapel
            if (! $pegawai->mapels()->where('mata_pelajaran.id', $mapelMtk->id)->exists()) {
                $pegawai->mapels()->attach($mapelMtk->id, ['unit_sekolah_id' => $smpUnit->id]);
            }
        }
    }
}
