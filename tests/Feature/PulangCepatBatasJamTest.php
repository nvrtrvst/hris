<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Jadwal;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\PegawaiMapel;
use App\Models\PengajuanPulangCepat;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Izin pulang cepat = batas bawah jam pulang: boleh mengizinkan lebih awal,
 * tapi TIDAK PERNAH memperketat aturan normal (min jam izin vs aturan normal).
 */
class PulangCepatBatasJamTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private Pegawai $tetap;

    private Pegawai $nonTetap;

    private PegawaiMapel $pmTetap;

    private PegawaiMapel $pmNonTetap;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->unit = UnitSekolah::create([
            'nama' => 'SD Uji Batas Jam',
            'singkatan' => 'SD',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'toleransi_menit' => 0,
            'toleransi_slide_menit' => 15,
            'wajib_pulang_mengajar' => true,
            'pulang_sebelum_menit' => 20,
            'jam_masuk_kantor' => '07:00:00',
            'jam_pulang_kantor' => '15:00:00',
        ]);

        $this->tetap = $this->makePegawai('guru_tetap_yayasan', 'Guru Tetap');
        $this->nonTetap = $this->makePegawai('guru_tidak_tetap', 'Guru Honorer');

        $mapel = MataPelajaran::create(['nama' => 'Matematika']);
        $this->pmTetap = PegawaiMapel::create([
            'pegawai_id' => $this->tetap->id,
            'mata_pelajaran_id' => $mapel->id,
            'unit_sekolah_id' => $this->unit->id,
        ]);
        $this->pmNonTetap = PegawaiMapel::create([
            'pegawai_id' => $this->nonTetap->id,
            'mata_pelajaran_id' => $mapel->id,
            'unit_sekolah_id' => $this->unit->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makePegawai(string $statusKepegawaian, string $nama): Pegawai
    {
        $jabatan = Jabatan::create(['nama' => 'Guru '.$nama]);
        $user = User::factory()->create();
        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '3273'.str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT),
            'nama_lengkap' => $nama,
            'jenis_kelamin' => 'L',
            'status_kepegawaian' => $statusKepegawaian,
            'status_aktif' => 'aktif',
            'tmt_mengajar' => '2020-01-01',
        ]);
        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    private function izin(Pegawai $pegawai, string $tanggal, string $jam): void
    {
        // 'status' tidak mass-assignable di model → set eksplisit.
        $izin = new PengajuanPulangCepat([
            'pegawai_id' => $pegawai->id,
            'tanggal' => $tanggal,
            'jam_diminta' => $jam,
            'alasan' => 'keperluan_pribadi',
            'nomor' => 'PPC/'.$tanggal.'/'.str_replace(':', '', $jam).'-'.$pegawai->id,
        ]);
        $izin->status = 'disetujui';
        $izin->save();
    }

    private function foto(): string
    {
        $img = imagecreatetruecolor(320, 240);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);

        return 'data:image/png;base64,'.base64_encode($png);
    }

    private function absen(Pegawai $pegawai, array $payload)
    {
        return $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.store'), array_merge([
                'foto' => $this->foto(),
                'latitude' => -6.2,
                'longitude' => 106.8,
                'accuracy' => 15,
            ], $payload));
    }

    private function kantorMasuk(Pegawai $pegawai, string $tanggal): void
    {
        $this->absen($pegawai, ['tipe' => 'masuk'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('presensi', [
            'pegawai_id' => $pegawai->id,
            'tipe_presensi' => 'kantor',
            'tanggal' => $tanggal,
        ]);
    }

    // --- Kantor ---

    public function test_kantor_tanpa_izin_tetap_ditolak_sebelum_jam_pulang(): void
    {
        Carbon::setTestNow('2026-08-21 07:00:00');
        $this->kantorMasuk($this->tetap, '2026-08-21');

        Carbon::setTestNow('2026-08-21 14:30:00');
        $res = $this->absen($this->tetap, ['tipe' => 'keluar'])->assertStatus(422);
        $this->assertSame('Anda belum bisa pulang. Jam pulang kantor: 15:00.', $res->json('message'));

        $this->assertNull(
            Presensi::where('pegawai_id', $this->tetap->id)->where('tipe_presensi', 'kantor')->first()->jam_keluar
        );
    }

    public function test_kantor_izin_lebih_awal_tetap_ditolak_sebelum_jam_izin(): void
    {
        Carbon::setTestNow('2026-08-21 07:00:00');
        $this->kantorMasuk($this->tetap, '2026-08-21');
        $this->izin($this->tetap, '2026-08-21', '10:00:00');

        Carbon::setTestNow('2026-08-21 09:30:00');
        $res = $this->absen($this->tetap, ['tipe' => 'keluar'])->assertStatus(422);
        $this->assertSame('Tombol pulang tersedia pukul 10:00.', $res->json('message'));
    }

    public function test_kantor_izin_membuka_gate_setelah_jam_izin(): void
    {
        Carbon::setTestNow('2026-08-21 07:00:00');
        $this->kantorMasuk($this->tetap, '2026-08-21');
        $this->izin($this->tetap, '2026-08-21', '10:00:00');

        Carbon::setTestNow('2026-08-21 10:05:00');
        $this->absen($this->tetap, ['tipe' => 'keluar'])->assertOk()->assertJson(['success' => true]);

        $this->assertNotNull(
            Presensi::where('pegawai_id', $this->tetap->id)->where('tipe_presensi', 'kantor')->first()->jam_keluar
        );
    }

    public function test_izin_tidak_memperketat_jam_pulang_kantor(): void
    {
        // Izin 19:00 (setelah jam kantor 18:00) tidak boleh menahan pulang 18:30 —
        // kalau jam izin dipakai mentah, pulang normal kantor ikut terblokir.
        Carbon::setTestNow('2026-08-21 07:00:00');
        $this->kantorMasuk($this->tetap, '2026-08-21');
        $this->unit->update(['jam_pulang_kantor' => '18:00:00']);
        $this->izin($this->tetap, '2026-08-21', '19:00:00');

        Carbon::setTestNow('2026-08-21 18:30:00');
        $this->absen($this->tetap, ['tipe' => 'keluar'])->assertOk()->assertJson(['success' => true]);
    }

    public function test_dua_izin_sehari_memakai_jam_paling_awal(): void
    {
        Carbon::setTestNow('2026-08-21 07:00:00');
        $this->kantorMasuk($this->tetap, '2026-08-21');
        $this->izin($this->tetap, '2026-08-21', '13:00:00');
        $this->izin($this->tetap, '2026-08-21', '09:00:00');

        Carbon::setTestNow('2026-08-21 09:30:00');
        $this->absen($this->tetap, ['tipe' => 'keluar'])->assertOk()->assertJson(['success' => true]);
    }

    // --- Slide keluar (pegawai tetap / wajib pulang mengajar) ---

    private function jadwalTetap(string $mulai, string $selesai): Jadwal
    {
        return Jadwal::create([
            'pegawai_id' => $this->tetap->id,
            'unit_sekolah_id' => $this->unit->id,
            'pegawai_mapel_id' => $this->pmTetap->id,
            'hari' => Carbon::now()->locale('id')->dayName,
            'jam_mulai' => $mulai,
            'jam_selesai' => $selesai,
            'jenis_jadwal' => 'mengajar',
            'kelas_label' => 'X-A',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);
    }

    private function slide(array $extra = [])
    {
        return $this->actingAs($this->tetap->user, 'web_mobile')
            ->postJson(route('presensi.absen.slide'), array_merge([
                'latitude' => -6.2,
                'longitude' => 106.8,
                'accuracy' => 15,
            ], $extra));
    }

    public function test_slide_keluar_tanpa_izin_dibatasi_window_normal(): void
    {
        Carbon::setTestNow('2026-08-21 10:00:00'); // JP 10:00-12:00, window 11:40
        $jadwal = $this->jadwalTetap('10:00', '12:00');
        Presensi::create([
            'pegawai_id' => $this->tetap->id,
            'unit_sekolah_id' => $this->unit->id,
            'tipe_presensi' => 'kantor',
            'tanggal' => '2026-08-21',
            'jam_masuk' => '07:05:00',
        ]);

        $this->slide(['jadwal_id' => $jadwal->id])->assertOk();

        $res = $this->slide(['jadwal_id' => $jadwal->id, 'tipe' => 'keluar'])->assertStatus(422);
        $this->assertSame('Tombol pulang tersedia pukul 11:40.', $res->json('message'));

        // Tepat pada batas → lolos.
        Carbon::setTestNow('2026-08-21 11:40:00');
        $this->slide(['jadwal_id' => $jadwal->id, 'tipe' => 'keluar'])->assertOk()->assertJson(['success' => true]);
    }

    public function test_slide_keluar_izin_lebih_awal_membuka_gate(): void
    {
        Carbon::setTestNow('2026-08-21 10:00:00');
        $jadwal = $this->jadwalTetap('10:00', '12:00');
        $this->izin($this->tetap, '2026-08-21', '09:00:00');
        Presensi::create([
            'pegawai_id' => $this->tetap->id,
            'unit_sekolah_id' => $this->unit->id,
            'tipe_presensi' => 'kantor',
            'tanggal' => '2026-08-21',
            'jam_masuk' => '07:05:00',
        ]);

        $this->slide(['jadwal_id' => $jadwal->id])->assertOk();

        Carbon::setTestNow('2026-08-21 10:05:00');
        $this->slide(['jadwal_id' => $jadwal->id, 'tipe' => 'keluar'])->assertOk()->assertJson(['success' => true]);
    }

    public function test_slide_keluar_izin_lebih_lambat_tidak_memperketat(): void
    {
        // JP 10:00-12:00 (normal 11:40). Izin 14:00 tidak boleh menahan pulang 11:45.
        Carbon::setTestNow('2026-08-21 10:00:00');
        $jadwal = $this->jadwalTetap('10:00', '12:00');
        $this->izin($this->tetap, '2026-08-21', '14:00:00');
        Presensi::create([
            'pegawai_id' => $this->tetap->id,
            'unit_sekolah_id' => $this->unit->id,
            'tipe_presensi' => 'kantor',
            'tanggal' => '2026-08-21',
            'jam_masuk' => '07:05:00',
        ]);

        $this->slide(['jadwal_id' => $jadwal->id])->assertOk();

        Carbon::setTestNow('2026-08-21 11:45:00');
        $this->slide(['jadwal_id' => $jadwal->id, 'tipe' => 'keluar'])->assertOk()->assertJson(['success' => true]);
    }

    // --- Keluar mengajar non-tetap (jalur absen biasa) ---

    private function jadwalNonTetap(string $mulai, string $selesai): Jadwal
    {
        return Jadwal::create([
            'pegawai_id' => $this->nonTetap->id,
            'unit_sekolah_id' => $this->unit->id,
            'pegawai_mapel_id' => $this->pmNonTetap->id,
            'hari' => Carbon::now()->locale('id')->dayName,
            'jam_mulai' => $mulai,
            'jam_selesai' => $selesai,
            'jenis_jadwal' => 'mengajar',
            'kelas_label' => 'X-B',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);
    }

    /**
     * Batas (ceiling) yang disengaja: prefill di storeAbsenTransaction menutup
     * record non-tetap lebih dulu, jadi bar keras di jalur keluar mengajar belum
     * pernah kena (flag ON maupun OFF sama). Saat prefill itu dibalik untuk
     * non-tetap, ganti assert ini dengan 422 "Tombol pulang tersedia pukul ...".
     */
    public function test_keluar_mengajar_non_tetap_ditutup_prefill_bukan_bar(): void
    {
        Carbon::setTestNow('2026-08-21 07:00:00');
        $jadwal = $this->jadwalNonTetap('07:00', '15:00');
        $this->izin($this->nonTetap, '2026-08-21', '13:00:00');

        $this->absen($this->nonTetap, ['tipe' => 'masuk', 'jadwal_id' => $jadwal->id])
            ->assertOk()->assertJson(['success' => true]);

        $row = Presensi::where('pegawai_id', $this->nonTetap->id)->where('jadwal_id', $jadwal->id)->first();
        $this->assertSame('15:00', substr((string) $row->jam_keluar, 0, 5), 'Prefill menutup record non-tetap.');

        Carbon::setTestNow('2026-08-21 12:30:00');
        $this->absen($this->nonTetap, ['tipe' => 'keluar', 'jadwal_id' => $jadwal->id])->assertStatus(422);
    }
}
