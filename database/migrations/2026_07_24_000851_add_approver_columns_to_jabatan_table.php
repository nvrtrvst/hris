<?php

use App\Models\Jabatan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jabatan', function (Blueprint $table) {
            $table->foreignId('approver_l1_jabatan_id')->nullable()->constrained('jabatan')->nullOnDelete()->after('is_guru');
            $table->foreignId('approver_l2_jabatan_id')->nullable()->constrained('jabatan')->nullOnDelete()->after('approver_l1_jabatan_id');
        });

        Jabatan::where('nama', 'Tata Usaha')->update([
            'approver_l1_jabatan_id' => Jabatan::where('nama', 'Kepala Tata Usaha')->value('id'),
            'approver_l2_jabatan_id' => Jabatan::where('nama', 'Kepala Sekolah')->value('id'),
        ]);
        Jabatan::where('nama', 'Staff Keamanan')->update([
            'approver_l1_jabatan_id' => Jabatan::where('nama', 'Kepala Tata Usaha')->value('id'),
            'approver_l2_jabatan_id' => Jabatan::where('nama', 'Kepala Sekolah')->value('id'),
        ]);
        Jabatan::where('nama', 'Staff Kebersihan')->update([
            'approver_l1_jabatan_id' => Jabatan::where('nama', 'Kepala Tata Usaha')->value('id'),
            'approver_l2_jabatan_id' => Jabatan::where('nama', 'Kepala Sekolah')->value('id'),
        ]);
        Jabatan::where('nama', 'Guru')->update([
            'approver_l1_jabatan_id' => Jabatan::where('nama', 'Kepala Sekolah')->value('id'),
        ]);
        Jabatan::where('nama', 'Wakil Kepala Sekolah')->update([
            'approver_l1_jabatan_id' => Jabatan::where('nama', 'Kepala Sekolah')->value('id'),
        ]);
        Jabatan::where('nama', 'Kepala Tata Usaha')->update([
            'approver_l1_jabatan_id' => Jabatan::where('nama', 'Kepala Sekolah')->value('id'),
        ]);
    }

    public function down(): void
    {
        Schema::table('jabatan', function (Blueprint $table) {
            $table->dropForeign(['approver_l2_jabatan_id']);
            $table->dropForeign(['approver_l1_jabatan_id']);
            $table->dropColumn(['approver_l1_jabatan_id', 'approver_l2_jabatan_id']);
        });
    }
};
