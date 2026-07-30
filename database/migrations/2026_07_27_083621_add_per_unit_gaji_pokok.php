<?php

use App\Models\KomponenGaji;
use App\Models\UnitSekolah;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (UnitSekolah::count() === 0) {
            return;
        }

        KomponenGaji::where('nama', 'Gaji Pokok')->whereNull('unit_sekolah_id')->update(['is_active' => false]);

        $unitValues = [
            'SMK' => 5000000,
            'SMP' => 2000000,
        ];

        foreach (UnitSekolah::all() as $unit) {
            $nilai = $unitValues[$unit->nama] ?? 3000000;
            KomponenGaji::updateOrCreate(
                ['kode' => 'gaji_pokok', 'unit_sekolah_id' => $unit->id],
                [
                    'nama' => 'Gaji Pokok '.$unit->nama,
                    'jenis' => 'fixed',
                    'tipe' => 'pendapatan',
                    'is_taxable' => false,
                    'applies_to_status_kepegawaian' => 'tetap',
                    'nilai_default' => $nilai,
                    'is_active' => true,
                    'urutan' => 1,
                    'tampil_di_matrix' => true,
                ]
            );
        }
    }

    public function down(): void
    {
        KomponenGaji::where('nama', 'Gaji Pokok')->whereNull('unit_sekolah_id')->update(['is_active' => true]);

        KomponenGaji::where('kode', 'gaji_pokok')->whereNotNull('unit_sekolah_id')->update(['is_active' => false]);
    }
};
