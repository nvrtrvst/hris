<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_sekolah', function (Blueprint $table) {
            $table->decimal('max_lembur_jam', 5, 2)->nullable()->after('durasi_jp')
                ->comment('Batas jam lembur per hari; NULL = tanpa batas');
        });
    }

    public function down(): void
    {
        Schema::table('unit_sekolah', function (Blueprint $table) {
            $table->dropColumn('max_lembur_jam');
        });
    }
};
