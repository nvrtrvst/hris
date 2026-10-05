<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Jadwal;
use App\Models\Pegawai;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class LaporanPresensiKantorAlpaTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private User $superadmin;

    private Pegawai $pegawai;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->addWeekdays(1));
        $this->seed(RolePermissionSeeder::class);

        $this->unit = UnitSekolah::create(['nama' => 'SMK', 'singkatan' => 'SMK', 'latitude' => -6.2, 'longitude' => 106.8, 'radius_meter' => 100]);
        $jabatan = Jabatan::create(['nama' => 'Guru', 'is_guru' => true]);
        $this->superadmin = User::factory()->create();
        $this->superadmin->syncRoles('superadmin');

        $user = User::factory()->create();
        $this->pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '1234567890000099',
            'nama_lengkap' => 'Yudi Heryadi',
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'Belum Menikah',
            'alamat' => 'Jl. Test',
            'no_hp' => '081234567890',
            'status_kepegawaian' => 'guru_tetap_yayasan',
            'tmt_mengajar' => '2020-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
        ]);
        $this->pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);
    }

    private function makeJadwal(string $mulai, string $selesai): Jadwal
    {
        return Jadwal::create([
            'pegawai_id' => $this->pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'hari' => now()->locale('id')->translatedFormat('l'),
            'jam_mulai' => $mulai,
            'jam_selesai' => $selesai,
            'jenis_jadwal' => 'mengajar',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);
    }

    private function preview(array $extra): TestResponse
    {
        return $this->actingAs($this->superadmin, 'web_admin')
            ->getJson('/laporan/preview?'.http_build_query(array_merge([
                'type' => 'presensi',
                'start_date' => now()->toDateString(),
                'end_date' => now()->toDateString(),
            ], $extra)))
            ->assertOk();
    }

    public function test_alpa_berjadwal_tampil_tunggal_dan_berlabel_kantor_di_filter_kantor(): void
    {
        $tanggal = now()->toDateString();
        $jp1 = $this->makeJadwal('07:30', '08:10');
        $jp2 = $this->makeJadwal('08:10', '08:50');

        foreach ([$jp1, $jp2] as $jadwal) {
            Presensi::forceCreate([
                'pegawai_id' => $this->pegawai->id,
                'jadwal_id' => $jadwal->id,
                'unit_sekolah_id' => $this->unit->id,
                'tanggal' => $tanggal,
                'tipe_presensi' => 'mengajar',
                'status' => 'alpa',
                'is_lembur' => false,
                'keterangan' => 'Tidak hadir (otomatis)',
            ]);
        }

        $res = $this->preview(['tipe_filter' => 'kantor', 'search' => 'Yudi']);

        $data = $res->json('data');
        $this->assertCount(1, $data, '2 baris alpa ber-jadwal harus terdedup jadi 1 baris harian');
        $this->assertSame('Kantor', $data[0][4], 'Alpa di mode kantor harus berlabel Kantor');
        $this->assertSame('Alpa', $data[0][8]);
        $this->assertSame(1, $res->json('total'));
    }

    public function test_hadir_berjadwal_tidak_bocor_ke_filter_kantor(): void
    {
        $tanggal = now()->toDateString();
        $jadwal = $this->makeJadwal('07:30', '08:10');

        Presensi::forceCreate([
            'pegawai_id' => $this->pegawai->id,
            'jadwal_id' => $jadwal->id,
            'unit_sekolah_id' => $this->unit->id,
            'tanggal' => $tanggal,
            'tipe_presensi' => 'mengajar',
            'jam_masuk' => '07:25:00',
            'jam_keluar' => '08:10:00',
            'status' => 'hadir',
            'is_lembur' => false,
        ]);

        $kantor = $this->preview(['tipe_filter' => 'kantor']);
        $this->assertCount(0, $kantor->json('data'), 'Baris hadir ber-jadwal bukan kehadiran kantor');
        $this->assertSame(0, $kantor->json('total'));

        $mengajar = $this->preview(['tipe_filter' => 'mengajar']);
        $this->assertCount(1, $mengajar->json('data'), 'Filter mengajar tetap menampilkan baris ber-jadwal');
    }
}
