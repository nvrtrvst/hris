<?php

use App\Models\KomponenGaji;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Koreksi model payroll:
     * - Grup status baru `guru` / `non_guru` (honor mengajar = guru, gaji pokok = non-guru).
     * - Kolom `persentase_base` (dasar PPh 21 / BPJS) + `mata_pelajaran_id` (ekskul).
     * - Status GTY (guru tetap non-sertifikasi) — belum ada, sama aturan uang dgn GTYS.
     */
    public function up(): void
    {
        Schema::table('komponen_gaji', function (Blueprint $table) {
            $table->foreignId('mata_pelajaran_id')->nullable()->after('payroll_reference_type_id')
                ->constrained('mata_pelajaran')->nullOnDelete();
            $table->string('persentase_base', 20)->nullable()->after('syarat_bayar_jam_mengajar');
        });

        $now = now()->toDateTimeString();

        if (! DB::table('status_kepegawaian')->where('kode', 'guru_tetap_non_sertifikasi')->exists()) {
            DB::table('status_kepegawaian')->insert([
                'kode' => 'guru_tetap_non_sertifikasi',
                'label' => 'GTY (Guru Tetap Yayasan)',
                'is_tetap' => true,
                'is_guru' => true,
                'urutan' => 2,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('status_kepegawaian')->where('kode', 'guru_tetap_yayasan')
            ->update(['label' => 'GTYS (Guru Tetap Yayasan Sertifikasi)']);

        DB::table('komponen_gaji')->whereNull('kode')->where('nama', 'like', '%transport%')
            ->update(['kode' => 'transport_harian']);

        KomponenGaji::where('kode', 'honor_mengajar')->update(['applies_to_status_kepegawaian' => 'guru']);
        KomponenGaji::where('kode', 'honor_mengajar_tetap')->update(['is_active' => false]);
        KomponenGaji::where('kode', 'gaji_pokok')->update(['applies_to_status_kepegawaian' => 'non_guru']);
        KomponenGaji::where('kode', 'transport_harian')->update(['applies_to_status_kepegawaian' => 'tetap']);

        DB::table('komponen_gaji')->where('nama', 'like', '%PPh%')
            ->update(['persentase_base' => 'taxable']);
        DB::table('komponen_gaji')->where('nama', 'like', '%BPJS%')
            ->update(['persentase_base' => 'gaji_pokok']);
    }

    public function down(): void
    {
        Schema::table('komponen_gaji', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mata_pelajaran_id');
            $table->dropColumn('persentase_base');
        });

        DB::table('status_kepegawaian')->where('kode', 'guru_tetap_non_sertifikasi')->delete();
    }
};
