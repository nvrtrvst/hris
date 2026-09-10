<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollReferenceType extends Model
{
    protected $table = 'payroll_reference_types';

    protected $fillable = [
        'kode',
        'nama',
        'source_field',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function values()
    {
        return $this->hasMany(PayrollReferenceValue::class);
    }
}
