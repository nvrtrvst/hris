<?php

namespace Tests\Feature;

use App\Models\HariLibur;
use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\Presensi;
use App\Models\StatusKepegawaian;
use App\Models\UnitSekolah;
use App\Models\User;
use App\Support\HariKerja;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileLemburWindowTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private Pegawai $pegawai;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-20 10:00:00');

        $this->unit = UnitSekolah::create([
            'nama' => 'SMK Lembur',
            'singkatan' => 'SMK',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'jam_masuk_kantor' => '07:00:00',
            'jam_pulang_kantor' => '15:00:00',
            'toleransi_menit' => 0,
        ]);

        $jabatan = Jabatan::create(['nama' => 'Guru Lembur']);
        $this->pegawai = Pegawai::create([
            'user_id' => User::factory()->create(['email' => 'lembur@example.test'])->id,
            'nik' => '3273010101900199',
            'nama_lengkap' => 'Guru Lembur',
            'jenis_kelamin' => 'L',
            'status_kepegawaian' => 'guru_tetap_yayasan',
            'status_aktif' => 'aktif',
            'tmt_mengajar' => '2020-01-01',
            'wajib_kantor' => true,
        ]);
        $this->pegawai->units()->attach($this->unit->id, [
            'jabatan_id' => $jabatan->id,
            'is_primary' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'tipe' => 'masuk',
            'foto' => $this->fotoBase64(),
            'latitude' => -6.2,
            'longitude' => 106.8,
            'accuracy' => 10,
            'is_lembur' => true,
        ], $overrides);
    }

    private function fotoBase64(): string
    {
        $img = imagecreatetruecolor(32, 32);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);

        return 'data:image/png;base64,'.base64_encode($png);
    }

    public function test_window_menunggu_jam_selesai_paling_akhir_semua_unit(): void
    {
        $unitKedua = UnitSekolah::create([
            'nama' => 'Unit Kedua',
            'singkatan' => 'U2',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'jam_masuk_kantor' => '12:00:00',
            'jam_pulang_kantor' => '20:00:00',
        ]);
        $this->pegawai->units()->attach($unitKedua->id, [
            'jabatan_id' => Jabatan::first()->id,
            'is_primary' => false,
        ]);

        $window = HariKerja::lemburWindow($this->pegawai, Carbon::parse('2026-08-20 16:00:00'));
        $this->assertFalse($window['tersedia']);
        $this->assertSame('20:00', $window['batas_akhir']);
        $this->assertSame('U2', $window['unit_terakhir']);

        $window = HariKerja::lemburWindow($this->pegawai, Carbon::parse('2026-08-20 20:00:00'));
        $this->assertTrue($window['tersedia']);
    }

    public function test_unit_buka_tanpa_jam_selesai_memblokir(): void
    {
        $this->unit->update(['jam_pulang_kantor' => null]);

        $window = HariKerja::lemburWindow($this->pegawai, Carbon::parse('2026-08-20 16:00:00'));
        $this->assertFalse($window['tersedia']);
        $this->assertNull($window['batas_akhir']);
        $this->assertStringContainsString('belum lengkap', (string) $window['alasan']);
    }

    public function test_unit_libur_dan_semua_unit_tutup_tetap_mengizinkan_lembur(): void
    {
        HariLibur::create([
            'tanggal' => '2026-08-20',
            'nama' => 'Libur',
            'unit_sekolah_id' => $this->unit->id,
        ]);

        $window = HariKerja::lemburWindow($this->pegawai, Carbon::parse('2026-08-20 10:00:00'));
        $this->assertTrue($window['tersedia']);
    }

    public function test_halaman_absen_mengirim_window_dan_endpoint_menolak_di_jam_kerja(): void
    {
        $page = $this->actingAs($this->pegawai->user, 'web_mobile')
            ->get(route('presensi.absen'))
            ->assertOk();
        $this->assertFalse($page->viewData('page')['props']['lemburWindow']['tersedia']);

        $this->actingAs($this->pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.store'), $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('lembur');
    }

    public function test_endpoint_mengizinkan_lembur_setelah_jam_kerja(): void
    {
        Carbon::setTestNow('2026-08-20 16:00:00');

        $this->actingAs($this->pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.store'), $this->payload())
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('presensi', [
            'pegawai_id' => $this->pegawai->id,
            'is_lembur' => true,
            'tipe_presensi' => 'lembur',
        ]);
    }

    public function test_checkout_lembur_terbuka_di_jam_kerja_tetap_diizinkan(): void
    {
        $presensi = Presensi::create([
            'pegawai_id' => $this->pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'tipe_presensi' => 'lembur',
            'tanggal' => '2026-08-20',
            'jam_masuk' => '07:00:00',
        ]);
        $presensi->forceFill(['status' => 'hadir', 'is_lembur' => true, 'lembur_status' => 'pending'])->save();

        $this->actingAs($this->pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.store'), $this->payload(['tipe' => 'keluar']))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertNotNull($presensi->fresh()->jam_keluar);
    }

    public function test_store_absen_tetap_menolak_lembur_di_jam_kerja(): void
    {
        StatusKepegawaian::create([
            'kode' => 'GTYS',
            'label' => 'Guru Tetap Yayasan',
            'is_tetap' => true,
            'is_guru' => true,
            'is_active' => true,
        ]);
        $this->pegawai->update(['status_kepegawaian' => 'GTYS']);

        $this->actingAs($this->pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.tetap'), $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('lembur');
    }
}
