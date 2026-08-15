<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Jadwal;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JadwalPresensiLiveTest extends TestCase
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

    private function partialHeaders(string $data): array
    {
        $headers = [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'Jadwal/Index',
            'X-Inertia-Partial-Data' => $data,
        ];
        if (($version = $this->inertiaVersion()) !== null) {
            $headers['X-Inertia-Version'] = $version;
        }

        return $headers;
    }

    private function seedData(): array
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

        $mapel = MataPelajaran::create(['nama' => 'Matematika']);

        $hariMap = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
        $hariIni = $hariMap[Carbon::now()->format('l')];

        $jadwal = Jadwal::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $unit->id,
            'mata_pelajaran_id' => $mapel->id,
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
        // status di-$guarded — set langsung setelah create
        $presensi->forceFill(['status' => 'hadir'])->save();

        return compact('jadwal', 'presensi');
    }

    public function test_load_penuh_jadwal_admin_mengandung_presensi_hari_ini(): void
    {
        $this->seedData();

        $this->actingAs($this->superadmin, 'web_admin')
            ->get(route('jadwal.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Jadwal/Index')
                ->has('jadwals', 1)
                ->has('presensiHariIni', 1)
                ->where('presensiHariIni.0.jadwal_id', function ($val) {
                    return $val !== null;
                })
                ->where('presensiHariIni.0.status', 'hadir'));
    }

    public function test_partial_reload_jadwal_hanya_mengembalikan_presensi_hari_ini(): void
    {
        $this->seedData();

        // Simulasi polling 60 detik: minta hanya prop presensiHariIni.
        $this->actingAs($this->superadmin, 'web_admin')
            ->get(route('jadwal.index'), $this->partialHeaders('presensiHariIni'))
            ->assertOk()
            ->assertJsonPath('component', 'Jadwal/Index')
            ->assertJsonStructure(['props' => ['presensiHariIni']])
            // Prop besar (jadwals, pegawais) TIDAK dikirim ulang — hemat bandwidth
            ->assertJsonMissingPath('props.jadwals')
            ->assertJsonMissingPath('props.pegawais');
    }

    public function test_partial_reload_presensi_hari_ini_berisi_data_segar(): void
    {
        [$jadwal] = array_values($this->seedData());

        $this->actingAs($this->superadmin, 'web_admin')
            ->get(route('jadwal.index'), $this->partialHeaders('presensiHariIni'))
            ->assertOk()
            ->assertJsonPath('component', 'Jadwal/Index')
            ->assertJsonPath('props.presensiHariIni.0.jadwal_id', $jadwal->id)
            ->assertJsonPath('props.presensiHariIni.0.status', 'hadir');
    }
}
