<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Jadwal;
use App\Models\Pegawai;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileTapGeofenceTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    protected function setUp(): void
    {
        parent::setUp();

        // Unit di sekitar (-6.2, 106.8) dengan radius 100m.
        $this->unit = UnitSekolah::create([
            'nama' => 'SMP Test',
            'singkatan' => 'SMP',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'toleransi_menit' => 0,
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

    private function makePegawaiTetap(bool $withFotoPagi = true): Pegawai
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

        // Pivot unit (primary)
        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        if ($withFotoPagi) {
            Presensi::create([
                'pegawai_id' => $pegawai->id,
                'jadwal_id' => null,
                'unit_sekolah_id' => $this->unit->id,
                'tipe_presensi' => 'kantor',
                'tanggal' => Carbon::today(),
                'jam_masuk' => '07:00:00',
                'status' => 'hadir',
            ]);
        }

        return $pegawai;
    }

    private function makeJadwalHariIni(Pegawai $pegawai): Jadwal
    {
        return Jadwal::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'hari' => $this->hariIniIndo(),
            'jam_mulai' => '08:00:00',
            'jam_selesai' => '09:45:00',
            'jenis_jadwal' => 'mengajar',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);
    }

    public function test_tap_jadwal_sukses_dalam_radius(): void
    {
        $pegawai = $this->makePegawaiTetap();
        $jadwal = $this->makeJadwalHariIni($pegawai);

        $res = $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.tap'), [
                'jadwal_id' => $jadwal->id,
                'latitude' => -6.2,
                'longitude' => 106.8,
                'accuracy' => 15,
            ]);

        $res->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('presensi', [
            'pegawai_id' => $pegawai->id,
            'jadwal_id' => $jadwal->id,
            'tipe_presensi' => 'mengajar',
            'is_lembur' => false,
        ]);

        $record = Presensi::where('pegawai_id', $pegawai->id)
            ->where('jadwal_id', $jadwal->id)
            ->first();
        $this->assertNotNull($record->jam_masuk);
        $this->assertSame(Carbon::today()->format('Y-m-d'), $record->tanggal->format('Y-m-d'));
        $this->assertContains($record->status, ['hadir', 'telat']);
    }

    public function test_tap_jadwal_ditolak_di_luar_radius(): void
    {
        $pegawai = $this->makePegawaiTetap();
        $jadwal = $this->makeJadwalHariIni($pegawai);

        // offset ~0.003° lat ≈ 333m > radius 100m
        $res = $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.tap'), [
                'jadwal_id' => $jadwal->id,
                'latitude' => -6.203,
                'longitude' => 106.8,
                'accuracy' => 15,
            ]);

        $res->assertStatus(422);
        $this->assertDatabaseMissing('presensi', [
            'pegawai_id' => $pegawai->id,
            'jadwal_id' => $jadwal->id,
        ]);
    }

    public function test_tap_jadwal_ditolak_tanpa_foto_pagi(): void
    {
        $pegawai = $this->makePegawaiTetap(false);
        $jadwal = $this->makeJadwalHariIni($pegawai);

        $res = $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.tap'), [
                'jadwal_id' => $jadwal->id,
                'latitude' => -6.2,
                'longitude' => 106.8,
                'accuracy' => 15,
            ]);

        $res->assertStatus(422);
        $res->assertJsonPath('message', 'Silakan foto pagi terlebih dahulu.');
    }

    public function test_tap_jadwal_ditolak_saat_akurasi_nol_mock_gps(): void
    {
        $pegawai = $this->makePegawaiTetap();
        $jadwal = $this->makeJadwalHariIni($pegawai);

        $res = $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.tap'), [
                'jadwal_id' => $jadwal->id,
                'latitude' => -6.2,
                'longitude' => 106.8,
                'accuracy' => 0,
            ]);

        $res->assertStatus(422);
    }

    public function test_tap_jadwal_ditolak_untuk_non_tetap(): void
    {
        $jabatan = Jabatan::create(['nama' => 'Guru']);
        $user = User::factory()->create();
        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '99887766'.str_pad((string) $user->id, 8, '0', STR_PAD_LEFT),
            'nama_lengkap' => 'Guru Honorer',
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1995-05-05',
            'jenis_kelamin' => 'P',
            'agama' => 'Islam',
            'status_pernikahan' => 'belum kawin',
            'jumlah_tanggungan' => 0,
            'alamat_ktp' => 'Jl. Test No. 2',
            'no_hp' => '081298765432',
            'status_kepegawaian' => 'honorer',
            'tanggal_mulai_kerja' => '2022-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'SMA',
        ]);
        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);
        $jadwal = $this->makeJadwalHariIni($pegawai);

        $res = $this->actingAs($user, 'web_mobile')
            ->postJson(route('presensi.absen.tap'), [
                'jadwal_id' => $jadwal->id,
                'latitude' => -6.2,
                'longitude' => 106.8,
                'accuracy' => 15,
            ]);

        $res->assertForbidden();
    }
}
