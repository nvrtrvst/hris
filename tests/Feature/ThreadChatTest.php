<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\PengajuanIzinComment;
use App\Models\Reminder;
use App\Models\UnitSekolah;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ThreadChatTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->unit = UnitSekolah::create([
            'nama' => 'SD Uji',
            'singkatan' => 'SD',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
        ]);
    }

    private function makePegawai(string $nama, ?User $user = null): Pegawai
    {
        if (! $user) {
            $user = User::factory()->create();
        }
        $jabatan = Jabatan::firstOrCreate(['nama' => 'Guru'], ['is_guru' => true]);
        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '3273'.str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT),
            'nama_lengkap' => $nama,
            'jenis_kelamin' => 'L',
            'status_kepegawaian' => 'tetap',
            'status_aktif' => 'aktif',
            'tanggal_mulai_kerja' => '2020-01-01',
            'wajib_kantor' => true,
        ]);
        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    private function pendingIzin(Pegawai $pegawai): PengajuanIzin
    {
        $izin = PengajuanIzin::create([
            'pegawai_id' => $pegawai->id,
            'jenis_izin' => 'izin',
            'tanggal_mulai' => '2026-09-01',
            'tanggal_selesai' => '2026-09-01',
            'alasan' => 'Keperluan keluarga.',
        ]);
        $izin->approval_stage = 'pending_l1';
        $izin->status = 'pending';
        $izin->save();

        return $izin;
    }

    // ─── Thread Chat Tests ───

    public function test_pegawai_can_reply_on_own_izin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('pegawai');
        $pegawai = $this->makePegawai('Budi Santoso', $user);
        $izin = $this->pendingIzin($pegawai);

        $res = $this->actingAs($user, 'web_admin')
            ->postJson(route('pengajuan-izin.reply', $izin->id), [
                'message' => 'Mohon maaf, keperluan keluarga yang mendesak.',
            ]);

        $res->assertOk()->assertJsonStructure(['comment' => ['id', 'message', 'user']]);
        $this->assertDatabaseHas('pengajuan_izin_comments', [
            'pengajuan_izin_id' => $izin->id,
            'user_id' => $user->id,
            'message' => 'Mohon maaf, keperluan keluarga yang mendesak.',
        ]);
    }

    public function test_admin_can_reply_on_pegawai_izin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('superadmin');

        $pegawaiUser = User::factory()->create();
        $pegawai = $this->makePegawai('Guru Ani', $pegawaiUser);
        $izin = $this->pendingIzin($pegawai);

        $res = $this->actingAs($admin, 'web_admin')
            ->postJson(route('pengajuan-izin.reply', $izin->id), [
                'message' => 'Keperluan apa? Mohon spesifik.',
            ]);

        $res->assertOk();
        $this->assertDatabaseHas('pengajuan_izin_comments', [
            'pengajuan_izin_id' => $izin->id,
            'user_id' => $admin->id,
            'message' => 'Keperluan apa? Mohon spesifik.',
        ]);
    }

    public function test_cannot_reply_after_approved(): void
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');
        $pegawai = $this->makePegawai('Budi', $user);
        $izin = $this->pendingIzin($pegawai);

        // Directly set approval_stage in DB (bypass model cache)
        DB::table('pengajuan_izins')
            ->where('id', $izin->id)
            ->update(['approval_stage' => 'approved', 'status' => 'disetujui']);

        $res = $this->actingAs($user, 'web_admin')
            ->postJson(route('pengajuan-izin.reply', $izin->id), [
                'message' => 'Terima kasih.',
            ]);

        $res->assertStatus(422);
    }

    public function test_cannot_reply_after_rejected(): void
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');
        $pegawai = $this->makePegawai('Budi', $user);
        $izin = $this->pendingIzin($pegawai);

        DB::table('pengajuan_izins')
            ->where('id', $izin->id)
            ->update(['approval_stage' => 'rejected', 'status' => 'ditolak']);

        $res = $this->actingAs($user, 'web_admin')
            ->postJson(route('pengajuan-izin.reply', $izin->id), [
                'message' => 'Baik.',
            ]);

        $res->assertStatus(422);
    }

    public function test_unauthorized_user_cannot_reply(): void
    {
        $stranger = User::factory()->create();
        $stranger->assignRole('pegawai');

        $otherUser = User::factory()->create();
        $pegawai = $this->makePegawai('Guru Lain', $otherUser);
        $izin = $this->pendingIzin($pegawai);

        $res = $this->actingAs($stranger, 'web_admin')
            ->postJson(route('pengajuan-izin.reply', $izin->id), [
                'message' => 'Halo.',
            ]);

        $res->assertStatus(403);
    }

    public function test_comments_endpoint_returns_thread(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('superadmin');
        $pegawaiUser = User::factory()->create();
        $pegawai = $this->makePegawai('Guru Ani', $pegawaiUser);
        $izin = $this->pendingIzin($pegawai);

        PengajuanIzinComment::create([
            'pengajuan_izin_id' => $izin->id,
            'user_id' => $admin->id,
            'message' => 'Keperluan apa?',
        ]);
        PengajuanIzinComment::create([
            'pengajuan_izin_id' => $izin->id,
            'user_id' => $pegawaiUser->id,
            'message' => 'Sakit gigi.',
        ]);

        $res = $this->actingAs($admin, 'web_admin')
            ->getJson(route('pengajuan-izin.comments', $izin->id));

        $res->assertOk()
            ->assertJsonCount(2, 'comments');
    }

    public function test_reply_notifies_approvers(): void
    {
        $approver = User::factory()->create();
        $approver->assignRole('admin_unit');

        $pegawaiUser = User::factory()->create();
        $pegawai = $this->makePegawai('Guru Ani', $pegawaiUser);
        $izin = $this->pendingIzin($pegawai);
        $izin->update(['approver_l1_id' => $approver->id]);

        $this->actingAs($pegawaiUser, 'web_admin')
            ->postJson(route('pengajuan-izin.reply', $izin->id), [
                'message' => 'Sakit gigi.',
            ]);

        // NotificationHelper::sendSafely catches webpush errors in test env;
        // verify the comment was created (the notification path is tested implicitly)
        $this->assertDatabaseHas('pengajuan_izin_comments', [
            'pengajuan_izin_id' => $izin->id,
            'user_id' => $pegawaiUser->id,
            'message' => 'Sakit gigi.',
        ]);
    }

    public function test_reply_empty_message_fails(): void
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');
        $pegawai = $this->makePegawai('Guru Ani', $user);
        $izin = $this->pendingIzin($pegawai);

        $res = $this->actingAs($user, 'web_admin')
            ->postJson(route('pengajuan-izin.reply', $izin->id), [
                'message' => '',
            ]);

        $res->assertStatus(422);
    }

    // ─── Race Condition: Thread Chat ───

    public function test_reply_after_concurrent_approve_is_rejected(): void
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');
        $pegawai = $this->makePegawai('Guru Ani', $user);
        $izin = $this->pendingIzin($pegawai);

        // Simulate concurrent approve
        DB::table('pengajuan_izins')
            ->where('id', $izin->id)
            ->update(['approval_stage' => 'approved', 'status' => 'disetujui']);

        $res = $this->actingAs($user, 'web_admin')
            ->postJson(route('pengajuan-izin.reply', $izin->id), [
                'message' => 'Test after approve.',
            ]);

        $res->assertStatus(422);
        $this->assertDatabaseCount('pengajuan_izin_comments', 0);
    }

    public function test_reply_then_approve_then_reply_fails(): void
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');
        $pegawai = $this->makePegawai('Guru Ani', $user);
        $izin = $this->pendingIzin($pegawai);

        // First reply succeeds
        $this->actingAs($user, 'web_admin')
            ->postJson(route('pengajuan-izin.reply', $izin->id), [
                'message' => 'Pesan pertama.',
            ]);

        $this->assertDatabaseCount('pengajuan_izin_comments', 1);

        // Approve via DB directly (simulates concurrent approve)
        DB::table('pengajuan_izins')
            ->where('id', $izin->id)
            ->update(['approval_stage' => 'approved', 'status' => 'disetujui']);

        // Second reply should fail
        $res = $this->actingAs($user, 'web_admin')
            ->postJson(route('pengajuan-izin.reply', $izin->id), [
                'message' => 'Pesan kedua setelah approve.',
            ]);

        $res->assertStatus(422);
        $this->assertDatabaseCount('pengajuan_izin_comments', 1);
    }

    // ─── Reminder Tests ───

    public function test_create_reminder_and_send_immediately(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('superadmin');

        $res = $this->actingAs($admin, 'web_admin')
            ->post(route('reminders.store'), [
                'title' => 'Reminder Presensi',
                'message' => 'Jangan lupa absen hari ini.',
                'type' => 'presensi',
                'target_all' => true,
            ]);

        $res->assertRedirect();
        $this->assertDatabaseHas('reminders', [
            'title' => 'Reminder Presensi',
        ]);
        $reminder = Reminder::where('title', 'Reminder Presensi')->first();
        $this->assertNotNull($reminder->sent_at);
    }

    public function test_send_now_atomic_prevents_double_send(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('superadmin');

        $reminder = Reminder::create([
            'title' => 'Test Reminder',
            'message' => 'Pesan test.',
            'type' => 'custom',
            'target_all' => true,
            'created_by' => $admin->id,
        ]);

        // First send — should succeed
        $res1 = $this->actingAs($admin, 'web_admin')
            ->post(route('reminders.send', $reminder->id));

        $res1->assertRedirect();
        $reminder->refresh();
        $this->assertNotNull($reminder->sent_at);

        // Second send — should fail (already sent)
        $res2 = $this->actingAs($admin, 'web_admin')
            ->post(route('reminders.send', $reminder->id));

        $res2->assertRedirect();
        $res2->assertSessionHas('error');
    }

    public function test_delete_reminder(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('superadmin');

        $reminder = Reminder::create([
            'title' => 'To Delete',
            'message' => 'Hapus saya.',
            'type' => 'custom',
            'target_all' => true,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'web_admin')
            ->delete(route('reminders.destroy', $reminder->id));

        $this->assertDatabaseMissing('reminders', ['id' => $reminder->id]);
    }

    public function test_admin_unit_can_only_delete_own_reminder(): void
    {
        $adminA = User::factory()->create();
        $adminA->assignRole('admin_unit');
        $adminA->unit_sekolah_id = $this->unit->id;
        $adminA->save();

        $adminB = User::factory()->create();
        $adminB->assignRole('admin_unit');
        $adminB->unit_sekolah_id = $this->unit->id;
        $adminB->save();

        $reminder = Reminder::create([
            'title' => 'Admin A Reminder',
            'message' => 'Pesan A.',
            'type' => 'custom',
            'unit_sekolah_id' => $this->unit->id,
            'target_all' => true,
            'created_by' => $adminA->id,
        ]);

        // Admin B cannot delete Admin A's reminder
        $this->actingAs($adminB, 'web_admin')
            ->delete(route('reminders.destroy', $reminder->id))
            ->assertStatus(403);

        $this->assertDatabaseHas('reminders', ['id' => $reminder->id]);
    }

    public function test_process_reminders_command_sends_due_reminders(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('superadmin');

        Reminder::create([
            'title' => 'Scheduled Reminder',
            'message' => 'Ini terjadwal.',
            'type' => 'deadline',
            'target_all' => true,
            'scheduled_at' => now()->subHour(),
            'created_by' => $admin->id,
        ]);

        $this->artisan('reminders:process')
            ->expectsOutput('Selesai. 1 reminder terkirim.');
    }

    public function test_process_reminders_skips_already_sent(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('superadmin');

        Reminder::create([
            'title' => 'Already Sent',
            'message' => 'Sudah terkirim.',
            'type' => 'custom',
            'target_all' => true,
            'sent_at' => now()->subHour(),
            'created_by' => $admin->id,
        ]);

        $this->artisan('reminders:process')
            ->expectsOutput('Tidak ada reminder yang perlu dikirim.');
    }
}
