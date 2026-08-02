<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presensi', function (Blueprint $table) {
            $table->enum('status', ['hadir', 'telat', 'izin', 'sakit', 'cuti', 'alpa'])->default('alpa')->change();
        });
    }

    public function down(): void
    {
        Schema::table('presensi', function (Blueprint $table) {
            $table->enum('status', ['hadir', 'telat', 'izin', 'sakit', 'alpa'])->default('alpa')->change();
        });
    }
};
