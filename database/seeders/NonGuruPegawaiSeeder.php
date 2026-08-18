<?php

namespace Database\Seeders;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Seeder data pegawai TENAGA KEPENDIDIKAN (non-guru) — sesuai Permendikdasmen
 * 21/2025: TU, Bendahara, Operator, Pustakawan, Laboran, Satpam, OB, dll.
 *
 * Tujuan:
 *  - Mengisi data tendik untuk menguji filter "Jenis Pegawai" (Pendidik/Tendik)
 *    di modul Presensi, Pegawai, Jadwal, Laporan, dan Pengajuan Izin.
 *  - Setiap tendik punya akun login (role pegawai) + data lengkap siap payroll.
 *  - Membuat presensi kantor 5 hari kerja terakhir + 2 pengajuan izin tendik.
 *
 * Idempoten (firstOrCreate by nik_hash) — aman dijalankan berulang.
 * Produksi: wajib set SEED_DEFAULT_PASSWORD selain 'password' (sama dgn
 * MassivePegawaiSeeder).
 */
class NonGuruPegawaiSeeder extends Seeder
{
    private const NIK_PREFIX = '3273900000000'; // 13 digit + 3 digit index = 16 digit NIK

    /**
     * [unitSingkatan, nama_lengkap, jenis_kelamin, namaJabatan, statusKepegawaian]
     */
    private const TENDIK = [
        // LPQ
        ['LPQ', 'Rina Kurniasari', 'P', 'Operator / Pranata Komputer', 'honorer'],

        // TK
        ['TK', 'Siti Maryam', 'P', 'Tenaga Administrasi (TU)', 'tetap'],

        // SD
        ['SD', 'Dewi Anggraini', 'P', 'Tenaga Administrasi (TU)', 'tetap'],
        ['SD', 'Fajar Ramadhan', 'L', 'Operator / Pranata Komputer', 'honorer'],
        ['SD', 'Nur Hidayah', 'P', 'Pustakawan', 'honorer'],
        ['SD', 'Supriyadi', 'L', 'Petugas Kebersihan', 'honorer'],

        // SMP
        ['SMP', 'Endang Lestari', 'P', 'Tenaga Administrasi (TU)', 'tetap'],
        ['SMP', 'Sri Wahyuni', 'P', 'Bendahara', 'tetap'],
        ['SMP', 'Agus Setiawan', 'L', 'Operator / Pranata Komputer', 'tetap'],
        ['SMP', 'Rahmawati', 'P', 'Pustakawan', 'honorer'],
        ['SMP', 'Dedi Kurniawan', 'L', 'Laboran', 'honorer'],
        ['SMP', 'Bambang Sutrisno', 'L', 'Satpam / Petugas Keamanan', 'honorer'],
        ['SMP', 'Joko Prasetyo', 'L', 'Pesuruh / Office Boy', 'honorer'],

        // SMA
        ['SMA', 'Yuni Astuti', 'P', 'Tenaga Administrasi (TU)', 'tetap'],
        ['SMA', 'Fitri Handayani', 'P', 'Bendahara', 'tetap'],
        ['SMA', 'Rizky Pratama', 'L', 'Operator / Pranata Komputer', 'honorer'],
        ['SMA', 'Hj. Nurjanah', 'P', 'Kepala Perpustakaan', 'tetap'],
        ['SMA', 'Hendra Gunawan', 'L', 'Laboran', 'honorer'],
        ['SMA', 'Teguh Santoso', 'L', 'Satpam / Petugas Keamanan', 'honorer'],
        ['SMA', 'Karyono', 'L', 'Tukang Kebun', 'honorer'],

        // SMK
        ['SMK', 'Lilis Suryani', 'P', 'Tenaga Administrasi (TU)', 'tetap'],
        ['SMK', 'Ratna Dewi', 'P', 'Bendahara', 'tetap'],
        ['SMK', 'Arif Hidayat', 'L', 'Operator / Pranata Komputer', 'honorer'],
        ['SMK', 'Siti Aminah', 'P', 'Pustakawan', 'honorer'],
        ['SMK', 'Yusuf Maulana', 'L', 'Laboran', 'honorer'],
        ['SMK', 'Slamet Riyadi', 'L', 'Satpam / Petugas Keamanan', 'honorer'],
        ['SMK', 'Wahyu Nugroho', 'L', 'Pesuruh / Office Boy', 'honorer'],
        ['SMK', 'Tutik Wulandari', 'P', 'Petugas Kebersihan', 'honorer'],
    ];

    public function run(): void
    {
        $password = (string) env('SEED_DEFAULT_PASSWORD', 'password');
        if (app()->environment('production') && $password === 'password') {
            throw new RuntimeException('Set SEED_DEFAULT_PASSWORD sebelum menjalankan seeder di production.');
        }

        $units = UnitSekolah::query()->orderBy('id')->get()->keyBy('singkatan');
        if ($units->isEmpty()) {
            throw new RuntimeException('Unit sekolah kosong — jalankan UnitSekolahSeeder terlebih dahulu.');
        }

        $passwordHash = Hash::make($password);
        $index = 0;
        $created = 0;

        foreach (self::TENDIK as [$singkatan, $nama, $jk, $namaJabatan, $kepegawaian]) {
            $index++;
            $unit = $units[$singkatan] ?? null;
            if (! $unit) {
                $this->command?->warn("Unit {$singkatan} tidak ditemukan — lewati {$nama}.");

                continue;
            }

            $jabatan = Jabatan::where('nama', $namaJabatan)->first()
                ?? Jabatan::firstOrCreate(['nama' => $namaJabatan], ['is_guru' => false]);

            $nik = self::NIK_PREFIX.str_pad((string) $index, 3, '0', STR_PAD_LEFT);
            $email = 'tendik'.str_pad((string) $index, 2, '0', STR_PAD_LEFT).'@yayasan.com';

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $nama,
                    'password' => $passwordHash,
                    'unit_sekolah_id' => $unit->id,
                ]
            );
            $user->assignRole('pegawai');

