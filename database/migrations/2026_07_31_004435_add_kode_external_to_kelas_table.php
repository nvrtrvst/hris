<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('kelas', function (Blueprint $table) {
            $table->string('kode_external', 100)->nullable()->after('jurusan_id');
            $table->unique(['kode_external', 'unit_sekolah_id']);
        });
    }

    public function down(): void
    {
        Schema::table('kelas', function (Blueprint $table) {
            $table->dropUnique(['kode_external', 'unit_sekolah_id']);
            $table->dropColumn('kode_external');
        });
    }
};
