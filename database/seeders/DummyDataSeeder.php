<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Pegawai;
use App\Models\UnitSekolah;
use App\Models\Jabatan;
use App\Models\KomponenGaji;
use App\Models\Presensi;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class DummyDataSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID');

        // 1. Admin Account
        User::firstOrCreate(
            ['email' => 'admin@yayasan.com'],
            [
                'name' => 'Admin Pimpinan Yayasan',
                'password' => Hash::make('password'), // password is 'password'
            ]
        );

        // 2. Komponen Gaji
        $komponens = [
            ['nama' => 'Gaji Pokok', 'tipe' => 'pendapatan', 'jenis' => 'fixed', 'nilai_default' => 3000000],
            ['nama' => 'Tunjangan Transport', 'tipe' => 'pendapatan', 'jenis' => 'dinamis_kehadiran', 'nilai_default' => 20000],
            ['nama' => 'Potongan BPJS', 'tipe' => 'potongan', 'jenis' => 'persentase', 'nilai_default' => 2],
            ['nama' => 'Potongan Telat', 'tipe' => 'potongan', 'jenis' => 'dinamis_kehadiran', 'nilai_default' => 50000],
            ['nama' => 'Potongan Alpa', 'tipe' => 'potongan', 'jenis' => 'dinamis_kehadiran', 'nilai_default' => 100000],
        ];

        foreach ($komponens as $k) {
            KomponenGaji::firstOrCreate(['nama' => $k['nama']], $k);
        }

        $units = UnitSekolah::all();
        $jabatans = Jabatan::all();

        if ($units->isEmpty() || $jabatans->isEmpty()) {
            $this->command->info('Menjalankan MasterDataSeeder terlebih dahulu...');
            $this->call(MasterDataSeeder::class);
            $units = UnitSekolah::all();
            $jabatans = Jabatan::all();
        }

        // 3. Create 150 Pegawais
        for ($i = 0; $i < 150; $i++) {
            $pegawai = Pegawai::create([
                'nik' => $faker->unique()->numerify('16############'),
                'nama_lengkap' => $faker->name,
                'jenis_kelamin' => $faker->randomElement(['L', 'P']),
                'tempat_lahir' => $faker->city,
                'tanggal_lahir' => $faker->dateTimeBetween('-50 years', '-25 years')->format('Y-m-d'),
                'alamat_ktp' => $faker->address,
                'alamat_domisili' => $faker->address,
                'agama' => $faker->randomElement(['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha']),
                'status_pernikahan' => $faker->randomElement(['Belum Menikah', 'Menikah']),
                'no_hp' => $faker->phoneNumber,
                'email' => $faker->unique()->safeEmail,
                'status_aktif' => 'aktif',
                'status_kepegawaian' => $faker->randomElement(['tetap', 'kontrak', 'honorer']),
                'tanggal_mulai_kerja' => $faker->dateTimeBetween('-5 years', '-1 year')->format('Y-m-d'),
                'nama_bank' => $faker->randomElement(['BCA', 'Mandiri', 'BRI', 'BNI']),
                'no_rekening' => $faker->numerify('##########'),
                'pendidikan_terakhir' => $faker->randomElement(['S1', 'S2', 'D3', 'SMA']),
                'nuptk' => $faker->numerify('################'),
            ]);

            // Assign Unit & Jabatan
            $pegawai->jabatans()->attach($jabatans->random()->id, [
                'unit_sekolah_id' => $units->random()->id,
                'is_primary' => true
            ]);

            // Create Presensi (Last 5 days)
            for ($d = 0; $d <= 4; $d++) {
                $date = Carbon::today()->subDays($d);
                if ($date->isWeekend()) continue;

                $status = $faker->randomElement(['hadir', 'hadir', 'hadir', 'hadir', 'hadir', 'telat', 'alpa']);
                
                if ($status !== 'alpa') {
                    Presensi::create([
                        'pegawai_id' => $pegawai->id,
                        'unit_sekolah_id' => $units->random()->id,
                        'tanggal' => $date->format('Y-m-d'),
                        'jam_masuk' => $status === 'hadir' ? '06:50:00' : '07:15:00',
                        'jam_keluar' => '15:10:00',
                        'status' => $status,
                        'jarak_masuk_meter' => rand(5, 45),
                        'jarak_keluar_meter' => rand(5, 45),
                    ]);
                } else {
                    Presensi::create([
                        'pegawai_id' => $pegawai->id,
                        'unit_sekolah_id' => $units->random()->id,
                        'tanggal' => $date->format('Y-m-d'),
                        'status' => 'alpa',
                    ]);
                }
            }
        }
    }
}
