<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presensi', function (Blueprint $table) {
            $table->decimal('pos_a_lat', 10, 7)->nullable()->after('captured_at');
            $table->decimal('pos_a_lng', 10, 7)->nullable()->after('pos_a_lat');
            $table->decimal('pos_a_accuracy', 8, 2)->nullable()->after('pos_a_lng');
            $table->timestamp('pos_a_captured_at')->nullable()->after('pos_a_accuracy');
            $table->boolean('posisi_mencurigakan')->default(false)->after('pos_a_captured_at');
        });
    }

    public function down(): void
    {
        Schema::table('presensi', function (Blueprint $table) {
            $table->dropColumn(['pos_a_lat', 'pos_a_lng', 'pos_a_accuracy', 'pos_a_captured_at', 'posisi_mencurigakan']);
        });
    }
};
