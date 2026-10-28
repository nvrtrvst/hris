<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengajuan_izins', function (Blueprint $table) {
            $table->string('sumber', 20)->default('pwa')->after('status')->index();
            $table->foreignId('created_by')->nullable()->after('sumber')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pengajuan_izins', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn(['sumber', 'created_by']);
        });
    }
};
