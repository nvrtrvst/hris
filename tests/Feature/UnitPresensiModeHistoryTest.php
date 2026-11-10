<?php

namespace Tests\Feature;

use App\Models\UnitPresensiModeHistory;
use App\Models\UnitSekolah;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitPresensiModeHistoryTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Carbon::setTestNow('2026-11-10 08:00:00');

        $this->unit = UnitSekolah::create([
            'nama' => 'SMP Mode',
            'singkatan' => 'SMPM',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'jam_masuk_kantor' => '07:00',
        ]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('superadmin');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_default_mode_adalah_kantor(): void
    {
        $this->assertSame(UnitPresensiModeHistory::MODE_KANTOR, $this->unit->modePresensiAktif());
        $segments = $this->unit->modeSegments(Carbon::today(), Carbon::today());
        $this->assertCount(1, $segments);
        $this->assertSame(UnitPresensiModeHistory::MODE_KANTOR, $segments[0]['mode']);
    }

    public function test_mode_future_belum_aktif_sampai_tanggal_berlaku(): void
    {
        UnitPresensiModeHistory::create([
            'unit_sekolah_id' => $this->unit->id,
            'mode' => UnitPresensiModeHistory::MODE_HANYA_JADWAL,
            'berlaku_mulai' => '2026-11-12',
        ]);

        $this->assertSame(UnitPresensiModeHistory::MODE_KANTOR, $this->unit->modePresensiPada('2026-11-11'));
        $this->assertSame(UnitPresensiModeHistory::MODE_HANYA_JADWAL, $this->unit->modePresensiPada('2026-11-12'));
    }

    public function test_store_mode_dan_unique_tanggal(): void
    {
        $this->actingAs($this->admin, 'web_admin')
            ->post(route('unit-sekolah.store-presensi-mode', $this->unit), [
                'mode' => UnitPresensiModeHistory::MODE_HANYA_JADWAL,
                'berlaku_mulai' => '2026-11-11',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('unit_presensi_mode_histories', [
            'unit_sekolah_id' => $this->unit->id,
            'mode' => UnitPresensiModeHistory::MODE_HANYA_JADWAL,
            'berlaku_mulai' => '2026-11-11 00:00:00',
        ]);

        $this->actingAs($this->admin, 'web_admin')
            ->post(route('unit-sekolah.store-presensi-mode', $this->unit), [
                'mode' => UnitPresensiModeHistory::MODE_KANTOR,
                'berlaku_mulai' => '2026-11-11',
            ])
            ->assertSessionHasErrors('berlaku_mulai');
    }

    public function test_tidak_boleh_backdate(): void
    {
        $this->actingAs($this->admin, 'web_admin')
            ->post(route('unit-sekolah.store-presensi-mode', $this->unit), [
                'mode' => UnitPresensiModeHistory::MODE_HANYA_JADWAL,
                'berlaku_mulai' => '2026-11-09',
            ])
            ->assertSessionHasErrors('berlaku_mulai');
    }

    public function test_hari_ini_bisa_diubah_tapi_lampau_read_only(): void
    {
        $past = UnitPresensiModeHistory::create([
            'unit_sekolah_id' => $this->unit->id,
            'mode' => UnitPresensiModeHistory::MODE_HANYA_JADWAL,
            'berlaku_mulai' => '2026-11-09',
        ]);
        $today = UnitPresensiModeHistory::create([
            'unit_sekolah_id' => $this->unit->id,
            'mode' => UnitPresensiModeHistory::MODE_KANTOR,
            'berlaku_mulai' => '2026-11-10',
        ]);

        $this->actingAs($this->admin, 'web_admin')
            ->put(route('unit-sekolah.update-presensi-mode', [$this->unit, $today]), [
                'mode' => UnitPresensiModeHistory::MODE_HANYA_JADWAL,
                'berlaku_mulai' => '2026-11-10',
            ])
            ->assertRedirect();

        $this->assertSame(UnitPresensiModeHistory::MODE_HANYA_JADWAL, $today->fresh()->mode);

        $this->actingAs($this->admin, 'web_admin')
            ->delete(route('unit-sekolah.destroy-presensi-mode', [$this->unit, $past]))
            ->assertStatus(422);
    }
}
