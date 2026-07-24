<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengajuan_izins', function (Blueprint $table) {
            $table->string('approval_stage', 20)->default('pending_l1')->after('status');
            $table->foreignId('approver_l1_id')->nullable()->constrained('users')->nullOnDelete()->after('approval_stage');
            $table->foreignId('approver_l2_id')->nullable()->constrained('users')->nullOnDelete()->after('approver_l1_id');
            $table->timestamp('approved_at_l1')->nullable()->after('approver_l2_id');
            $table->timestamp('approved_at_l2')->nullable()->after('approved_at_l1');
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete()->after('approved_at_l2');
        });
    }

    public function down(): void
    {
        Schema::table('pengajuan_izins', function (Blueprint $table) {
            $table->dropForeign(['rejected_by']);
            $table->dropForeign(['approver_l2_id']);
            $table->dropForeign(['approver_l1_id']);
            $table->dropColumn(['approval_stage', 'approver_l1_id', 'approver_l2_id', 'approved_at_l1', 'approved_at_l2', 'rejected_by']);
        });
    }
};
