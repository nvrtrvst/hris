<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollReferenceValue extends Model
{
    protected $table = 'payroll_reference_values';

    protected $fillable = [
        'komponen_gaji_id',
        'payroll_reference_type_id',
        'reference_key',
        'nominal',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
    ];

    public function komponenGaji()
    {
        return $this->belongsTo(KomponenGaji::class);
    }

    public function referenceType()
    {
        return $this->belongsTo(PayrollReferenceType::class, 'payroll_reference_type_id');
    }
}
