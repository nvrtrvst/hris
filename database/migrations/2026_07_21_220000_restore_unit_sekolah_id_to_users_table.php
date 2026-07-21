<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'unit_sekolah_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('unit_sekolah_id')
                    ->nullable()
                    ->after('email')
                    ->constrained('unit_sekolah')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'unit_sekolah_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('unit_sekolah_id');
            });
        }
    }
};
