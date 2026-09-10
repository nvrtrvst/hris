<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class PayrollReferenceType extends Model
{
    use Auditable;

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
