<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengajuan_pulang_cepats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('pegawai')->cascadeOnDelete();
            $table->date('tanggal');
            $table->time('jam_diminta');
            $table->enum('alasan', ['sakit', 'urusan_keluarga', 'keperluan_pribadi', 'dinas_luar', 'lainnya']);
            $table->text('alasan_detail')->nullable();
            $table->enum('status', ['pending', 'disetujui', 'ditolak'])->default('pending');
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('alasan_penolakan')->nullable();
            $table->text('catatan_approval')->nullable();
            $table->string('nomor')->unique();
            $table->timestamps();

            $table->index(['pegawai_id', 'tanggal', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengajuan_pulang_cepats');
    }
};
