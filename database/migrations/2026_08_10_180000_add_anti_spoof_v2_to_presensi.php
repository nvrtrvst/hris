<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presensi', function (Blueprint $table) {
            // Trajectory 3-titik: posisi awal (load), A (foto), B (submit) — disimpan sebagai JSON
            // untuk analisis pergerakan & deteksi fake GPS.
            $table->json('trajectory_samples')->nullable()->after('posisi_mencurigakan')
                ->comment('[{"label":"awal","lat":-6.2,"lng":106.8,"accuracy":15,"captured_at":"..."}, ...]');

            // Accelerometer/gyroscope samples dari DeviceMotion API.
            // Array [{x,y,z,timestamp}...] — variance rendah / semua nol = suspect.
            $table->json('motion_samples')->nullable()->after('trajectory_samples');

            // IP geolocation data (ip, city, country, distance_km) untuk cross-reference.
            $table->json('ip_geo')->nullable()->after('motion_samples');

            // EXIF metadata dari foto (GPS + datetime) — mismatch dengan koordinat reported = suspect.
            $table->json('exif_meta')->nullable()->after('ip_geo');

            // Flag hasil analisis motion (dari accelerometer)
            $table->boolean('motion_suspect')->default(false)->after('posisi_mencurigakan');
        });
    }

    public function down(): void
    {
        Schema::table('presensi', function (Blueprint $table) {
            $table->dropColumn(['trajectory_samples', 'motion_samples', 'ip_geo', 'exif_meta', 'motion_suspect']);
        });
    }
};
