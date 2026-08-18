<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\UnitSekolah;
use App\Models\User;
use Database\Seeders\AtasanHierarchySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AtasanHierarchyTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $yayasan;

    private UnitSekolah $sd;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->yayasan = $this->makeUnit('Yayasan Uji', 'YAYASAN');
        $this->sd = $this->makeUnit('SD Uji', 'SD');
    }

    private function makeUnit(string $nama, string $singkatan): UnitSekolah
    {
        return UnitSekolah::create([
            'nama' => $nama,
            'singkatan' => $singkatan,
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
        ]);
    }

    private function makePegawai(string $nama, UnitSekolah $unit, string $namaJabatan, ?User $user = null): Pegawai
    {
        $jabatan = Jabatan::firstOrCreate(['nama' => $namaJabatan], ['is_guru' => str_contains($namaJabatan, 'Guru')]);

        $pegawai = Pegawai::create([
            'user_id' => $user?->id,
            'nik' => '3273'.str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT),
            'nama_lengkap' => $nama,
            'jenis_kelamin' => 'L',
            'status_kepegawaian' => 'tetap',
            'status_aktif' => 'aktif',
            'tanggal_mulai_kerja' => '2020-01-01',
            'wajib_kantor' => true,
        ]);
        $pegawai->units()->attach($unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    // ─────────────────────────── Seeder hierarki ───────────────────────────

    public function test_hierarchy_seeder_assigns_dapodik_structure(): void
    {
        $ketuaYayasan = $this->makePegawai('Ketua Yayasan Uji', $this->yayasan, 'Ketua Yayasan');
        $this->makePegawai('Staf Yayasan', $this->yayasan, 'Tenaga Administrasi (TU)');

        $kepsek = $this->makePegawai('Kepsek SD', $this->sd, 'Kepala Sekolah');
        $kepalaTU = $this->makePegawai('Kepala TU', $this->sd, 'Kepala Tata Usaha');
        $guru = $this->makePegawai('Guru Reza', $this->sd, 'Guru Mata Pelajaran');
        $bendahara = $this->makePegawai('Bendahara SD', $this->sd, 'Bendahara');

        (new AtasanHierarchySeeder())->run();

        $this->assertSame($ketuaYayasan->id, $kepsek->fresh()->atasan_langsung_id);
        $this->assertSame($ketuaYayasan->id, Pegawai::where('nama_lengkap', 'Staf Yayasan')->first()->atasan_langsung_id);
        $this->assertSame($kepsek->id, $kepalaTU->fresh()->atasan_langsung_id);
        $this->assertSame($kepsek->id, $guru->fresh()->atasan_langsung_id);
        $this->assertSame($kepalaTU->id, $bendahara->fresh()->atasan_langsung_id);
        $this->assertNull($ketuaYayasan->fresh()->atasan_langsung_id);
    }

    public function test_hierarchy_seeder_does_not_overwrite_manual_assignment(): void
    {
        $kepsek = $this->makePegawai('Kepsek SD', $this->sd, 'Kepala Sekolah');
        $guru = $this->makePegawai('Guru Reza', $this->sd, 'Guru Mata Pelajaran');
        $guru->update(['atasan_langsung_id' => $kepsek->id]);

        // Atasan guru diubah manual ke sesuatu yang berbeda dari hasil seeder.
        $manual = $this->makePegawai('Atasan Manual', $this->sd, 'Guru Kelas');
        $guru->update(['atasan_langsung_id' => $manual->id]);

        (new AtasanHierarchySeeder())->run();

        // Tidak ditimpa — tetap atasan manual.
        $this->assertSame($manual->id, $guru->fresh()->atasan_langsung_id);
    }

    // ────────────────────────────── Form atasan ─────────────────────────────

    public function test_update_sets_atasan_langsung(): void
    {
        $kepsek = $this->makePegawai('Kepsek SD', $this->sd, 'Kepala Sekolah');
        $guru = $this->makePegawai('Guru Reza', $this->sd, 'Guru Mata Pelajaran');
        $guru->update(['user_id' => User::factory()->create()->id]);

        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        $this->actingAs($superadmin)
            ->put(route('pegawai.update', $guru->id), [
                'nik' => '3273000000000001',
                'nama_lengkap' => 'Guru Reza',
                'email' => $guru->user->email,
                'tempat_lahir' => 'Jakarta',
                'tanggal_lahir' => '1990-01-01',
                'jenis_kelamin' => 'L',
                'agama' => 'Islam',
                'status_pernikahan' => 'Belum Menikah',
                'alamat_ktp' => 'Jl. Uji 1',
                'no_hp' => '081200000001',
                'status_kepegawaian' => 'honorer',
                'status_aktif' => 'aktif',
                'tanggal_mulai_kerja' => '2020-01-01',
                'pendidikan_terakhir' => 'S1',
                'atasan_langsung_id' => $kepsek->id,
            ])
            ->assertRedirect();

        $this->assertSame($kepsek->id, $guru->fresh()->atasan_langsung_id);
    }

    public function test_admin_unit_cannot_assign_atasan_from_other_unit(): void
    {
        $unitLain = $this->makeUnit('SMP Lain', 'SMPL');
        $kepsekLain = $this->makePegawai('Kepsek SMPL', $unitLain, 'Kepala Sekolah');
        $guru = $this->makePegawai('Guru Reza', $this->sd, 'Guru Mata Pelajaran');
        $guru->update(['user_id' => User::factory()->create()->id]);

        $adminUnit = User::factory()->create(['unit_sekolah_id' => $this->sd->id]);
        $adminUnit->assignRole('admin_unit');

        $this->actingAs($adminUnit)
            ->put(route('pegawai.update', $guru->id), [
                'nik' => '3273000000000001',
                'nama_lengkap' => 'Guru Reza',
                'email' => $guru->user->email,
                'tempat_lahir' => 'Jakarta',
                'tanggal_lahir' => '1990-01-01',
                'jenis_kelamin' => 'L',
                'agama' => 'Islam',
                'status_pernikahan' => 'Belum Menikah',
                'alamat_ktp' => 'Jl. Uji 1',
                'no_hp' => '081200000001',
                'status_kepegawaian' => 'honorer',
                'status_aktif' => 'aktif',
                'tanggal_mulai_kerja' => '2020-01-01',
                'pendidikan_terakhir' => 'S1',
                'atasan_langsung_id' => $kepsekLain->id,
            ])
            ->assertSessionHasErrors('atasan_langsung_id');
    }

    // ──────────────────────── Visibilitas kontrak ─────────────────────────

    public function test_show_kontrak_visibility(): void
    {
        $kepsek = $this->makePegawai('Kepsek SD', $this->sd, 'Kepala Sekolah');
        $guru = $this->makePegawai('Guru Honorer', $this->sd, 'Guru Mata Pelajaran');
        $guru->update(['status_kepegawaian' => 'honorer', 'tanggal_akhir_kontrak' => now()->addDays(10), 'atasan_langsung_id' => $kepsek->id]);

        // Superadmin → boleh lihat
        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');
        $this->actingAs($superadmin)
            ->get(route('pegawai.show', $guru->id))
            ->assertInertia(fn ($page) => $page->component('Pegawai/Show')->where('canViewKontrak', true));

        // Atasan langsung → boleh lihat
        $userKepsek = User::factory()->create();
        $kepsek->update(['user_id' => $userKepsek->id]);
        $this->actingAs($userKepsek)
            ->get(route('pegawai.show', $guru->id))
            ->assertInertia(fn ($page) => $page->component('Pegawai/Show')->where('canViewKontrak', true));

        // Admin unit lain di unit yang sama (bukan atasan) → tidak boleh lihat
        $adminUnit = User::factory()->create(['unit_sekolah_id' => $this->sd->id]);
        $adminUnit->assignRole('admin_unit');
        $this->actingAs($adminUnit)
            ->get(route('pegawai.show', $guru->id))
            ->assertInertia(fn ($page) => $page->component('Pegawai/Show')->where('canViewKontrak', false));
    }

    // ──────────────────────── Scope widget dashboard ───────────────────────

    public function test_dashboard_kontrak_scoped_to_bawahan_for_admin_unit(): void
    {
        $kepsek = $this->makePegawai('Kepsek SD', $this->sd, 'Kepala Sekolah');
        $bawahan = $this->makePegawai('Bawahan', $this->sd, 'Tenaga Administrasi (TU)');
        $bawahan->update(['status_kepegawaian' => 'honorer', 'tanggal_akhir_kontrak' => now()->addDays(5), 'atasan_langsung_id' => $kepsek->id]);

        $bukanBawahan = $this->makePegawai('Bukan Bawahan', $this->sd, 'Tenaga Administrasi (TU)');
        $bukanBawahan->update(['status_kepegawaian' => 'honorer', 'tanggal_akhir_kontrak' => now()->addDays(6)]);

        // Admin unit = kepala sekolah (punya pegawai record sebagai atasan)
        $userKepsek = User::factory()->create(['unit_sekolah_id' => $this->sd->id]);
        $userKepsek->assignRole('admin_unit');
        $kepsek->update(['user_id' => $userKepsek->id]);

        $this->actingAs($userKepsek)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->component('Dashboard')
                ->has('kontrakBerakhir', 1)
                ->where('kontrakBerakhir.0.nama_lengkap', 'Bawahan'));

        // Superadmin melihat semua
        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');
        $this->actingAs($superadmin)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->component('Dashboard')
                ->has('kontrakBerakhir', 2));
    }
}