            $pegawai = Pegawai::where('nik_hash', Pegawai::nikHash($nik))->first();
            if (! $pegawai) {
                $pegawai = Pegawai::create([
                    'user_id' => $user->id,
                    'nik' => $nik,
                    // Prefix '2000' — terpisah dari NIP lain (yang berawalan '19'),
                    // sehingga unik dan tidak mungkin bentrok dengan seeder lain.
                    'nip' => '2000'.str_pad((string) $index, 6, '0', STR_PAD_LEFT),
                    'nama_lengkap' => $nama,
                    'tempat_lahir' => 'Bandung',
                    'tanggal_lahir' => now()->subYears(24 + ($index % 22))->subDays($index)->toDateString(),
                    'jenis_kelamin' => $jk,
                    'agama' => 'Islam',
                    'status_pernikahan' => $index % 3 === 0 ? 'Belum Menikah' : 'Menikah',
                    'jumlah_tanggungan' => $index % 3 === 0 ? 0 : 2,
                    'alamat_ktp' => 'Jl. Tendik No. '.$index.', Bandung',
                    'alamat_domisili' => 'Jl. Tendik No. '.$index.', Bandung',
                    'no_hp' => '0813'.str_pad((string) ($index * 137), 8, '0', STR_PAD_LEFT),
                    'status_kepegawaian' => $kepegawaian,
                    'wajib_kantor' => true,
                    'status_aktif' => 'aktif',
                    'tanggal_mulai_kerja' => now()->subYears(1 + ($index % 9))->toDateString(),
                    'pendidikan_terakhir' => $jk === 'L' ? 'SMA' : 'S1',
                    'pendidikan_jurusan' => $jk === 'L' ? 'Administrasi Perkantoran' : 'Manajemen',
                    'nama_bank' => ['BCA', 'Mandiri', 'BRI', 'BNI'][$index % 4],
                    'no_rekening' => str_pad((string) ($index * 99991), 10, '0', STR_PAD_LEFT),
                    'npwp' => str_pad((string) ($index * 777777), 15, '0', STR_PAD_LEFT),
                    'jatah_cuti_tahunan' => 12,
                ]);
                $created++;
            }

            if (! $pegawai->units()->where('unit_sekolah.id', $unit->id)->exists()) {
                $pegawai->units()->attach($unit->id, [
                    'jabatan_id' => $jabatan->id,
                    'is_primary' => true,
                ]);
            }

            $this->seedPresensi($pegawai, $unit);
        }

        $this->seedPengajuanIzin($units['SMP'] ?? null, $units['SMK'] ?? null);

        $this->command?->info("Seeder tendik selesai: {$created} pegawai baru dibuat dari ".count(self::TENDIK).' data.');
    }

    /**
     * Presensi kantor 5 hari kerja terakhir (idempoten via updateOrCreate).
     */
    private function seedPresensi(Pegawai $pegawai, UnitSekolah $unit): void
    {
        $hariKerja = [];
        $tanggal = Carbon::today();
        while (count($hariKerja) < 5) {
            if (! $tanggal->isWeekend()) {
                $hariKerja[] = $tanggal->copy();
            }
            $tanggal->subDay();
        }

        foreach ($hariKerja as $i => $date) {
            $telat = ($pegawai->id + $i) % 7 === 0;
            Presensi::updateOrCreate(
                [
                    'pegawai_id' => $pegawai->id,
                    'tanggal' => $date->format('Y-m-d'),
                ],
                [
                    'unit_sekolah_id' => $unit->id,
                    'tipe_presensi' => 'kantor',
                    'jam_masuk' => $telat ? '07:40:00' : '06:55:00',
                    'jam_keluar' => '15:30:00',
                    'latitude_masuk' => (float) $unit->latitude,
                    'longitude_masuk' => (float) $unit->longitude,
                    'jarak_masuk_meter' => rand(5, 40),
                    'akurasi_masuk' => rand(8, 25),
                    'status' => $telat ? 'telat' : 'hadir',
                ]
            );
        }
    }

    /**
     * 2 pengajuan izin tendik (status default pending / pending_l1) supaya
     * filter Jenis di halaman Pengajuan Izin punya data kependidikan.
     */
    private function seedPengajuanIzin(?UnitSekolah $smp, ?UnitSekolah $smk): void
    {
        $sumber = [];
        if ($smp) {
            $sumber[] = [$smp, 'Endang Lestari', 'cuti', 'Cuti tahunan keluarga', Carbon::today()->addDays(7), Carbon::today()->addDays(9)];
        }
        if ($smk) {
            $sumber[] = [$smk, 'Wahyu Nugroho', 'sakit', 'Sakit demam, perlu istirahat 2 hari', Carbon::today()->addDays(3), Carbon::today()->addDays(4)];
        }

        foreach ($sumber as [$unit, $nama, $jenis, $alasan, $mulai, $selesai]) {
            $pegawai = Pegawai::where('nama_lengkap', $nama)
                ->whereHas('units', fn ($q) => $q->where('unit_sekolah.id', $unit->id))
                ->first();
            if (! $pegawai) {
                continue;
            }

            PengajuanIzin::firstOrCreate(
                [
                    'pegawai_id' => $pegawai->id,
                    'tanggal_mulai' => $mulai->format('Y-m-d'),
                    'jenis_izin' => $jenis,
                ],
                [
                    'tanggal_selesai' => $selesai->format('Y-m-d'),
                    'alasan' => $alasan,
                ]
            );
        }
    }
}
