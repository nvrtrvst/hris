<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Jadwal;
use App\Models\Pegawai;
use App\Models\UnitSekolah;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WajibKantorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function makeUnit(): UnitSekolah
    {
        return UnitSekolah::create([
            'nama' => 'SMP Test', 'singkatan' => 'SMP',
            'latitude' => -6.2, 'longitude' => 106.8, 'radius_meter' => 100,
        ]);
    }

    private function makePegawai(UnitSekolah $unit, string $status, string $nama): Pegawai
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
            'wajib_kantor' => true,
        ]);
        $jabatan = Jabatan::create(['nama' => 'Guru '.$nama]);
        $pegawai->units()->attach($unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai->fresh();
    }

    public function test_hook_status_tidak_tetap_bukan_wajib_kantor(): void
    {
        $unit = $this->makeUnit();

        // Hook saving membaca kolom kode (bukan PK): GTT/pemula = false.
        $this->assertFalse($this->makePegawai($unit, 'guru_tidak_tetap', 'GTT')->wajib_kantor);
        $this->assertFalse($this->makePegawai($unit, 'guru_pemula', 'Pemula')->wajib_kantor);
        $this->assertTrue($this->makePegawai($unit, 'guru_tetap_yayasan', 'GTYS')->wajib_kantor);
        $this->assertTrue($this->makePegawai($unit, 'pegawai_tetap_yayasan', 'PTY')->wajib_kantor);
    }

    public function test_piket_only_non_wajib_bisa_masuk_kantor(): void
    {
        $unit = $this->makeUnit();
        $pegawai = $this->makePegawai($unit, 'guru_tidak_tetap', 'Guru Piket');
        $this->assertFalse($pegawai->wajib_kantor);

        Jadwal::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $unit->id,
            'hari' => 'Senin',
            'jam_mulai' => '07:30:00',
            'jam_selesai' => '15:00:00',
            'jenis_jadwal' => 'piket',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);
        Carbon::setTestNow('2026-10-12 07:00:00'); // Senin

        $this->actingAs($pegawai->user, 'web_mobile')
            ->get(route('presensi.absen'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('officeAttendance', true));

        $img = imagecreatetruecolor(320, 240);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);

        $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.store'), [
                'tipe' => 'masuk',
                'foto' => 'data:image/png;base64,'.base64_encode($png),
                'latitude' => -6.2,
                'longitude' => 106.8,
                'accuracy' => 15,
            ])->assertOk()->assertJson(['success' => true]);
        Carbon::setTestNow();

        $this->assertDatabaseHas('presensi', [
            'pegawai_id' => $pegawai->id,
            'tipe_presensi' => 'kantor',
        ]);
    }
}
