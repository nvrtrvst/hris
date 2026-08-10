<?php

namespace Tests\Feature;

use App\Models\AuditPresensi;
use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PresensiAuditIdorTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unitA;

    private UnitSekolah $unitB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->unitA = $this->makeUnit('SMP Test A', 'SMPA');
        $this->unitB = $this->makeUnit('SMP Test B', 'SMPB');
    }

    private function makeUnit(string $nama, string $singkatan): UnitSekolah
    {
        return UnitSekolah::create([
            'nama' => $nama,
            'singkatan' => $singkatan,
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'toleransi_menit' => 0,
        ]);
    }

    /**
     * Admin unit dengan unit_sekolah_id (tanpa view_all_units).
     */
    private function makeAdminUnit(UnitSekolah $unit): User
    {
        $admin = User::factory()->create([
            'role' => 'admin_unit',
            'unit_sekolah_id' => $unit->id,
        ]);
        $admin->assignRole('admin_unit');

        return $admin;
    }

    private function makeSuperadmin(): User
    {
        $admin = User::factory()->create(['role' => 'superadmin']);
        $admin->assignRole('superadmin');

        return $admin;
    }

    private function makePegawai(string $nik, UnitSekolah $unit): Pegawai
    {
        $jabatan = Jabatan::create(['nama' => 'Guru']);
        $pegawai = Pegawai::create([
            'nik' => $nik,
            'nama_lengkap' => 'Pegawai '.$nik,
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '1992-03-03',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'kawin',
            'jumlah_tanggungan' => 0,
            'alamat_ktp' => 'Jl. IDOR Test No. 1',
            'no_hp' => '0812'.substr($nik, -7),
            'status_kepegawaian' => 'honorer',
            'tanggal_mulai_kerja' => '2021-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
        ]);
        $pegawai->units()->attach($unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    private function makePresensi(Pegawai $pegawai, UnitSekolah $unit): Presensi
    {
        return Presensi::create([
            'pegawai_id' => $pegawai->id,
            'jadwal_id' => null,
            'unit_sekolah_id' => $unit->id,
            'tipe_presensi' => 'kantor',
            'tanggal' => '2026-07-01',
            'jam_masuk' => '07:00:00',
            'jam_keluar' => '15:30:00',
            'status' => 'hadir',
        ]);
    }

    public function test_admin_unit_tidak_bisa_akses_audit_record_unit_lain(): void
    {
        $adminA = $this->makeAdminUnit($this->unitA);
        $pegawaiB = $this->makePegawai('7788990011223344', $this->unitB);
        $presensiB = $this->makePresensi($pegawaiB, $this->unitB);
        AuditPresensi::create([
            'presensi_id' => $presensiB->id,
            'user_id' => $adminA->id,
            'aksi' => 'ubah_status',
            'field' => 'status',
            'nilai_lama' => 'alpa',
            'nilai_baru' => 'hadir',
        ]);

        $this->actingAs($adminA, 'web_admin')
            ->getJson(route('presensi.audit', $presensiB->id))
            ->assertForbidden();
    }

    public function test_admin_unit_bisa_akses_audit_record_unit_sendiri(): void
    {
        $adminA = $this->makeAdminUnit($this->unitA);
        $pegawaiA = $this->makePegawai('5566778899001122', $this->unitA);
        $presensiA = $this->makePresensi($pegawaiA, $this->unitA);
        AuditPresensi::create([
            'presensi_id' => $presensiA->id,
            'user_id' => $adminA->id,
            'aksi' => 'ubah_status',
            'field' => 'status',
            'nilai_lama' => 'alpa',
            'nilai_baru' => 'hadir',
        ]);

        $this->actingAs($adminA, 'web_admin')
            ->getJson(route('presensi.audit', $presensiA->id))
            ->assertOk()
            ->assertJsonCount(1, 'audits')
            ->assertJsonPath('audits.0.aksi', 'ubah_status')
            ->assertJsonPath('presensi.pegawai_nama', 'Pegawai 5566778899001122')
            ->assertJsonPath('presensi.tanggal', '2026-07-01')
            // Konteks bukti foto ikut dikirim (regresi fitur foto evidence)
            ->assertJsonStructure(['presensi' => ['foto_masuk_url', 'foto_keluar_url', 'foto_masuk_status', 'foto_keluar_status']]);
    }

    public function test_superadmin_bisa_akses_audit_semua_unit(): void
    {
        $superadmin = $this->makeSuperadmin();
        $pegawaiB = $this->makePegawai('3344556677889900', $this->unitB);
        $presensiB = $this->makePresensi($pegawaiB, $this->unitB);

        $this->actingAs($superadmin, 'web_admin')
            ->getJson(route('presensi.audit', $presensiB->id))
            ->assertOk()
            ->assertJsonCount(0, 'audits');
    }

    public function test_pegawai_tanpa_permission_tidak_bisa_akses_audit(): void
    {
        $pegawaiUser = User::factory()->create(['role' => 'pegawai']);
        $pegawaiUser->assignRole('pegawai');

        $pegawaiA = $this->makePegawai('1122334455667788', $this->unitA);
        $presensiA = $this->makePresensi($pegawaiA, $this->unitA);

        $this->actingAs($pegawaiUser, 'web_admin')
            ->getJson(route('presensi.audit', $presensiA->id))
            ->assertForbidden();
    }

    public function test_guest_dialihkan_ke_login(): void
    {
        $pegawaiA = $this->makePegawai('9988776655443322', $this->unitA);
        $presensiA = $this->makePresensi($pegawaiA, $this->unitA);

        $this->get(route('presensi.audit', $presensiA->id))
            ->assertRedirect(route('login'));
    }

    public function test_admin_unit_tidak_bisa_akses_foto_pegawai_unit_lain(): void
    {
        $adminA = $this->makeAdminUnit($this->unitA);
        $pegawaiB = $this->makePegawai('6655443322110099', $this->unitB);

        $photoPath = 'presensi/'.$pegawaiB->id.'_pegawai_foto/uuid.webp';

        $this->actingAs($adminA, 'web_admin')
            ->get(route('presensi.photo', $photoPath))
            ->assertForbidden();
    }

    public function test_admin_unit_bisa_akses_foto_pegawai_unit_sendiri(): void
    {
        $adminA = $this->makeAdminUnit($this->unitA);
        $pegawaiA = $this->makePegawai('6655443322110000', $this->unitA);
        $photoPath = 'presensi/'.$pegawaiA->id.'_pegawai_foto/uuid.webp';
        Storage::disk(config('filesystems.presensi_disk', 'presensi'))->put($photoPath, 'fake-image');

        $this->actingAs($adminA, 'web_admin')
            ->get(route('presensi.photo', $photoPath))
            ->assertOk();
    }
}
