<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Jadwal;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\PegawaiMapel;
use App\Models\Presensi;
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
            'status_kepegawaian' => 'guru_pemula',
            'status_aktif' => 'aktif',
            'tmt_mengajar' => '2020-01-01',
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
                'status_kepegawaian' => 'guru_pemula',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('pegawai', ['nama_lengkap' => 'Coba Tambah']);
    }

    public function test_pimpinan_dashboard_kontrak_scoped_to_unit_for_kepsek(): void
    {
        $kepsek = $this->makePegawai('Kepsek SD');
        // Attach "Kepala Sekolah" jabatan for isKepsek() to return true.
        $jabatanKepsek = Jabatan::firstOrCreate(['nama' => 'Kepala Sekolah']);
        $kepsek->units()->updateExistingPivot($this->sd->id, ['jabatan_id' => $jabatanKepsek->id]);
        $bawahan = $this->makePegawai('Guru Bawahan', $kepsek);
        $this->makePegawai('Guru Bukan Bawahan');

        $user = $this->makePimpinan($kepsek);

        // Kepsek sees ALL pegawai in unit (unit-scoped).
        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->component('Dashboard')
                ->has('kontrakBerakhir', 3));
    }

    public function test_pimpinan_presensi_index_scoped_to_bawahan(): void
    {
        $kepsek = $this->makePegawai('Kepsek SD');
        $jabatanKepsek = Jabatan::firstOrCreate(['nama' => 'Kepala Sekolah']);
        $kepsek->units()->updateExistingPivot($this->sd->id, ['jabatan_id' => $jabatanKepsek->id]);
        $bawahan = $this->makePegawai('Guru Bawahan', $kepsek);
        $orangLain = $this->makePegawai('Guru Bukan Bawahan');

        Presensi::create([
            'pegawai_id' => $bawahan->id,
            'unit_sekolah_id' => $this->sd->id,
            'tanggal' => now()->toDateString(),
            'jam_masuk' => '07:00:00',
            'status' => 'hadir',
        ]);
        Presensi::create([
            'pegawai_id' => $orangLain->id,
            'unit_sekolah_id' => $this->sd->id,
            'tanggal' => now()->toDateString(),
            'jam_masuk' => '07:00:00',
            'status' => 'hadir',
        ]);

        $user = $this->makePimpinan($kepsek);

        // Kepsek sees ALL presensi in unit (unit-scoped, not bawahan-only).
        $this->actingAs($user)
            ->get(route('presensi.index'))
            ->assertInertia(fn ($page) => $page->component('Presensi/Index')
                ->has('presensis.data', 2));
    }

    public function test_pimpinan_jadwal_index_scoped_to_bawahan(): void
    {
        $kepsek = $this->makePegawai('Kepsek SD');
        $bawahan = $this->makePegawai('Guru Bawahan', $kepsek);
        $orangLain = $this->makePegawai('Guru Bukan Bawahan');

        $mapel = MataPelajaran::firstOrCreate(['nama' => 'Matematika']);
        $bawahanMapel = PegawaiMapel::create([
            'pegawai_id' => $bawahan->id,
            'mata_pelajaran_id' => $mapel->id,
            'unit_sekolah_id' => $this->sd->id,
        ]);
        $orangLainMapel = PegawaiMapel::create([
            'pegawai_id' => $orangLain->id,
            'mata_pelajaran_id' => $mapel->id,
            'unit_sekolah_id' => $this->sd->id,
        ]);
        Jadwal::create([
            'pegawai_id' => $bawahan->id,
            'unit_sekolah_id' => $this->sd->id,
            'pegawai_mapel_id' => $bawahanMapel->id,
            'hari' => 'Senin',
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '08:00:00',
            'jenis_jadwal' => 'reguler',
            'tahun_ajaran' => '2026/2027',
            'semester' => 'Ganjil',
        ]);
        Jadwal::create([
            'pegawai_id' => $orangLain->id,
            'unit_sekolah_id' => $this->sd->id,
            'pegawai_mapel_id' => $orangLainMapel->id,
            'hari' => 'Senin',
            'jam_mulai' => '09:00:00',
            'jam_selesai' => '10:00:00',
            'jenis_jadwal' => 'reguler',
            'tahun_ajaran' => '2026/2027',
            'semester' => 'Ganjil',
        ]);

        $user = $this->makePimpinan($kepsek);

        $this->actingAs($user)
            ->get(route('jadwal.index'))
            ->assertInertia(fn ($page) => $page->component('Jadwal/Index')
                ->has('jadwals', 1)
                ->where('jadwals.0.pegawai.nama_lengkap', 'Guru Bawahan'));
    }

    public function test_auto_assign_pimpinan_role_when_jabatan_is_supervisor(): void
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        $kepsekJabatan = Jabatan::firstOrCreate(
            ['nama' => 'Kepala Sekolah'],
            ['is_supervisor' => true]
        );

        $this->actingAs($superadmin)
            ->post(route('pegawai.store'), [
                'nama_lengkap' => 'Kepsek Auto',
                'email' => 'kepsek.auto@yayasan.com',
                'no_hp' => '081200000099',
                'unit_sekolah_id' => $this->sd->id,
                'jabatan_id' => $kepsekJabatan->id,
                'role' => 'pegawai', // default, should be overridden
                'status_kepegawaian' => 'guru_pemula',
            ])
            ->assertRedirect();

        $user = User::where('email', 'kepsek.auto@yayasan.com')->first();
        $this->assertTrue($user->hasRole('pimpinan'), 'User dengan jabatan supervisor harus otomatis dapat role pimpinan.');
        $this->assertFalse($user->hasRole('pegawai'), 'Role pegawai tidak boleh tetap ada setelah override.');
    }

    public function test_admin_unit_role_not_overridden_by_supervisor_jabatan(): void
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        $kepsekJabatan = Jabatan::firstOrCreate(
            ['nama' => 'Kepala Sekolah'],
            ['is_supervisor' => true]
        );

        $this->actingAs($superadmin)
            ->post(route('pegawai.store'), [
                'nama_lengkap' => 'Kepsek Admin Unit',
                'email' => 'kepsek.admin@yayasan.com',
                'no_hp' => '081200000088',
                'unit_sekolah_id' => $this->sd->id,
                'jabatan_id' => $kepsekJabatan->id,
                'role' => 'admin_unit', // explicit choice, should NOT be overridden
                'status_kepegawaian' => 'guru_pemula',
            ])
            ->assertRedirect();

        $user = User::where('email', 'kepsek.admin@yayasan.com')->first();
        $this->assertTrue($user->hasRole('admin_unit'), 'Role admin_unit pilihan admin harus dipertahankan.');
        $this->assertFalse($user->hasRole('pimpinan'), 'Role pimpinan tidak boleh di-override dari admin_unit.');
    }
}
