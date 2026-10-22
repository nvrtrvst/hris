<?php

namespace Tests\Feature;

use App\Models\HariLibur;
use App\Models\Jabatan;
use App\Models\Jadwal;
use App\Models\Pegawai;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Blokir presensi mobile di hari unit tidak beroperasi (Sabtu/Minggu) dan
 * hari libur. Lembur & tugas luar selalu dikecualikan; admin bebas.
 */
class HariKerjaGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makeUnit(array $extra = []): UnitSekolah
    {
        return UnitSekolah::create(array_merge([
            'nama' => 'LPQ Test', 'singkatan' => 'LPQ',
            'latitude' => -6.2, 'longitude' => 106.8, 'radius_meter' => 100,
        ], $extra));
    }

    private function makePegawai(UnitSekolah $unit, string $status = 'guru_tetap_yayasan', string $nama = 'Pegawai Uji'): Pegawai
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
            'status_kepegawaian' => $status,
            'tmt_mengajar' => '2020-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
        ]);
        $jabatan = Jabatan::create(['nama' => 'Jabatan '.$nama]);
        $pegawai->units()->attach($unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);
        $pegawai->user->assignRole('pegawai');

        return $pegawai->fresh();
    }

    private function fotoPng(): string
    {
        $img = imagecreatetruecolor(320, 240);
        imagefilledrectangle($img, 0, 0, 320, 240, imagecolorallocate($img, 20, 80, 60));
        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);

        return 'data:image/png;base64,'.base64_encode($png);
    }

    private function payloadAbsen(array $extra = []): array
    {
        return array_merge([
            'tipe' => 'masuk',
            'foto' => $this->fotoPng(),
            'latitude' => -6.2,
            'longitude' => 106.8,
            'accuracy' => 15,
        ], $extra);
    }

    // ---------- Minggu: unit tidak beroperasi ----------

    public function test_minggu_unit_tidak_beroperasi_presensi_diblokir(): void
    {
        $unit = $this->makeUnit();
        $pegawai = $this->makePegawai($unit);
        Carbon::setTestNow('2026-10-18 07:00:00'); // Minggu

        $this->actingAs($pegawai->user, 'web_mobile')
            ->get(route('presensi.absen'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('officeAttendance', false)
                ->where('alasanLibur', 'Unit ini tidak beroperasi di hari Minggu.'));

        $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.store'), $this->payloadAbsen())
            ->assertStatus(422)
            ->assertJsonPath('message', 'Unit ini tidak beroperasi di hari Minggu.');
    }

    public function test_minggu_unit_lpq_beroperasi_presensi_diizinkan(): void
    {
        $unit = $this->makeUnit([
            'jam_kerja_minggu_mulai' => '07:00',
            'jam_kerja_minggu_selesai' => '12:00',
        ]);
        $pegawai = $this->makePegawai($unit);
        Carbon::setTestNow('2026-10-18 07:00:00'); // Minggu

        $this->actingAs($pegawai->user, 'web_mobile')
            ->get(route('presensi.absen'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('officeAttendance', true)
                ->where('alasanLibur', null));

        $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.store'), $this->payloadAbsen())
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_minggu_absen_tetap_diblokir(): void
    {
        $unit = $this->makeUnit();
        $pegawai = $this->makePegawai($unit);
        Carbon::setTestNow('2026-10-18 07:00:00');

        $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.tetap'), $this->payloadAbsen())
            ->assertStatus(422)
            ->assertJsonPath('message', 'Unit ini tidak beroperasi di hari Minggu.');
    }

    public function test_minggu_slide_jadwal_lolos_guard_hari_karena_ada_jadwal(): void
    {
        $unit = $this->makeUnit();
        $pegawai = $this->makePegawai($unit);
        Carbon::setTestNow('2026-10-18 07:00:00');

        $jadwal = Jadwal::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $unit->id,
            'hari' => 'Minggu',
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '08:00:00',
            'jenis_jadwal' => 'mengajar',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);

        // Ada jadwal = unit beroperasi hari itu: guard hari tidak memblokir,
        // permintaan lanjut ke alur normal (wajib foto pagi dulu).
        $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.slide'), $this->payloadAbsen(['jadwal_id' => $jadwal->id]))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Silakan foto pagi terlebih dahulu.');
    }

    // ---------- Sabtu ----------

    public function test_sabtu_unit_libur_diblokir_dan_unit_masuk_diizinkan(): void
    {
        $unitLibur = $this->makeUnit(['nama' => 'Unit Libur', 'singkatan' => 'UL']);
        $pegawaiLibur = $this->makePegawai($unitLibur, 'guru_tetap_yayasan', 'Pegawai Libur');
        Carbon::setTestNow('2026-10-17 07:00:00'); // Sabtu

        $this->actingAs($pegawaiLibur->user, 'web_mobile')
            ->postJson(route('presensi.absen.store'), $this->payloadAbsen())
            ->assertStatus(422)
            ->assertJsonPath('message', 'Unit ini tidak beroperasi di hari Sabtu.');

        $unitMasuk = $this->makeUnit([
            'nama' => 'Unit Masuk', 'singkatan' => 'UM',
            'jam_kerja_sabtu_mulai' => '07:00',
            'jam_kerja_sabtu_selesai' => '12:00',
        ]);
        $pegawaiMasuk = $this->makePegawai($unitMasuk, 'guru_tetap_yayasan', 'Pegawai Masuk');

        $this->actingAs($pegawaiMasuk->user, 'web_mobile')
            ->postJson(route('presensi.absen.store'), $this->payloadAbsen())
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    // ---------- Hari libur ----------

    public function test_hari_libur_nasional_diblokir(): void
    {
        $unit = $this->makeUnit();
        $pegawai = $this->makePegawai($unit);
        Carbon::setTestNow('2026-10-15 07:00:00'); // Kamis
        HariLibur::create([
            'tanggal' => '2026-10-15', 'nama' => 'Cuti Bersama', 'tipe' => 'nasional',
        ]);

        $this->actingAs($pegawai->user, 'web_mobile')
            ->get(route('presensi.absen'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('alasanLibur', 'Hari ini libur: Cuti Bersama. Presensi dinonaktifkan.'));

        $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.store'), $this->payloadAbsen())
            ->assertStatus(422)
            ->assertJsonPath('message', 'Hari ini libur: Cuti Bersama. Presensi dinonaktifkan.');
    }

    public function test_hari_libur_memblokir_semua_jalur_termasuk_berbasis_jadwal(): void
    {
        $unit = $this->makeUnit();
        $pegawai = $this->makePegawai($unit);
        Carbon::setTestNow('2026-10-15 07:00:00'); // Kamis, unit buka

        $jadwal = Jadwal::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $unit->id,
            'hari' => 'Kamis',
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '08:00:00',
            'jenis_jadwal' => 'mengajar',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);

        HariLibur::create([
            'tanggal' => '2026-10-15', 'nama' => 'Cuti Bersama', 'tipe' => 'nasional',
        ]);

        // Punya jadwal tidak menyelamatkan: hari libur memblokir presensi mengajar.
        $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.store'), $this->payloadAbsen(['jadwal_id' => $jadwal->id]))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Hari ini libur: Cuti Bersama. Presensi dinonaktifkan.');

        // Slide ke jadwal pun diblokir walau guard hari dilewati ($adaJadwal = true).
        $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.slide'), $this->payloadAbsen(['jadwal_id' => $jadwal->id]))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Hari ini libur: Cuti Bersama. Presensi dinonaktifkan.');
    }

    // ---------- Pengecualian ----------

    public function test_lembur_dan_tugas_luar_tetap_bisa_di_hari_libur(): void
    {
        $unit = $this->makeUnit();
        $pegawai = $this->makePegawai($unit);
        Carbon::setTestNow('2026-10-18 07:00:00'); // Minggu

        $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.store'), $this->payloadAbsen(['is_lembur' => true]))
            ->assertOk()
            ->assertJson(['success' => true]);

        $pegawaiDua = $this->makePegawai($unit, 'guru_tetap_yayasan', 'Pegawai Dinas');
        $this->actingAs($pegawaiDua->user, 'web_mobile')
            ->postJson(route('presensi.absen.store'), $this->payloadAbsen(['is_tugas_luar' => true, 'tujuan' => 'Rapat yayasan']))
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_absen_tetap_tugas_luar_tetap_bisa_di_minggu(): void
    {
        $unit = $this->makeUnit();
        $pegawai = $this->makePegawai($unit);
        Carbon::setTestNow('2026-10-18 07:00:00');

        $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.tetap'), $this->payloadAbsen(['is_tugas_luar' => true, 'tujuan' => 'Dinas luar']))
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_absen_tetap_lembur_bisa_di_minggu_dari_masuk_sampai_keluar(): void
    {
        $unit = $this->makeUnit();
        $pegawai = $this->makePegawai($unit);
        Carbon::setTestNow('2026-10-18 07:00:00'); // Minggu — unit tidak beroperasi.

        $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.tetap'), $this->payloadAbsen(['tipe' => 'masuk', 'is_lembur' => true]))
            ->assertOk()
            ->assertJson(['success' => true]);

        $presensi = Presensi::where('pegawai_id', $pegawai->id)->whereDate('tanggal', '2026-10-18')->first();
        $this->assertNotNull($presensi, 'Presensi lembur tersimpan');
        $this->assertTrue((bool) $presensi->is_lembur);
        $this->assertSame('pending', $presensi->lembur_status);
        $this->assertSame('hadir', $presensi->status);
        $this->assertNull($presensi->jadwal_id, 'Lembur tanpa jadwal');

        Carbon::setTestNow('2026-10-18 09:00:00');

        $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.tetap'), $this->payloadAbsen(['tipe' => 'keluar', 'is_lembur' => true]))
            ->assertOk()
            ->assertJson(['success' => true]);

        $presensi->refresh();
        $this->assertNotNull($presensi->jam_keluar, 'Presensi lembur terekam jam keluar');
        $this->assertSame('pending', $presensi->lembur_status);
    }

    // ---------- Admin ----------

    public function test_admin_bisa_input_presensi_di_minggu(): void
    {
        $unit = $this->makeUnit();
        $pegawai = $this->makePegawai($unit);
        Carbon::setTestNow('2026-10-18 07:00:00');

        $jadwal = Jadwal::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $unit->id,
            'hari' => 'Minggu',
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '08:00:00',
            'jenis_jadwal' => 'mengajar',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('superadmin');

        $this->actingAs($admin, 'web_admin')
            ->post(route('presensi.store'), [
                'pegawai_id' => $pegawai->id,
                'jadwal_id' => $jadwal->id,
                'tipe' => 'masuk',
                'latitude' => -6.2,
                'longitude' => 106.8,
                'foto' => $this->fotoPng(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('presensi', [
            'pegawai_id' => $pegawai->id,
            'jadwal_id' => $jadwal->id,
            'tanggal' => '2026-10-18',
        ]);
    }
}
