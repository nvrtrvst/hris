<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// [AUDIT L3] Index komposit utk accessor cuti_terpakai / sisa_cuti:
// query rutin = WHERE pegawai_id = ? AND jenis_izin = 'cuti' AND status = 'disetujui'.
// Tanpa index, tabel pengajuan_izins di-scan penuh per serialisasi pegawai.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengajuan_izins', function (Blueprint $table) {
            $table->index(['pegawai_id', 'jenis_izin', 'status'], 'pengajuan_izins_cuti_idx');
        });
    }

    public function down(): void
    {
        Schema::table('pengajuan_izins', function (Blueprint $table) {
            $table->dropIndex('pengajuan_izins_cuti_idx');
        });
    }
};
