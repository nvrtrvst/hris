<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $fillable = [
        'title',
        'body',
        'unit_sekolah_id',
        'is_pinned',
        'published_at',
        'created_by',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function unitSekolah()
    {
        return $this->belongsTo(UnitSekolah::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope unit: null = semua unit; selain itu harus match unit id.
     */
    public function scopeForUnit(Builder $query, ?int $unitId): Builder
    {
        if ($unitId) {
            return $query->where(fn ($q) => $q->whereNull('unit_sekolah_id')->orWhere('unit_sekolah_id', $unitId));
        }

        return $query->whereNull('unit_sekolah_id');
    }

    /**
     * Yang tampil di mobile: sudah published (waktu publish <= now).
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }
}
