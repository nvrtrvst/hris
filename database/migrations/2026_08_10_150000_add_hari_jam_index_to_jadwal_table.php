<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * [AUDIT PERF] Dashboard superadmin menampilkan semua jadwal hari ini
     * (where hari + orderBy jam_mulai) — sebelumnya full scan + filesort.
     * Index komposit (hari, jam_mulai) menutupi filter + sort sekaligus.
     */
    public function up(): void
    {
        Schema::table('jadwal', function (Blueprint $table) {
            if (! Schema::hasIndex('jadwal', 'idx_jadwal_hari_jam')) {
                $table->index(['hari', 'jam_mulai'], 'idx_jadwal_hari_jam');
            }
        });
    }

    public function down(): void
    {
        Schema::table('jadwal', function (Blueprint $table) {
            $table->dropIndex('idx_jadwal_hari_jam');
        });
    }
};
