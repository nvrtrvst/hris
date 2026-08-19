<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\UnitSekolah;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JadwalMapelRequiredTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function makeAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'superadmin']);
        $admin->assignRole('superadmin');

        return $admin;
    }

    private function makeUnit(): UnitSekolah
    {
        return UnitSekolah::create([
            'nama' => 'SMP Mapel Test',
            'singkatan' => 'SMPMT',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'toleransi_menit' => 0,
            'max_jam_minggu' => 10,
        ]);
    }

    private function makePegawai(UnitSekolah $unit): Pegawai
    {
        $pegawai = Pegawai::create([
            'nik' => '9900112233445566',
            'nama_lengkap' => 'Guru Mapel Test',
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'kawin',
            'jumlah_tanggungan' => 0,
            'alamat_ktp' => 'Jl. Test No. 1',
            'no_hp' => '081299001122',
            'status_kepegawaian' => 'honorer',
            'tanggal_mulai_kerja' => '2021-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
        ]);
        $jabatan = Jabatan::create(['nama' => 'Guru']);
        $pegawai->units()->attach($unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    private function storePayload(Pegawai $pegawai, UnitSekolah $unit, array $overrides = []): array
    {
        return array_merge([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $unit->id,
            'kelas_label' => '7 - A',
            'hari' => ['Senin'],
            'jam_mulai' => '08:00',
            'jam_selesai' => '09:00',
            'jenis_jadwal' => 'mengajar',
            'tahun_ajaran' => '2026/2027',
            'semester' => '1',
        ], $overrides);
    }

    public function test_jadwal_mengajar_tanpa_mapel_ditolak(): void
    {
        $admin = $this->makeAdmin();
        $unit = $this->makeUnit();
        $pegawai = $this->makePegawai($unit);

        $response = $this->actingAs($admin, 'web_admin')
            ->from('/jadwal/create')
            ->post('/jadwal', $this->storePayload($pegawai, $unit, ['mata_pelajaran_id' => '']));

        $response->assertSessionHasErrors('mata_pelajaran_id');
        $this->assertDatabaseCount('jadwal', 0);
    }

    public function test_jadwal_mengajar_dengan_mapel_berhasil(): void
    {
        $admin = $this->makeAdmin();
        $unit = $this->makeUnit();
        $pegawai = $this->makePegawai($unit);
        $mapel = MataPelajaran::create(['nama' => 'Matematika']);

        $response = $this->actingAs($admin, 'web_admin')
            ->post('/jadwal', $this->storePayload($pegawai, $unit, ['mata_pelajaran_id' => $mapel->id]));

        $response->assertRedirect(route('jadwal.index'));
        $this->assertDatabaseHas('jadwal', [
            'pegawai_id' => $pegawai->id,
            'jenis_jadwal' => 'mengajar',
            'mata_pelajaran_id' => $mapel->id,
        ]);
    }

    public function test_jadwal_piket_tanpa_mapel_tetap_berhasil(): void
    {
        $admin = $this->makeAdmin();
        $unit = $this->makeUnit();
        $pegawai = $this->makePegawai($unit);

        $response = $this->actingAs($admin, 'web_admin')
            ->post('/jadwal', $this->storePayload($pegawai, $unit, [
                'jenis_jadwal' => 'piket',
                'mata_pelajaran_id' => '',
            ]));

        $response->assertRedirect(route('jadwal.index'));
        $this->assertDatabaseHas('jadwal', [
            'pegawai_id' => $pegawai->id,
            'jenis_jadwal' => 'piket',
            'mata_pelajaran_id' => null,
        ]);
    }

    public function test_generate_tanpa_mapel_ditolak(): void
    {
        $admin = $this->makeAdmin();
        $unit = $this->makeUnit();
        $this->makePegawai($unit);

        $response = $this->actingAs($admin, 'web_admin')
            ->from('/jadwal')
            ->post('/jadwal/generate', [
                'tahun_ajaran' => '2026/2027',
                'semester' => '1',
                'unit_sekolah_id' => $unit->id,
                'waktu_mulai' => '07:00',
                'waktu_selesai' => '15:00',
            ]);

        $response->assertSessionHasErrors('mata_pelajaran_id');
        $this->assertDatabaseCount('jadwal', 0);
    }

    public function test_generate_dengan_mapel_berhasil(): void
    {
        $admin = $this->makeAdmin();
        $unit = $this->makeUnit();
        $pegawai = $this->makePegawai($unit);
        $mapel = MataPelajaran::create(['nama' => 'Bahasa Indonesia']);

        $response = $this->actingAs($admin, 'web_admin')
            ->post('/jadwal/generate', [
                'tahun_ajaran' => '2026/2027',
                'semester' => '1',
                'unit_sekolah_id' => $unit->id,
                'mata_pelajaran_id' => $mapel->id,
                'waktu_mulai' => '07:00',
                'waktu_selesai' => '15:00',
            ]);

        $response->assertRedirect(route('jadwal.index'));
        $this->assertDatabaseHas('jadwal', [
            'pegawai_id' => $pegawai->id,
            'jenis_jadwal' => 'mengajar',
            'mata_pelajaran_id' => $mapel->id,
        ]);
    }
}
