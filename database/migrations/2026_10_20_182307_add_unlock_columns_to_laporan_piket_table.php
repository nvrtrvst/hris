<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jalur remedial piket: admin membuka (unlocked_at/unlocked_by) laporan
     * draft agar item seksi Pagi boleh dicatat telat setelah jendela pagi.
     */
    public function up(): void
    {
        Schema::table('laporan_piket', function (Blueprint $table) {
            $table->timestamp('unlocked_at')->nullable()->after('submitted_at');
            $table->foreignId('unlocked_by')->nullable()->after('unlocked_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('laporan_piket', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unlocked_by');
            $table->dropColumn('unlocked_at');
        });
    }
};
