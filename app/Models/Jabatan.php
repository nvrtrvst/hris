<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Jabatan extends Model
{
    protected $table = 'jabatan';

    protected $fillable = ['nama', 'is_guru'];

    protected function casts(): array
    {
        return [
            'is_guru' => 'boolean',
        ];
    }

    public function pegawai(): BelongsToMany
    {
        return $this->belongsToMany(Pegawai::class, 'pegawai_unit')
            ->withPivot('unit_sekolah_id', 'is_primary')
            ->withTimestamps();
    }
}
