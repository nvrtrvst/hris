<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'username', 'password', 'role', 'unit_sekolah_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    // Guard Spatie HARUS cocok dengan guard_name di tabel permissions & roles.
    // Permissions di-seed dengan guard 'web', bukan 'web_admin'/'web_mobile'.
    // Sesuaikan agar $user->can('...') bisa resolve permission dengan benar.
    protected $guard_name = 'web';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function unitSekolah()
    {
        return $this->belongsTo(UnitSekolah::class);
    }

    public function pegawai()
    {
        return $this->hasOne(Pegawai::class);
    }
}
