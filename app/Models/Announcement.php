<?php

namespace App\Models;

use App\Helpers\FileHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

class Announcement extends Model
{
    protected $fillable = [
        'title',
        'body',
        'image',
        'unit_sekolah_id',
        'is_pinned',
        'published_at',
        'created_by',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'published_at' => 'datetime',
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? FileHelper::fotoUrl($this->image) : null;
    }

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

    /**
     * Pegawai yang sudah menandai pengumuman ini terbaca (pivot announcement_pegawai).
     */
    public function readers(): BelongsToMany
    {
        return $this->belongsToMany(Pegawai::class)->withPivot('read_at')->withTimestamps();
    }

    /**
     * Hanya pengumuman yang BELUM dibaca pegawai tertentu (badge count).
     */
    public function scopeUnreadFor(Builder $query, ?Pegawai $pegawai): Builder
    {
        if (! $pegawai) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereDoesntHave('readers', fn ($q) => $q->where('pegawai.id', $pegawai->id));
    }

    /**
     * Tandai satu pengumuman terbaca oleh pegawai (upsert pivot).
     */
    public function markReadBy(Pegawai $pegawai): void
    {
        $this->readers()->syncWithoutDetaching([$pegawai->id => ['read_at' => now()]]);
    }

    /**
     * Tandai banyak pengumuman terbaca sekaligus — 1 query upsert, tanpa N+1.
     */
    public static function markReadBatch(array $ids, Pegawai $pegawai): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return;
        }

        $now = now();
        DB::table('announcement_pegawai')->upsert(
            array_map(fn ($id) => [
                'announcement_id' => $id,
                'pegawai_id' => $pegawai->id,
                'read_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ], $ids),
            ['announcement_id', 'pegawai_id'],
            ['read_at', 'updated_at']
        );
    }
}
