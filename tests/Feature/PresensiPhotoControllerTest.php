<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\UnitSekolah;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Regression: izinkan admin_unit melihat foto izin presensi untuk pegawai unitnya sendiri,
 * tolak lintas unit (anti-IDOR), dan superadmin tidak di-scope.
 *
 * Bug historis: file path izin/{pegawai_id}_{slug}/{uuid}.webp tidak lolos regex
 * unit scope karena foto disimpan sebagai bytes 0-byte (imagewebp silent-fail).
 *
 * ponytail: 4 test inti saja (sesuai cakupan perbaikan); tambah kalau regresi nyata.
 */
class PresensiPhotoControllerTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unitA;

    private UnitSekolah $unitB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->unitA = $this->makeUnit('SMK Uji A', 'SMKA');
        $this->unitB = $this->makeUnit('SMK Uji B', 'SMKB');

        Storage::fake(config('filesystems.presensi_disk'));
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
            'alamat' => 'Jl. Test No. 1',
            'no_hp' => '0812'.substr($nik, -7),
            'status_kepegawaian' => 'guru_pemula',
            'tmt_mengajar' => '2021-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
        ]);
        $pegawai->units()->attach($unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    public function test_admin_unit_dapat_akses_foto_pegawai_unit_sendiri(): void
    {
        $adminA = $this->makeAdminUnit($this->unitA);
        $pegawaiA = $this->makePegawai('1234567890123456', $this->unitA);
        $path = "izin/{$pegawaiA->id}_pegawai_a/uuid.webp";
        Storage::disk(config('filesystems.presensi_disk'))->put($path, 'fake-webp');

        // Symfony gabung Cache-Control dengan default no-cache — cek substring 'no-store, private' saja.
        $response = $this->actingAs($adminA, 'web_admin')->get(route('presensi.photo', ['path' => $path]));
        $response->assertOk();
        $this->assertStringContainsString('no-store, private', $response->headers->get('Cache-Control', ''));
    }

    public function test_admin_unit_ditolak_akses_foto_pegawai_unit_lain(): void
    {
        $adminA = $this->makeAdminUnit($this->unitA);
        $pegawaiB = $this->makePegawai('6543210987654321', $this->unitB);
        $path = "izin/{$pegawaiB->id}_pegawai_b/uuid.webp";
        Storage::disk(config('filesystems.presensi_disk'))->put($path, 'fake-webp');

        $this->actingAs($adminA, 'web_admin')
            ->get(route('presensi.photo', ['path' => $path]))
            ->assertForbidden();
    }

    public function test_superadmin_tidak_di_scope_unit(): void
    {
        $super = $this->makeSuperadmin();
        $pegawaiB = $this->makePegawai('1111222233334444', $this->unitB);
        $path = "izin/{$pegawaiB->id}_pegawai_b/uuid.webp";
        Storage::disk(config('filesystems.presensi_disk'))->put($path, 'fake-webp');

        $this->actingAs($super, 'web_admin')
            ->get(route('presensi.photo', ['path' => $path]))
            ->assertOk();
    }

    public function test_path_traversal_ditolak(): void
    {
        $adminA = $this->makeAdminUnit($this->unitA);

        $this->actingAs($adminA, 'web_admin')
            ->get(route('presensi.photo', ['path' => '../etc/passwd']))
            ->assertForbidden();
    }
}
