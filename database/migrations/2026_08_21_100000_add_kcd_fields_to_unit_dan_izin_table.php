<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_sekolah', function (Blueprint $table) {
            $table->string('web')->nullable()->after('singkatan');
            $table->string('telepon')->nullable()->after('web');
            $table->text('alamat')->nullable()->after('telepon');
        });

        Schema::table('pengajuan_izins', function (Blueprint $table) {
            $table->boolean('dihitung_hadir_kcd')->default(false)->after('catatan_approval');
        });
    }

    public function down(): void
    {
        Schema::table('unit_sekolah', function (Blueprint $table) {
            $table->dropColumn(['web', 'telepon', 'alamat']);
        });

        Schema::table('pengajuan_izins', function (Blueprint $table) {
            $table->dropColumn('dihitung_hadir_kcd');
        });
    }
};
