<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laporan_tindak_lanjut', function (Blueprint $table) {
            $table->id();
            $table->string('finding_key', 64)->unique();
            $table->foreignId('unit_sekolah_id')->constrained('unit_sekolah');
            $table->foreignId('pegawai_id')->constrained('pegawai');
            $table->date('tanggal');
            $table->string('jenis', 60);
            $table->foreignId('penanggung_jawab_id')->nullable()->constrained('users');
            $table->string('status')->default('belum_ditindaklanjuti');
            $table->text('catatan')->nullable();
            $table->string('hasil')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->json('snapshot');
            $table->json('history');
            $table->timestamps();
            $table->index(['unit_sekolah_id', 'tanggal']);
        });
        $permission = Permission::findOrCreate('manage_tindak_lanjut_laporan', 'web');
        foreach (['superadmin', 'admin_unit', 'tu_kepegawaian'] as $role) {
            Role::where('name', $role)->where('guard_name', 'web')->first()?->givePermissionTo($permission);
        }
        Role::where('name', 'tu_kepegawaian')->where('guard_name', 'web')->first()?->givePermissionTo(Permission::findOrCreate('view_presensi', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_tindak_lanjut');
        Permission::where('name', 'manage_tindak_lanjut_laporan')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
