<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileAbsenMotionSamplesTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unit = UnitSekolah::create([
            'nama' => 'SMP Test',
            'singkatan' => 'SMP',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'toleransi_menit' => 0,
            'toleransi_tap_menit' => 15,
        ]);
    }

    private function makePegawaiKantor(): Pegawai
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
            'alamat_ktp' => 'Jl. Test No. 1',
            'no_hp' => '081234567890',
            'status_kepegawaian' => 'tetap',
            'tanggal_mulai_kerja' => '2020-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
            'wajib_kantor' => true,
        ]);

        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
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

    public function test_absen_masuk_dengan_motion_samples_json_tidak_500(): void
    {
        $pegawai = $this->makePegawaiKantor();

        $motionSamples = [
            ['x' => 0.1, 'y' => 0.2, 'z' => 9.8, 'timestamp' => 1000],
            ['x' => 0.3, 'y' => -0.1, 'z' => 9.9, 'timestamp' => 1100],
            ['x' => -0.2, 'y' => 0.4, 'z' => 9.7, 'timestamp' => 1200],
        ];

        $res = $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.store'), [
                'tipe' => 'masuk',
                'foto' => $this->fotoBase64(),
                'latitude' => -6.2,
                'longitude' => 106.8,
                'accuracy' => 15,
                'motion_samples' => json_encode($motionSamples),
            ]);

        $res->assertOk()->assertJson(['success' => true]);

        $record = Presensi::where('pegawai_id', $pegawai->id)->first();
        $this->assertNotNull($record);
        $this->assertSame('kantor', $record->tipe_presensi);
        $this->assertSame(3, count($record->motion_samples));
    }
}
