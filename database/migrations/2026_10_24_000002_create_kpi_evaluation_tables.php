<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('pegawai')->cascadeOnDelete();
            $table->foreignId('unit_sekolah_id')->constrained('unit_sekolah')->cascadeOnDelete();
            $table->foreignId('jabatan_id')->constrained('jabatan')->restrictOnDelete();
            $table->foreignId('kpi_template_id')->nullable()->constrained('kpi_templates')->nullOnDelete();
            $table->date('periode_mulai');
            $table->date('periode_selesai');
            $table->enum('status', ['draft', 'finalized'])->default('draft');
            $table->decimal('nilai_akhir', 5, 2)->nullable();
            $table->text('ringkasan')->nullable();
            $table->text('tanggapan_pegawai')->nullable();
            $table->foreignId('ditanggapi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ditanggapi_pada')->nullable();
            $table->foreignId('dinilai_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('difinalisasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('difinalisasi_pada')->nullable();
            $table->timestamps();
            $table->unique(
                ['pegawai_id', 'unit_sekolah_id', 'jabatan_id', 'periode_mulai', 'periode_selesai'],
                'kpi_eval_pegawai_unit_jabatan_periode_unique',
            );
            $table->index(
                ['unit_sekolah_id', 'periode_mulai', 'periode_selesai', 'status'],
                'kpi_eval_unit_periode_status_index',
            );
        });

        Schema::create('kpi_evaluation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_evaluation_id')->constrained('kpi_evaluations')->cascadeOnDelete();
            $table->foreignId('kpi_indicator_id')->nullable()->constrained('kpi_indicators')->nullOnDelete();
            $table->string('nama_snapshot', 150);
            $table->text('deskripsi_snapshot')->nullable();
            $table->enum('jenis_snapshot', ['numeric', 'rubric']);
            $table->enum('sumber_snapshot', ['system', 'manual']);
            $table->string('source_key_snapshot', 60)->nullable();
            $table->decimal('bobot_snapshot', 5, 2);
            $table->decimal('target_snapshot', 12, 2)->nullable();
            $table->enum('arah_snapshot', ['higher', 'lower'])->nullable();
            $table->string('satuan_snapshot', 30)->nullable();
            $table->boolean('wajib_bukti_snapshot')->default(false);
            $table->decimal('nilai_aktual', 12, 2)->nullable();
            $table->unsignedTinyInteger('level_rubrik')->nullable();
            $table->json('rubrik_snapshot')->nullable();
            $table->decimal('nilai', 5, 2)->nullable();
            $table->text('bukti')->nullable();
            $table->text('catatan')->nullable();
            $table->boolean('tidak_berlaku')->default(false);
            $table->text('alasan_tidak_berlaku')->nullable();
            $table->timestamps();
            $table->unique(['kpi_evaluation_id', 'kpi_indicator_id'], 'kpi_eval_item_indicator_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_evaluation_items');
        Schema::dropIfExists('kpi_evaluations');
    }
};
