<?php

namespace App\Console\Commands;

use App\Helpers\NotificationHelper;
use App\Models\Reminder;
use App\Models\User;
use App\Notifications\ReminderPush;
use Illuminate\Console\Command;

class ProcessReminders extends Command
{
    protected $signature = 'reminders:process';

    protected $description = 'Kirim reminder yang sudah jatuh tempo (scheduled_at <= now, belum terkirim)';

    public function handle(): int
    {
        $due = Reminder::due()->get();

        if ($due->isEmpty()) {
            $this->info('Tidak ada reminder yang perlu dikirim.');

            return self::SUCCESS;
        }

        $sent = 0;
        foreach ($due as $reminder) {
            // Atomic: claim send slot — prevents double-send from concurrent cron runs
            $claimed = Reminder::where('id', $reminder->id)
                ->whereNull('sent_at')
                ->update(['sent_at' => now()]);

            if (! $claimed) {
                continue;
            }

            $this->sendNotifications($reminder);
            $sent++;
        }

        $this->info("Selesai. {$sent} reminder terkirim.");

        return self::SUCCESS;
    }

    private function sendNotifications(Reminder $reminder): void
    {
        $query = User::whereHas('pegawai', function ($q) use ($reminder) {
            $q->where('status_aktif', 'aktif')
                ->when($reminder->target_all && $reminder->unit_sekolah_id, function ($uq) use ($reminder) {
                    $uq->forUnit($reminder->unit_sekolah_id);
                });
        });

        if (! $reminder->target_all && ! empty($reminder->target_user_ids)) {
            $query->whereIn('id', $reminder->target_user_ids);
        }

        $users = $query->get();
        foreach ($users as $targetUser) {
            NotificationHelper::sendSafely($targetUser, new ReminderPush($reminder));
        }
    }
}
