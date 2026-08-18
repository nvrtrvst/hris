<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaporanJenisFilterTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private User $superadmin;

    private Pegawai $pendidik;

    private Pegawai $kependidikan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->unit = UnitSekolah::create([
            'nama' => 'SMP Laporan Jenis',
            'singkatan' => 'SMPLJ',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'toleransi_menit' => 0,
            'toleransi_tap_menit' => 15,
        ]);

        $this->superadmin = User::factory()->create(['role' => 'superadmin']);
        $this->superadmin->assignRole('superadmin');

        // Pendidik: jabatan guru (is_guru = true)
        $this->pendidik = $this->makePegawai('1111111111', 'Guru Pendidik', 'Guru Mata Pelajaran', true);
        // Tenaga kependidikan: jabatan non-guru (TU)
        $this->kependidikan = $this->makePegawai('2222222222', 'Staf Tata Usaha', 'Tenaga Administrasi', false);

        $this->makePresensi($this->pendidik);
        $this->makePresensi($this->kependidikan);
    }

    private function makePegawai(string $nik, string $nama, string $namaJabatan, bool $isGuru): Pegawai
    {
        $jabatan = Jabatan::create(['nama' => $namaJabatan, 'is_guru' => $isGuru]);
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
            'alamat_ktp' => 'Jl. Laporan Test No. 1',
            'no_hp' => '0812'.substr($nik, -6),
            'status_kepegawaian' => 'honorer',
            'tanggal_mulai_kerja' => '2021-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
        ]);
        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    private function makePresensi(Pegawai $pegawai): Presensi
    {
        return Presensi::create([
            'pegawai_id' => $pegawai->id,
            'jadwal_id' => null,
            'unit_sekolah_id' => $this->unit->id,
            'tipe_presensi' => 'kantor',
            'tanggal' => Carbon::today()->toDateString(),
            'jam_masuk' => '07:00:00',
            'jam_keluar' => '15:30:00',
            'status' => 'hadir',
        ]);
    }

    private function previewUrl(string $jenis = ''): string
    {
        $params = [
            'type' => 'presensi',
            'start_date' => Carbon::today()->startOfMonth()->toDateString(),
            'end_date' => Carbon::today()->toDateString(),
        ];

        if ($jenis !== '') {
            $params['jenis_filter'] = $jenis;
        }

        return '/laporan/preview?'.http_build_query($params);
    }

    private function namaKolom(array $rows): array
    {
        // Kolom ke-2 (index 1) pada laporan presensi adalah nama pegawai.
        return array_map(fn (array $row) => $row[1] ?? null, $rows);
    }

    public function test_preview_pendidik_hanya_menampilkan_pegawai_berjabatan_guru(): void
    {
        $this->actingAs($this->superadmin)
            ->get($this->previewUrl('pendidik'))
            ->assertOk()
            ->assertJson(fn ($json) => $json
                ->has('data', 1)
                ->where('data.0.1', 'Guru Pendidik')
                ->where('data.0.3', 'Pendidik')
                ->etc());
    }

    public function test_preview_kependidikan_hanya_menampilkan_pegawai_tanpa_jabatan_guru(): void
    {
        $this->actingAs($this->superadmin)
            ->get($this->previewUrl('kependidikan'))
            ->assertOk()
            ->assertJson(fn ($json) => $json
                ->has('data', 1)
                ->where('data.0.1', 'Staf Tata Usaha')
                ->where('data.0.3', 'Tenaga Kependidikan')
                ->etc());
    }

    public function test_preview_tanpa_filter_menampilkan_semua_jenis(): void
    {
        $this->actingAs($this->superadmin)
            ->get($this->previewUrl())
            ->assertOk()
            ->assertJson(fn ($json) => $json->has('data', 2)->etc());
    }

    public function test_nilai_filter_invalid_ditolak(): void
    {
        $this->actingAs($this->superadmin)
            ->get($this->previewUrl().'&jenis_filter=invalid')
            ->assertSessionHasErrors('jenis_filter');
    }
}
