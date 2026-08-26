<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PresensiBlockedOnLeaveTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->unit = UnitSekolah::create([
            'nama' => 'SMP Test',
            'singkatan' => 'SMP',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'jam_masuk_kantor' => '07:00:00',
            'jam_pulang_kantor' => '15:00:00',
            'toleransi_menit' => 0,
        ]);
    }

    private function makePegawai(): Pegawai
    {
        $jabatan = Jabatan::create(['nama' => 'Guru']);
        $user = User::factory()->create();
        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '1234567890'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT),
            'nama_lengkap' => 'Guru Test',
            'jenis_kelamin' => 'L',
            'status_kepegawaian' => 'tetap',
            'status_aktif' => 'aktif',
            'tanggal_mulai_kerja' => '2020-01-01',
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

    private function postPresensi(Pegawai $pegawai)
    {
        return $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.store'), [
                'tipe' => 'masuk',
                'foto' => $this->fotoBase64(),
                'latitude' => $this->unit->latitude,
                'longitude' => $this->unit->longitude,
                'accuracy' => 15,
            ]);
    }

    private function seedLeave(Pegawai $pegawai, string $status): void
    {
        $leave = new Presensi;
        $leave->pegawai_id = $pegawai->id;
        $leave->tanggal = '2026-08-26';
        $leave->unit_sekolah_id = $this->unit->id;
        $leave->status = $status;
        $leave->save();
    }

    public function test_presensi_normal_hari_biasa_sukses(): void
    {
        Carbon::setTestNow('2026-08-26 08:00:00');
        $pegawai = $this->makePegawai();

        $res = $this->postPresensi($pegawai);

        $res->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('presensi', [
            'pegawai_id' => $pegawai->id,
            'tanggal' => '2026-08-26',
        ]);
        Carbon::setTestNow();
    }

    public function test_presensi_diblokir_jika_sudah_izin(): void
    {
        Carbon::setTestNow('2026-08-26 08:00:00');
        $pegawai = $this->makePegawai();
        $this->seedLeave($pegawai, 'izin');

        $res = $this->postPresensi($pegawai);

        $res->assertStatus(422)->assertJsonValidationErrors('conflict');
        $this->assertDatabaseMissing('presensi', [
            'pegawai_id' => $pegawai->id,
            'tanggal' => '2026-08-26',
            'status' => 'hadir',
        ]);
        Carbon::setTestNow();
    }

    public function test_presensi_diblokir_jika_sudah_cuti(): void
    {
        Carbon::setTestNow('2026-08-26 08:00:00');
        $pegawai = $this->makePegawai();
        $this->seedLeave($pegawai, 'cuti');

        $res = $this->postPresensi($pegawai);

        $res->assertStatus(422)->assertJsonValidationErrors('conflict');
        $this->assertDatabaseMissing('presensi', [
            'pegawai_id' => $pegawai->id,
            'tanggal' => '2026-08-26',
            'status' => 'hadir',
        ]);
        Carbon::setTestNow();
    }

    public function test_presensi_diblokir_jika_sudah_sakit(): void
    {
        Carbon::setTestNow('2026-08-26 08:00:00');
        $pegawai = $this->makePegawai();
        $this->seedLeave($pegawai, 'sakit');

        $res = $this->postPresensi($pegawai);

        $res->assertStatus(422)->assertJsonValidationErrors('conflict');
        $this->assertDatabaseMissing('presensi', [
            'pegawai_id' => $pegawai->id,
            'tanggal' => '2026-08-26',
            'status' => 'hadir',
        ]);
        Carbon::setTestNow();
    }
}
