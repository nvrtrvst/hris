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
use Tests\TestCase;

class LaporanJadwalFilterTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->unit = UnitSekolah::create([
            'nama' => 'SMP Laporan Jadwal',
            'singkatan' => 'SMPLJ',
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

    private function makePegawai(string $nik, string $nama): Pegawai
    {
        $jabatan = Jabatan::create(['nama' => 'Guru '.$nama]);
        $user = User::factory()->create();
        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => $nik,
            'nama_lengkap' => $nama,
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '1992-03-03',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'kawin',
            'jumlah_tanggungan' => 0,
            'alamat' => 'Jl. Laporan Test No. 1',
            'no_hp' => '0812'.substr($nik, -6),
            'status_kepegawaian' => 'guru_pemula',
            'tmt_mengajar' => '2021-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
        ]);
        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    private function makeJadwal(Pegawai $pegawai, string $jenis): Jadwal
    {
        return Jadwal::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'hari' => 'Senin',
            'jam_mulai' => '07:30:00',
            'jam_selesai' => '15:00:00',
            'jenis_jadwal' => $jenis,
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);
    }

    private function preview(array $extra = []): string
    {
        return '/laporan/preview?'.http_build_query(array_merge([
            'type' => 'presensi',
            'start_date' => Carbon::today()->startOfMonth()->toDateString(),
            'end_date' => Carbon::today()->toDateString(),
        ], $extra));
    }

    public function test_kolom_jenis_jadwal_terisi_piket(): void
    {
        $pegawai = $this->makePegawai('1111111111', 'Guru Piket');
        $jadwal = $this->makeJadwal($pegawai, 'piket');
        Presensi::create([
            'pegawai_id' => $pegawai->id,
            'jadwal_id' => $jadwal->id,
            'unit_sekolah_id' => $this->unit->id,
            'tipe_presensi' => 'mengajar',
            'tanggal' => Carbon::today()->toDateString(),
            'jam_masuk' => '07:30:00',
        ]);

        $this->actingAs($this->superadmin)
            ->get($this->preview())
            ->assertOk()
            ->assertJson(fn ($json) => $json
                ->where('headings.5', 'Jenis Jadwal')
                ->where('data.0.1', 'Guru Piket')
                ->where('data.0.5', 'Piket')
                ->etc());
    }

    public function test_filter_piket_hanya_baris_piket(): void
    {
        $piket = $this->makePegawai('1111111111', 'Guru Piket');
        $ngajar = $this->makePegawai('2222222222', 'Guru Ngajar');
        $jadwalPiket = $this->makeJadwal($piket, 'piket');
        $jadwalNgajar = $this->makeJadwal($ngajar, 'mengajar');
        foreach ([[$piket, $jadwalPiket], [$ngajar, $jadwalNgajar]] as [$pg, $jd]) {
            Presensi::create([
                'pegawai_id' => $pg->id,
                'jadwal_id' => $jd->id,
                'unit_sekolah_id' => $this->unit->id,
                'tipe_presensi' => 'mengajar',
                'tanggal' => Carbon::today()->toDateString(),
                'jam_masuk' => '07:30:00',
            ]);
        }

        $this->actingAs($this->superadmin)
            ->get($this->preview(['jadwal_filter' => 'piket']))
            ->assertOk()
            ->assertJson(fn ($json) => $json
                ->has('data', 1)
                ->where('data.0.1', 'Guru Piket')
                ->where('data.0.5', 'Piket')
                ->etc());
    }

    public function test_filter_jadwal_invalid_ditolak(): void
    {
        $this->actingAs($this->superadmin)
            ->get($this->preview(['jadwal_filter' => 'ngawur']))
            ->assertSessionHasErrors('jadwal_filter');
    }
}
