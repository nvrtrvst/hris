<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ApprovalPolicy extends Model
{
    protected $fillable = [
        'jenis',
        'levels',
        'second_level_min_days',
        'sla_days',
        'tembusan_yayasan',
        'is_active',
    ];

    protected $casts = [
        'levels' => 'int',
        'second_level_min_days' => 'int',
        'sla_days' => 'int',
        'tembusan_yayasan' => 'bool',
        'is_active' => 'bool',
    ];

    public static function for(string $jenis): self
    {
        $key = config('approval.policy_keys')[$jenis] ?? $jenis;

        return Cache::rememberForever("approval_policy:{$key}", function () use ($key) {
            return self::where('jenis', $key)->where('is_active', true)->first()
                ?? new self([
                    'jenis' => $key,
                    'levels' => 1,
                    'sla_days' => 1,
                    'tembusan_yayasan' => true,
                ]);
        });
    }

    protected static function booted(): void
    {
        static::saved(fn (self $policy) => Cache::forget("approval_policy:{$policy->jenis}"));
        static::deleted(fn (self $policy) => Cache::forget("approval_policy:{$policy->jenis}"));
    }
}
