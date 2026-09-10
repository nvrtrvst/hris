<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_reference_types', function (Blueprint $t) {
            $t->id();
            $t->string('kode', 50)->unique();
            $t->string('nama');
            $t->string('source_field', 50);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('payroll_reference_values', function (Blueprint $t) {
            $t->id();
            $t->foreignId('komponen_gaji_id')->constrained('komponen_gaji')->cascadeOnDelete();
            $t->foreignId('payroll_reference_type_id')->constrained('payroll_reference_types')->cascadeOnDelete();
            $t->string('reference_key', 100);
            $t->decimal('nominal', 15, 2)->default(0);
            $t->timestamps();
            $t->unique(['komponen_gaji_id', 'payroll_reference_type_id', 'reference_key'], 'prv_unique_lookup');
        });

        Schema::table('komponen_gaji', function (Blueprint $t) {
            $t->foreignId('payroll_reference_type_id')
                ->nullable()
                ->after('jenis')
                ->constrained('payroll_reference_types');
        });
    }

    public function down(): void
    {
        Schema::table('komponen_gaji', function (Blueprint $t) {
            $t->dropConstrainedForeignId('payroll_reference_type_id');
        });
        Schema::dropIfExists('payroll_reference_values');
        Schema::dropIfExists('payroll_reference_types');
    }
};
