<?php

namespace App\Models;

use Carbon\Carbon;
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
        'recurring_days',
        'recurring_time',
        'scheduled_at',
        'next_run_at',
        'sent_at',
        'created_by',
    ];

    protected $casts = [
        'target_user_ids' => 'array',
        'is_recurring' => 'boolean',
        'target_all' => 'boolean',
        'recurring_days' => 'array',
        'scheduled_at' => 'datetime',
        'next_run_at' => 'datetime',
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
     * Scope: reminders yang perlu dikirim.
     * One-shot: sent_at NULL + scheduled_at sudah lewat.
     * Recurring: next_run_at sudah lewat.
     */
    public function scopeDue($query)
    {
        return $query->where(function ($q) {
            // One-shot: belum terkirim + scheduled_at sudah lewat
            $q->where(function ($sq) {
                $sq->whereNull('is_recurring')
                    ->orWhere('is_recurring', false);
            })->whereNull('sent_at')
                ->where(function ($sq) {
                    $sq->whereNull('scheduled_at')
                        ->orWhere('scheduled_at', '<=', now());
                });
        })->orWhere(function ($q) {
            // Recurring: next_run_at sudah lewat
            $q->where('is_recurring', true)
                ->whereNotNull('next_run_at')
                ->where('next_run_at', '<=', now());
        });
    }

    /**
     * Hitung next_run_at berdasarkan recurring_days + recurring_time.
     */
    public function getNextRunAt(): ?Carbon
    {
        if (! $this->is_recurring || empty($this->recurring_days) || ! $this->recurring_time) {
            return null;
        }

        $time = Carbon::parse($this->recurring_time);
        $now = Carbon::now();
        $days = collect($this->recurring_days)->sort()->values();

        if ($this->recurring_schedule === 'daily') {
            for ($i = 1; $i <= 7; $i++) {
                $next = $now->copy()->addDays($i);
                if ($days->contains($next->dayOfWeekIso)) {
                    return $next->setTime($time->hour, $time->minute, 0);
                }
            }
        } elseif ($this->recurring_schedule === 'weekly') {
            for ($i = 7; $i <= 14; $i++) {
                $next = $now->copy()->addDays($i);
                if ($days->contains($next->dayOfWeekIso)) {
                    return $next->setTime($time->hour, $time->minute, 0);
                }
            }
        } elseif ($this->recurring_schedule === 'monthly') {
            $next = $now->copy()->addMonthNoOverflow()->day(min($now->day, 28));

            return $next->setTime($time->hour, $time->minute, 0);
        }

        return null;
    }

    /**
     * Tandai terkirim + set next_run_at untuk recurring.
     */
    public function markSentAndReschedule(): void
    {
        if ($this->is_recurring) {
            $this->update(['next_run_at' => $this->getNextRunAt()]);
        } else {
            $this->update(['sent_at' => now()]);
        }
    }
}
