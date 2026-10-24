<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jabatan_id')->constrained('jabatan')->cascadeOnDelete();
            $table->foreignId('unit_sekolah_id')->nullable()->constrained('unit_sekolah')->cascadeOnDelete();
            $table->string('nama');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['jabatan_id', 'unit_sekolah_id'], 'kpi_template_jabatan_unit_unique');
            $table->index(['jabatan_id', 'unit_sekolah_id', 'is_active']);
        });

        Schema::create('kpi_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_template_id')->constrained('kpi_templates')->cascadeOnDelete();
            $table->string('kode', 60);
            $table->string('nama', 150);
            $table->text('deskripsi')->nullable();
            $table->enum('jenis', ['numeric', 'rubric']);
            $table->enum('sumber', ['system', 'manual']);
            $table->string('source_key', 60)->nullable();
            $table->decimal('target', 12, 2)->nullable();
            $table->enum('arah', ['higher', 'lower'])->nullable();
            $table->decimal('bobot', 5, 2);
            $table->string('satuan', 30)->nullable();
            $table->json('rubrik')->nullable();
            $table->boolean('wajib_bukti')->default(false);
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['kpi_template_id', 'kode']);
            $table->index(['kpi_template_id', 'is_active', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_indicators');
        Schema::dropIfExists('kpi_templates');
    }
};
