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
            $table->string('syarat_bayar_jam_mengajar', 30)->nullable()->after('applies_to_status_kepegawaian');
        });

        KomponenGaji::where('kode', 'honor_mengajar')->update(['syarat_bayar_jam_mengajar' => 'hanya_hadir']);
    }

    public function down(): void
    {
        Schema::table('komponen_gaji', function (Blueprint $table) {
            $table->dropColumn('syarat_bayar_jam_mengajar');
        });
    }
};
