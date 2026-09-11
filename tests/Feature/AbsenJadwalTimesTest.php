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
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Flow A (honorer / storeAbsen): JP pertama yang hadir memakai jam aktual user;
 * JP lanjutan (mapel+kelas+unit sama) memakai jam dari jadwal. Semua jam_keluar
 * mengajar pre-filled dari jadwal — absen keluar per JP tidak diperlukan.
 */
class AbsenJadwalTimesTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->unit = UnitSekolah::create([
            'nama' => 'SMK Test',
            'singkatan' => 'SMK',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 40,
            'toleransi_menit' => 0,
            'toleransi_slide_menit' => 15,
        ]);
    }

    private function makePegawaiHonorer(): Pegawai
    {
        $jabatan = Jabatan::create(['nama' => 'Guru']);
        $user = User::factory()->create();
        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '1234567890'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT),
            'nama_lengkap' => 'Guru Honorer',
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'kawin',
            'jumlah_tanggungan' => 2,
            'alamat' => 'Jl. Test No. 1',
            'no_hp' => '081234567890',
            'status_kepegawaian' => 'guru_honorer',
            'tmt_mengajar' => '2020-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
            'wajib_kantor' => false,
        ]);

        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    private function makeMapel(Pegawai $pegawai): PegawaiMapel
    {
        $mapel = MataPelajaran::create(['nama' => 'Matematika', 'kode' => 'mat']);

        return PegawaiMapel::create([
            'pegawai_id' => $pegawai->id,
            'mata_pelajaran_id' => $mapel->id,
            'unit_sekolah_id' => $this->unit->id,
        ]);
    }

    private function makeJadwal(Pegawai $pegawai, PegawaiMapel $pegawaiMapel, string $hari, string $mulai, string $selesai): Jadwal
    {
        return Jadwal::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'pegawai_mapel_id' => $pegawaiMapel->id,
            'kelas_label' => 'X MPLB 4',
            'hari' => $hari,
            'jam_mulai' => $mulai,
            'jam_selesai' => $selesai,
            'jenis_jadwal' => 'mengajar',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);
    }

    private function fotoBase64(): string
    {
        $img = imagecreatetruecolor(320, 240);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);

        return 'data:image/png;base64,'.base64_encode($png);
    }

    private function postAbsenMasuk(Pegawai $pegawai, Jadwal $jadwal): TestResponse
    {
        return $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.store'), [
                'tipe' => 'masuk',
                'foto' => $this->fotoBase64(),
                'jadwal_id' => $jadwal->id,
                'latitude' => -6.2,
                'longitude' => 106.8,
                'accuracy' => 15,
            ]);
    }

    private function rowFor(Pegawai $pegawai, Jadwal $jadwal): ?Presensi
    {
        return Presensi::where('pegawai_id', $pegawai->id)->where('jadwal_id', $jadwal->id)->first();
    }

    public function test_tiga_jp_semua_hadir(): void
    {
        // JP1 08:30-09:10, JP2 09:10-09:40, JP3 09:40-10:20 (mapel+kelas sama).
        // Absen JP1 08:35 → anchor + auto-cover JP2 + auto-cover JP3 (forward-only).
        // Attempt absen JP2/JP3 → 422 SUDAH_ABSEN_MASUK (sudah auto-cover).
        $hariMap = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];

        $pegawai = $this->makePegawaiHonorer();
        $pegawaiMapel = $this->makeMapel($pegawai);
        $hari = $hariMap[Carbon::now()->format('l')];
        $jp1 = $this->makeJadwal($pegawai, $pegawaiMapel, $hari, '08:30:00', '09:10:00');
        $jp2 = $this->makeJadwal($pegawai, $pegawaiMapel, $hari, '09:10:00', '09:40:00');
        $jp3 = $this->makeJadwal($pegawai, $pegawaiMapel, $hari, '09:40:00', '10:20:00');

        Carbon::setTestNow(Carbon::today()->setTime(8, 35));
        $this->postAbsenMasuk($pegawai, $jp1)->assertOk()->assertJson(['success' => true]);

        $r1 = $this->rowFor($pegawai, $jp1);
        $this->assertSame('08:35:00', $r1->jam_masuk, 'JP1 jam_masuk = aktual user (anchor)');
        $this->assertSame('09:10:00', $r1->jam_keluar, 'JP1 jam_keluar = jadwal');
        $this->assertSame('telat', $r1->status, 'JP1 telat (08:35 > 08:30)');

        $r2 = $this->rowFor($pegawai, $jp2);
        $this->assertNotNull($r2, 'JP2 auto-cover row exists');
        $this->assertSame('09:10:00', $r2->jam_masuk, 'JP2 jam_masuk = jadwal');
        $this->assertSame('09:40:00', $r2->jam_keluar, 'JP2 jam_keluar = jadwal');
        $this->assertSame('hadir', $r2->status, 'JP2 auto-cover status hadir');

        $r3 = $this->rowFor($pegawai, $jp3);
        $this->assertNotNull($r3, 'JP3 auto-cover row exists');
        $this->assertSame('09:40:00', $r3->jam_masuk, 'JP3 jam_masuk = jadwal');
        $this->assertSame('10:20:00', $r3->jam_keluar, 'JP3 jam_keluar = jadwal');
        $this->assertSame('hadir', $r3->status, 'JP3 auto-cover status hadir');

        // Attempt absen JP2 manual → ditolak karena sudah auto-cover.
        Carbon::setTestNow(Carbon::today()->setTime(9, 12));
        $this->postAbsenMasuk($pegawai, $jp2)->assertStatus(422);

        Carbon::setTestNow(Carbon::today()->setTime(9, 45));
        $this->postAbsenMasuk($pegawai, $jp3)->assertStatus(422);

        Carbon::setTestNow();
    }

    public function test_jp1_tidak_hadir_jp2_pakai_jam_aktual(): void
    {
        // JP1 TIDAK diabsen (alpa, no row). JP2 absen 09:12 → jadi JP pertama yang
        // hadir → jam aktual user. JP3 (lanjut JP2) auto-cover dengan jam jadwal.
        // Forward-only: JP1 TIDAK auto-cover karena sebelum anchor.
        $hariMap = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];

        $pegawai = $this->makePegawaiHonorer();
        $pegawaiMapel = $this->makeMapel($pegawai);
        $hari = $hariMap[Carbon::now()->format('l')];
        $jp1 = $this->makeJadwal($pegawai, $pegawaiMapel, $hari, '08:30:00', '09:10:00');
        $jp2 = $this->makeJadwal($pegawai, $pegawaiMapel, $hari, '09:10:00', '09:40:00');
        $jp3 = $this->makeJadwal($pegawai, $pegawaiMapel, $hari, '09:40:00', '10:20:00');

        Carbon::setTestNow(Carbon::today()->setTime(9, 12));
        $this->postAbsenMasuk($pegawai, $jp2)->assertOk()->assertJson(['success' => true]);

        $this->assertNull($this->rowFor($pegawai, $jp1), 'JP1 tidak di-cover (forward-only, sebelum anchor)');

        $r2 = $this->rowFor($pegawai, $jp2);
        $this->assertSame('09:12:00', $r2->jam_masuk, 'JP2 jadi JP pertama → jam aktual user');
        $this->assertSame('09:40:00', $r2->jam_keluar, 'JP2 jam_keluar = jadwal');
        $this->assertSame('telat', $r2->status, 'JP2 telat (09:12 > 09:10)');

        $r3 = $this->rowFor($pegawai, $jp3);
        $this->assertNotNull($r3, 'JP3 auto-cover row exists (forward dari JP2)');
        $this->assertSame('09:40:00', $r3->jam_masuk, 'JP3 jam_masuk = jadwal');
        $this->assertSame('10:20:00', $r3->jam_keluar, 'JP3 jam_keluar = jadwal');
        $this->assertSame('hadir', $r3->status, 'JP3 auto-cover status hadir');

        Carbon::setTestNow();
    }

    public function test_jp_mapel_berbeda_tidak_lanjutan(): void
    {
        // JP1 Matematika 08:30-09:10 diabsen. JP2 (mapel beda, jam sama-nyambung)
        // 09:10-09:40 → BUKAN lanjutan → jam aktual user.
        $hariMap = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];

        $pegawai = $this->makePegawaiHonorer();
        $pegawaiMapel = $this->makeMapel($pegawai);
        $mapelLain = MataPelajaran::create(['nama' => 'Bahasa Indonesia', 'kode' => 'bin']);
        $pegawaiMapelLain = PegawaiMapel::create([
            'pegawai_id' => $pegawai->id,
            'mata_pelajaran_id' => $mapelLain->id,
            'unit_sekolah_id' => $this->unit->id,
        ]);
        $hari = $hariMap[Carbon::now()->format('l')];
        $jp1 = $this->makeJadwal($pegawai, $pegawaiMapel, $hari, '08:30:00', '09:10:00');

        $jp2 = Jadwal::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'pegawai_mapel_id' => $pegawaiMapelLain->id,
            'kelas_label' => 'X MPLB 4',
            'hari' => $hari,
            'jam_mulai' => '09:10:00',
            'jam_selesai' => '09:40:00',
            'jenis_jadwal' => 'mengajar',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);

        Carbon::setTestNow(Carbon::today()->setTime(8, 28));
        $this->postAbsenMasuk($pegawai, $jp1)->assertOk();
        Carbon::setTestNow(Carbon::today()->setTime(9, 12));
        $this->postAbsenMasuk($pegawai, $jp2)->assertOk();

        $r2 = $this->rowFor($pegawai, $jp2);
        $this->assertSame('09:12:00', $r2->jam_masuk, 'Mapel beda → jam aktual user');
        $this->assertSame('telat', $r2->status);

        Carbon::setTestNow();
    }

    public function test_absen_keluar_mengajar_ditolak_karena_prefilled(): void
    {
        // jam_keluar sudah pre-filled dari jadwal → absen keluar ditolak.
        $hariMap = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];

        $pegawai = $this->makePegawaiHonorer();
        $pegawaiMapel = $this->makeMapel($pegawai);
        $hari = $hariMap[Carbon::now()->format('l')];
        $jp1 = $this->makeJadwal($pegawai, $pegawaiMapel, $hari, '08:30:00', '09:10:00');

        Carbon::setTestNow(Carbon::today()->setTime(8, 28));
        $this->postAbsenMasuk($pegawai, $jp1)->assertOk();

        Carbon::setTestNow(Carbon::today()->setTime(9, 5));
        $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.store'), [
                'tipe' => 'keluar',
                'foto' => $this->fotoBase64(),
                'jadwal_id' => $jp1->id,
                'latitude' => -6.2,
                'longitude' => 106.8,
                'accuracy' => 15,
            ])->assertStatus(422);

        $r1 = $this->rowFor($pegawai, $jp1);
        $this->assertSame('09:10:00', $r1->jam_keluar, 'jam_keluar tetap dari jadwal');

        Carbon::setTestNow();
    }
}
