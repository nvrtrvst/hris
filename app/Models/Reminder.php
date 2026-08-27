<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'message',
        'type',
        'unit_sekolah_id',
        'target_all',
        'target_user_ids',
        'is_recurring',
        'recurring_schedule',
        'scheduled_at',
        'sent_at',
        'created_by',
    ];

    protected $casts = [
        'target_user_ids' => 'array',
        'is_recurring' => 'boolean',
        'target_all' => 'boolean',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
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
     * Scope: reminders yang perlu dikirim (scheduled_at <= now, belum terkirim)
     */
    public function scopeDue($query)
    {
        return $query->where('sent_at', null)
            ->where(function ($q) {
                $q->whereNull('scheduled_at')
                    ->orWhere('scheduled_at', '<=', now());
            });
    }
}
