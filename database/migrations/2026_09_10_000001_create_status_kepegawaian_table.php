<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel referensi status kepegawaian (Tier B — ref table + kode).
     *
     * `pegawais.status_kepegawaian` tetap string kode; kode = identifier
     * stabil, sedangkan label + flag (is_tetap, is_guru, is_active, urutan)
     * hidup di tabel ini dan bisa diubah lewat DB tanpa deploy.
     *
     * Seed di migration (bukan seeder) supaya RefreshDatabase pada test
     * otomatis mendapatkan 6 baris referensi.
     */
    public function up(): void
    {
        Schema::create('status_kepegawaian', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique();
            $table->string('label', 100);
            $table->boolean('is_tetap')->default(false);
            $table->boolean('is_guru')->default(false);
            $table->integer('urutan')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now()->toDateTimeString();
        $rows = [
            ['guru_tetap_yayasan', 'GTYS (Guru Tetap Yayasan)', true, true, 1],
            ['pegawai_tetap_yayasan', 'PTY (Pegawai Tetap Yayasan)', true, false, 2],
            ['guru_tidak_tetap', 'GTT (Guru Tidak Tetap)', false, true, 3],
            ['pegawai_tidak_tetap', 'Pegawai Tidak Tetap', false, false, 4],
            ['guru_pemula', 'Guru Pemula', false, true, 5],
            ['pegawai_pemula', 'Pegawai Pemula', false, false, 6],
        ];

        foreach ($rows as [$kode, $label, $isTetap, $isGuru, $urutan]) {
            DB::table('status_kepegawaian')->insert([
                'kode' => $kode,
                'label' => $label,
                'is_tetap' => $isTetap,
                'is_guru' => $isGuru,
                'urutan' => $urutan,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('status_kepegawaian');
    }
};
