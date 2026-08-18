<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\UnitSekolah;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PengajuanIzinJenisFilterTest extends TestCase
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
            'nama' => 'SMP Izin Jenis',
            'singkatan' => 'SMPIJ',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'toleransi_menit' => 0,
            'toleransi_tap_menit' => 15,
        ]);

        $this->superadmin = User::factory()->create(['role' => 'superadmin']);
        $this->superadmin->assignRole('superadmin');

        $this->pendidik = $this->makePegawai('1111111111', 'Guru Pengaju', 'Guru Mata Pelajaran', true);
        $this->kependidikan = $this->makePegawai('2222222222', 'TU Pengaju', 'Tenaga Administrasi', false);

        $this->makePengajuan($this->pendidik, 'Cuti tahunan guru');
        $this->makePengajuan($this->kependidikan, 'Izin keperluan keluarga TU');
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
            'alamat_ktp' => 'Jl. Izin Test No. 1',
            'no_hp' => '0812'.substr($nik, -6),
            'status_kepegawaian' => 'honorer',
            'tanggal_mulai_kerja' => '2021-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
        ]);
        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    private function makePengajuan(Pegawai $pegawai, string $alasan): PengajuanIzin
    {
        return PengajuanIzin::create([
            'pegawai_id' => $pegawai->id,
            'jenis_izin' => 'cuti',
            'tanggal_mulai' => Carbon::today()->toDateString(),
            'tanggal_selesai' => Carbon::today()->toDateString(),
            'alasan' => $alasan,
        ]);
    }

    private function indexUrl(string $jenis = ''): string
    {
        $params = ['tab' => 'semua'];
        if ($jenis !== '') {
            $params['jenis_filter'] = $jenis;
        }

        return '/pengajuan-izin?'.http_build_query($params);
    }

    private function namaPegawai(array $rows): array
    {
        return array_map(fn ($row) => $row['pegawai']['nama_lengkap'] ?? null, $rows);
    }

    public function test_pendidik_hanya_menampilkan_pengajuan_pegawai_berjabatan_guru(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->get($this->indexUrl('pendidik'))
            ->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->component('PengajuanIzin/Index')
            ->where('filters.jenis_filter', 'pendidik')
            ->has('pengajuans.data', 1)
            ->where('pengajuans.data.0.pegawai.nama_lengkap', 'Guru Pengaju')
            ->etc());
    }

    public function test_kependidikan_hanya_menampilkan_pengajuan_pegawai_tanpa_jabatan_guru(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->get($this->indexUrl('kependidikan'))
            ->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->component('PengajuanIzin/Index')
            ->where('filters.jenis_filter', 'kependidikan')
            ->has('pengajuans.data', 1)
            ->where('pengajuans.data.0.pegawai.nama_lengkap', 'TU Pengaju')
            ->etc());
    }

    public function test_tanpa_filter_menampilkan_semua_jenis(): void
    {
        $this->actingAs($this->superadmin)
            ->get($this->indexUrl())
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PengajuanIzin/Index')
                ->has('pengajuans.data', 2)
                ->etc());
    }

    public function test_nilai_filter_invalid_diabaikan_tanpa_error(): void
    {
        // Nilai di luar whitelist tidak memfilter (dan tidak error).
        $this->actingAs($this->superadmin)
            ->get($this->indexUrl('bukan-jenis'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PengajuanIzin/Index')
                ->has('pengajuans.data', 2)
                ->etc());
    }
}
