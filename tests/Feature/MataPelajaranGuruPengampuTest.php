<?php

namespace Tests\Feature;

use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\PegawaiMapel;
use App\Models\UnitSekolah;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MataPelajaranGuruPengampuTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unitA;

    private UnitSekolah $unitB;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->unitA = UnitSekolah::create([
            'nama' => 'SMP A',
            'singkatan' => 'SMPA',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
        ]);
        $this->unitB = UnitSekolah::create([
            'nama' => 'SMP B',
            'singkatan' => 'SMPB',
            'latitude' => -6.3,
            'longitude' => 106.9,
            'radius_meter' => 100,
        ]);

        $this->superadmin = User::factory()->create(['role' => 'superadmin']);
        $this->superadmin->assignRole('superadmin');
    }

    public function test_index_menampilkan_guru_unik_beserta_unit(): void
    {
        $mapel = MataPelajaran::create(['nama' => 'Matematika']);
        $ahmad = $this->buatPegawai('Ahmad');
        $budi = $this->buatPegawai('Budi');
        $citra = $this->buatPegawai('Citra');

        $this->tugaskan($mapel, $ahmad, $this->unitA);
        $this->tugaskan($mapel, $ahmad, $this->unitB);
        $this->tugaskan($mapel, $budi, $this->unitA);
        $this->tugaskan($mapel, $citra, $this->unitB);

        $this->actingAs($this->superadmin, 'web_admin')
            ->get(route('mata-pelajaran.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('MataPelajaran/Index')
                ->has('mapels.0.guru', 3)
                ->where('mapels.0.guru.0.nama', 'Ahmad')
                ->where('mapels.0.guru.0.units', ['SMP A', 'SMP B'])
                ->where('mapels.0.guru.1.nama', 'Budi')
                ->where('mapels.0.guru.2.nama', 'Citra')
                ->where('stats.total_penugasan_guru', 3)
            );
    }

    private function buatPegawai(string $nama): Pegawai
    {
        $user = User::factory()->create(['unit_sekolah_id' => $this->unitA->id]);

        return Pegawai::create([
            'user_id' => $user->id,
            'nik' => '3273'.str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT),
            'nama_lengkap' => $nama,
            'jenis_kelamin' => 'L',
            'status_kepegawaian' => 'guru_pemula',
            'status_aktif' => 'aktif',
            'tmt_mengajar' => '2020-01-01',
        ]);
    }

    private function tugaskan(MataPelajaran $mapel, Pegawai $pegawai, UnitSekolah $unit): void
    {
        PegawaiMapel::create([
            'pegawai_id' => $pegawai->id,
            'mata_pelajaran_id' => $mapel->id,
            'unit_sekolah_id' => $unit->id,
        ]);
    }
}
