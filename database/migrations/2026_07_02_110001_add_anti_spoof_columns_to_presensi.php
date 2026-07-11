<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presensi', function (Blueprint $table) {
            $table->decimal('akurasi_masuk', 8, 2)->nullable()->after('jarak_masuk_meter')->comment('Akurasi GPS (meter) saat absen masuk');
            $table->decimal('kecepatan_masuk', 8, 2)->nullable()->after('akurasi_masuk')->comment('Kecepatan (m/s) saat absen masuk');
            $table->decimal('akurasi_keluar', 8, 2)->nullable()->after('jarak_keluar_meter');
            $table->decimal('kecepatan_keluar', 8, 2)->nullable()->after('akurasi_keluar');
            $table->boolean('lokasi_perlu_review')->default(false)->after('keterangan')->comment('Flag bila lokasi curigaan (mock GPS / akurasi buruk)');
            $table->timestamp('captured_at')->nullable()->after('lokasi_perlu_review')->comment('Waktu lokasi di-capture di HP (bukan server)');
        });
    }

    public function down(): void
    {
        Schema::table('presensi', function (Blueprint $table) {
            $table->dropColumn([
                'akurasi_masuk',
                'kecepatan_masuk',
                'akurasi_keluar',
                'kecepatan_keluar',
                'lokasi_perlu_review',
                'captured_at',
            ]);
        });
    }
};
