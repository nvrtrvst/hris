<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengajuan_izin_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_izin_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('message');
            $table->timestamps();

            $table->index('pengajuan_izin_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengajuan_izin_comments');
    }
};
