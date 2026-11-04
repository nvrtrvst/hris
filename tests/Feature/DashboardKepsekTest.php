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

class DashboardKepsekTest extends TestCase
{
    use RefreshDatabase;

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
            'X-Inertia-Partial-Data' => 'kepsekDaily',
        ];
        if (($version = $this->inertiaVersion()) !== null) {
            $headers['X-Inertia-Version'] = $version;
        }

        return $headers;
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
            'toleransi_slide_menit' => 15,
        ]);
    }

    private function makePegawai(string $nama, UnitSekolah $unit, Jabatan $jabatan, ?User $user = null): Pegawai
    {
        $pegawai = Pegawai::create([
            'user_id' => $user?->id ?? User::factory()->create()->id,
            'nik' => (string) random_int(1000000000000000, 9999999999999999),
            'nama_lengkap' => $nama,
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '1992-03-03',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'kawin',
            'jumlah_tanggungan' => 0,
            'alamat' => 'Jl. Dashboard Test No. 1',
            'no_hp' => '081211223344',
            'status_kepegawaian' => 'guru_pemula',
            'tmt_mengajar' => '2021-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
        ]);

        $pegawai->units()->attach($unit->id, [
            'jabatan_id' => $jabatan->id,
            'is_primary' => true,
        ]);
        $pegawai->load('units');

        return $pegawai;
    }

    private function seedKepsek(): User
    {
        $this->seed(RolePermissionSeeder::class);

        Carbon::setTestNow(Carbon::parse('2026-10-20 08:00:00'));
        $this->beforeApplicationDestroyed(fn () => Carbon::setTestNow());

        $unit = $this->makeUnit('SMP Dashboard', 'SMPD');
        $otherUnit = $this->makeUnit('SMK Lain', 'SMKL');

        $kepala = Jabatan::create(['nama' => 'Kepala Sekolah']);
        $guru = Jabatan::create(['nama' => 'Guru']);

        $kepsekUser = User::factory()->create([
            'name' => 'Kepala Dashboard',
            'unit_sekolah_id' => $unit->id,
        ]);
        $kepsekUser->assignRole('pimpinan');
        $this->makePegawai('Kepala Dashboard', $unit, $kepala, $kepsekUser);

        $guruUnit = $this->makePegawai('Guru Unit', $unit, $guru);
        $guruLain = $this->makePegawai('Guru Unit Lain', $otherUnit, $guru);

        $hariMap = [
            'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
        ];
        $hari = $hariMap[Carbon::now('Asia/Jakarta')->format('l')];

        foreach ([$guruUnit, $guruLain] as $pegawai) {
            Jadwal::create([
                'pegawai_id' => $pegawai->id,
                'unit_sekolah_id' => $pegawai->units->first()->id,
                'kelas_label' => 'X-1',
                'hari' => $hari,
                'jam_mulai' => '08:00:00',
                'jam_selesai' => '08:45:00',
                'jenis_jadwal' => 'mengajar',
                'tahun_ajaran' => '2026/2027',
                'semester' => 1,
            ]);
        }

        $jadwalUnit = Jadwal::where('pegawai_id', $guruUnit->id)->first();
        $presensi = Presensi::create([
            'pegawai_id' => $guruUnit->id,
            'jadwal_id' => $jadwalUnit->id,
            'unit_sekolah_id' => $unit->id,
            'tipe_presensi' => 'mengajar',
            'tanggal' => Carbon::today('Asia/Jakarta')->toDateString(),
            'jam_masuk' => '08:00:00',
        ]);
        $presensi->forceFill(['status' => 'hadir'])->save();

        return $kepsekUser;
    }

    public function test_kepsek_mendapat_snapshot_harian_terpisah_dan_ter_scoped_unit(): void
    {
        $kepsek = $this->seedKepsek();

        $this->actingAs($kepsek, 'web_admin')
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('roleType', 'Kepala Sekolah')
                ->where('kepsekDaily.unit_nama', 'SMP Dashboard')
                ->where('kepsekDaily.guru.dijadwalkan', 1)
                ->where('kepsekDaily.guru.hadir', 1)
                ->where('kepsekDaily.jp.total', 1)
                ->where('kepsekDaily.jp.kosong', 0)
                ->where('kepsekDaily.jp.tercatat', 1)
                ->has('kepsekDaily.piket')
                ->has('kepsekDaily.siswa')
                ->has('pengumuman')
                ->has('stats'));
    }

    public function test_polling_kepsek_hanya_mengembalikan_snapshot(): void
    {
        $kepsek = $this->seedKepsek();

        $this->actingAs($kepsek, 'web_admin')
            ->get(route('dashboard'), $this->partialHeaders())
            ->assertOk()
            ->assertJsonPath('component', 'Dashboard')
            ->assertJsonPath('props.kepsekDaily.guru.dijadwalkan', 1)
            ->assertJsonMissingPath('props.jadwalHariIni')
            ->assertJsonMissingPath('props.trends');
    }
}
