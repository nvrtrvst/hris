<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\UnitSekolah;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PimpinanRoleTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $sd;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->sd = UnitSekolah::create(['nama' => 'SD Uji', 'singkatan' => 'SD']);
    }

    private function makePegawai(string $nama, ?Pegawai $atasan = null): Pegawai
    {
        $jabatan = Jabatan::firstOrCreate(['nama' => 'Guru Mata Pelajaran'], ['is_guru' => true]);

        $pegawai = Pegawai::create([
            'nik' => '3273'.str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT),
            'nama_lengkap' => $nama,
            'jenis_kelamin' => 'L',
            'status_kepegawaian' => 'honorer',
            'status_aktif' => 'aktif',
            'tanggal_mulai_kerja' => '2020-01-01',
            'tanggal_akhir_kontrak' => now()->addDays(15),
            'atasan_langsung_id' => $atasan?->id,
        ]);
        $pegawai->units()->attach($this->sd->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    private function makePimpinan(Pegawai $pegawai): User
    {
        $user = User::factory()->create(['unit_sekolah_id' => $this->sd->id]);
        $user->assignRole('pimpinan');
        $pegawai->update(['user_id' => $user->id]);

        return $user;
    }

    public function test_pimpinan_index_scoped_to_bawahan_only(): void
    {
        $kepsek = $this->makePegawai('Kepsek SD');
        $bawahan = $this->makePegawai('Guru Bawahan', $kepsek);
        $this->makePegawai('Guru Bukan Bawahan'); // atasan NULL

        $user = $this->makePimpinan($kepsek);

        $this->actingAs($user)
            ->get(route('pegawai.index'))
            ->assertInertia(fn ($page) => $page->component('Pegawai/Index')
                ->has('pegawais.data', 1)
                ->where('pegawais.data.0.nama_lengkap', 'Guru Bawahan'));
    }

    public function test_pimpinan_show_403_for_non_bawahan(): void
    {
        $kepsek = $this->makePegawai('Kepsek SD');
        $bawahan = $this->makePegawai('Guru Bawahan', $kepsek);
        $orangLain = $this->makePegawai('Guru Orang Lain');

        $user = $this->makePimpinan($kepsek);

        $this->actingAs($user)->get(route('pegawai.show', $bawahan->id))->assertOk();
        $this->actingAs($user)->get(route('pegawai.show', $orangLain->id))->assertForbidden();
    }

    public function test_pimpinan_store_forbidden(): void
    {
        $kepsek = $this->makePegawai('Kepsek SD');
        $user = $this->makePimpinan($kepsek);

        $this->actingAs($user)
            ->post(route('pegawai.store'), [
                'nama_lengkap' => 'Coba Tambah',
                'email' => 'coba@yayasan.com',
                'password' => 'rahasia123',
                'no_hp' => '081200000001',
                'unit_sekolah_id' => $this->sd->id,
                'jabatan_id' => Jabatan::first()->id,
                'status_kepegawaian' => 'honorer',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('pegawai', ['nama_lengkap' => 'Coba Tambah']);
    }

    public function test_pimpinan_dashboard_kontrak_scoped_to_bawahan(): void
    {
        $kepsek = $this->makePegawai('Kepsek SD');
        $bawahan = $this->makePegawai('Guru Bawahan', $kepsek);
        $this->makePegawai('Guru Bukan Bawahan');

        $user = $this->makePimpinan($kepsek);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->component('Dashboard')
                ->has('kontrakBerakhir', 1)
                ->where('kontrakBerakhir.0.nama_lengkap', 'Guru Bawahan'));
    }
}
