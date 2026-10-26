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

        // Cache hanya skalar; model tidak diserialisasi agar tidak jadi __PHP_Incomplete_Class.
        $row = Cache::rememberForever("approval_policy:{$key}", function () use ($key) {
            $policy = self::where('jenis', $key)->where('is_active', true)->first();

            return $policy?->only(['jenis', 'levels', 'second_level_min_days', 'sla_days', 'tembusan_yayasan'])
                ?? [
                    'jenis' => $key,
                    'levels' => 1,
                    'second_level_min_days' => null,
                    'sla_days' => 1,
                    'tembusan_yayasan' => true,
                ];
        });

        return new self($row);
    }

    protected static function booted(): void
    {
        static::saved(fn (self $policy) => Cache::forget("approval_policy:{$policy->jenis}"));
        static::deleted(fn (self $policy) => Cache::forget("approval_policy:{$policy->jenis}"));
    }
}
