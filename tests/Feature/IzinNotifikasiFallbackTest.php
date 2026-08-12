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
            'alamat_ktp' => 'Jl. Test No. 2',
            'no_hp' => '081211223344',
            'status_kepegawaian' => 'honorer',
            'tanggal_mulai_kerja' => '2022-01-01',
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

    public function test_notifikasi_tetap_ke_approver_l1_saat_dikonfigurasi(): void
    {
        $jabatanKepsek = Jabatan::create(['nama' => 'Kepala Sekolah']);
        $jabatanGuru = Jabatan::create([
            'nama' => 'Guru',
            'approver_l1_jabatan_id' => $jabatanKepsek->id,
        ]);

        // Atasan L1 = pegawai berjabatan Kepala Sekolah di unit yang sama.
        $approverUser = User::factory()->create();
        $this->makePegawai($approverUser, $jabatanKepsek);

        $adminUnit = $this->makeAdminUnit();
        $pegawaiUser = User::factory()->create();
        $this->makePegawai($pegawaiUser, $jabatanGuru);

        $this->ajukanIzin($pegawaiUser)->assertRedirect(route('presensi.izin.index'));

        // Notifikasi ke approver L1...
        $this->assertDatabaseHas('notifications', [
            'type' => IzinBaru::class,
            'notifiable_id' => $approverUser->id,
        ]);

        // ...dan BUKAN fallback ke admin unit (L1 ada, fallback tidak jalan).
        $this->assertDatabaseMissing('notifications', [
            'type' => IzinBaru::class,
            'notifiable_id' => $adminUnit->id,
        ]);
    }
}
