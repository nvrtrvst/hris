<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('piket_sesi', function (Blueprint $table) {
            $table->unsignedInteger('assignment_version')->default(1)->after('status');
        });

        Schema::table('piket_petugas', function (Blueprint $table) {
            $table->string('status', 20)->default('hadir')->after('peran');
            $table->string('status_alasan', 255)->nullable()->after('status');
        });

        Schema::create('piket_petugas_kelas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesi_id')->constrained('piket_sesi')->cascadeOnDelete();
            $table->foreignId('petugas_id')->constrained('pegawai')->cascadeOnDelete();
            $table->foreignId('dialihkan_dari')->nullable()->constrained('pegawai')->nullOnDelete();
            $table->string('kelas_label', 100);
            $table->string('status', 20)->default('aktif');
            $table->timestamps();

            $table->unique(['sesi_id', 'kelas_label'], 'piket_petugas_kelas_sesi_kelas_uq');
            $table->index(['sesi_id', 'petugas_id'], 'piket_petugas_kelas_sesi_petugas_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('piket_petugas_kelas');

        Schema::table('piket_petugas', function (Blueprint $table) {
            $table->dropColumn(['status', 'status_alasan']);
        });

        Schema::table('piket_sesi', function (Blueprint $table) {
            $table->dropColumn('assignment_version');
        });
    }
};
