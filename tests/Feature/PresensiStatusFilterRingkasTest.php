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

class PresensiStatusFilterRingkasTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->unit = UnitSekolah::create([
            'nama' => 'SMP Status',
            'singkatan' => 'SMPS',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'toleransi_menit' => 0,
            'toleransi_slide_menit' => 15,
        ]);

        $this->superadmin = User::factory()->create(['role' => 'superadmin']);
        $this->superadmin->assignRole('superadmin');
    }

    private function makePegawai(): Pegawai
    {
        $jabatan = Jabatan::create(['nama' => 'Guru']);
        $user = User::factory()->create();
        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '1111222233339999',
            'nama_lengkap' => 'Guru Status',
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '1992-03-03',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'kawin',
            'jumlah_tanggungan' => 0,
            'alamat' => 'Jl. Status Test No. 1',
            'no_hp' => '081211223399',
            'status_kepegawaian' => 'guru_pemula',
            'tmt_mengajar' => '2021-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
        ]);
        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    private function makePresensi(Pegawai $pegawai, string $tipe, string $status, ?Jadwal $jadwal = null): Presensi
    {
        $presensi = Presensi::create([
            'pegawai_id' => $pegawai->id,
            'jadwal_id' => $jadwal?->id,
            'unit_sekolah_id' => $this->unit->id,
            'tipe_presensi' => $tipe,
            'tanggal' => Carbon::today()->toDateString(),
            'jam_masuk' => $jadwal ? $jadwal->jam_mulai : '07:00:00',
        ]);
        $presensi->forceFill(['status' => $status])->save();

        return $presensi;
    }

    private function hariIniIndo(): string
    {
        return [
            'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
        ][Carbon::now()->format('l')];
    }

    private function seedKantorTelatDanMengajarHadir(): array
    {
        $pegawai = $this->makePegawai();
        $jadwal = Jadwal::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'hari' => $this->hariIniIndo(),
            'jam_mulai' => '08:00:00',
            'jam_selesai' => '08:45:00',
            'jenis_jadwal' => 'mengajar',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);

        $kantor = $this->makePresensi($pegawai, 'kantor', 'telat');
        $mengajar = $this->makePresensi($pegawai, 'mengajar', 'hadir', $jadwal);

        return [$pegawai, $kantor, $mengajar];
    }

    private function params(array $overrides = []): array
    {
        return array_merge([
            'display_mode' => 'ringkas',
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->toDateString(),
        ], $overrides);
    }

    public function test_filter_hadir_ringkas_mempertahankan_semua_baris_grup(): void
    {
        [$pegawai, $kantor, $mengajar] = $this->seedKantorTelatDanMengajarHadir();

        $this->actingAs($this->superadmin, 'web_admin')
            ->get(route('presensi.index', $this->params(['status_filter' => 'hadir'])))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Presensi/Index')
                ->where('presensis.total', 1)
                ->has('presensis.data', 2)
                ->where('presensis.data.0.id', $kantor->id)
                ->where('presensis.data.1.id', $mengajar->id)
                ->where('stats.hadir', 1)
                ->where('stats.telat', 0));
    }

    public function test_filter_detail_tetap_memfilter_baris(): void
    {
        [, , $mengajar] = $this->seedKantorTelatDanMengajarHadir();

        $this->actingAs($this->superadmin, 'web_admin')
            ->get(route('presensi.index', $this->params([
                'display_mode' => 'detail',
                'status_filter' => 'hadir',
            ])))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Presensi/Index')
                ->where('presensis.total', 1)
                ->has('presensis.data', 1)
                ->where('presensis.data.0.id', $mengajar->id));
    }

    public function test_filter_telat_tidak_mengambil_grup_yang_efektif_hadir(): void
    {
        $this->seedKantorTelatDanMengajarHadir();

        $this->actingAs($this->superadmin, 'web_admin')
            ->get(route('presensi.index', $this->params(['status_filter' => 'telat'])))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Presensi/Index')
                ->where('presensis.total', 0)
                ->has('presensis.data', 0));
    }
}
