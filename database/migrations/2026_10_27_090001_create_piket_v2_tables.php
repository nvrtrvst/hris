<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_sekolah', function (Blueprint $table) {
            $table->string('piket_mode', 20)->default('simple')->after('singkatan');
        });

        Schema::create('piket_area', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_sekolah_id')->constrained('unit_sekolah')->cascadeOnDelete();
            $table->string('nama');
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('piket_area_kelas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_sekolah_id')->constrained('unit_sekolah')->cascadeOnDelete();
            $table->foreignId('area_id')->constrained('piket_area')->cascadeOnDelete();
            $table->string('kelas_label', 100);
            $table->timestamps();
            $table->unique(['unit_sekolah_id', 'kelas_label'], 'piket_area_kelas_unit_label_uq');
        });

        Schema::create('piket_pos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_sekolah_id')->constrained('unit_sekolah')->cascadeOnDelete();
            $table->foreignId('area_id')->nullable()->constrained('piket_area')->nullOnDelete();
            $table->string('nama');
            $table->string('jam_mulai', 5)->nullable();
            $table->string('jam_selesai', 5)->nullable();
            $table->boolean('is_wajib')->default(false);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('piket_item_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_sekolah_id')->constrained('unit_sekolah')->cascadeOnDelete();
            $table->string('kode', 40);
            $table->string('label');
            $table->string('seksi', 10)->default('bebas');
            $table->string('scope', 10)->default('global');
            $table->string('tipe', 20)->default('centang');
            $table->boolean('wajib')->default(true);
            $table->boolean('butuh_foto')->default(false);
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->unique(['unit_sekolah_id', 'kode'], 'piket_item_unit_kode_uq');
        });

        Schema::create('piket_penugasan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_sekolah_id')->constrained('unit_sekolah')->cascadeOnDelete();
            $table->string('tahun_ajaran', 20)->nullable();
            $table->string('semester', 10)->nullable();
            $table->foreignId('pegawai_id')->constrained('pegawai')->cascadeOnDelete();
            $table->foreignId('area_id')->nullable()->constrained('piket_area')->nullOnDelete();
            $table->foreignId('pos_id')->nullable()->constrained('piket_pos')->nullOnDelete();
            $table->string('peran', 20)->default('area');
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->unique(['unit_sekolah_id', 'tahun_ajaran', 'semester', 'pegawai_id'], 'piket_penugasan_unique');
        });

        Schema::create('piket_sesi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_sekolah_id')->constrained('unit_sekolah')->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('jam_mulai', 5);
            $table->string('jam_selesai', 5);
            $table->string('status', 20)->default('open');
            $table->foreignId('ditutup_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ditutup_at')->nullable();
            $table->timestamps();
            $table->unique(['unit_sekolah_id', 'tanggal', 'jam_mulai', 'jam_selesai'], 'piket_sesi_unique');
        });

        Schema::create('piket_petugas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesi_id')->constrained('piket_sesi')->cascadeOnDelete();
            $table->foreignId('pegawai_id')->constrained('pegawai')->cascadeOnDelete();
            $table->foreignId('area_id')->nullable()->constrained('piket_area')->nullOnDelete();
            $table->foreignId('pos_id')->nullable()->constrained('piket_pos')->nullOnDelete();
            $table->string('peran', 20)->default('area');
            $table->foreignId('laporan_piket_id')->nullable()->constrained('laporan_piket')->nullOnDelete();
            $table->timestamps();
            $table->unique(['sesi_id', 'pegawai_id'], 'piket_petugas_unique');
        });

        Schema::create('piket_round', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesi_id')->constrained('piket_sesi')->cascadeOnDelete();
            $table->foreignId('area_id')->nullable()->constrained('piket_area')->nullOnDelete();
            $table->foreignId('petugas_id')->nullable()->constrained('pegawai')->nullOnDelete();
            $table->unsignedTinyInteger('putaran');
            $table->string('jendela_mulai', 5);
            $table->string('jendela_selesai', 5);
            $table->timestamp('selesai_at')->nullable();
            $table->string('status', 20)->default('tertunda');
            $table->text('alasan')->nullable();
            $table->timestamps();
            $table->unique(['sesi_id', 'area_id', 'putaran'], 'piket_round_unique');
        });

        Schema::create('piket_pos_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesi_id')->constrained('piket_sesi')->cascadeOnDelete();
            $table->foreignId('pos_id')->constrained('piket_pos')->cascadeOnDelete();
            $table->foreignId('petugas_id')->nullable()->constrained('pegawai')->nullOnDelete();
            $table->timestamp('done_at')->nullable();
            $table->boolean('nihil')->default(false);
            $table->unsignedSmallInteger('jumlah')->nullable();
            $table->text('catatan')->nullable();
            $table->string('sumber', 20)->default('manual');
            $table->foreignId('override_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('alasan_override')->nullable();
            $table->timestamps();
            $table->unique(['sesi_id', 'pos_id'], 'piket_pos_log_unique');
        });

        Schema::table('laporan_piket', function (Blueprint $table) {
            $table->foreignId('sesi_id')->nullable()->after('unit_sekolah_id')->constrained('piket_sesi')->nullOnDelete();
            $table->foreignId('area_id')->nullable()->after('sesi_id')->constrained('piket_area')->nullOnDelete();
            $table->string('peran', 20)->nullable()->after('area_id');
            $table->text('alasan_ronde_terlewat')->nullable()->after('peran');
        });
    }

    public function down(): void
    {
        Schema::table('laporan_piket', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sesi_id');
            $table->dropConstrainedForeignId('area_id');
            $table->dropColumn(['peran', 'alasan_ronde_terlewat']);
        });

        Schema::dropIfExists('piket_pos_log');
        Schema::dropIfExists('piket_round');
        Schema::dropIfExists('piket_petugas');
        Schema::dropIfExists('piket_sesi');
        Schema::dropIfExists('piket_penugasan');
        Schema::dropIfExists('piket_item_templates');
        Schema::dropIfExists('piket_pos');
        Schema::dropIfExists('piket_area_kelas');
        Schema::dropIfExists('piket_area');

        Schema::table('unit_sekolah', function (Blueprint $table) {
            $table->dropColumn('piket_mode');
        });
    }
};
