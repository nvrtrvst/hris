<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sinkronkan schema dengan model PegawaiDokumen + UI upload dokumen:
     * - rename `jenis_dokumen` -> `nama_dokumen` (judul dokumen, mis. "SK Pengangkatan")
     * - tambah `jenis` (kategori: SK/Ijazah/KTP/dll) & `keterangan`.
     */
    public function up(): void
    {
        Schema::table('pegawai_dokumen', function (Blueprint $table) {
            $table->renameColumn('jenis_dokumen', 'nama_dokumen');
            $table->string('jenis')->nullable()->after('nama_dokumen');
            $table->text('keterangan')->nullable()->after('file_path');
        });
    }

    public function down(): void
    {
        Schema::table('pegawai_dokumen', function (Blueprint $table) {
            $table->dropColumn(['jenis', 'keterangan']);
            $table->renameColumn('nama_dokumen', 'jenis_dokumen');
        });
    }
};
