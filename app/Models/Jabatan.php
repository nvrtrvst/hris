<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Jabatan extends Model
{
    protected $table = 'jabatan';

    protected $fillable = ['nama', 'is_guru', 'approver_l1_jabatan_id', 'approver_l2_jabatan_id'];

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

    public function approverL1(): BelongsTo
    {
        return $this->belongsTo(self::class, 'approver_l1_jabatan_id');
    }

    public function approverL2(): BelongsTo
    {
        return $this->belongsTo(self::class, 'approver_l2_jabatan_id');
    }
}
