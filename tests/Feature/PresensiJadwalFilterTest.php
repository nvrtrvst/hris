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

class PresensiJadwalFilterTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->unit = UnitSekolah::create([
            'nama' => 'SMP Filter',
            'singkatan' => 'SMPF',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'toleransi_menit' => 0,
            'toleransi_tap_menit' => 15,
        ]);

        $this->superadmin = User::factory()->create(['role' => 'superadmin']);
        $this->superadmin->assignRole('superadmin');
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

    private function makePegawai(string $nik, string $nama): Pegawai
    {
        $jabatan = Jabatan::create(['nama' => 'Guru']);
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
            'alamat_ktp' => 'Jl. Filter Test No. 1',
            'no_hp' => '0812'.substr($nik, -6),
            'status_kepegawaian' => 'honorer',
            'tanggal_mulai_kerja' => '2021-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
        ]);
        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    private function makeJadwal(Pegawai $pegawai, string $mulai, string $selesai): Jadwal
    {
        return Jadwal::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'hari' => $this->hariIniIndo(),
            'jam_mulai' => $mulai,
            'jam_selesai' => $selesai,
            'jenis_jadwal' => 'mengajar',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);
    }

    private function makePresensiMengajar(Pegawai $pegawai, Jadwal $jadwal, string $jamMasuk): Presensi
    {
        return Presensi::create([
            'pegawai_id' => $pegawai->id,
            'jadwal_id' => $jadwal->id,
            'unit_sekolah_id' => $this->unit->id,
            'tipe_presensi' => 'mengajar',
            'tanggal' => Carbon::today()->toDateString(),
            'jam_masuk' => $jamMasuk,
            'status' => 'hadir',
        ]);
    }

    private function makePresensiKantor(Pegawai $pegawai): Presensi
    {
        return Presensi::create([
            'pegawai_id' => $pegawai->id,
            'jadwal_id' => null,
            'unit_sekolah_id' => $this->unit->id,
            'tipe_presensi' => 'kantor',
            'tanggal' => Carbon::today()->toDateString(),
            'jam_masuk' => '07:00:00',
            'jam_keluar' => '15:30:00',
            'status' => 'hadir',
        ]);
    }

    public function test_filter_sedang_berlangsung_hanya_menampilkan_kelas_dalam_jendela_waktu(): void
    {
        // Jadwal A 08:00-09:45, Jadwal B 10:00-11:45; sekarang 08:30 → hanya A berlangsung.
        Carbon::setTestNow(Carbon::today()->setTime(8, 30));
        $pegawaiA = $this->makePegawai('1111222233334444', 'Guru A');
        $pegawaiB = $this->makePegawai('5555666677778888', 'Guru B');
        $jadwalA = $this->makeJadwal($pegawaiA, '08:00:00', '09:45:00');
        $jadwalB = $this->makeJadwal($pegawaiB, '10:00:00', '11:45:00');
        $this->makePresensiMengajar($pegawaiA, $jadwalA, '08:00:00');
        $this->makePresensiMengajar($pegawaiB, $jadwalB, '10:00:00');

        $this->actingAs($this->superadmin, 'web_admin')
            ->get(route('presensi.index', ['jadwal_filter' => 'sedang_berlangsung']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Presensi/Index')
                ->has('presensis.data', 1)
                ->where('presensis.data.0.pegawai.nama_lengkap', 'Guru A'));
    }

    public function test_filter_sedang_berlangsung_kosong_saat_di_luar_jendela(): void
    {
        // Sekarang 10:00 → jadwal 08:00-09:45 sudah selesai, jadwal 10:00-11:45 baru mulai.
        Carbon::setTestNow(Carbon::today()->setTime(10, 0));
        $pegawai = $this->makePegawai('1111222233334444', 'Guru A');
        $jadwal = $this->makeJadwal($pegawai, '08:00:00', '09:45:00');
        $this->makePresensiMengajar($pegawai, $jadwal, '08:00:00');

        $this->actingAs($this->superadmin, 'web_admin')
            ->get(route('presensi.index', ['jadwal_filter' => 'sedang_berlangsung']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Presensi/Index')
                ->has('presensis.data', 0));
    }

    public function test_filter_sedang_berlangsung_mengabaikan_record_non_mengajar(): void
    {
        // Sekarang 11:00 → jadwal 10:00-11:45 sedang berlangsung.
        Carbon::setTestNow(Carbon::today()->setTime(11, 0));
        $pegawaiMengajar = $this->makePegawai('1111222233334444', 'Guru A');
        $pegawaiKantor = $this->makePegawai('5555666677778888', 'Staf Kantor');
        $jadwal = $this->makeJadwal($pegawaiMengajar, '10:00:00', '11:45:00');
        $this->makePresensiMengajar($pegawaiMengajar, $jadwal, '10:00:00');
        $this->makePresensiKantor($pegawaiKantor);

        $this->actingAs($this->superadmin, 'web_admin')
            ->get(route('presensi.index', ['jadwal_filter' => 'sedang_berlangsung']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Presensi/Index')
                ->has('presensis.data', 1)
                ->where('presensis.data.0.pegawai.nama_lengkap', 'Guru A'));
    }

    public function test_filter_sedang_berlangsung_mengabaikan_record_belum_absen_masuk(): void
    {
        // Record ada (mis. dibuat via izin) tapi belum jam_masuk → bukan kelas berjalan.
        Carbon::setTestNow(Carbon::today()->setTime(11, 0));
        $pegawai = $this->makePegawai('1111222233334444', 'Guru A');
        $jadwal = $this->makeJadwal($pegawai, '10:00:00', '11:45:00');
        Presensi::create([
            'pegawai_id' => $pegawai->id,
            'jadwal_id' => $jadwal->id,
            'unit_sekolah_id' => $this->unit->id,
            'tipe_presensi' => 'mengajar',
            'tanggal' => Carbon::today()->toDateString(),
            'jam_masuk' => null,
            'status' => 'izin',
        ]);

        $this->actingAs($this->superadmin, 'web_admin')
            ->get(route('presensi.index', ['jadwal_filter' => 'sedang_berlangsung']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Presensi/Index')
                ->has('presensis.data', 0));
    }

    public function test_filter_sedang_berlangsung_hanya_record_hari_ini(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(11, 0));
        $pegawai = $this->makePegawai('1111222233334444', 'Guru A');
        $jadwal = $this->makeJadwal($pegawai, '10:00:00', '11:45:00');
        $this->makePresensiMengajar($pegawai, $jadwal, '10:00:00');

        // Record kemarin dengan jadwal sama (jam masih relevan) — harus diabaikan.
        Presensi::create([
            'pegawai_id' => $pegawai->id,
            'jadwal_id' => $jadwal->id,
            'unit_sekolah_id' => $this->unit->id,
            'tipe_presensi' => 'mengajar',
            'tanggal' => Carbon::yesterday()->toDateString(),
            'jam_masuk' => '10:00:00',
            'status' => 'hadir',
        ]);

        $this->actingAs($this->superadmin, 'web_admin')
            ->get(route('presensi.index', ['jadwal_filter' => 'sedang_berlangsung']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Presensi/Index')
                ->has('presensis.data', 1));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
