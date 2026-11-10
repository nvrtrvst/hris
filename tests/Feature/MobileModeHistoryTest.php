<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\UnitPresensiModeHistory;
use App\Models\UnitSekolah;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MobileModeHistoryTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private Jabatan $guruJabatan;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-05 07:00:00');

        $this->unit = UnitSekolah::create([
            'nama' => 'SMP Mobile Mode',
            'singkatan' => 'SMPMM',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'jam_masuk_kantor' => '07:00',
        ]);
        $this->guruJabatan = Jabatan::create(['nama' => 'Guru', 'is_guru' => true]);

        UnitPresensiModeHistory::create([
            'unit_sekolah_id' => $this->unit->id,
            'mode' => UnitPresensiModeHistory::MODE_HANYA_JADWAL,
            'berlaku_mulai' => '2026-11-01',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_kantor_off_tanpa_jp_untuk_pendidik(): void
    {
        $pegawai = $this->pegawai('Guru Tanpa JP');

        $this->actingAs($pegawai->user, 'web_mobile')
            ->get(route('presensi.absen'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Mobile/Absen')
                ->where('officeAttendance', false)
            );
    }

    public function test_piket_only_tetap_membuka_absen_kantor(): void
    {
        $pegawai = $this->pegawai('Guru Piket');
        $pegawai->jadwals()->create([
            'unit_sekolah_id' => $this->unit->id,
            'hari' => 'Kamis',
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '07:45:00',
            'jenis_jadwal' => 'piket',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);

        $this->actingAs($pegawai->user, 'web_mobile')
            ->get(route('presensi.absen'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Mobile/Absen')
                ->where('officeAttendance', true)
            );
    }

    public function test_tendik_tetap_mode_kantor(): void
    {
        $tendikJabatan = Jabatan::create(['nama' => 'TU', 'is_guru' => false]);
        $pegawai = $this->pegawai('Staf TU', $tendikJabatan);

        $this->actingAs($pegawai->user, 'web_mobile')
            ->get(route('presensi.absen'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Mobile/Absen')
                ->where('officeAttendance', true)
            );
    }

    private function pegawai(string $nama, ?Jabatan $jabatan = null): Pegawai
    {
        $user = User::factory()->create();
        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => (string) random_int(1000000000000000, 9999999999999999),
            'nama_lengkap' => $nama,
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'kawin',
            'jumlah_tanggungan' => 1,
            'alamat' => 'Jl. Uji',
            'no_hp' => '08123456789',
            'status_kepegawaian' => 'pegawai_tetap_yayasan',
            'tmt_mengajar' => '2020-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
        ]);
        $pegawai->units()->attach($this->unit->id, [
            'jabatan_id' => ($jabatan ?? $this->guruJabatan)->id,
            'is_primary' => true,
        ]);

        return $pegawai->setRelation('user', $user);
    }
}
