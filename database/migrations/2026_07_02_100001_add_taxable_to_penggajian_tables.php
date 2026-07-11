<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penggajian_detail', function (Blueprint $table) {
            $table->boolean('is_taxable')->default(false)->after('nominal');
        });

        Schema::table('penggajian', function (Blueprint $table) {
            $table->decimal('total_taxable', 15, 2)->default(0)->after('gaji_bersih');
        });
    }

    public function down(): void
    {
        Schema::table('penggajian_detail', function (Blueprint $table) {
            $table->dropColumn('is_taxable');
        });

        Schema::table('penggajian', function (Blueprint $table) {
            $table->dropColumn('total_taxable');
        });
    }
};
