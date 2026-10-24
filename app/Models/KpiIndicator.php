<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiIndicator extends Model
{
    use Auditable;

    protected $fillable = [
        'kpi_template_id', 'kode', 'nama', 'deskripsi', 'jenis', 'sumber', 'source_key',
        'target', 'arah', 'bobot', 'satuan', 'rubrik', 'wajib_bukti', 'urutan', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'target' => 'decimal:2',
            'bobot' => 'decimal:2',
            'rubrik' => 'array',
            'wajib_bukti' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(KpiTemplate::class, 'kpi_template_id');
    }
}
