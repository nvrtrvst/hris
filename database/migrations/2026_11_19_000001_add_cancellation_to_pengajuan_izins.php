<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengajuan_izins', function (Blueprint $table) {
            $table->enum('status', ['pending', 'disetujui', 'ditolak', 'dibatalkan'])->default('pending')->change();
            $table->timestamp('cancelled_at')->nullable()->after('rejected_by');
        });
    }

    public function down(): void
    {
        DB::table('pengajuan_izins')
            ->where('status', 'dibatalkan')
            ->update(['status' => 'ditolak']);

        Schema::table('pengajuan_izins', function (Blueprint $table) {
            $table->enum('status', ['pending', 'disetujui', 'ditolak'])->default('pending')->change();
            $table->dropColumn('cancelled_at');
        });
    }
};
