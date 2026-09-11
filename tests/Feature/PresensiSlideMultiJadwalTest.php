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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 1-slide-to-cover-multi-jadwal: slide presensi mengajar JP-A1 otomatis
 * cover JP-A2/A3 dst dalam grup (hari=kelas=mapel=unit sama) yang
 * jam_mulai <= now.
 *
 * Lihat: app/Http/Controllers/MobileController.php::slideJadwal
 */
class PresensiSlideMultiJadwalTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unitA;

    private UnitSekolah $unitB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unitA = UnitSekolah::create([
            'nama' => 'SMP Test A',
            'singkatan' => 'SMPA',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'toleransi_menit' => 0,
            'toleransi_slide_menit' => 15,
        ]);

        $this->unitB = UnitSekolah::create([
            'nama' => 'SMP Test B',
            'singkatan' => 'SMPB',
            'latitude' => -6.4,
            'longitude' => 107.0,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'toleransi_menit' => 0,
            'toleransi_slide_menit' => 15,
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

    private function makePegawaiTetap(): Pegawai
    {
        $jabatan = Jabatan::create(['nama' => 'Guru']);
        $user = User::factory()->create();
        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '1234567890'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT),
            'nama_lengkap' => 'Guru Tetap',
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'kawin',
            'jumlah_tanggungan' => 2,
            'alamat' => 'Jl. Test No. 1',
            'no_hp' => '081234567890',
            'status_kepegawaian' => 'guru_tetap_yayasan',
            'tmt_mengajar' => '2020-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
            'wajib_kantor' => true,
        ]);
        $pegawai->units()->attach($this->unitA->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        // Pagi record (wajib sebelum slide mengajar).
        Presensi::create([
            'pegawai_id' => $pegawai->id,
            'jadwal_id' => null,
            'unit_sekolah_id' => $this->unitA->id,
            'tipe_presensi' => 'kantor',
            'tanggal' => Carbon::today()->toDateString(),
            'jam_masuk' => '07:00:00',
            'status' => 'hadir',
        ]);

        return $pegawai;
    }

    private function makeMapel(Pegawai $pegawai, UnitSekolah $unit, string $nama = 'Matematika'): PegawaiMapel
    {
        $mapel = MataPelajaran::create([
            'nama' => $nama,
            'kode' => strtolower(substr($nama, 0, 3)),
        ]);

        return PegawaiMapel::create([
            'pegawai_id' => $pegawai->id,
            'mata_pelajaran_id' => $mapel->id,
            'unit_sekolah_id' => $unit->id,
        ]);
    }

    private function makeJadwal(
        Pegawai $pegawai,
        PegawaiMapel $pegawaiMapel,
        UnitSekolah $unit,
        string $hari,
        string $jamMulai,
        string $jamSelesai,
        string $kelasLabel = '7-A',
    ): Jadwal {
        return Jadwal::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $unit->id,
            'pegawai_mapel_id' => $pegawaiMapel->id,
            'kelas_label' => $kelasLabel,
            'hari' => $hari,
            'jam_mulai' => $jamMulai,
            'jam_selesai' => $jamSelesai,
            'jenis_jadwal' => 'mengajar',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);
    }

    private function postSlide(Jadwal $jadwal, float $lat = -6.2, float $lng = 106.8, float $accuracy = 15, string $tipe = 'masuk')
    {
        return $this->actingAs($jadwal->pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.slide'), [
                'jadwal_id' => $jadwal->id,
                'latitude' => $lat,
                'longitude' => $lng,
                'accuracy' => $accuracy,
                'tipe' => $tipe,
            ]);
    }

    // ========================================================================
    // 9 FUNCTIONAL SCENARIOS
    // ========================================================================

    public function test_slide_jp_a1_otomatis_cover_a2_a3_dengan_jam_mulai_sebelum_now(): void
    {
        // Hari ini (Senin), 09:00. JP group: A1 08:00-08:45, A2 08:45-09:30, A3 09:30-10:15.
        // Guru slide A1 jam 09:00 → harus cover A1+A2+A3 (jam_mulai <= 09:00).
        $hari = $this->hariIniIndo();
        Carbon::setTestNow(Carbon::today()->setTime(9, 0));

        $pegawai = $this->makePegawaiTetap();
        $pegawaiMapel = $this->makeMapel($pegawai, $this->unitA);
        $jpA1 = $this->makeJadwal($pegawai, $pegawaiMapel, $this->unitA, $hari, '08:00:00', '08:45:00');
        $jpA2 = $this->makeJadwal($pegawai, $pegawaiMapel, $this->unitA, $hari, '08:45:00', '09:30:00');
        $jpA3 = $this->makeJadwal($pegawai, $pegawaiMapel, $this->unitA, $hari, '09:30:00', '10:15:00');

        $res = $this->postSlide($jpA1);
        $res->assertOk()->assertJson(['success' => true]);

        // A1, A2, A3 semuanya tercatat sebagai presensi mengajar.
        $this->assertDatabaseHas('presensi', [
            'pegawai_id' => $pegawai->id,
            'jadwal_id' => $jpA1->id,
            'tanggal' => Carbon::today()->toDateString(),
        ]);
        $this->assertDatabaseHas('presensi', [
            'pegawai_id' => $pegawai->id,
            'jadwal_id' => $jpA2->id,
            'tanggal' => Carbon::today()->toDateString(),
        ]);
        $this->assertDatabaseHas('presensi', [
            'pegawai_id' => $pegawai->id,
            'jadwal_id' => $jpA3->id,
            'tanggal' => Carbon::today()->toDateString(),
        ]);

        Carbon::setTestNow();
    }

    public function test_slide_jp_a1_tanpa_a2_di_group_hanya_cover_a1(): void
    {
        // Hari ini, 08:30. JP group hanya A1 (tidak ada A2/A3).
        // Slide A1 → hanya cover A1.
        $hari = $this->hariIniIndo();
        Carbon::setTestNow(Carbon::today()->setTime(8, 30));

        $pegawai = $this->makePegawaiTetap();
        $pegawaiMapel = $this->makeMapel($pegawai, $this->unitA);
        $jpA1 = $this->makeJadwal($pegawai, $pegawaiMapel, $this->unitA, $hari, '08:00:00', '08:45:00');

        $res = $this->postSlide($jpA1);
        $res->assertOk()->assertJson(['success' => true]);

        // Hanya 1 presensi mengajar tercipta.
        $this->assertSame(1, Presensi::where('pegawai_id', $pegawai->id)
            ->whereNotNull('jadwal_id')
            ->where('tipe_presensi', 'mengajar')
            ->count());

        Carbon::setTestNow();
    }

    public function test_slide_tidak_cover_jadwal_kelas_berbeda(): void
    {
        // Hari ini, 09:00. JP group: A1 (7-A), A2 (7-B). Beda kelas → TIDAK cover.
        $hari = $this->hariIniIndo();
        Carbon::setTestNow(Carbon::today()->setTime(9, 0));

        $pegawai = $this->makePegawaiTetap();
        $pegawaiMapel = $this->makeMapel($pegawai, $this->unitA);
        $jpA1 = $this->makeJadwal($pegawai, $pegawaiMapel, $this->unitA, $hari, '08:00:00', '08:45:00', '7-A');
        $jpA2 = $this->makeJadwal($pegawai, $pegawaiMapel, $this->unitA, $hari, '08:45:00', '09:30:00', '7-B');

        $this->postSlide($jpA1)->assertOk();

        // Hanya JP 7-A yang tercatat.
        $this->assertDatabaseHas('presensi', ['pegawai_id' => $pegawai->id, 'jadwal_id' => $jpA1->id]);
        $this->assertDatabaseMissing('presensi', ['pegawai_id' => $pegawai->id, 'jadwal_id' => $jpA2->id]);

        Carbon::setTestNow();
    }

    public function test_slide_tidak_cover_jadwal_mapel_berbeda(): void
    {
        // Hari ini, 09:00. JP group: A1 (Matematika), A2 (IPA). Beda mapel → TIDAK cover.
        $hari = $this->hariIniIndo();
        Carbon::setTestNow(Carbon::today()->setTime(9, 0));

        $pegawai = $this->makePegawaiTetap();
        $pegawaiMapelMtk = $this->makeMapel($pegawai, $this->unitA, 'Matematika');
        $pegawaiMapelIpa = $this->makeMapel($pegawai, $this->unitA, 'IPA');
        $jpA1 = $this->makeJadwal($pegawai, $pegawaiMapelMtk, $this->unitA, $hari, '08:00:00', '08:45:00');
        $jpA2 = $this->makeJadwal($pegawai, $pegawaiMapelIpa, $this->unitA, $hari, '08:45:00', '09:30:00');

        $this->postSlide($jpA1)->assertOk();

        $this->assertDatabaseHas('presensi', ['pegawai_id' => $pegawai->id, 'jadwal_id' => $jpA1->id]);
        $this->assertDatabaseMissing('presensi', ['pegawai_id' => $pegawai->id, 'jadwal_id' => $jpA2->id]);

        Carbon::setTestNow();
    }

    public function test_slide_tidak_cover_jadwal_unit_berbeda(): void
    {
        // Hari ini, 09:00. JP group: A1 (unitA), A2 (unitB). Beda unit → TIDAK cover.
        $hari = $this->hariIniIndo();
        Carbon::setTestNow(Carbon::today()->setTime(9, 0));

        $pegawai = $this->makePegawaiTetap();
        $pegawaiMapelA = $this->makeMapel($pegawai, $this->unitA);
        $pegawaiMapelB = $this->makeMapel($pegawai, $this->unitB);
        $jpA1 = $this->makeJadwal($pegawai, $pegawaiMapelA, $this->unitA, $hari, '08:00:00', '08:45:00');
        $jpA2 = $this->makeJadwal($pegawai, $pegawaiMapelB, $this->unitB, $hari, '08:45:00', '09:30:00');

        $this->postSlide($jpA1)->assertOk();

        $this->assertDatabaseHas('presensi', ['pegawai_id' => $pegawai->id, 'jadwal_id' => $jpA1->id]);
        $this->assertDatabaseMissing('presensi', ['pegawai_id' => $pegawai->id, 'jadwal_id' => $jpA2->id]);

        Carbon::setTestNow();
    }

    public function test_double_slide_idempotent_tidak_buat_presensi_ganda(): void
    {
        // Hari ini, 08:30. Slide A1 dua kali (race condition / double tap).
        // Kedua slide harus idempotent — presensi A1 hanya tercatat 1x.
        $hari = $this->hariIniIndo();
        Carbon::setTestNow(Carbon::today()->setTime(8, 30));

        $pegawai = $this->makePegawaiTetap();
        $pegawaiMapel = $this->makeMapel($pegawai, $this->unitA);
        $jpA1 = $this->makeJadwal($pegawai, $pegawaiMapel, $this->unitA, $hari, '08:00:00', '08:45:00');

        // Slide pertama → sukses.
        $this->postSlide($jpA1)->assertOk();

        // Slide kedua → harus gagal 422 (unique constraint).
        $res = $this->postSlide($jpA1);
        $res->assertStatus(422)->assertJsonValidationErrors(['jadwal_id']);

        // Presensi mengajar hanya 1 row.
        $this->assertSame(1, Presensi::where('pegawai_id', $pegawai->id)
            ->where('jadwal_id', $jpA1->id)
            ->count());

        Carbon::setTestNow();
    }

    public function test_slide_ditolak_jika_di_luar_jendela_grace(): void
    {
        // Hari ini, 11:00. JP A1 sudah selesai (09:45 + grace 15 = 10:00).
        // Slide harus ditolak.
        $hari = $this->hariIniIndo();
        Carbon::setTestNow(Carbon::today()->setTime(11, 0));

        $pegawai = $this->makePegawaiTetap();
        $pegawaiMapel = $this->makeMapel($pegawai, $this->unitA);
        $jpA1 = $this->makeJadwal($pegawai, $pegawaiMapel, $this->unitA, $hari, '08:00:00', '09:45:00');

        $this->postSlide($jpA1)->assertStatus(422);

        $this->assertDatabaseMissing('presensi', ['pegawai_id' => $pegawai->id, 'jadwal_id' => $jpA1->id]);

        Carbon::setTestNow();
    }

    public function test_jp_auto_cover_memiliki_jam_dari_jadwal(): void
    {
        // Hari ini, 09:00. Cover A1+A2+A3.
        // Anchor A1: jam_masuk aktual, jam_keluar = jadwal.
        // JP cover (A2, A3): jam_masuk/jam_keluar = jadwal masing-masing.
        $hari = $this->hariIniIndo();
        Carbon::setTestNow(Carbon::today()->setTime(9, 0));

        $pegawai = $this->makePegawaiTetap();
        $pegawaiMapel = $this->makeMapel($pegawai, $this->unitA);
        $jpA1 = $this->makeJadwal($pegawai, $pegawaiMapel, $this->unitA, $hari, '08:00:00', '08:45:00');
        $jpA2 = $this->makeJadwal($pegawai, $pegawaiMapel, $this->unitA, $hari, '08:45:00', '09:30:00');
        $jpA3 = $this->makeJadwal($pegawai, $pegawaiMapel, $this->unitA, $hari, '09:30:00', '10:15:00');

        $this->postSlide($jpA1)->assertOk();

        // A1 (JP utama): jam_masuk terisi, jam_keluar = jadwal A1.
        $jpA1Row = Presensi::where('pegawai_id', $pegawai->id)->where('jadwal_id', $jpA1->id)->first();
        $this->assertNotNull($jpA1Row->jam_masuk);
        $this->assertSame('08:45:00', $jpA1Row->jam_keluar);

        // A2, A3 (cover otomatis): jam lengkap dari jadwal, tanpa lokasi.
        foreach ([$jpA2, $jpA3] as $jp) {
            $row = Presensi::where('pegawai_id', $pegawai->id)->where('jadwal_id', $jp->id)->first();
            $this->assertNotNull($row, "Presensi for jadwal_id={$jp->id} should exist");
            $this->assertSame($jp->jam_mulai, $row->jam_masuk, 'jam_masuk should be from jadwal for auto-cover JP');
            $this->assertSame($jp->jam_selesai, $row->jam_keluar, 'jam_keluar should be from jadwal for auto-cover JP');
            $this->assertNull($row->latitude_masuk, 'latitude_masuk should be NULL for auto-cover JP');
            $this->assertNull($row->longitude_masuk, 'longitude_masuk should be NULL for auto-cover JP');
        }

        Carbon::setTestNow();
    }

    public function test_status_jp_auto_cover_selalu_hadir(): void
    {
        // Hari ini, 09:00. JP A1 08:00-08:45, A2 08:45-09:30.
        // Anchor A1: telat (09:00 > 08:00, toleransi 0). Cover A2: selalu hadir
        // karena jam_masuknya = jadwal (08:45), bukan waktu slide.
        $hari = $this->hariIniIndo();
        Carbon::setTestNow(Carbon::today()->setTime(9, 0));

        $pegawai = $this->makePegawaiTetap();
        $pegawaiMapel = $this->makeMapel($pegawai, $this->unitA);
        $jpA1 = $this->makeJadwal($pegawai, $pegawaiMapel, $this->unitA, $hari, '08:00:00', '08:45:00');
        $jpA2 = $this->makeJadwal($pegawai, $pegawaiMapel, $this->unitA, $hari, '08:45:00', '09:30:00');

        $this->postSlide($jpA1)->assertOk();

        // A1: telat (09:00 > 08:00, toleransi 0).
        $this->assertSame('telat', Presensi::where('pegawai_id', $pegawai->id)->where('jadwal_id', $jpA1->id)->value('status'));
        // A2: hadir — jam cover dari jadwal, tidak dibanding waktu slide.
        $this->assertSame('hadir', Presensi::where('pegawai_id', $pegawai->id)->where('jadwal_id', $jpA2->id)->value('status'));

        Carbon::setTestNow();
    }

    // ========================================================================
    // 4 RACE CONDITION HARDENING SCENARIOS
    // ========================================================================

    public function test_slide_keluar_mengajar_ditolak_karena_jam_keluar_prefilled(): void
    {
        // Grup 3 JP: 08:00, 08:45, 09:30. Slide masuk A1 (cover semua, jam_keluar
        // = jadwal masing-masing) → slide keluar tidak lagi diperlukan: 422.
        $hari = $this->hariIniIndo();
        Carbon::setTestNow(Carbon::today()->setTime(9, 0));

        $pegawai = $this->makePegawaiTetap();
        $pegawaiMapel = $this->makeMapel($pegawai, $this->unitA);
        $jpA1 = $this->makeJadwal($pegawai, $pegawaiMapel, $this->unitA, $hari, '08:00:00', '08:45:00');
        $jpA2 = $this->makeJadwal($pegawai, $pegawaiMapel, $this->unitA, $hari, '08:45:00', '09:30:00');
        $jpA3 = $this->makeJadwal($pegawai, $pegawaiMapel, $this->unitA, $hari, '09:30:00', '10:15:00');

        $this->postSlide($jpA1)->assertOk();

        Carbon::setTestNow(Carbon::today()->setTime(10, 30));
        $this->postSlide($jpA1, tipe: 'keluar')->assertStatus(422);

        // Semua row: jam lengkap dari jadwal (anchor masuk aktual).
        $rowA1 = Presensi::where('pegawai_id', $pegawai->id)->where('jadwal_id', $jpA1->id)->first();
        $this->assertNotNull($rowA1->jam_masuk);
        $this->assertSame('08:45:00', $rowA1->jam_keluar);
        $rowA2 = Presensi::where('pegawai_id', $pegawai->id)->where('jadwal_id', $jpA2->id)->first();
        $this->assertSame('08:45:00', $rowA2->jam_masuk);
        $this->assertSame('09:30:00', $rowA2->jam_keluar);
        $rowA3 = Presensi::where('pegawai_id', $pegawai->id)->where('jadwal_id', $jpA3->id)->first();
        $this->assertSame('09:30:00', $rowA3->jam_masuk);
        $this->assertSame('10:15:00', $rowA3->jam_keluar);

        Carbon::setTestNow();
    }

    public function test_slide_keluar_grup_dobel_ditolak(): void
    {
        $hari = $this->hariIniIndo();
        Carbon::setTestNow(Carbon::today()->setTime(9, 0));

        $pegawai = $this->makePegawaiTetap();
        $pegawaiMapel = $this->makeMapel($pegawai, $this->unitA);
        $jpA1 = $this->makeJadwal($pegawai, $pegawaiMapel, $this->unitA, $hari, '08:00:00', '08:45:00');
        $jpA2 = $this->makeJadwal($pegawai, $pegawaiMapel, $this->unitA, $hari, '08:45:00', '09:30:00');

        $this->postSlide($jpA1)->assertOk();

        Carbon::setTestNow(Carbon::today()->setTime(9, 45));
        // Slide pulang ditolak (jam_keluar sudah pre-filled dari jadwal).
        $this->postSlide($jpA1, tipe: 'keluar')->assertStatus(422);

        Carbon::setTestNow();
    }

    public function test_pre_flight_check_skip_jp_yang_sudah_ada_presensinya(): void
    {
        // Hari ini, 09:00. A1, A2, A3. Pre-insert presensi untuk A2.
        // Slide A1 → harus cover A1+A3 (skip A2 karena sudah ada).
        $hari = $this->hariIniIndo();
        Carbon::setTestNow(Carbon::today()->setTime(9, 0));

        $pegawai = $this->makePegawaiTetap();
        $pegawaiMapel = $this->makeMapel($pegawai, $this->unitA);
        $jpA1 = $this->makeJadwal($pegawai, $pegawaiMapel, $this->unitA, $hari, '08:00:00', '08:45:00');
        $jpA2 = $this->makeJadwal($pegawai, $pegawaiMapel, $this->unitA, $hari, '08:45:00', '09:30:00');
        $jpA3 = $this->makeJadwal($pegawai, $pegawaiMapel, $this->unitA, $hari, '09:30:00', '10:15:00');

        // Pre-insert presensi A2 (existing record).
        $preExistingA2 = Presensi::create([
            'pegawai_id' => $pegawai->id,
            'jadwal_id' => $jpA2->id,
            'unit_sekolah_id' => $this->unitA->id,
            'tipe_presensi' => 'mengajar',
            'tanggal' => Carbon::today()->toDateString(),
            'jam_masuk' => '08:46:00',
            'status' => 'hadir',
        ]);

        $this->postSlide($jpA1)->assertOk();

        // A1 dan A3 tercipta.
        $this->assertDatabaseHas('presensi', ['pegawai_id' => $pegawai->id, 'jadwal_id' => $jpA1->id]);
        $this->assertDatabaseHas('presensi', ['pegawai_id' => $pegawai->id, 'jadwal_id' => $jpA3->id]);

        // A2 pre-existing tetap (tidak ditimpa/duplikat).
        $this->assertSame(1, Presensi::where('pegawai_id', $pegawai->id)->where('jadwal_id', $jpA2->id)->count());
        $this->assertSame($preExistingA2->id, Presensi::where('pegawai_id', $pegawai->id)->where('jadwal_id', $jpA2->id)->value('id'));

        Carbon::setTestNow();
    }

    public function test_per_jp_try_catch_unique_violation_tidak_rollback_seluruh_loop(): void
    {
        // Skenario: 1 JP di grup sudah ada presensi (dari race / external insert).
        // Loop cover harus skip JP itu (try-catch unique) dan tetap cover JP lainnya.
        // Implementasi: simulasi dengan pre-insert lalu invoke flow.
        $hari = $this->hariIniIndo();
        Carbon::setTestNow(Carbon::today()->setTime(9, 0));

        $pegawai = $this->makePegawaiTetap();
        $pegawaiMapel = $this->makeMapel($pegawai, $this->unitA);
        $jpA1 = $this->makeJadwal($pegawai, $pegawaiMapel, $this->unitA, $hari, '08:00:00', '08:45:00');
        $jpA2 = $this->makeJadwal($pegawai, $pegawaiMapel, $this->unitA, $hari, '08:45:00', '09:30:00');
        $jpA3 = $this->makeJadwal($pegawai, $pegawaiMapel, $this->unitA, $hari, '09:30:00', '10:15:00');

        // Pre-insert A3 (will conflict saat cover loop).
        Presensi::create([
            'pegawai_id' => $pegawai->id,
            'jadwal_id' => $jpA3->id,
            'unit_sekolah_id' => $this->unitA->id,
            'tipe_presensi' => 'mengajar',
            'tanggal' => Carbon::today()->toDateString(),
            'jam_masuk' => '09:31:00',
            'status' => 'hadir',
        ]);

        // Slide A1 — harus cover A1+A2, skip A3 (pre-existing).
        $res = $this->postSlide($jpA1);
        $res->assertOk();

        $this->assertDatabaseHas('presensi', ['pegawai_id' => $pegawai->id, 'jadwal_id' => $jpA1->id]);
        $this->assertDatabaseHas('presensi', ['pegawai_id' => $pegawai->id, 'jadwal_id' => $jpA2->id]);
        $this->assertSame(1, Presensi::where('pegawai_id', $pegawai->id)->where('jadwal_id', $jpA3->id)->count());

        Carbon::setTestNow();
    }

    public function test_cover_jp_di_luar_radius_per_unit_di_skip(): void
    {
        // Skenario: guru mengajar di unitA dan unitB.
        // Slide A1 (unitA) harus cover A2 (unitA) tapi SKIP A3 (unitB).
        // Forward-only: JP sebelum anchor tidak di-cover.
        $hari = $this->hariIniIndo();
        Carbon::setTestNow(Carbon::today()->setTime(9, 0));

        $pegawai = $this->makePegawaiTetap();
        $pegawaiMapelA = $this->makeMapel($pegawai, $this->unitA);
        $pegawaiMapelB = $this->makeMapel($pegawai, $this->unitB, 'IPA');

        $jpA1 = $this->makeJadwal($pegawai, $pegawaiMapelA, $this->unitA, $hari, '08:00:00', '08:45:00');
        $jpA2 = $this->makeJadwal($pegawai, $pegawaiMapelA, $this->unitA, $hari, '08:45:00', '09:30:00');
        // Beda unit, beda mapel → tidak masuk sesi.
        $jpA3 = $this->makeJadwal($pegawai, $pegawaiMapelB, $this->unitB, $hari, '08:00:00', '08:45:00', '7-A');

        $res = $this->postSlide($jpA1);
        $res->assertOk();

        // A1 (anchor), A2 (forward, same unit): covered.
        $this->assertDatabaseHas('presensi', ['pegawai_id' => $pegawai->id, 'jadwal_id' => $jpA1->id]);
        $this->assertDatabaseHas('presensi', ['pegawai_id' => $pegawai->id, 'jadwal_id' => $jpA2->id]);

        // A3 (unitB): NOT covered (beda grouping key — beda unit & mapel).
        $this->assertDatabaseMissing('presensi', ['pegawai_id' => $pegawai->id, 'jadwal_id' => $jpA3->id]);

        Carbon::setTestNow();
    }

    public function test_order_by_jam_mulai_asc_pada_cover_loop(): void
    {
        // Cover loop harus deterministic ORDER BY jam_mulai ASC.
        // Skenario: 3 JP (A1 08:00, A2 08:45, A3 09:30) di grup sama.
        // Saat di-insert, order harus konsisten — pakai field created_at atau id presensi.
        //
        // Test: insert 3 JP di grup. Slide A1. Assert presensi_id ordering = jam_mulai ordering.
        // Karena auto-increment, presensi.id mencerminkan insertion order.
        $hari = $this->hariIniIndo();
        Carbon::setTestNow(Carbon::today()->setTime(9, 0));

        $pegawai = $this->makePegawaiTetap();
        $pegawaiMapel = $this->makeMapel($pegawai, $this->unitA);
        $jpA1 = $this->makeJadwal($pegawai, $pegawaiMapel, $this->unitA, $hari, '08:00:00', '08:45:00');
        $jpA2 = $this->makeJadwal($pegawai, $pegawaiMapel, $this->unitA, $hari, '08:45:00', '09:30:00');
        $jpA3 = $this->makeJadwal($pegawai, $pegawaiMapel, $this->unitA, $hari, '09:30:00', '10:15:00');

        $this->postSlide($jpA1)->assertOk();

        // Ordering by jam_mulai ASC harus match presensi.id ASC untuk 3 JP ini.
        $rows = Presensi::where('pegawai_id', $pegawai->id)
            ->whereIn('jadwal_id', [$jpA1->id, $jpA2->id, $jpA3->id])
            ->orderBy('jadwal_id')
            ->get()
            ->keyBy('jadwal_id');

        $expectedOrder = [$jpA1->id, $jpA2->id, $jpA3->id];
        $actualPresensiIds = collect($expectedOrder)->map(fn ($jid) => $rows[$jid]->id)->all();

        // presensi.id harus ascending untuk jam_mulai ASC.
        $sorted = $actualPresensiIds;
        sort($sorted);
        $this->assertSame($sorted, $actualPresensiIds, 'presensi.id harus ascending (ORDER BY jam_mulai ASC)');

        Carbon::setTestNow();
    }

    // ========================================================================
    // GAP-BOUNDED SESSION SCENARIOS
    // ========================================================================

    public function test_gap_20_menit_merge_satu_sesi(): void
    {
        // JP A1 09:00-09:45, istirahat 20 menit, JP A2 10:05-10:50.
        // Slide A1 jam 09:10 → harus cover A1+A2 (gap 20 ≤ 60).
        $hari = $this->hariIniIndo();
        Carbon::setTestNow(Carbon::today()->setTime(9, 10));

        $pegawai = $this->makePegawaiTetap();
        $pegawaiMapel = $this->makeMapel($pegawai, $this->unitA);
        $jpA1 = $this->makeJadwal($pegawai, $pegawaiMapel, $this->unitA, $hari, '09:00:00', '09:45:00');
        $jpA2 = $this->makeJadwal($pegawai, $pegawaiMapel, $this->unitA, $hari, '10:05:00', '10:50:00');

        $this->postSlide($jpA1)->assertOk();

        $this->assertDatabaseHas('presensi', ['pegawai_id' => $pegawai->id, 'jadwal_id' => $jpA1->id]);
        $this->assertDatabaseHas('presensi', ['pegawai_id' => $pegawai->id, 'jadwal_id' => $jpA2->id]);

        Carbon::setTestNow();
    }

    public function test_gap_4_jam_pisah_sesi_terpisah(): void
    {
        // JP A1 08:00-08:45, istirahat 4 jam, JP A2 12:45-13:30.
        // Slide A1 jam 08:10 → HANYA cover A1 (gap 240 > 60 → sesi terpisah).
        $hari = $this->hariIniIndo();
        Carbon::setTestNow(Carbon::today()->setTime(8, 10));

        $pegawai = $this->makePegawaiTetap();
        $pegawaiMapel = $this->makeMapel($pegawai, $this->unitA);
        $jpA1 = $this->makeJadwal($pegawai, $pegawaiMapel, $this->unitA, $hari, '08:00:00', '08:45:00');
        $jpA2 = $this->makeJadwal($pegawai, $pegawaiMapel, $this->unitA, $hari, '12:45:00', '13:30:00');

        $this->postSlide($jpA1)->assertOk();

        $this->assertDatabaseHas('presensi', ['pegawai_id' => $pegawai->id, 'jadwal_id' => $jpA1->id]);
        $this->assertDatabaseMissing('presensi', ['pegawai_id' => $pegawai->id, 'jadwal_id' => $jpA2->id]);

        Carbon::setTestNow();
    }

    public function test_slide_mid_break_diterima(): void
    {
        // JP A1 09:00-09:45, istirahat 20 menit, JP A2 10:05-10:50.
        // Slide A1 jam 10:15 (mid-break, setelah A1 berakhir) → diterima
        // karena masih dalam sesi window (10:15 ≤ 10:50 + 15 grace).
        $hari = $this->hariIniIndo();
        Carbon::setTestNow(Carbon::today()->setTime(10, 15));

        $pegawai = $this->makePegawaiTetap();
        $pegawaiMapel = $this->makeMapel($pegawai, $this->unitA);
        $jpA1 = $this->makeJadwal($pegawai, $pegawaiMapel, $this->unitA, $hari, '09:00:00', '09:45:00');
        $jpA2 = $this->makeJadwal($pegawai, $pegawaiMapel, $this->unitA, $hari, '10:05:00', '10:50:00');

        $res = $this->postSlide($jpA1);
        $res->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('presensi', ['pegawai_id' => $pegawai->id, 'jadwal_id' => $jpA1->id]);
        $this->assertDatabaseHas('presensi', ['pegawai_id' => $pegawai->id, 'jadwal_id' => $jpA2->id]);

        // A1 status: telat (10:15 > 09:00).
        $this->assertSame('telat', Presensi::where('pegawai_id', $pegawai->id)->where('jadwal_id', $jpA1->id)->value('status'));

        Carbon::setTestNow();
    }

    public function test_cover_rows_have_keterangan_provenance(): void
    {
        // Cover rows harus punya keterangan provenance yang bisa ditrace.
        $hari = $this->hariIniIndo();
        Carbon::setTestNow(Carbon::today()->setTime(9, 0));

        $pegawai = $this->makePegawaiTetap();
        $pegawaiMapel = $this->makeMapel($pegawai, $this->unitA);
        $jpA1 = $this->makeJadwal($pegawai, $pegawaiMapel, $this->unitA, $hari, '08:00:00', '08:45:00');
        $jpA2 = $this->makeJadwal($pegawai, $pegawaiMapel, $this->unitA, $hari, '08:45:00', '09:30:00');
        $jpA3 = $this->makeJadwal($pegawai, $pegawaiMapel, $this->unitA, $hari, '09:30:00', '10:15:00');

        $this->postSlide($jpA1)->assertOk();

        // A1 (anchor): tidak ada keterangan (main slide).
        $a1 = Presensi::where('pegawai_id', $pegawai->id)->where('jadwal_id', $jpA1->id)->first();
        $this->assertNull($a1->keterangan);

        // A2, A3 (cover): ada keterangan provenance.
        foreach ([$jpA2, $jpA3] as $jp) {
            $row = Presensi::where('pegawai_id', $pegawai->id)->where('jadwal_id', $jp->id)->first();
            $this->assertNotNull($row->keterangan, "Cover row jadwal_id={$jp->id} harus ada keterangan");
            $this->assertStringContainsString('auto-cover', $row->keterangan);
            $this->assertStringContainsString("JP #{$jpA1->id}", $row->keterangan);
        }

        Carbon::setTestNow();
    }
}
