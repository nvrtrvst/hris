<?php

namespace Tests\Feature;

use App\Models\HariLibur;
use App\Models\Jabatan;
use App\Models\Jadwal;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\Penggajian;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use App\Notifications\PresensiDikoreksi;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class KoreksiJpPresensiTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private Pegawai $pegawai;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-20 10:00:00');
        $this->seed(RolePermissionSeeder::class);

        $this->unit = UnitSekolah::create([
            'nama' => 'SMP Koreksi JP',
            'singkatan' => 'SMPJP',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'toleransi_menit' => 0,
        ]);

        $jabatan = Jabatan::create(['nama' => 'Guru Koreksi JP', 'is_guru' => true]);
        $this->pegawai = Pegawai::create([
            'user_id' => User::factory()->create(['email' => 'guru.jp@example.test'])->id,
            'nik' => '3273010101900099',
            'nama_lengkap' => 'Guru Koreksi JP',
            'jenis_kelamin' => 'L',
            'status_kepegawaian' => 'guru_tetap_yayasan',
            'status_aktif' => 'aktif',
            'tmt_mengajar' => '2020-01-01',
            'wajib_kantor' => true,
        ]);
        $this->pegawai->units()->attach($this->unit->id, [
            'jabatan_id' => $jabatan->id,
            'is_primary' => true,
        ]);

        $this->superadmin = User::factory()->create();
        $this->superadmin->assignRole('superadmin');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function buatJadwal(array $overrides = []): Jadwal
    {
        return Jadwal::create(array_merge([
            'pegawai_id' => $this->pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'kelas_label' => 'X A',
            'hari' => 'Kamis',
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '07:45:00',
            'jenis_jadwal' => 'mengajar',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ], $overrides));
    }

    private function buatPresensi(Jadwal $jadwal, string $status, ?string $keterangan = null): Presensi
    {
        $presensi = Presensi::create([
            'pegawai_id' => $this->pegawai->id,
            'jadwal_id' => $jadwal->id,
            'unit_sekolah_id' => $this->unit->id,
            'tipe_presensi' => 'mengajar',
            'tanggal' => '2026-08-20',
            'keterangan' => $keterangan,
        ]);
        $presensi->forceFill(['status' => $status])->save();

        return $presensi;
    }

    private function jpRows(User $user, array $query = []): array
    {
        $response = $this->actingAs($user, 'web_admin')
            ->get(route('koreksi-presensi.index', array_merge(['tab' => 'jp'], $query)));

        $response->assertOk();

        return $response->viewData('page')['props']['jp_terlewat']['data'];
    }

    private function koreksiPayload(Jadwal $jadwal, array $overrides = []): array
    {
        return array_merge([
            'jadwal_id' => $jadwal->id,
            'tanggal' => '2026-08-20',
            'jam_masuk' => '07:00',
            'jam_keluar' => '07:45',
            'status' => 'hadir',
            'alasan' => 'Lupa absen karena mendampingi siswa.',
        ], $overrides);
    }

    public function test_jp_tanpa_presensi_muncul_termasuk_pegawai_tanpa_presensi_lain(): void
    {
        $this->buatJadwal();

        $rows = $this->jpRows($this->superadmin);

        $this->assertCount(1, $rows);
        $this->assertSame('belum_presensi', $rows[0]['sumber']);
        $this->assertSame('Belum presensi', $rows[0]['sumber_label']);
        $this->assertSame('Guru Koreksi JP', $rows[0]['pegawai']['nama_lengkap']);
    }

    public function test_alpa_otomatis_dan_manual_muncul_dengan_label_berbeda(): void
    {
        $otomatis = $this->buatJadwal(['jam_mulai' => '07:00:00', 'jam_selesai' => '07:45:00']);
        $manual = $this->buatJadwal(['jam_mulai' => '08:00:00', 'jam_selesai' => '08:45:00']);
        $this->buatPresensi($otomatis, 'alpa', 'Tidak hadir (otomatis)');
        $this->buatPresensi($manual, 'alpa');

        $rows = collect($this->jpRows($this->superadmin))->keyBy('jadwal_id');

        $this->assertSame('alpa_otomatis', $rows[$otomatis->id]['sumber']);
        $this->assertSame('alpa_manual', $rows[$manual->id]['sumber']);
    }

    public function test_jp_yang_sudah_hadir_izin_dan_tugas_tidak_muncul(): void
    {
        $hadir = $this->buatJadwal(['jam_mulai' => '07:00:00', 'jam_selesai' => '07:45:00']);
        $this->buatPresensi($hadir, 'hadir');

        $this->buatJadwal(['jam_mulai' => '09:00:00', 'jam_selesai' => '09:45:00']);
        $izin = PengajuanIzin::create([
            'pegawai_id' => $this->pegawai->id,
            'jenis_izin' => 'izin',
            'tanggal_mulai' => '2026-08-20',
            'tanggal_selesai' => '2026-08-20',
            'alasan' => 'Keperluan keluarga.',
        ]);
        $izin->forceFill(['status' => 'disetujui'])->save();

        $tugas = Presensi::create([
            'pegawai_id' => $this->pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'tipe_presensi' => 'tugas_luar',
            'tanggal' => '2026-08-20',
            'jam_masuk' => '07:00:00',
        ]);
        $tugas->forceFill(['status' => 'hadir'])->save();

        $this->assertCount(0, $this->jpRows($this->superadmin));
    }

    public function test_hari_libur_tidak_muncul(): void
    {
        $this->buatJadwal();
        HariLibur::create(['tanggal' => '2026-08-20', 'nama' => 'Libur', 'unit_sekolah_id' => $this->unit->id]);

        $this->assertCount(0, $this->jpRows($this->superadmin));
    }

    public function test_jadwal_semester_lama_tidak_muncul(): void
    {
        $this->buatJadwal([
            'tahun_ajaran' => '2025/2026',
            'semester' => 2,
        ]);

        $this->assertCount(0, $this->jpRows($this->superadmin));
    }

    public function test_koreksi_membuat_presensi_hadir_audit_dan_email(): void
    {
        Notification::fake();
        $jadwal = $this->buatJadwal();

        $this->actingAs($this->superadmin, 'web_admin')
            ->post(route('koreksi-presensi.koreksi-jp'), $this->koreksiPayload($jadwal))
            ->assertRedirect();

        $presensi = Presensi::where('jadwal_id', $jadwal->id)->firstOrFail();
        $this->assertSame('hadir', $presensi->status);
        $this->assertSame('07:00:00', substr((string) $presensi->jam_masuk, 0, 8));
        $this->assertSame('07:45:00', substr((string) $presensi->jam_keluar, 0, 8));

        $this->assertDatabaseHas('audit_presensi', [
            'presensi_id' => $presensi->id,
            'aksi' => 'koreksi_jp',
            'field' => 'status',
            'nilai_baru' => 'hadir',
        ]);

        Notification::assertSentTo(
            $this->pegawai->user,
            PresensiDikoreksi::class,
            fn ($notification, $channels) => in_array('mail', $channels, true)
        );
    }

    public function test_koreksi_alpa_manual_menjadi_hadir(): void
    {
        $jadwal = $this->buatJadwal();
        $presensi = $this->buatPresensi($jadwal, 'alpa');

        $this->actingAs($this->superadmin, 'web_admin')
            ->post(route('koreksi-presensi.koreksi-jp'), $this->koreksiPayload($jadwal))
            ->assertRedirect();

        $this->assertSame('hadir', $presensi->fresh()->status);
        $this->assertDatabaseHas('audit_presensi', [
            'presensi_id' => $presensi->id,
            'nilai_lama' => 'alpa',
            'nilai_baru' => 'hadir',
        ]);
    }

    public function test_koreksi_ulang_ditolak(): void
    {
        $jadwal = $this->buatJadwal();

        $this->actingAs($this->superadmin, 'web_admin')
            ->post(route('koreksi-presensi.koreksi-jp'), $this->koreksiPayload($jadwal))
            ->assertRedirect();

        $this->actingAs($this->superadmin, 'web_admin')
            ->post(route('koreksi-presensi.koreksi-jp'), $this->koreksiPayload($jadwal))
            ->assertSessionHasErrors('jadwal_id');
    }

    public function test_payroll_terkunci_ditolak(): void
    {
        Penggajian::create([
            'pegawai_id' => $this->pegawai->id,
            'periode_bulan' => '08-2026',
            'tanggal_generate' => '2026-08-20',
            'status' => 'paid',
        ]);
        $jadwal = $this->buatJadwal();

        $this->actingAs($this->superadmin, 'web_admin')
            ->post(route('koreksi-presensi.koreksi-jp'), $this->koreksiPayload($jadwal))
            ->assertSessionHasErrors('jadwal_id');
    }

    public function test_jadwal_pegawai_lain_dan_unit_lain_ditolak(): void
    {
        $unitLain = UnitSekolah::create([
            'nama' => 'SMP Lain',
            'singkatan' => 'SMPL',
            'latitude' => -6.3,
            'longitude' => 106.9,
            'radius_meter' => 100,
        ]);
        $pegawaiLain = Pegawai::create([
            'user_id' => User::factory()->create()->id,
            'nik' => '3273010101900100',
            'nama_lengkap' => 'Guru Lain',
            'jenis_kelamin' => 'P',
            'status_kepegawaian' => 'guru_tetap_yayasan',
            'status_aktif' => 'aktif',
            'tmt_mengajar' => '2020-01-01',
        ]);
        $pegawaiLain->units()->attach($unitLain->id, [
            'jabatan_id' => Jabatan::first()->id,
            'is_primary' => true,
        ]);
        $jadwalLain = $this->buatJadwal([
            'pegawai_id' => $pegawaiLain->id,
            'unit_sekolah_id' => $unitLain->id,
        ]);

        $role = Role::create(['name' => 'hr_unit', 'guard_name' => 'web']);
        $role->givePermissionTo('koreksi_presensi');
        $adminUnit = User::factory()->create(['unit_sekolah_id' => $this->unit->id]);
        $adminUnit->assignRole($role);

        $this->actingAs($adminUnit, 'web_admin')
            ->post(route('koreksi-presensi.koreksi-jp'), $this->koreksiPayload($jadwalLain))
            ->assertForbidden();
    }

    public function test_role_custom_dengan_permission_bisa_akses_tab_jp(): void
    {
        $this->buatJadwal();

        $role = Role::create(['name' => 'hr_koreksi', 'guard_name' => 'web']);
        $role->givePermissionTo('koreksi_presensi');
        $user = User::factory()->create(['unit_sekolah_id' => $this->unit->id]);
        $user->assignRole($role);

        $response = $this->actingAs($user, 'web_admin')
            ->get(route('koreksi-presensi.index', ['tab' => 'jp']));

        $response->assertOk();
        $this->assertTrue($response->viewData('page')['props']['can_koreksi_jp']);
        $this->assertFalse($response->viewData('page')['props']['can_view_pengajuan']);
        $this->assertCount(1, $response->viewData('page')['props']['jp_terlewat']['data']);
    }

    public function test_view_izin_tanpa_koreksi_hanya_melihat_tab_pengajuan(): void
    {
        $user = User::factory()->create(['unit_sekolah_id' => $this->unit->id]);
        $user->assignRole('admin_unit');

        $response = $this->actingAs($user, 'web_admin')
            ->get(route('koreksi-presensi.index', ['tab' => 'jp']));

        $response->assertOk();
        $this->assertFalse($response->viewData('page')['props']['can_koreksi_jp']);
        $this->assertSame('pengajuan', $response->viewData('page')['props']['tab']);
    }

    public function test_role_tanpa_permission_tidak_bisa_akses_halaman(): void
    {
        $role = Role::create(['name' => 'staf_biasa', 'guard_name' => 'web']);
        $user = User::factory()->create(['unit_sekolah_id' => $this->unit->id]);
        $user->assignRole($role);

        $this->actingAs($user, 'web_admin')
            ->get(route('koreksi-presensi.index'))
            ->assertForbidden();
    }

    public function test_finalize_alpa_tidak_menimpa_hasil_koreksi(): void
    {
        Notification::fake();
        $jadwal = $this->buatJadwal();

        $this->actingAs($this->superadmin, 'web_admin')
            ->post(route('koreksi-presensi.koreksi-jp'), $this->koreksiPayload($jadwal))
            ->assertRedirect();

        $this->artisan('presensi:finalize-alpa', ['--date' => '2026-08-20'])->assertSuccessful();

        $presensi = Presensi::where('jadwal_id', $jadwal->id)->firstOrFail();
        $this->assertSame('hadir', $presensi->status);
        $this->assertSame(1, Presensi::where('jadwal_id', $jadwal->id)->count());
    }
}
