<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_policies', function (Blueprint $table) {
            $table->id();
            $table->string('jenis', 30)->unique();
            $table->unsignedTinyInteger('levels')->default(1);
            $table->unsignedTinyInteger('second_level_min_days')->nullable();
            $table->unsignedTinyInteger('sla_days')->default(1);
            $table->boolean('tembusan_yayasan')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_policies');
    }
};
