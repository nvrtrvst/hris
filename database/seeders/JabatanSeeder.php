<?php

namespace Database\Seeders;

use App\Models\Jabatan;
use Illuminate\Database\Seeder;

class JabatanSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Pendidik (is_guru = true) ───
        // Sesuai Permendikdasmen 21/2025: Guru, Konselor, Tutor, Instruktur
        $jabatans = [
            // Pendidik
            ['nama' => 'Guru Mata Pelajaran', 'is_guru' => true],
            ['nama' => 'Guru Kelas', 'is_guru' => true],
            ['nama' => 'Guru BK', 'is_guru' => true],
            ['nama' => 'Wali Kelas', 'is_guru' => false],  // Tugas tambahan guru, tapi bukan jabatan fungsional sendiri

            // ─── Tenaga Kependidikan (is_guru = false) ───
            // A. Kepala Satuan Pendidikan
            ['nama' => 'Kepala Sekolah', 'is_guru' => false],
            ['nama' => 'Wakil Kepala Sekolah', 'is_guru' => false],

            // B. Tenaga Perpustakaan
            ['nama' => 'Kepala Perpustakaan', 'is_guru' => false],
            ['nama' => 'Pustakawan', 'is_guru' => false],

            // C. Tenaga Laboratorium
            ['nama' => 'Kepala Laboratorium', 'is_guru' => false],
            ['nama' => 'Laboran', 'is_guru' => false],

            // D. Tenaga Administrasi
            ['nama' => 'Tenaga Administrasi (TU)', 'is_guru' => false],
            ['nama' => 'Bendahara', 'is_guru' => false],
            ['nama' => 'Kasir', 'is_guru' => false],
            ['nama' => 'Operator / Pranata Komputer', 'is_guru' => false],

            // E. Tenaga Kependidikan lainnya
            ['nama' => 'Satpam / Petugas Keamanan', 'is_guru' => false],
            ['nama' => 'Pesuruh / Office Boy', 'is_guru' => false],
            ['nama' => 'Petugas Kebersihan', 'is_guru' => false],
            ['nama' => 'Tukang Kebun', 'is_guru' => false],
            ['nama' => 'Terapis', 'is_guru' => false],
        ];

        foreach ($jabatans as $jabatan) {
            Jabatan::firstOrCreate(['nama' => $jabatan['nama']], $jabatan);
        }
    }
}
