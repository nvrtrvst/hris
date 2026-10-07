<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\IzinBaru;
use App\Notifications\PresensiReminder;
use App\Notifications\ReminderPush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PrunePresensiReminderNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function insertNotification(string $type, int $daysAgo): void
    {
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => $type,
            'notifiable_type' => User::class,
            'notifiable_id' => 1,
            'data' => json_encode([]),
            'read_at' => null,
            'created_at' => now()->subDays($daysAgo),
            'updated_at' => now()->subDays($daysAgo),
        ]);
    }

    public function test_prune_hapus_pengingat_presensi_lebih_tua_dari_7_hari(): void
    {
        $this->insertNotification(ReminderPush::class, 8);
        $this->insertNotification(PresensiReminder::class, 10);
        $this->insertNotification(ReminderPush::class, 1);

        $this->artisan('notification:prune-presensi-reminder')->assertExitCode(0);

        $remaining = DB::table('notifications')
            ->whereIn('type', [ReminderPush::class, PresensiReminder::class])
            ->pluck('type')
            ->all();

        $this->assertSame([ReminderPush::class], $remaining);
    }

    public function test_prune_tidak_menyentuh_type_notifikasi_lain(): void
    {
        $this->insertNotification(IzinBaru::class, 40);
        $this->insertNotification(ReminderPush::class, 8);

        $this->artisan('notification:prune-presensi-reminder')->assertExitCode(0);

        $this->assertSame(
            [IzinBaru::class],
            DB::table('notifications')->pluck('type')->all()
        );
    }
}
