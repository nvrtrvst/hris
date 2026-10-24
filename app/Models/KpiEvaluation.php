<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KpiEvaluation extends Model
{
    use Auditable;

    protected $fillable = [
        'pegawai_id', 'unit_sekolah_id', 'jabatan_id', 'kpi_template_id',
        'periode_mulai', 'periode_selesai', 'status', 'nilai_akhir', 'ringkasan',
        'tanggapan_pegawai', 'ditanggapi_oleh', 'ditanggapi_pada',
        'dinilai_oleh', 'difinalisasi_oleh', 'difinalisasi_pada',
    ];

    protected function casts(): array
    {
        return [
            'periode_mulai' => 'date:Y-m-d',
            'periode_selesai' => 'date:Y-m-d',
            'nilai_akhir' => 'decimal:2',
            'difinalisasi_pada' => 'datetime',
            'ditanggapi_pada' => 'datetime',
        ];
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function unitSekolah(): BelongsTo
    {
        return $this->belongsTo(UnitSekolah::class);
    }

    public function jabatan(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(KpiTemplate::class, 'kpi_template_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(KpiEvaluationItem::class);
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dinilai_oleh');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'difinalisasi_oleh');
    }
}
