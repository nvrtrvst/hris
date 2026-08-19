<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jabatan', function (Blueprint $table) {
            $table->unsignedTinyInteger('hierarchy_level')->default(99)->after('nama');
        });

        // Seed hierarchy levels: lower = lebih tinggi jabatannya
        $levels = [
            1 => 1,   // Kepala Sekolah
            2 => 2,   // Wakil Kepala Sekolah
            3 => 5,   // Guru Mata Pelajaran
            4 => 5,   // Guru Kelas
            5 => 5,   // Guru BK
            6 => 5,   // Wali Kelas
            7 => 6,   // Tenaga Administrasi
            8 => 4,   // Operator / Pranata Komputer
            9 => 6,   // Pustakawan
            10 => 7,  // Satpam
            11 => 7,  // Petugas Kebersihan
            12 => 6,  // Tenaga Administrasi (TU)
            13 => 4,  // Bendahara
            14 => 6,  // Laboran
            15 => 7,  // Satpam / Petugas Keamanan
            16 => 7,  // Pesuruh / Office Boy
            17 => 3,  // Kepala Perpustakaan
            18 => 7,  // Tukang Kebun
            19 => 3,  // Kepala Laboratorium
            20 => 6,  // Kasir
            21 => 6,  // Terapis
            22 => 5,  // Guru Mata Pelajaran
            23 => 0,  // Ketua Yayasan
            24 => 3,  // Kepala Tata Usaha
        ];

        foreach ($levels as $id => $level) {
            DB::table('jabatan')->where('id', $id)->update(['hierarchy_level' => $level]);
        }
    }

    public function down(): void
    {
        Schema::table('jabatan', function (Blueprint $table) {
            $table->dropColumn('hierarchy_level');
        });
    }
};
