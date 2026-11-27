<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_sekolah', function (Blueprint $table) {
            $table->unsignedTinyInteger('min_hari_minggu')->default(0)->after('max_jam_minggu');
            $table->date('min_hari_berlaku_mulai')->nullable()->after('min_hari_minggu');
        });

        DB::table('unit_sekolah')->update([
            'min_hari_minggu' => 4,
            'min_hari_berlaku_mulai' => Carbon::today()->startOfWeek(Carbon::MONDAY)->addWeek()->toDateString(),
        ]);
    }

    public function down(): void
    {
        Schema::table('unit_sekolah', function (Blueprint $table) {
            $table->dropColumn(['min_hari_minggu', 'min_hari_berlaku_mulai']);
        });
    }
};
