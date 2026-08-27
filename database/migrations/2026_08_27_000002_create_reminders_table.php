<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('message');
            $table->string('type')->default('custom'); // presensi|cuti|deadline|custom
            $table->foreignId('unit_sekolah_id')->nullable()->constrained('unit_sekolah')->nullOnDelete();
            $table->boolean('target_all')->default(false); // true = semua pegawai aktif
            $table->json('target_user_ids')->nullable(); // array of user IDs (jika target_all=false)
            $table->boolean('is_recurring')->default(false);
            $table->string('recurring_schedule')->nullable(); // daily|weekly|monthly
            $table->timestamp('scheduled_at')->nullable(); // waktu kirim (one-shot)
            $table->timestamp('sent_at')->nullable(); // waktu terkirim
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('type');
            $table->index('scheduled_at');
            $table->index('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
