<?php

use App\Models\KomponenGaji;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('komponen_gaji', function (Blueprint $table) {
            $table->string('applies_to_status_kepegawaian', 50)->nullable()->after('jenis');
        });

        // Update old komponen (set applies_to_status)
        KomponenGaji::whereIn('nama', ['Gaji Pokok', 'Tunjangan Jabatan', 'Tunjangan Transport', 'Tunjangan Makan'])
            ->update(['applies_to_status_kepegawaian' => 'tetap']);

        // Deactivate old PPh/BPJS komponen (not delete — FK safety)
        KomponenGaji::whereIn('nama', ['PPh 21', 'BPJS Kesehatan', 'BPJS Ketenagakerjaan'])
            ->update(['is_active' => false]);

        // Create: PPh 21 - Tetap
        KomponenGaji::create([
            'nama' => 'PPh 21 - Tetap',
            'jenis' => 'persentase',
            'applies_to_status_kepegawaian' => 'tetap',
            'tipe' => 'potongan',
            'nilai_default' => 5,
            'is_taxable' => false,
            'is_active' => true,
            'urutan' => 10,
            'tampil_di_matrix' => true,
        ]);

        // Create: PPh 21 - Honorer
        KomponenGaji::create([
            'nama' => 'PPh 21 - Honorer',
            'jenis' => 'persentase',
            'applies_to_status_kepegawaian' => 'honorer',
            'tipe' => 'potongan',
            'nilai_default' => 0,
            'is_taxable' => false,
            'is_active' => true,
            'urutan' => 11,
            'tampil_di_matrix' => true,
        ]);

        // Create: BPJS Kesehatan - Tetap
        KomponenGaji::create([
            'nama' => 'BPJS Kesehatan - Tetap',
            'jenis' => 'persentase',
            'applies_to_status_kepegawaian' => 'tetap',
            'tipe' => 'potongan',
            'nilai_default' => 1,
            'is_taxable' => false,
            'is_active' => true,
            'urutan' => 12,
            'tampil_di_matrix' => true,
        ]);

        // Create: BPJS Kesehatan - Honorer
        KomponenGaji::create([
            'nama' => 'BPJS Kesehatan - Honorer',
            'jenis' => 'persentase',
            'applies_to_status_kepegawaian' => 'honorer',
            'tipe' => 'potongan',
            'nilai_default' => 0,
            'is_taxable' => false,
            'is_active' => true,
            'urutan' => 13,
            'tampil_di_matrix' => true,
        ]);

        // Create: BPJS Ketenagakerjaan - Tetap
        KomponenGaji::create([
            'nama' => 'BPJS Ketenagakerjaan - Tetap',
            'jenis' => 'persentase',
            'applies_to_status_kepegawaian' => 'tetap',
            'tipe' => 'potongan',
            'nilai_default' => 2,
            'is_taxable' => false,
            'is_active' => true,
            'urutan' => 14,
            'tampil_di_matrix' => true,
        ]);

        // Create: BPJS Ketenagakerjaan - Honorer
        KomponenGaji::create([
            'nama' => 'BPJS Ketenagakerjaan - Honorer',
            'jenis' => 'persentase',
            'applies_to_status_kepegawaian' => 'honorer',
            'tipe' => 'potongan',
            'nilai_default' => 0,
            'is_taxable' => false,
            'is_active' => true,
            'urutan' => 15,
            'tampil_di_matrix' => true,
        ]);

        // Create: Honor Mengajar
        $honorMengajar = KomponenGaji::create([
            'nama' => 'Honor Mengajar',
            'kode' => 'honor_mengajar',
            'jenis' => 'dinamis_jam_mengajar',
            'applies_to_status_kepegawaian' => 'honorer',
            'tipe' => 'pendapatan',
            'nilai_default' => 25000,
            'is_taxable' => true,
            'is_active' => true,
            'urutan' => 5,
            'tampil_di_matrix' => true,
        ]);
    }

    public function down(): void
    {
        // Reverse: reactivate old komponen
        KomponenGaji::whereIn('nama', ['PPh 21', 'BPJS Kesehatan', 'BPJS Ketenagakerjaan'])
            ->update(['is_active' => true]);

        // Reverse: delete new komponen
        KomponenGaji::whereIn('nama', [
            'PPh 21 - Tetap', 'PPh 21 - Honorer',
            'BPJS Kesehatan - Tetap', 'BPJS Kesehatan - Honorer',
            'BPJS Ketenagakerjaan - Tetap', 'BPJS Ketenagakerjaan - Honorer',
            'Honor Mengajar',
        ])->delete();

        Schema::table('komponen_gaji', function (Blueprint $table) {
            $table->dropColumn('applies_to_status_kepegawaian');
        });
    }
};
