<?php

use App\Models\Jabatan;
use App\Services\KpiTemplateDefaults;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $jabatan = Jabatan::firstOrCreate(
            ['nama' => 'Sarana dan Prasarana'],
            ['is_guru' => false, 'is_supervisor' => false, 'is_payroll_operator' => false],
        );

        $kepalaTU = Jabatan::where('nama', 'Kepala Tata Usaha')->value('id');
        $kepalaSekolah = Jabatan::where('nama', 'Kepala Sekolah')->value('id');

        $jabatan->update([
            'hierarchy_level' => (int) ($jabatan->hierarchy_level ?? 99) === 99 ? 4 : $jabatan->hierarchy_level,
            'is_supervisor' => false,
            'approver_l1_jabatan_id' => $jabatan->approver_l1_jabatan_id ?? $kepalaTU,
            'approver_l2_jabatan_id' => $jabatan->approver_l2_jabatan_id ?? $kepalaSekolah,
        ]);

        app(KpiTemplateDefaults::class)->ensureForJabatan($jabatan);
    }

    public function down(): void {}
};
