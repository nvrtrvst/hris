<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiEvaluationItem extends Model
{
    use Auditable;

    protected $fillable = [
        'kpi_evaluation_id', 'kpi_indicator_id', 'nama_snapshot', 'deskripsi_snapshot', 'jenis_snapshot',
        'sumber_snapshot', 'source_key_snapshot', 'bobot_snapshot', 'target_snapshot', 'arah_snapshot', 'satuan_snapshot', 'wajib_bukti_snapshot',
        'nilai_aktual', 'level_rubrik', 'rubrik_snapshot', 'nilai', 'bukti', 'catatan', 'tidak_berlaku', 'alasan_tidak_berlaku',
    ];

    protected function casts(): array
    {
        return [
            'bobot_snapshot' => 'decimal:2',
            'target_snapshot' => 'decimal:2',
            'nilai_aktual' => 'decimal:2',
            'nilai' => 'decimal:2',
            'rubrik_snapshot' => 'array',
            'wajib_bukti_snapshot' => 'boolean',
            'tidak_berlaku' => 'boolean',
        ];
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(KpiEvaluation::class, 'kpi_evaluation_id');
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(KpiIndicator::class, 'kpi_indicator_id');
    }
}
