<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enkripsi NIK + hash lookup column.
     *
     * - `nik` jadi TEXT nullable (ciphertext panjang acak, encrypted cast).
     * - `nik_hash` VARCHAR(64) SHA-256 hex, unique (lookup equality tanpa decrypt).
     * - Backfill dilakukan pada migration data + oleh seeder pre-compute.
     * - Drop unique index `pegawai_nik_unique` lama dulu (kolom jadi TEXT).
     */
    public function up(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            // Drop unique index lama dulu (kolom jadi TEXT, perlu drop sebelum alter)
            $table->dropUnique('pegawai_nik_unique');
        });

        Schema::table('pegawai', function (Blueprint $table) {
            // Ciphertext: encrypted cast serialize base64 => panjang tidak tetap.
            $table->text('nik')->nullable()->change();

            // Hash untuk lookup equality (WHERE nik_hash = ?).
            // Nullable dulu agar ALTER tidak gagal di DB yang strict.
            // Setelah backfill, semua baris akan terisi (command enforce).
            $table->string('nik_hash', 64)->nullable()->after('nik');
            $table->unique('nik_hash', 'pegawai_nik_hash_unique');
        });
    }

    public function down(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->dropUnique('pegawai_nik_hash_unique');
            $table->dropColumn('nik_hash');
        });

        Schema::table('pegawai', function (Blueprint $table) {
            $table->string('nik', 16)->nullable()->change();
            $table->unique('nik', 'pegawai_nik_unique');
        });
    }
};
