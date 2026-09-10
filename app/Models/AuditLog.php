<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'data_lama' => 'array',
        'data_baru' => 'array',
    ];

    public function auditable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function log(Model $model, string $aksi, ?array $lama, ?array $baru): ?static
    {
        try {
            return static::create([
                'auditable_type' => get_class($model),
                'auditable_id' => $model->getKey(),
                'user_id' => auth()->id(),
                'aksi' => $aksi,
                'data_lama' => $lama,
                'data_baru' => $baru,
            ]);
        } catch (\Throwable) {
            return null;
        }
    }
}
