<?php

namespace Database\Seeders;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\UnitSekolah;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class MassivePegawaiSeeder extends Seeder
{
    private const TOTAL = 500;

    public function run(): void
    {
        $password = (string) env('SEED_DEFAULT_PASSWORD', 'password');
        if (app()->environment('production') && $password === 'password') {
            throw new RuntimeException('Set SEED_DEFAULT_PASSWORD sebelum menjalankan seeder di production.');
        }

        $units = UnitSekolah::query()->orderBy('id')->get();
        $jabatan = Jabatan::query()->where('is_guru', true)->orderBy('id')->first()
            ?? Jabatan::query()->orderBy('id')->first();

        if ($units->isEmpty() || ! $jabatan) {
            throw new RuntimeException('Unit sekolah dan jabatan wajib tersedia sebelum seeding pegawai.');
        }

        $passwordHash = Hash::make($password);
        $firstNames = [
            'Adi', 'Agus', 'Ahmad', 'Aisyah', 'Andi', 'Anisa', 'Arif', 'Aulia', 'Bagus', 'Bambang',
            'Citra', 'Dedi', 'Dewi', 'Dimas', 'Dina', 'Eka', 'Fajar', 'Farah', 'Galih', 'Hana',
            'Hendra', 'Ika', 'Ilham', 'Indah', 'Joko', 'Kartika', 'Laila', 'Maya', 'Nadia', 'Nanda',
            'Naufal', 'Nisa', 'Putri', 'Rafi', 'Rani', 'Rizal', 'Sari', 'Taufik', 'Vina', 'Yusuf',
        ];
        $lastNames = [
            'Abdullah', 'Adinata', 'Anwar', 'Ardiansyah', 'Bakri', 'Budiman', 'Cahyono', 'Darmawan',
            'Fauzan', 'Firmansyah', 'Gunawan', 'Hakim', 'Halim', 'Haryanto', 'Hasan', 'Ibrahim',
            'Iskandar', 'Kurniawan', 'Lesmana', 'Maulana', 'Nugraha', 'Pratama', 'Ramadhan', 'Santoso',
            'Saputra', 'Setiawan', 'Siregar', 'Suharto', 'Suryadi', 'Susanto', 'Syahputra', 'Tanjung',
            'Utama', 'Wahyudi', 'Wijaya', 'Wulandari', 'Yulianto', 'Yunus', 'Zainal', 'Zulkarnain',
        ];

        DB::transaction(function () use ($passwordHash, $firstNames, $lastNames, $units, $jabatan): void {
            $superadmin = User::firstOrCreate(
                ['email' => 'admin@yayasan.com'],
                ['name' => 'Super Admin Yayasan', 'password' => $passwordHash]
            );
            $superadmin->syncRoles('superadmin');

            for ($index = 1; $index <= self::TOTAL; $index++) {
                $firstName = $firstNames[($index - 1) % count($firstNames)];
                $lastName = $lastNames[intdiv($index - 1, count($firstNames))];
                $name = $firstName.' '.$lastName;
                $email = sprintf('pegawai%03d@demo.yayasan.com', $index);
                $nik = '3273'.str_pad((string) $index, 12, '0', STR_PAD_LEFT);
                $unit = $units[($index - 1) % $units->count()];

                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name' => $name,
                        'password' => $passwordHash,
                        'unit_sekolah_id' => $unit->id,
                    ]
                );
                $user->syncRoles('pegawai');

                $pegawai = Pegawai::firstOrCreate(
                    ['nik' => $nik],
                    [
                        'user_id' => $user->id,
                        'nama_lengkap' => $name,
                        'tempat_lahir' => 'Jakarta',
                        'tanggal_lahir' => now()->subYears(25 + ($index % 25))->subDays($index)->toDateString(),
                        'jenis_kelamin' => $index % 2 === 0 ? 'P' : 'L',
                        'agama' => 'Islam',
                        'status_pernikahan' => 'Belum Menikah',
                        'alamat_ktp' => 'Alamat demo '.$index,
                        'alamat_domisili' => 'Alamat demo '.$index,
                        'no_hp' => '08'.str_pad((string) $index, 10, '0', STR_PAD_LEFT),
                        'email' => $email,
                        'status_kepegawaian' => $index % 3 === 0 ? 'honorer' : 'tetap',
                        'wajib_kantor' => $index % 3 !== 0,
                        'status_aktif' => 'aktif',
                        'tanggal_mulai_kerja' => now()->subYears(1 + ($index % 8))->toDateString(),
                        'pendidikan_terakhir' => 'S1',
                    ]
                );

                if ($pegawai->user_id !== $user->id) {
                    $pegawai->update(['user_id' => $user->id]);
                }
                $pegawai->update(['wajib_kantor' => $index % 3 !== 0]);

                if (! $pegawai->units()->where('unit_sekolah.id', $unit->id)->exists()) {
                    $pegawai->units()->attach($unit->id, [
                        'jabatan_id' => $jabatan->id,
                        'is_primary' => true,
                    ]);
                }
            }
        });

        $this->command?->info(self::TOTAL.' pegawai demo siap. Password dari SEED_DEFAULT_PASSWORD.');
    }
}
