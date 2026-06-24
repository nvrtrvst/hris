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
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('pegawai')->after('email'); // superadmin, admin_unit, pegawai
            $table->foreignId('unit_sekolah_id')->nullable()->after('role')->constrained('unit_sekolah')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['unit_sekolah_id']);
            $table->dropColumn(['role', 'unit_sekolah_id']);
        });
    }
};
