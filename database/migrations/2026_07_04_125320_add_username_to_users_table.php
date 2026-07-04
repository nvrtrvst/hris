<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('email');
        });

        // Set default username for existing users
        // For Pegawai, use NIP or NIK. For others, use email.
        $users = \App\Models\User::all();
        foreach ($users as $user) {
            $pegawai = \App\Models\Pegawai::where('user_id', $user->id)->first();
            if ($pegawai) {
                $user->username = $pegawai->nip ?: $pegawai->nik;
            } else {
                $user->username = $user->email;
            }
            $user->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('username');
        });
    }
};
