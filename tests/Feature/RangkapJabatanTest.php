<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\UnitSekolah;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RangkapJabatanTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private Jabatan $guru;

    private Jabatan $wakasek;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->unit = UnitSekolah::create([
            'nama' => 'SMP Test', 'singkatan' => 'SMP',
            'latitude' => -6.2, 'longitude' => 106.8, 'radius_meter' => 100,
        ]);
        $this->guru = Jabatan::create(['nama' => 'Guru']);
        $this->wakasek = Jabatan::create(['nama' => 'Wakasek Kurikulum']);
        DB::table('jabatan')->where('id', $this->guru->id)->update(['hierarchy_level' => 5]);
        DB::table('jabatan')->where('id', $this->wakasek->id)->update(['hierarchy_level' => 2]);
    }

    private function makePegawai(string $nama = 'Guru Rangkap'): Pegawai
    {
        $user = User::factory()->create();
        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '1234567890'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT),
            'nama_lengkap' => $nama,
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'kawin',
            'jumlah_tanggungan' => 0,
            'alamat' => 'Jl. Test',
            'no_hp' => '081234567890',
            'status_kepegawaian' => 'guru_tidak_tetap',
            'tmt_mengajar' => '2020-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
            'wajib_kantor' => false,
        ]);

        return $pegawai;
    }

    public function test_primer_struktural_menang_atas_guru(): void
    {
        $pegawai = $this->makePegawai();
        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $this->guru->id, 'is_primary' => true]);
        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $this->wakasek->id, 'is_primary' => false]);
        $pegawai->load('units');

        $this->assertSame('Wakasek Kurikulum', $pegawai->jabatanPrimer()->nama);
    }

    public function test_primer_tunggal_tidak_berubah(): void
    {
        $pegawai = $this->makePegawai();
        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $this->guru->id, 'is_primary' => true]);
        $pegawai->load('units');

        $this->assertSame('Guru', $pegawai->jabatanPrimer()->nama);
    }

    public function test_update_simpan_dua_jabatan_seunit(): void
    {
        $admin = User::factory()->create(['role' => 'superadmin']);
        $admin->assignRole('superadmin');
        $pegawai = $this->makePegawai();
        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $this->guru->id, 'is_primary' => true]);

        $response = $this->actingAs($admin, 'web_admin')->put(route('pegawai.update', $pegawai->id), [
            'nama_lengkap' => $pegawai->nama_lengkap,
            'nik' => '1234567890123456',
            'email' => $pegawai->user->email,
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'alamat' => 'Jl. Test',
            'no_hp' => '081234567890',
            'status_kepegawaian' => 'guru_tidak_tetap',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
            'lokasi_pilihan' => 'primary',
            'units' => [
                ['unit_sekolah_id' => $this->unit->id, 'jabatan_id' => $this->guru->id, 'is_primary' => true],
                ['unit_sekolah_id' => $this->unit->id, 'jabatan_id' => $this->wakasek->id, 'is_primary' => false],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('pegawai_unit', 2);
        $this->assertDatabaseHas('pegawai_unit', [
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'jabatan_id' => $this->wakasek->id,
        ]);
    }

    public function test_update_hapus_jabatan_yang_dilepas(): void
    {
        $admin = User::factory()->create(['role' => 'superadmin']);
        $admin->assignRole('superadmin');
        $pegawai = $this->makePegawai();
        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $this->guru->id, 'is_primary' => true]);
        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $this->wakasek->id, 'is_primary' => false]);

        $this->actingAs($admin, 'web_admin')->put(route('pegawai.update', $pegawai->id), [
            'nama_lengkap' => $pegawai->nama_lengkap,
            'nik' => '1234567890123456',
            'email' => $pegawai->user->email,
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'alamat' => 'Jl. Test',
            'no_hp' => '081234567890',
            'status_kepegawaian' => 'guru_tidak_tetap',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
            'lokasi_pilihan' => 'primary',
            'units' => [
                ['unit_sekolah_id' => $this->unit->id, 'jabatan_id' => $this->guru->id, 'is_primary' => true],
            ],
        ])->assertRedirect();

        $this->assertDatabaseCount('pegawai_unit', 1);
        $this->assertDatabaseMissing('pegawai_unit', [
            'pegawai_id' => $pegawai->id,
            'jabatan_id' => $this->wakasek->id,
        ]);
    }
}
