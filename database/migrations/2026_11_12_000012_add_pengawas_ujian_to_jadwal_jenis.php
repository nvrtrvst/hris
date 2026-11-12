<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const JENIS = [
        'mengajar',
        'piket',
        'ekskul',
        'shift_satpam',
        'shift_kebersihan',
        'lainnya',
        'pengawas_ujian',
    ];

    public function up(): void
    {
        Schema::table('jadwal', function (Blueprint $table) {
            $table->enum('jenis_jadwal', self::JENIS)->default('mengajar')->change();
        });
    }

    public function down(): void
    {
        Schema::table('jadwal', function (Blueprint $table) {
            $table->enum('jenis_jadwal', array_slice(self::JENIS, 0, -1))->default('mengajar')->change();
        });
    }
};
