<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\UnitSekolah;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class JadwalUnitPegawaiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
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
            'max_jam_minggu' => 40,
        ]);
    }

    private function makePegawai(UnitSekolah $unit, string $nama): Pegawai
    {
        $user = User::factory()->create();
        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '1234567890'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT),
            'nama_lengkap' => $nama,
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'kawin',
            'jumlah_tanggungan' => 0,
            'alamat' => 'Jl. Test No. 1',
            'no_hp' => '081234567890',
            'status_kepegawaian' => 'guru_tidak_tetap',
            'tmt_mengajar' => '2020-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
            'wajib_kantor' => false,
        ]);
        $jabatan = Jabatan::create(['nama' => 'Guru '.$nama]);
        $pegawai->units()->attach($unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    private function makeSuperadmin(): User
    {
        $admin = User::factory()->create(['role' => 'superadmin']);
        $admin->assignRole('superadmin');

        return $admin;
    }

    private function piketPayload(Pegawai $pegawai, UnitSekolah $unit): array
    {
        return [
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $unit->id,
            'hari' => ['Senin'],
            'jam_mulai' => '07:30',
            'jam_selesai' => '15:00',
            'jenis_jadwal' => 'piket',
            'tahun_ajaran' => '2026/2027',
            'semester' => '1',
        ];
    }

    public function test_superadmin_jadwal_silang_unit_ditolak(): void
    {
        $unitA = $this->makeUnit('SD Test', 'SDT');
        $unitB = $this->makeUnit('SMP Test', 'SMPT');
        $pegawaiA = $this->makePegawai($unitA, 'Guru A');

        $response = $this->actingAs($this->makeSuperadmin(), 'web_admin')
            ->from('/jadwal/create')
            ->post('/jadwal', $this->piketPayload($pegawaiA, $unitB));

        $response->assertSessionHasErrors('pegawai_id');
        $this->assertDatabaseCount('jadwal', 0);
    }

    public function test_superadmin_jadwal_seunit_diterima(): void
    {
        $unitA = $this->makeUnit('SD Test', 'SDT');
        $pegawaiA = $this->makePegawai($unitA, 'Guru A');

        $response = $this->actingAs($this->makeSuperadmin(), 'web_admin')
            ->post('/jadwal', $this->piketPayload($pegawaiA, $unitA));

        $response->assertRedirect(route('jadwal.index'));
        $this->assertDatabaseHas('jadwal', [
            'pegawai_id' => $pegawaiA->id,
            'unit_sekolah_id' => $unitA->id,
            'jenis_jadwal' => 'piket',
        ]);
    }

    public function test_admin_unit_tetap_bisa_untuk_unitnya(): void
    {
        $unitA = $this->makeUnit('SD Test', 'SDT');
        $pegawaiA = $this->makePegawai($unitA, 'Guru A');
        $adminUnit = User::factory()->create(['role' => 'admin_unit', 'unit_sekolah_id' => $unitA->id]);
        $adminUnit->assignRole('admin_unit');

        $response = $this->actingAs($adminUnit, 'web_admin')
            ->post('/jadwal', $this->piketPayload($pegawaiA, $unitA));

        $response->assertRedirect(route('jadwal.index'));
        $this->assertDatabaseCount('jadwal', 1);
    }

    private function mengajarPayload(Pegawai $pegawai, UnitSekolah $unit, int $mapelId): array
    {
        return [
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $unit->id,
            'mata_pelajaran_id' => $mapelId,
            'kelas_label' => '7 - A',
            'hari' => ['Selasa'],
            'jam_mulai' => '10:00',
            'jam_selesai' => '10:45',
            'jenis_jadwal' => 'mengajar',
            'tahun_ajaran' => '2026/2027',
            'semester' => '1',
        ];
    }

    public function test_piket_boleh_overlap_mengajar(): void
    {
        $unit = $this->makeUnit('SD Test', 'SDT');
        $pegawai = $this->makePegawai($unit, 'Guru A');
        $mapel = MataPelajaran::create(['nama' => 'Matematika']);
        $admin = $this->makeSuperadmin();

        $this->actingAs($admin, 'web_admin')
            ->post('/jadwal', $this->mengajarPayload($pegawai, $unit, $mapel->id))
            ->assertRedirect(route('jadwal.index'));

        $payload = $this->piketPayload($pegawai, $unit);
        $payload['hari'] = ['Selasa'];
        $this->actingAs($admin, 'web_admin')
            ->post('/jadwal', $payload)
            ->assertRedirect(route('jadwal.index'));

        $this->assertDatabaseCount('jadwal', 2);
    }

    public function test_mengajar_boleh_overlap_piket(): void
    {
        $unit = $this->makeUnit('SD Test', 'SDT');
        $pegawai = $this->makePegawai($unit, 'Guru A');
        $mapel = MataPelajaran::create(['nama' => 'Matematika']);
        $admin = $this->makeSuperadmin();

        $payload = $this->piketPayload($pegawai, $unit);
        $payload['hari'] = ['Selasa'];
        $this->actingAs($admin, 'web_admin')
            ->post('/jadwal', $payload)
            ->assertRedirect(route('jadwal.index'));

        $this->actingAs($admin, 'web_admin')
            ->post('/jadwal', $this->mengajarPayload($pegawai, $unit, $mapel->id))
            ->assertRedirect(route('jadwal.index'));

        $this->assertDatabaseCount('jadwal', 2);
    }

    public function test_piket_overlap_piket_tetap_ditolak(): void
    {
        $unit = $this->makeUnit('SD Test', 'SDT');
        $pegawai = $this->makePegawai($unit, 'Guru A');
        $admin = $this->makeSuperadmin();

        $payload = $this->piketPayload($pegawai, $unit);
        $payload['hari'] = ['Selasa'];
        $this->actingAs($admin, 'web_admin')
            ->post('/jadwal', $payload)
            ->assertRedirect(route('jadwal.index'));

        $this->actingAs($admin, 'web_admin')
            ->from('/jadwal/create')
            ->post('/jadwal', $payload)
            ->assertSessionHasErrors('conflict');

        $this->assertDatabaseCount('jadwal', 1);
    }

    public function test_prop_create_memuat_unit_pegawai(): void
    {
        $unitA = $this->makeUnit('SD Test', 'SDT');
        $this->makePegawai($unitA, 'Guru A');

        $this->actingAs($this->makeSuperadmin(), 'web_admin')
            ->get('/jadwal/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Jadwal/Create')
                ->has('pegawais.0.units')
            );
    }
}
