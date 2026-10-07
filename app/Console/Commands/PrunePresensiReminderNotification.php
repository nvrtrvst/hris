<?php

namespace App\Console\Commands;

use App\Notifications\PresensiReminder;
use App\Notifications\ReminderPush;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PrunePresensiReminderNotification extends Command
{
    protected $signature = 'notification:prune-presensi-reminder {--days=7 : Hapus notifikasi lebih tua dari N hari}';

    protected $description = 'Hapus notifikasi pengingat presensi (ReminderPush/PresensiReminder) kadaluarsa — type lain tidak tersentuh';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));

        $deleted = DB::table('notifications')
            ->whereIn('type', [ReminderPush::class, PresensiReminder::class])
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        $this->info("{$deleted} notifikasi pengingat presensi dihapus (lebih tua dari {$days} hari).");

        return self::SUCCESS;
    }
}
