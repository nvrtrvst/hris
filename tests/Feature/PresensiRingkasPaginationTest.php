<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Jadwal;
use App\Models\Pegawai;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Pagination mode "ringkas" di /presensi dihitung per kelompok
 * (pegawai + tanggal), bukan per baris — supaya ringkasan satu orang
 * tidak terbelah antar halaman.
 */
class PresensiRingkasPaginationTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private User $superadmin;

    private string $hariIni;

    private string $kemarin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->unit = UnitSekolah::create([
            'nama' => 'SMP Paginasi',
            'singkatan' => 'SMPP',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'toleransi_menit' => 0,
            'toleransi_slide_menit' => 15,
        ]);

        $this->superadmin = User::factory()->create(['role' => 'superadmin']);
        $this->superadmin->assignRole('superadmin');

        $this->hariIni = Carbon::today()->toDateString();
        $this->kemarin = Carbon::yesterday()->toDateString();
    }

    private function makePegawai(string $nik, string $nama): Pegawai
    {
        $jabatan = Jabatan::create(['nama' => 'Guru '.$nama]);
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
            'alamat' => 'Jl. Paginasi No. 1',
            'no_hp' => '0812'.substr($nik, -6),
            'status_kepegawaian' => 'guru_pemula',
            'tmt_mengajar' => '2021-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
        ]);
        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    private function makePresensi(Pegawai $pegawai, string $tanggal, string $jamMasuk): Presensi
    {
        return Presensi::create([
            'pegawai_id' => $pegawai->id,
            'jadwal_id' => null,
            'unit_sekolah_id' => $this->unit->id,
            'tipe_presensi' => 'kantor',
            'tanggal' => $tanggal,
            'jam_masuk' => $jamMasuk,
            'status' => 'hadir',
        ]);
    }

    private function hariIniIndo(): string
    {
        $map = [
            'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
        ];

        return $map[Carbon::now()->format('l')];
    }

    /**
     * Satu pegawai hanya boleh punya satu presensi 'kantor' per tanggal
     * (unique index pegawai_id + presensi_key = 'K{tanggal}'), jadi baris
     * ganda per hari memakai presensi berbasis jadwal.
     */
    private function makePresensiMengajar(Pegawai $pegawai, string $tanggal, string $jamMasuk): Presensi
    {
        $jadwal = Jadwal::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'hari' => $this->hariIniIndo(),
            'jam_mulai' => $jamMasuk,
            'jam_selesai' => Carbon::parse($jamMasuk)->addMinutes(45)->format('H:i:s'),
            'jenis_jadwal' => 'mengajar',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);

        return Presensi::create([
            'pegawai_id' => $pegawai->id,
            'jadwal_id' => $jadwal->id,
            'unit_sekolah_id' => $this->unit->id,
            'tipe_presensi' => 'mengajar',
            'tanggal' => $tanggal,
            'jam_masuk' => $jamMasuk,
            'status' => 'hadir',
        ]);
    }

    /**
     * 3 kelompok: A|hari ini (3 baris), B|hari ini (1 baris), C|kemarin (1 baris).
     */
    private function seedKelompok(): array
    {
        $a = $this->makePegawai('1111222233334444', 'Guru A');
        $b = $this->makePegawai('5555666677778888', 'Guru B');
        $c = $this->makePegawai('9999000011112222', 'Guru C');

        $barisA = [
            $this->makePresensiMengajar($a, $this->hariIni, '07:00:00'),
            $this->makePresensiMengajar($a, $this->hariIni, '09:00:00'),
            $this->makePresensiMengajar($a, $this->hariIni, '11:00:00'),
        ];
        $barisB = [$this->makePresensi($b, $this->hariIni, '08:00:00')];
        $barisC = [$this->makePresensi($c, $this->kemarin, '07:30:00')];

        return compact('a', 'b', 'c', 'barisA', 'barisB', 'barisC');
    }

    private function params(array $overrides = []): array
    {
        return array_merge([
            'display_mode' => 'ringkas',
            'start_date' => $this->kemarin,
            'end_date' => $this->hariIni,
        ], $overrides);
    }

    public function test_ringkas_dipaginasi_per_kelompok_pegawai_dan_tanggal(): void
    {
        $data = $this->seedKelompok();

        $this->actingAs($this->superadmin, 'web_admin')
            ->get(route('presensi.index', $this->params(['per_page' => 2, 'page' => 1])))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Presensi/Index')
                ->where('presensis.total', 3)
                ->where('presensis.current_page', 1)
                ->where('presensis.last_page', 2)
                ->where('presensis.per_page', 2)
                ->has('presensis.data', 4)
                // Baris diurutkan global (tanggal DESC, jam_masuk ASC) — bukan blok per kelompok;
                // FE mengelompokkan ulang per pegawai+tanggal. Yang penting: kelompok tidak terbelah
                // antar halaman, jadi 3 baris Guru A semuanya ikut di halaman 1.
                ->where('presensis.data.0.pegawai_id', $data['a']->id)
                ->where('presensis.data.0.jam_masuk', '07:00:00')
                ->where('presensis.data.1.pegawai_id', $data['b']->id)
                ->where('presensis.data.1.jam_masuk', '08:00:00')
                ->where('presensis.data.2.pegawai_id', $data['a']->id)
                ->where('presensis.data.2.jam_masuk', '09:00:00')
                ->where('presensis.data.3.pegawai_id', $data['a']->id)
                ->where('presensis.data.3.jam_masuk', '11:00:00')
            );
    }

    public function test_gabungan_semua_halaman_sama_dengan_seluruh_data_tanpa_duplikat(): void
    {
        $total = 5;
        $this->seedKelompok();
        $kumpulanId = [];

        foreach ([1, 2] as $halaman) {
            $this->actingAs($this->superadmin, 'web_admin')
                ->get(route('presensi.index', $this->params(['per_page' => 2, 'page' => $halaman])))
                ->assertOk()
                ->assertInertia(function (Assert $page) use (&$kumpulanId) {
                    $page->component('Presensi/Index');
                    foreach ($page->toArray()['props']['presensis']['data'] as $baris) {
                        $kumpulanId[] = $baris['id'];
                    }
                });
        }

        $this->assertCount($total, $kumpulanId, 'Gabungan halaman harus memuat seluruh baris.');
        $this->assertCount($total, array_unique($kumpulanId), 'Tidak boleh ada baris duplikat antar halaman.');
    }

    public function test_per_page_ringkas_dibatasi_100(): void
    {
        $this->seedKelompok();

        $this->actingAs($this->superadmin, 'web_admin')
            ->get(route('presensi.index', $this->params(['per_page' => 500])))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('presensis.per_page', 100));
    }

    public function test_halaman_di_luar_rentang_dialihkan_ke_halaman_terakhir(): void
    {
        $this->seedKelompok();

        $response = $this->actingAs($this->superadmin, 'web_admin')
            ->get(route('presensi.index', $this->params(['per_page' => 2, 'page' => 99])));

        $response->assertRedirect();
        $this->assertStringContainsString('page=2', $response->headers->get('Location'));
    }

    public function test_mode_detail_tidak_berubah(): void
    {
        $this->seedKelompok();

        $this->actingAs($this->superadmin, 'web_admin')
            ->get(route('presensi.index', [
                'start_date' => $this->kemarin,
                'end_date' => $this->hariIni,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Presensi/Index')
                ->where('presensis.total', 5)
                ->where('presensis.per_page', 100)
                ->where('presensis.last_page', 1)
                ->has('presensis.data', 5)
            );
    }
}
