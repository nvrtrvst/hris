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
        Schema::table('reminders', function (Blueprint $table) {
            $table->timestamp('next_run_at')->nullable()->after('sent_at');
            $table->json('recurring_days')->nullable()->after('recurring_schedule');
            $table->time('recurring_time')->nullable()->after('recurring_days');
        });
    }

    public function down(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->dropColumn(['next_run_at', 'recurring_days', 'recurring_time']);
        });
    }
};
