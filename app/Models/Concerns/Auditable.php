<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;

trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(function ($model) {
            AuditLog::log($model, 'create', null, $model->toArray());
        });

        static::updated(function ($model) {
            $dirty = $model->getDirty();
            $original = $model->getOriginal();

            $changedLama = [];
            $changedBaru = [];
            foreach ($dirty as $key => $newVal) {
                $changedLama[$key] = $original[$key] ?? null;
                $changedBaru[$key] = $newVal;
            }

            if ($changedBaru) {
                AuditLog::log($model, 'update', $changedLama, $changedBaru);
            }
        });

        static::deleted(function ($model) {
            AuditLog::log($model, 'delete', $model->toArray(), null);
        });
    }
}
