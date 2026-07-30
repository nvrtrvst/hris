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
        Schema::table('jadwal', function (Blueprint $table) {
            $table->dropForeign(['kelas_id']);
            $table->string('kelas_label', 255)->nullable()->after('kelas_id');
        });

        DB::statement('UPDATE jadwal SET kelas_label = (SELECT CONCAT(tingkat, " - ", nama) FROM kelas WHERE kelas.id = jadwal.kelas_id) WHERE kelas_id IS NOT NULL');

        Schema::table('jadwal', function (Blueprint $table) {
            $table->dropColumn('kelas_id');
        });
    }

    public function down(): void
    {
        Schema::table('jadwal', function (Blueprint $table) {
            $table->foreignId('kelas_id')->nullable()->after('kelas_label');
            $table->dropColumn('kelas_label');
        });
    }
};
