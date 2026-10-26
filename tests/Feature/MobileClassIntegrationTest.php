<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Jadwal;
use App\Models\Pegawai;
use App\Models\UnitSekolah;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MobileClassIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private User $user;

    private Pegawai $pegawai;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->unit = UnitSekolah::create([
            'nama' => 'SMK Uji',
            'singkatan' => 'SMK',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
        ]);

        $jabatan = Jabatan::create(['nama' => 'Guru', 'is_guru' => true]);

        $this->user = User::factory()->create();
        $this->pegawai = Pegawai::create([
            'user_id' => $this->user->id,
            'nik' => '3273'.str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT),
            'nama_lengkap' => 'Guru Piket',
            'jenis_kelamin' => 'L',
            'status_kepegawaian' => 'guru_tetap_yayasan',
            'status_aktif' => 'aktif',
            'tmt_mengajar' => '2020-01-01',
            'wajib_kantor' => true,
        ]);
        $this->pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        config(['keuangan.url' => 'http://keuangan.test', 'keuangan.key' => 'test-key']);
    }

    private function jadwal(?string $kelasLabel = null): Jadwal
    {
        return Jadwal::create([
            'pegawai_id' => $this->pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'kelas_label' => $kelasLabel,
            'hari' => 'Senin',
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '08:00:00',
            'jenis_jadwal' => 'piket',
            'tahun_ajaran' => '2026/2027',
            'semester' => 'Ganjil',
        ]);
    }

    public function test_guest_cannot_lookup_classes(): void
    {
        $this->get('/mobile/jadwal/kelas')->assertRedirect();
    }

    public function test_kelas_unit_mengembalikan_json_dan_cache_control(): void
    {
        Http::fake([
            '*/api/integration/kelas-by-unit*' => Http::response([
                'data' => ['classes' => [['id' => 10, 'grade' => 'X', 'name' => 'A']]],
            ]),
        ]);

        $jadwal = $this->jadwal();

        $res = $this->actingAs($this->user, 'web_mobile')
            ->getJson(route('presensi.jadwal.kelas', ['jadwal_id' => $jadwal->id]));

        $res->assertOk()
            ->assertHeader('content-type', 'application/json')
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'kelas');

        $this->assertStringContainsString('no-store', $res->headers->get('cache-control'));
    }

    public function test_kelas_unit_gagal_integrasi_tetap_json_502(): void
    {
        config(['keuangan.url' => '', 'keuangan.key' => '']);

        $jadwal = $this->jadwal();

        $res = $this->actingAs($this->user, 'web_mobile')
            ->getJson(route('presensi.jadwal.kelas', ['jadwal_id' => $jadwal->id]));

        $res->assertStatus(502)
            ->assertHeader('content-type', 'application/json')
            ->assertJsonPath('success', false);

        $this->assertStringContainsString('no-store', $res->headers->get('cache-control'));
    }

    public function test_siswa_kelas_mengembalikan_json(): void
    {
        Http::fake([
            '*/api/integration/siswa-by-class*' => Http::response([
                'data' => [
                    'schoolName' => 'SMK Uji',
                    'classLabels' => ['X A'],
                    'students' => [['nis' => '001', 'nama' => 'Siswa Satu']],
                ],
            ]),
        ]);

        $jadwal = $this->jadwal('X - A');

        $res = $this->actingAs($this->user, 'web_mobile')
            ->getJson(route('presensi.jadwal.siswa', ['jadwal_id' => $jadwal->id]));

        $res->assertOk()
            ->assertHeader('content-type', 'application/json')
            ->assertJsonPath('success', true)
            ->assertJsonPath('siswa.0.nis', '001');

        $this->assertStringContainsString('no-store', $res->headers->get('cache-control'));
    }
}
