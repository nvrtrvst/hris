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

class DashboardPartialReloadTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->superadmin = User::factory()->create(['role' => 'superadmin']);
        $this->superadmin->assignRole('superadmin');
    }

    /**
     * Replikasi HandleInertiaRequests::version() — partial reload memerlukan
     * header X-Inertia-Version yang cocok, selain itu Inertia membalas 409.
     */
    private function inertiaVersion(): ?string
    {
        $manifest = public_path('build/manifest.json');
        if (is_file($manifest)) {
            $mtime = @filemtime($manifest);
            if ($mtime !== false) {
                return (string) $mtime;
            }
        }

        return null;
    }

    private function partialHeaders(): array
    {
        $headers = [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'Dashboard',
            'X-Inertia-Partial-Data' => 'stats,trends,jadwalHariIni,presensiHariIni',
        ];
        if (($version = $this->inertiaVersion()) !== null) {
            $headers['X-Inertia-Version'] = $version;
        }

        return $headers;
    }

    private function seedData(): void
    {
        $unit = UnitSekolah::create([
            'nama' => 'SMP Live',
            'singkatan' => 'SMPL',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'toleransi_menit' => 0,
            'toleransi_slide_menit' => 15,
        ]);

        $jabatan = Jabatan::create(['nama' => 'Guru']);
        $pegawai = Pegawai::create([
            'user_id' => User::factory()->create()->id,
            'nik' => '1111222233334444',
            'nama_lengkap' => 'Guru Live',
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '1992-03-03',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'kawin',
            'jumlah_tanggungan' => 0,
            'alamat' => 'Jl. Live Test No. 1',
            'no_hp' => '081211223344',
            'status_kepegawaian' => 'guru_pemula',
            'tmt_mengajar' => '2021-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
        ]);
        $pegawai->units()->attach($unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        $hariMap = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
        $hariIni = $hariMap[Carbon::now()->format('l')];

        $jadwal = Jadwal::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $unit->id,
            'hari' => $hariIni,
            'jam_mulai' => '08:00:00',
            'jam_selesai' => '09:45:00',
            'jenis_jadwal' => 'mengajar',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);

        $presensi = Presensi::create([
            'pegawai_id' => $pegawai->id,
            'jadwal_id' => $jadwal->id,
            'unit_sekolah_id' => $unit->id,
            'tipe_presensi' => 'mengajar',
            'tanggal' => Carbon::today()->toDateString(),
            'jam_masuk' => '08:00:00',
        ]);
        // status ada di $guarded — set langsung setelah create agar tersimpan 'hadir'.
        $presensi->forceFill(['status' => 'hadir'])->save();
    }

    public function test_load_penuh_dashboard_admin_mengandung_semua_prop(): void
    {
        $this->seedData();

        $this->actingAs($this->superadmin, 'web_admin')
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('roleType', 'Admin')
                ->has('stats')
                ->has('trends')
                ->has('jadwalHariIni')
                ->has('presensiHariIni')
                ->has('kontrakBerakhir'));
    }

    public function test_partial_reload_hanya_mengembalikan_prop_live(): void
    {
        $this->seedData();

        // Simulasi polling 60 detik: minta hanya 4 prop live.
        // Partial reload mengembalikan JSON Inertia murni (bukan view) —
        // assertInertia tidak bisa dipakai, gunakan assertJson.
        $this->actingAs($this->superadmin, 'web_admin')
            ->get(route('dashboard'), $this->partialHeaders())
            ->assertOk()
            ->assertJsonPath('component', 'Dashboard')
            ->assertJsonStructure(['props' => ['stats', 'trends', 'jadwalHariIni', 'presensiHariIni']])
            // Prop cached TIDAK dikirim ulang saat partial reload (hemat bandwidth)
            ->assertJsonMissingPath('props.kontrakBerakhir');
    }

    public function test_partial_reload_stats_kehadiran_hari_ini_segar(): void
    {
        $this->seedData();

        $this->actingAs($this->superadmin, 'web_admin')
            ->get(route('dashboard'), $this->partialHeaders())
            ->assertOk()
            ->assertJsonPath('component', 'Dashboard')
            ->assertJsonPath('props.stats.hadir_hari_ini_count', 1)
            ->assertJsonPath('props.stats.pegawai_dijadwalkan', 1)
            ->assertJsonPath('props.stats.hadir_percentage', 100)
            ->assertJsonStructure(['props' => ['jadwalHariIni' => [], 'presensiHariIni' => []]]);
    }

    /**
     * Ringkasan "JP belum diabsen" harus menghitung bobot JP (durasi ÷ durasi_jp),
     * bukan jumlah baris jadwal, dan mengklasifikasi status waktu + guru hadir harian.
     */
    public function test_ringkasan_jp_belum_diabsen_pakai_bobot_dan_status_waktu(): void
    {
        $smk = UnitSekolah::create([
            'nama' => 'SMK Uji', 'singkatan' => 'SMK', 'latitude' => -6.2, 'longitude' => 106.8,
            'radius_meter' => 100, 'durasi_jp' => 40, 'toleransi_menit' => 0, 'toleransi_slide_menit' => 15,
        ]);
        $smp = UnitSekolah::create([
            'nama' => 'SMP Uji', 'singkatan' => 'SMP', 'latitude' => -6.3, 'longitude' => 106.9,
            'radius_meter' => 100, 'durasi_jp' => 35, 'toleransi_menit' => 0, 'toleransi_slide_menit' => 15,
        ]);

        $jabatan = Jabatan::create(['nama' => 'Guru']);
        $pegawai = function (string $nik, string $nama, UnitSekolah $unit) use ($jabatan) {
            $p = Pegawai::create([
                'user_id' => User::factory()->create()->id,
                'nik' => $nik,
                'nama_lengkap' => $nama,
                'tempat_lahir' => 'Bandung',
                'tanggal_lahir' => '1992-03-03',
                'jenis_kelamin' => 'L',
                'agama' => 'Islam',
                'status_pernikahan' => 'kawin',
                'jumlah_tanggungan' => 0,
                'alamat' => 'Jl. Uji No. 1',
                'no_hp' => '081211223344',
                'status_kepegawaian' => 'guru_pemula',
                'tmt_mengajar' => '2021-01-01',
                'status_aktif' => 'aktif',
                'pendidikan_terakhir' => 'S1',
            ]);
            $p->units()->attach($unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

            return $p;
        };

        $p1 = $pegawai('1111222200000001', 'Guru Satu', $smk);   // JP 01:00 sudah diabsen
        $p2 = $pegawai('1111222200000002', 'Guru Dua', $smk);    // JP 20:00 kosong, tapi hadir harian
        $p3 = $pegawai('1111222200000003', 'Guru Tiga', $smp);   // JP 00:00 kosong (lewat) + 22:00 kosong (2 JP)
        $p4 = $pegawai('1111222200000004', 'Guru Empat', $smk);  // JP 21:00 kosong, hanya lembur

        $hariMap = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
        // Waktu dipatok supaya klasifikasi lewat/berjalan/belum-mulai tidak bergantung jam CI.
        Carbon::setTestNow(Carbon::parse('2026-10-20 08:00:00'));
        $this->beforeApplicationDestroyed(fn () => Carbon::setTestNow());

        $hariIni = $hariMap[Carbon::now('Asia/Jakarta')->format('l')];
        $jadwal = function (Pegawai $p, UnitSekolah $unit, string $mulai, string $selesai) use ($hariIni) {
            return Jadwal::create([
                'pegawai_id' => $p->id,
                'unit_sekolah_id' => $unit->id,
                'kelas_label' => 'X-1',
                'hari' => $hariIni,
                'jam_mulai' => $mulai,
                'jam_selesai' => $selesai,
                'jenis_jadwal' => 'mengajar',
                'tahun_ajaran' => '2026/2027',
                'semester' => 1,
            ]);
        };

        $j0 = $jadwal($p3, $smp, '00:00:00', '00:35:00'); // 1 JP, selalu lewat
        $j1 = $jadwal($p1, $smk, '01:00:00', '01:40:00'); // 1 JP, sudah diabsen
        $j2 = $jadwal($p2, $smk, '20:00:00', '20:40:00'); // 1 JP
        $j4 = $jadwal($p4, $smk, '21:00:00', '21:40:00'); // 1 JP
        $j3 = $jadwal($p3, $smp, '22:00:00', '23:10:00'); // 70 menit ÷ 35 = 2 JP

        $today = Carbon::now('Asia/Jakarta')->toDateString();
        $presensi = Presensi::create([
            'pegawai_id' => $p1->id,
            'jadwal_id' => $j1->id,
            'unit_sekolah_id' => $smk->id,
            'tipe_presensi' => 'mengajar',
            'tanggal' => $today,
            'jam_masuk' => '01:00:00',
        ]);
        $presensi->forceFill(['status' => 'hadir'])->save();

        // Hadir harian (kantor) → JP 20:00 dianggap "guru sudah di unit".
        Presensi::create([
            'pegawai_id' => $p2->id,
            'jadwal_id' => null,
            'unit_sekolah_id' => $smk->id,
            'tipe_presensi' => 'kantor',
            'tanggal' => $today,
            'jam_masuk' => '06:00:00',
        ])->forceFill(['status' => 'hadir'])->save();

        // Lembur saja TIDAK boleh dianggap hadir untuk JP pagi/siang.
        Presensi::create([
            'pegawai_id' => $p4->id,
            'jadwal_id' => null,
            'unit_sekolah_id' => $smk->id,
            'tipe_presensi' => 'kantor',
            'tanggal' => $today,
            'jam_masuk' => '19:00:00',
        ])->forceFill(['status' => 'hadir', 'is_lembur' => true])->save();

        $this->actingAs($this->superadmin, 'web_admin')
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                // Total JP = 1 + 1 + 1 + 1 + 2 (bukan 5 baris jadwal).
                ->where('kelasKosongSummary.jp_total', 6)
                // Yang kosong: j0 (1) + j2 (1) + j4 (1) + j3 (2); j1 sudah diabsen.
                ->where('kelasKosongSummary.jp_kosong', 5)
                ->where('kelasKosongSummary.jp_lewat', 1)
                ->where('kelasKosongSummary.jp_guru_hadir', 1)
                ->has('kelasKosongSummary.per_unit', 2)
                ->where('kelasKosongSummary.per_unit.0.singkatan', 'SMP')
                ->where('kelasKosongSummary.per_unit.0.jp', 3)
                ->where('kelasKosongSummary.per_unit.1.singkatan', 'SMK')
                ->where('kelasKosongSummary.per_unit.1.jp', 2)
                // Baris kosong urut jam mulai; j0 paling awal.
                ->where('kelasKosongMengajar.0.jp', 1)
                ->where('kelasKosongMengajar.0.status_waktu', 'lewat')
                ->where('kelasKosongMengajar.0.guru_hadir', false)
                ->where('jadwalHariIni.4.jp', 2)
            );

        // Prop baru harus ikut terkirim saat polling (partial reload).
        $headers = array_merge($this->partialHeaders(), [
            'X-Inertia-Partial-Data' => 'kelasKosongMengajar,kelasKosongSummary',
        ]);
        $this->actingAs($this->superadmin, 'web_admin')
            ->get(route('dashboard'), $headers)
            ->assertOk()
            ->assertJsonPath('props.kelasKosongSummary.jp_kosong', 5)
            ->assertJsonStructure(['props' => ['kelasKosongMengajar' => [['id', 'jp', 'status_waktu', 'guru_hadir']]]]);
    }
}
