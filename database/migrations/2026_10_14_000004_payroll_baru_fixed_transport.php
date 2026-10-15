<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Migrasi model payroll: matikan honor per-JP + transport lama,
     * siapkan Transport per unit (nonaktif, nominal 0) dan Honor Honorer
     * per unit (nonaktif, nominal 0). Aktivasi + nominal via halaman Komponen.
     */
    public function up(): void
    {
        DB::table('komponen_gaji')
            ->whereIn('kode', ['honor_mengajar', 'honor_mengajar_tetap'])
            ->update(['is_active' => false, 'updated_at' => now()]);

        $transportLama = DB::table('komponen_gaji')
            ->where('nama', 'Tunjangan Transport')
            ->where(function ($q) {
                $q->whereNull('kode')->orWhere('kode', 'tunjangan_transport');
            })
            ->first();
        if ($transportLama) {
            DB::table('komponen_jabatan')->where('komponen_gaji_id', $transportLama->id)->delete();
            DB::table('komponen_gaji')->where('id', $transportLama->id)->update(['is_active' => false, 'updated_at' => now()]);
        }

        $units = DB::table('unit_sekolah')->get(['id', 'nama']);
        foreach ($units as $unit) {
            DB::table('komponen_gaji')->updateOrInsert(
                ['kode' => 'transport_harian', 'unit_sekolah_id' => $unit->id],
                [
                    'nama' => 'Tunjangan Transport '.$unit->nama,
                    'tipe' => 'pendapatan',
                    'jenis' => 'dinamis_kehadiran',
                    'nilai_default' => 0,
                    'applies_to_status_kepegawaian' => 'tetap',
                    'is_taxable' => false,
                    'is_active' => false,
                    'urutan' => 50,
                    'tampil_di_matrix' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
            DB::table('komponen_gaji')->updateOrInsert(
                ['kode' => 'honor_honorer', 'unit_sekolah_id' => $unit->id],
                [
                    'nama' => 'Honor Honorer '.$unit->nama,
                    'tipe' => 'pendapatan',
                    'jenis' => 'fixed',
                    'nilai_default' => 0,
                    'applies_to_status_kepegawaian' => 'honorer',
                    'is_taxable' => true,
                    'is_active' => false,
                    'urutan' => 51,
                    'tampil_di_matrix' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('komponen_gaji')->whereIn('kode', ['transport_harian', 'honor_honorer'])->delete();
        DB::table('komponen_gaji')
            ->whereIn('kode', ['honor_mengajar', 'honor_mengajar_tetap'])
            ->update(['is_active' => true, 'updated_at' => now()]);
        DB::table('komponen_gaji')->where('nama', 'Tunjangan Transport')
            ->where(function ($q) {
                $q->whereNull('kode')->orWhere('kode', 'tunjangan_transport');
            })
            ->update(['is_active' => true, 'updated_at' => now()]);
    }
};
