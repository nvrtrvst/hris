<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Jadwal;
use App\Models\Pegawai;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardPartialReloadTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->superadmin = User::factory()->create(['role' => 'superadmin']);
        $this->superadmin->assignRole('superadmin');
    }

    /**
     * Replikasi HandleInertiaRequests::version() — partial reload memerlukan
     * header X-Inertia-Version yang cocok, selain itu Inertia membalas 409.
     */
    private function inertiaVersion(): ?string
    {
        $manifest = public_path('build/manifest.json');
        if (is_file($manifest)) {
            $mtime = @filemtime($manifest);
            if ($mtime !== false) {
                return (string) $mtime;
            }
        }

        return null;
    }

    private function partialHeaders(): array
    {
        $headers = [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'Dashboard',
            'X-Inertia-Partial-Data' => 'stats,trends,jadwalHariIni,presensiHariIni',
        ];
        if (($version = $this->inertiaVersion()) !== null) {
            $headers['X-Inertia-Version'] = $version;
        }

        return $headers;
    }

    private function seedData(): void
    {
        $unit = UnitSekolah::create([
            'nama' => 'SMP Live',
            'singkatan' => 'SMPL',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'toleransi_menit' => 0,
            'toleransi_tap_menit' => 15,
        ]);

        $jabatan = Jabatan::create(['nama' => 'Guru']);
        $pegawai = Pegawai::create([
            'user_id' => User::factory()->create()->id,
            'nik' => '1111222233334444',
            'nama_lengkap' => 'Guru Live',
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '1992-03-03',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'kawin',
            'jumlah_tanggungan' => 0,
            'alamat_ktp' => 'Jl. Live Test No. 1',
            'no_hp' => '081211223344',
            'status_kepegawaian' => 'honorer',
            'tanggal_mulai_kerja' => '2021-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
        ]);
        $pegawai->units()->attach($unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        $hariMap = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
        $hariIni = $hariMap[Carbon::now()->format('l')];

        $jadwal = Jadwal::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $unit->id,
            'hari' => $hariIni,
            'jam_mulai' => '08:00:00',
            'jam_selesai' => '09:45:00',
            'jenis_jadwal' => 'mengajar',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);

        $presensi = Presensi::create([
            'pegawai_id' => $pegawai->id,
            'jadwal_id' => $jadwal->id,
            'unit_sekolah_id' => $unit->id,
            'tipe_presensi' => 'mengajar',
            'tanggal' => Carbon::today()->toDateString(),
            'jam_masuk' => '08:00:00',
        ]);
        // status ada di $guarded — set langsung setelah create agar tersimpan 'hadir'.
        $presensi->forceFill(['status' => 'hadir'])->save();
    }

    public function test_load_penuh_dashboard_admin_mengandung_semua_prop(): void
    {
        $this->seedData();

        $this->actingAs($this->superadmin, 'web_admin')
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('roleType', 'Admin')
                ->has('stats')
                ->has('trends')
                ->has('jadwalHariIni')
                ->has('presensiHariIni')
                ->has('kontrakBerakhir'));
    }

    public function test_partial_reload_hanya_mengembalikan_prop_live(): void
    {
        $this->seedData();

        // Simulasi polling 60 detik: minta hanya 4 prop live.
        // Partial reload mengembalikan JSON Inertia murni (bukan view) —
        // assertInertia tidak bisa dipakai, gunakan assertJson.
        $this->actingAs($this->superadmin, 'web_admin')
            ->get(route('dashboard'), $this->partialHeaders())
            ->assertOk()
            ->assertJsonPath('component', 'Dashboard')
            ->assertJsonStructure(['props' => ['stats', 'trends', 'jadwalHariIni', 'presensiHariIni']])
            // Prop cached TIDAK dikirim ulang saat partial reload (hemat bandwidth)
            ->assertJsonMissingPath('props.kontrakBerakhir');
    }

    public function test_partial_reload_stats_kehadiran_hari_ini_segar(): void
    {
        $this->seedData();

        $this->actingAs($this->superadmin, 'web_admin')
            ->get(route('dashboard'), $this->partialHeaders())
            ->assertOk()
            ->assertJsonPath('component', 'Dashboard')
            ->assertJsonPath('props.stats.hadir_hari_ini_count', 1)
            ->assertJsonPath('props.stats.pegawai_dijadwalkan', 1)
            ->assertJsonPath('props.stats.hadir_percentage', 100)
            ->assertJsonStructure(['props' => ['jadwalHariIni' => [], 'presensiHariIni' => []]]);
    }
}
