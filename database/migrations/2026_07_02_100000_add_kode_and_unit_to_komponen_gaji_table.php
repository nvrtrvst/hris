<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('komponen_gaji', function (Blueprint $table) {
            $table->string('kode', 50)->nullable()->after('nama')
                ->comment('Flag stabil untuk logika payroll (ganti stripos nama). Contoh: gaji_pokok, kehadiran_telat, kehadiran_alpa, kehadiran_sakit, kehadiran_izin, kehadiran_cuti, tunjangan_kehadiran');
            $table->foreignId('unit_sekolah_id')->nullable()->after('jenis')
                ->constrained('unit_sekolah')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('komponen_gaji', function (Blueprint $table) {
            $table->dropForeign(['unit_sekolah_id']);
            $table->dropColumn(['kode', 'unit_sekolah_id']);
        });
    }
};
