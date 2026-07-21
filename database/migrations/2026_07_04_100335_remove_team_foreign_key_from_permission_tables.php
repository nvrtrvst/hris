<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Kolom team (unit_sekolah_id) pada tabel Spatie permission hanya ada
        // saat fitur Teams aktif. Skip semua operasi jika kolom belum ada.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasColumn('roles', 'unit_sekolah_id')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropIndex('roles_team_foreign_key_index');
                $table->dropColumn('unit_sekolah_id');
                $table->dropUnique(['unit_sekolah_id', 'name', 'guard_name']);
                $table->unique(['name', 'guard_name']);
            });
        }

        if (Schema::hasColumn('model_has_permissions', 'unit_sekolah_id')) {
            Schema::table('model_has_permissions', function (Blueprint $table) {
                $table->dropPrimary('model_has_permissions_permission_model_type_primary');
                $table->dropIndex('model_has_permissions_team_foreign_key_index');
                $table->dropColumn('unit_sekolah_id');
                $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
            });
        }

        if (Schema::hasColumn('model_has_roles', 'unit_sekolah_id')) {
            Schema::table('model_has_roles', function (Blueprint $table) {
                $table->dropPrimary('model_has_roles_role_model_type_primary');
                $table->dropIndex('model_has_roles_team_foreign_key_index');
                $table->dropColumn('unit_sekolah_id');
                $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
