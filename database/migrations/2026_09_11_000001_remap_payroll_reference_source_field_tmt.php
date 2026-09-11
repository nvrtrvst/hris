<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('payroll_reference_types')
            ->where('source_field', 'tanggal_mulai_kerja')
            ->update(['source_field' => 'tmt_mengajar']);
    }

    public function down(): void
    {
        DB::table('payroll_reference_types')
            ->where('source_field', 'tmt_mengajar')
            ->update(['source_field' => 'tanggal_mulai_kerja']);
    }
};
