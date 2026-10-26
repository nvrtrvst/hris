<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengajuan_izins', function (Blueprint $table) {
            $table->dateTime('due_at')->nullable()->after('approved_at_l2');
            $table->dateTime('escalated_at')->nullable()->after('due_at');
            $table->dateTime('reminder_sent_at')->nullable()->after('escalated_at');

            // Nama index dipendekkan: batas identifier MySQL 64 karakter.
            $table->index(['approval_stage', 'approver_l1_id'], 'izin_stage_l1_idx');
            $table->index(['approval_stage', 'approver_l2_id'], 'izin_stage_l2_idx');
            $table->index(['approval_stage', 'due_at'], 'izin_stage_due_idx');
        });
    }

    public function down(): void
    {
        Schema::table('pengajuan_izins', function (Blueprint $table) {
            $table->dropIndex('izin_stage_l1_idx');
            $table->dropIndex('izin_stage_l2_idx');
            $table->dropIndex('izin_stage_due_idx');
            $table->dropColumn(['due_at', 'escalated_at', 'reminder_sent_at']);
        });
    }
};
