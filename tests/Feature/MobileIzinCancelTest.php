<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\UnitSekolah;
use App\Models\User;
use App\Notifications\IzinReply;
use App\Services\PengajuanIzinService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MobileIzinCancelTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private Jabatan $jabatan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unit = UnitSekolah::create([
            'nama' => 'SMP Test',
            'singkatan' => 'SMP',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'toleransi_menit' => 0,
        ]);
        $this->jabatan = Jabatan::create(['nama' => 'Guru', 'is_guru' => true]);
    }

    private function makePegawai(?User $user = null): Pegawai
    {
        $user ??= User::factory()->create();

        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '99887766'.str_pad((string) $user->id, 8, '0', STR_PAD_LEFT),
            'nama_lengkap' => 'Pegawai Uji',
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'kawin',
            'jumlah_tanggungan' => 2,
            'alamat' => 'Jl. Test No. 1',
            'no_hp' => '081234567890',
            'status_kepegawaian' => 'guru_tetap_yayasan',
            'tmt_mengajar' => '2020-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
            'jatah_cuti_tahunan' => 12,
        ]);
        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $this->jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    private function makePengajuan(Pegawai $pegawai, array $attrs = []): PengajuanIzin
    {
        $pengajuan = PengajuanIzin::create(array_merge([
            'pegawai_id' => $pegawai->id,
            'jenis_izin' => 'izin',
            'tanggal_mulai' => '2026-12-01',
            'tanggal_selesai' => '2026-12-01',
            'alasan' => 'Keperluan keluarga',
        ], $attrs));

        $pengajuan->forceFill([
            'status' => 'pending',
            'approval_stage' => 'pending_l1',
        ])->save();

        return $pengajuan;
    }

    public function test_pegawai_can_cancel_own_pending_request(): void
    {
        $user = User::factory()->create();
        $pegawai = $this->makePegawai($user);
        $pengajuan = $this->makePengajuan($pegawai);

        $this->actingAs($user, 'web_mobile')
            ->from(route('presensi.izin.show', $pengajuan->id))
            ->post(route('presensi.izin.cancel', $pengajuan->id))
            ->assertRedirect(route('presensi.izin.show', $pengajuan->id))
            ->assertSessionHas('message', 'Pengajuan berhasil dibatalkan.');

        $pengajuan->refresh();
        $this->assertSame('dibatalkan', $pengajuan->status);
        $this->assertSame('cancelled', $pengajuan->approval_stage);
        $this->assertNotNull($pengajuan->cancelled_at);
        $this->assertDatabaseHas('pengajuan_izin_comments', [
            'pengajuan_izin_id' => $pengajuan->id,
            'user_id' => $user->id,
            'message' => 'Pengajuan dibatalkan oleh pegawai.',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => PengajuanIzin::class,
            'auditable_id' => $pengajuan->id,
            'aksi' => 'batalkan',
        ]);
    }

    public function test_pegawai_cannot_cancel_another_pegawai_request(): void
    {
        $user = User::factory()->create();
        $this->makePegawai($user);

        $otherUser = User::factory()->create();
        $otherPegawai = $this->makePegawai($otherUser);
        $pengajuan = $this->makePengajuan($otherPegawai);

        $this->actingAs($user, 'web_mobile')
            ->post(route('presensi.izin.cancel', $pengajuan->id))
            ->assertNotFound();
    }

    public function test_pegawai_can_cancel_after_l1_approval(): void
    {
        $user = User::factory()->create();
        $pegawai = $this->makePegawai($user);
        $pengajuan = $this->makePengajuan($pegawai);
        $pengajuan->forceFill([
            'approval_stage' => 'pending_l2',
            'approver_l1_id' => User::factory()->create()->id,
        ])->save();

        $this->actingAs($user, 'web_mobile')
            ->post(route('presensi.izin.cancel', $pengajuan->id))
            ->assertRedirect();

        $this->assertSame('dibatalkan', $pengajuan->fresh()->status);
    }

    public function test_cancelled_cuti_does_not_use_quota(): void
    {
        $user = User::factory()->create();
        $pegawai = $this->makePegawai($user);
        $pengajuan = $this->makePengajuan($pegawai, [
            'jenis_izin' => 'cuti',
            'tanggal_mulai' => '2026-12-02',
            'tanggal_selesai' => '2026-12-03',
        ]);

        $this->actingAs($user, 'web_mobile')
            ->post(route('presensi.izin.cancel', $pengajuan->id))
            ->assertRedirect();

        $pegawai->refresh();
        $this->assertSame(0, $pegawai->cuti_terpakai);
        $this->assertSame(12, (int) $pegawai->sisa_cuti);
    }

    public function test_cancelled_request_can_be_resubmitted_with_same_dates(): void
    {
        $user = User::factory()->create();
        $pegawai = $this->makePegawai($user);
        $pengajuan = $this->makePengajuan($pegawai);

        $this->actingAs($user, 'web_mobile')
            ->post(route('presensi.izin.cancel', $pengajuan->id))
            ->assertRedirect();

        $baru = PengajuanIzinService::createPending($pegawai, [
            'jenis_izin' => 'izin',
            'tanggal_mulai' => '2026-12-01',
            'tanggal_selesai' => '2026-12-01',
            'alasan' => 'Jenis yang benar',
        ], null, false);

        $this->assertNotSame($pengajuan->id, $baru['pengajuan']->id);
        $this->assertSame('pending', $baru['pengajuan']->fresh()->status);
    }

    public function test_cannot_cancel_approved_request(): void
    {
        $user = User::factory()->create();
        $pegawai = $this->makePegawai($user);
        $pengajuan = $this->makePengajuan($pegawai);
        $pengajuan->forceFill([
            'status' => 'disetujui',
            'approval_stage' => 'approved',
        ])->save();

        $this->actingAs($user, 'web_mobile')
            ->post(route('presensi.izin.cancel', $pengajuan->id))
            ->assertSessionHasErrors('error');

        $this->assertSame('disetujui', $pengajuan->fresh()->status);
    }

    public function test_cannot_cancel_rejected_request(): void
    {
        $user = User::factory()->create();
        $pegawai = $this->makePegawai($user);
        $pengajuan = $this->makePengajuan($pegawai);
        $pengajuan->forceFill([
            'status' => 'ditolak',
            'approval_stage' => 'rejected',
        ])->save();

        $this->actingAs($user, 'web_mobile')
            ->post(route('presensi.izin.cancel', $pengajuan->id))
            ->assertSessionHasErrors('error');

        $this->assertSame('ditolak', $pengajuan->fresh()->status);
    }

    public function test_cannot_cancel_twice(): void
    {
        $user = User::factory()->create();
        $pegawai = $this->makePegawai($user);
        $pengajuan = $this->makePengajuan($pegawai);

        $this->actingAs($user, 'web_mobile')
            ->post(route('presensi.izin.cancel', $pengajuan->id))
            ->assertRedirect();

        $this->actingAs($user, 'web_mobile')
            ->post(route('presensi.izin.cancel', $pengajuan->id))
            ->assertSessionHasErrors('error');
    }

    public function test_cancelled_request_cannot_be_approved(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create();
        $pegawai = $this->makePegawai($user);
        $pengajuan = $this->makePengajuan($pegawai);

        $this->actingAs($user, 'web_mobile')
            ->post(route('presensi.izin.cancel', $pengajuan->id))
            ->assertRedirect();

        $admin = User::factory()->create();
        $admin->assignRole('superadmin');

        $this->actingAs($admin, 'web_admin')
            ->post(route('pengajuan-izin.approve', $pengajuan->id))
            ->assertSessionHasErrors('error');

        $this->assertSame('dibatalkan', $pengajuan->fresh()->status);
    }

    public function test_cancelled_thread_cannot_be_replied(): void
    {
        $user = User::factory()->create();
        $pegawai = $this->makePegawai($user);
        $pengajuan = $this->makePengajuan($pegawai);

        $this->actingAs($user, 'web_mobile')
            ->post(route('presensi.izin.cancel', $pengajuan->id))
            ->assertRedirect();

        $this->actingAs($user, 'web_mobile')
            ->postJson(route('presensi.izin.reply', $pengajuan->id), ['message' => 'Halo'])
            ->assertStatus(422);
    }

    public function test_cancel_notifies_approver(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $pegawai = $this->makePegawai($user);
        $approver = User::factory()->create();
        $pengajuan = $this->makePengajuan($pegawai);
        $pengajuan->forceFill(['approver_l1_id' => $approver->id])->save();

        $this->actingAs($user, 'web_mobile')
            ->post(route('presensi.izin.cancel', $pengajuan->id))
            ->assertRedirect();

        Notification::assertSentTo($approver, IzinReply::class);
    }

    public function test_index_exposes_cancelled_filter_and_stats(): void
    {
        $user = User::factory()->create();
        $pegawai = $this->makePegawai($user);
        $pengajuan = $this->makePengajuan($pegawai);

        $this->actingAs($user, 'web_mobile')
            ->post(route('presensi.izin.cancel', $pengajuan->id))
            ->assertRedirect();

        $this->actingAs($user, 'web_mobile')
            ->get(route('presensi.izin.index', ['status' => 'dibatalkan']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Mobile/Izin/Index')
                ->where('stats.dibatalkan', 1)
                ->where('filters.status', 'dibatalkan')
                ->has('pengajuan.data', 1));
    }
}
