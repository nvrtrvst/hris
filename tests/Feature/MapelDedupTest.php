<?php

namespace Tests\Feature;

use App\Models\Jadwal;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\PegawaiMapel;
use App\Models\UnitSekolah;
use App\Models\User;
use App\Services\MapelDedupService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MapelDedupTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->syncRoles('superadmin');
    }

    public function test_dedup_menggabungkan_dan_memindahkan_relasi(): void
    {
        // Keep = row dengan relasi terbanyak (mapelB: 2 pivot + 2 jadwal).
        $unit = UnitSekolah::create(['nama' => 'SMP', 'singkatan' => 'SMP', 'latitude' => -6.2, 'longitude' => 106.8, 'radius_meter' => 100]);
        $mapelA = MataPelajaran::create(['nama' => 'Matematika']); // sedikit relasi
        $mapelB = MataPelajaran::create(['nama' => 'matematika ']); // beda case + spasi

        $mkPegawai = function (int $i) {
            return Pegawai::create([
                'user_id' => User::factory()->create()->id,
                'nik' => '12345678900002'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'nama_lengkap' => 'Guru Dedup '.$i,
                'tempat_lahir' => 'Jakarta',
                'tanggal_lahir' => '1990-01-01',
                'jenis_kelamin' => 'L',
                'agama' => 'Islam',
                'status_pernikahan' => 'Belum Menikah',
                'alamat_ktp' => 'Jl. Test',
                'no_hp' => '08123456789'.$i,
                'status_kepegawaian' => 'tetap',
                'tanggal_mulai_kerja' => '2020-01-01',
                'status_aktif' => 'aktif',
                'pendidikan_terakhir' => 'S1',
            ]);
        };
        $g1 = $mkPegawai(1);
        $g2 = $mkPegawai(2);
        $g3 = $mkPegawai(3);

        $pmA = PegawaiMapel::create(['pegawai_id' => $g1->id, 'mata_pelajaran_id' => $mapelA->id, 'unit_sekolah_id' => $unit->id]);
        $pmB = PegawaiMapel::create(['pegawai_id' => $g2->id, 'mata_pelajaran_id' => $mapelB->id, 'unit_sekolah_id' => $unit->id]);
        $pmB2 = PegawaiMapel::create(['pegawai_id' => $g3->id, 'mata_pelajaran_id' => $mapelB->id, 'unit_sekolah_id' => $unit->id]);

        Jadwal::create(['pegawai_id' => $g2->id, 'unit_sekolah_id' => $unit->id, 'pegawai_mapel_id' => $pmB->id, 'hari' => 'Senin', 'jam_mulai' => '08:00:00', 'jam_selesai' => '08:45:00', 'jenis_jadwal' => 'mengajar', 'tahun_ajaran' => '2026/2027', 'semester' => 1]);
        Jadwal::create(['pegawai_id' => $g2->id, 'unit_sekolah_id' => $unit->id, 'pegawai_mapel_id' => $pmB2->id, 'hari' => 'Selasa', 'jam_mulai' => '08:00:00', 'jam_selesai' => '08:45:00', 'jenis_jadwal' => 'mengajar', 'tahun_ajaran' => '2026/2027', 'semester' => 1]);

        $result = (new MapelDedupService)->dedup();

        $this->assertSame(1, $result['merged']);
        $this->assertSame(1, $result['deleted']);

        // 1 mapel tersisa, relasi tidak hilang (3 pivot, 2 jadwal).
        $this->assertSame(1, MataPelajaran::count());
        $this->assertSame(3, PegawaiMapel::count());
        $this->assertSame(2, Jadwal::count());

        // Semua jadwal menunjuk pivot yang masih valid (mata_pelajaran_id = keep).
        $keepMapelId = MataPelajaran::first()->id;
        $this->assertSame(2, Jadwal::whereHas('pegawaiMapel', fn ($q) => $q->where('mata_pelajaran_id', $keepMapelId))->count());
    }

    public function test_crud_tolak_duplikat_case_insensitive(): void
    {
        MataPelajaran::create(['nama' => 'Bahasa Arab']);

        $this->actingAs($this->admin, 'web_admin')
            ->post(route('mata-pelajaran.store'), ['nama' => 'bahasa arab'])
            ->assertSessionHasErrors('nama');

        $this->assertSame(1, MataPelajaran::count());
    }
}
