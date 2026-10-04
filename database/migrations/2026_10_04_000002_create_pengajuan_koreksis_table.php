<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengajuan_koreksis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('pegawai')->cascadeOnDelete();
            $table->foreignId('presensi_id')->constrained('presensi')->cascadeOnDelete();
            $table->date('tanggal');
            $table->time('nilai_baru');
            $table->enum('alasan', ['lupa_presensi', 'hp_rusak', 'dinas_luar', 'rapat', 'lainnya']);
            $table->text('alasan_detail')->nullable();
            $table->text('penjelasan_khusus')->nullable();
            $table->enum('status', ['pending', 'disetujui', 'ditolak'])->default('pending');
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('alasan_penolakan')->nullable();
            $table->text('catatan_approval')->nullable();
            $table->string('nomor')->unique();
            $table->timestamps();

            $table->index(['pegawai_id', 'status', 'created_at']);
            $table->index('presensi_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengajuan_koreksis');
    }
};
