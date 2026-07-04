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
        Schema::table('komponen_gaji', function (Blueprint $table) {
            $table->integer('urutan')->default(99)->after('jenis');
            $table->boolean('tampil_di_matrix')->default(true)->after('urutan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('komponen_gaji', function (Blueprint $table) {
            $table->dropColumn(['urutan', 'tampil_di_matrix']);
        });
    }
};
