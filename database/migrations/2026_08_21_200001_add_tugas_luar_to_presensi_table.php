<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presensi', function (Blueprint $table) {
            $table->boolean('is_tugas_luar')->default(false)->index();
            $table->string('tugas_luar_status')->nullable()->comment('pending|disetujui|ditolak');
            $table->foreignId('tugas_luar_id')->nullable()->constrained('tugas_luar')->nullOnDelete();
            $table->string('tujuan')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('presensi', function (Blueprint $table) {
            $table->dropForeign(['tugas_luar_id']);
            $table->dropColumn(['is_tugas_luar', 'tugas_luar_status', 'tugas_luar_id', 'tujuan']);
        });
    }
};
