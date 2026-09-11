<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\UnitSekolah;
use App\Models\User;
use App\Notifications\IzinBaru;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IzinNotifikasiFallbackTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->unit = UnitSekolah::create([
            'nama' => 'SMP Test',
            'singkatan' => 'SMP',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'toleransi_menit' => 0,
        ]);
    }

    private function makeAdminUnit(): User
    {
        $admin = User::factory()->create(['role' => 'admin_unit', 'unit_sekolah_id' => $this->unit->id]);
        $admin->assignRole('admin_unit');

        return $admin;
    }

    private function makeSuperadmin(): User
    {
        $admin = User::factory()->create(['role' => 'superadmin']);
        $admin->assignRole('superadmin');

        return $admin;
    }

    private function makePegawai(User $user, Jabatan $jabatan): Pegawai
    {
        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '99001122'.str_pad((string) $user->id, 8, '0', STR_PAD_LEFT),
            'nama_lengkap' => 'Guru Pengaju Izin',
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '1995-05-05',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'kawin',
            'jumlah_tanggungan' => 0,
            'alamat' => 'Jl. Test No. 2',
            'no_hp' => '081211223344',
            'status_kepegawaian' => 'guru_pemula',
            'tmt_mengajar' => '2022-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
        ]);
        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    private function ajukanIzin(User $pegawaiUser)
    {
        return $this->actingAs($pegawaiUser, 'web_mobile')
            ->post(route('presensi.izin.store'), [
                'jenis_izin' => 'izin',
                'tanggal_mulai' => now()->format('Y-m-d'),
                'tanggal_selesai' => now()->format('Y-m-d'),
                'alasan' => 'Ada urusan keluarga yang harus diurus hari ini.',
            ]);
    }

    public function test_fallback_notifikasi_ke_admin_unit_dan_superadmin_saat_tanpa_approver(): void
    {
        // Jabatan Guru TANPA approver_l1_jabatan_id -> tidak ada approver L1.
        $jabatanGuru = Jabatan::create(['nama' => 'Guru']);
        $adminUnit = $this->makeAdminUnit();
        $superadmin = $this->makeSuperadmin();
        $pegawaiUser = User::factory()->create();
        $this->makePegawai($pegawaiUser, $jabatanGuru);

        $this->ajukanIzin($pegawaiUser)->assertRedirect(route('presensi.izin.index'));

        // Admin unit di unit pegawai dapat notifikasi.
        $this->assertDatabaseHas('notifications', [
            'type' => IzinBaru::class,
            'notifiable_id' => $adminUnit->id,
        ]);

        // Superadmin juga dapat.
        $this->assertDatabaseHas('notifications', [
            'type' => IzinBaru::class,
            'notifiable_id' => $superadmin->id,
        ]);
    }

    public function test_notifikasi_tetap_ke_approver_l1_dan_semua_superadmin_saat_dikonfigurasi(): void
    {
        $jabatanKepsek = Jabatan::create(['nama' => 'Kepala Sekolah']);
        $jabatanGuru = Jabatan::create([
            'nama' => 'Guru',
            'approver_l1_jabatan_id' => $jabatanKepsek->id,
        ]);

        // Atasan L1 = pegawai berjabatan Kepala Sekolah di unit yang sama.
        $approverUser = User::factory()->create();
        $this->makePegawai($approverUser, $jabatanKepsek);

        // Superadmin di scope global — harus ikut dapat notifikasi untuk audit.
        $superadmin = $this->makeSuperadmin();

        // Admin unit berbeda unit — TIDAK boleh dapat notifikasi (L1 ada).
        $adminUnitLain = $this->makeAdminUnit();

        $pegawaiUser = User::factory()->create();
        $this->makePegawai($pegawaiUser, $jabatanGuru);

        $this->ajukanIzin($pegawaiUser)->assertRedirect(route('presensi.izin.index'));

        // Notifikasi ke approver L1...
        $this->assertDatabaseHas('notifications', [
            'type' => IzinBaru::class,
            'notifiable_id' => $approverUser->id,
        ]);

        // ...dan ke superadmin (audit lintas unit).
        $this->assertDatabaseHas('notifications', [
            'type' => IzinBaru::class,
            'notifiable_id' => $superadmin->id,
        ]);

        // Admin unit di unit yang SAMA tidak dapat (L1 sudah handle).
        $this->assertDatabaseMissing('notifications', [
            'type' => IzinBaru::class,
            'notifiable_id' => $adminUnitLain->id,
        ]);
    }

    public function test_notifikasi_didedup_saat_superadmin_merangkap_approver_l1(): void
    {
        $jabatanKepsek = Jabatan::create(['nama' => 'Kepala Sekolah']);
        $jabatanGuru = Jabatan::create([
            'nama' => 'Guru',
            'approver_l1_jabatan_id' => $jabatanKepsek->id,
        ]);

        // Superadmin yang JUGA menjadi Kepala Sekolah unit → 2 role, 1 user.
        $kepsekSuperadminUser = User::factory()->create();
        $kepsekSuperadminUser->assignRole('superadmin');
        $this->makePegawai($kepsekSuperadminUser, $jabatanKepsek);

        $pegawaiUser = User::factory()->create();
        $this->makePegawai($pegawaiUser, $jabatanGuru);

        $this->ajukanIzin($pegawaiUser)->assertRedirect(route('presensi.izin.index'));

        // Hanya 1 baris notifikasi untuk user yg sama — dedup via unique('id').
        $count = \DB::table('notifications')
            ->where('type', IzinBaru::class)
            ->where('notifiable_id', $kepsekSuperadminUser->id)
            ->count();

        $this->assertSame(1, $count);
    }

    public function test_fallback_menyertakan_semua_superadmin_dan_admin_unit_unit_yang_sama(): void
    {
        // Jabatan Guru TANPA approver_l1_jabatan_id → fallback.
        $jabatanGuru = Jabatan::create(['nama' => 'Guru']);

        // 2 superadmin (mis. yayasan punya beberapa superadmin).
        $superadmin1 = $this->makeSuperadmin();
        $superadmin2 = $this->makeSuperadmin();

        // Admin unit di unit yang SAMA → dapat fallback.
        $adminUnit = $this->makeAdminUnit();

        // Admin unit di unit LAIN (buat unit kedua) → TIDAK dapat.
        $unitLain = UnitSekolah::create([
            'nama' => 'TK Test',
            'singkatan' => 'TK',
            'latitude' => -6.3,
            'longitude' => 106.9,
            'radius_meter' => 100,
        ]);
        $adminUnitLain = User::factory()->create(['unit_sekolah_id' => $unitLain->id]);
        $adminUnitLain->assignRole('admin_unit');

        $pegawaiUser = User::factory()->create();
        $this->makePegawai($pegawaiUser, $jabatanGuru);

        $this->ajukanIzin($pegawaiUser)->assertRedirect(route('presensi.izin.index'));

        // Kedua superadmin dapat notifikasi.
        $this->assertDatabaseHas('notifications', ['type' => IzinBaru::class, 'notifiable_id' => $superadmin1->id]);
        $this->assertDatabaseHas('notifications', ['type' => IzinBaru::class, 'notifiable_id' => $superadmin2->id]);

        // Admin unit unit yang sama dapat fallback.
        $this->assertDatabaseHas('notifications', ['type' => IzinBaru::class, 'notifiable_id' => $adminUnit->id]);

        // Admin unit unit lain TIDAK dapat.
        $this->assertDatabaseMissing('notifications', ['type' => IzinBaru::class, 'notifiable_id' => $adminUnitLain->id]);
    }
}
