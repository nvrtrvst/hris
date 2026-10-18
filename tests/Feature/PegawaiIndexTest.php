<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\UnitSekolah;
use App\Models\User;
use Database\Seeders\JabatanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PegawaiIndexTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unitA;

    private UnitSekolah $unitB;

    private User $superadmin;

    private User $adminUnit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(JabatanSeeder::class);

        $this->unitA = $this->makeUnit('SMP Index');
        $this->unitB = $this->makeUnit('SMA Index');

        $this->superadmin = User::factory()->create(['role' => 'superadmin']);
        $this->superadmin->assignRole('superadmin');

        $this->adminUnit = User::factory()->create(['role' => 'admin_unit', 'unit_sekolah_id' => $this->unitA->id]);
        $this->adminUnit->assignRole('admin_unit');

        $this->makePegawai('1111111111111111', 'Guru Unit A', $this->unitA);
        $this->makePegawai('2222222222222222', 'Guru Unit B', $this->unitB);
    }

    public function test_superadmin_tanpa_filter_unit_diminta_pilih_unit_dan_daftar_kosong(): void
    {
        // Superadmin tidak boleh langsung melihat seluruh pegawai yayasan.
        $this->actingAs($this->superadmin, 'web_admin')
            ->get(route('pegawai.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Pegawai/Index')
                ->where('perluPilihUnit', true)
                ->has('pegawais.data', 0)
                // Kartu statistik tetap menggambarkan seluruh yayasan.
                ->where('stats.total', 2)
                ->where('stats.aktif', 2)
            );
    }

    public function test_superadmin_dengan_unit_terpilih_daftar_terisi(): void
    {
        $this->actingAs($this->superadmin, 'web_admin')
            ->get(route('pegawai.index', ['unit_sekolah_id' => $this->unitA->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('perluPilihUnit', false)
                ->has('pegawais.data', 1)
                ->where('pegawais.data.0.nama_lengkap', 'Guru Unit A')
            );
    }

    public function test_superadmin_sentinel_all_menampilkan_semua_unit(): void
    {
        $this->actingAs($this->superadmin, 'web_admin')
            ->get(route('pegawai.index', ['unit_sekolah_id' => 'all']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('perluPilihUnit', false)
                ->has('pegawais.data', 2)
            );
    }

    public function test_superadmin_filter_lain_selain_unit_tetap_menampilkan_daftar(): void
    {
        // Niat eksplisit lewat pencarian: daftar tetap tampil walau unit belum dipilih.
        $this->actingAs($this->superadmin, 'web_admin')
            ->get(route('pegawai.index', ['search' => 'Guru Unit B']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('perluPilihUnit', false)
                ->has('pegawais.data', 1)
                ->where('pegawais.data.0.nama_lengkap', 'Guru Unit B')
            );
    }

    public function test_admin_unit_tidak_terpengaruh_dan_terbatas_unitnya(): void
    {
        $this->actingAs($this->adminUnit, 'web_admin')
            ->get(route('pegawai.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('perluPilihUnit', false)
                ->has('pegawais.data', 1)
                ->where('pegawais.data.0.nama_lengkap', 'Guru Unit A')
            );
    }

    private function makeUnit(string $nama): UnitSekolah
    {
        return UnitSekolah::create([
            'nama' => $nama,
            'singkatan' => strtoupper(substr($nama, 0, 3)),
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'toleransi_menit' => 0,
            'toleransi_slide_menit' => 15,
        ]);
    }

    private function makePegawai(string $nik, string $nama, UnitSekolah $unit): Pegawai
    {
        $user = User::factory()->create(['role' => 'pegawai']);
        $user->assignRole('pegawai');

        $jabatan = Jabatan::where('nama', 'Guru Mata Pelajaran')->firstOrFail();

        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => $nik,
            'nuptk' => $nik,
            'nama_lengkap' => $nama,
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'Menikah',
            'no_hp' => '081234567890',
            'alamat' => 'Jl. Contoh No. 1',
            'status_kepegawaian' => 'guru_tetap_yayasan',
            'tmt_mengajar' => '2020-01-01',
            'pendidikan_terakhir' => 'S1',
            'status_aktif' => 'aktif',
            'jumlah_tanggungan' => 2,
        ]);
        $pegawai->units()->attach($unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }
}
