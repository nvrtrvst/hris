<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\Presensi;
use App\Models\UnitPresensiModeHistory;
use App\Models\UnitSekolah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinalizeAlpaModeHistoryTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private Jabatan $guruJabatan;

    private Jabatan $tendikJabatan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unit = UnitSekolah::create([
            'nama' => 'SMP Mode Alpa',
            'singkatan' => 'SMPMA',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'jam_masuk_kantor' => '07:00',
        ]);
        $this->guruJabatan = Jabatan::create(['nama' => 'Guru', 'is_guru' => true]);
        $this->tendikJabatan = Jabatan::create(['nama' => 'TU', 'is_guru' => false]);

        UnitPresensiModeHistory::create([
            'unit_sekolah_id' => $this->unit->id,
            'mode' => UnitPresensiModeHistory::MODE_HANYA_JADWAL,
            'berlaku_mulai' => '2026-11-04',
        ]);
    }

    public function test_tanpa_jp_tidak_alpa_tetapi_tendik_tetap_alpa_kantor(): void
    {
        $guru = $this->pegawai('Guru Tanpa JP', $this->guruJabatan, 'guru_tetap_yayasan');
        $tendik = $this->pegawai('Staf Kantor', $this->tendikJabatan, 'pegawai_tetap_yayasan');

        $this->artisan('presensi:finalize-alpa', ['--date' => '2026-11-05'])->assertSuccessful();

        $this->assertFalse(Presensi::where('pegawai_id', $guru->id)->exists());
        $this->assertDatabaseHas('presensi', [
            'pegawai_id' => $tendik->id,
            'tipe_presensi' => 'kantor',
            'status' => 'alpa',
            'tanggal' => '2026-11-05',
        ]);
    }

    public function test_hari_dengan_jp_tetap_alpa_per_jadwal(): void
    {
        $guru = $this->pegawai('Guru Ada JP', $this->guruJabatan, 'guru_tetap_yayasan');
        $jadwal = $guru->jadwals()->create([
            'unit_sekolah_id' => $this->unit->id,
            'hari' => 'Kamis',
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '07:45:00',
            'jenis_jadwal' => 'mengajar',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);

        $this->artisan('presensi:finalize-alpa', ['--date' => '2026-11-05'])->assertSuccessful();

        $this->assertDatabaseHas('presensi', [
            'pegawai_id' => $guru->id,
            'jadwal_id' => $jadwal->id,
            'tipe_presensi' => 'mengajar',
            'status' => 'alpa',
            'tanggal' => '2026-11-05',
        ]);
    }

    public function test_piket_only_tidak_membuat_alpa_kantor(): void
    {
        $guru = $this->pegawai('Guru Piket', $this->guruJabatan, 'guru_tetap_yayasan');
        $guru->jadwals()->create([
            'unit_sekolah_id' => $this->unit->id,
            'hari' => 'Kamis',
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '07:45:00',
            'jenis_jadwal' => 'piket',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);

        $this->artisan('presensi:finalize-alpa', ['--date' => '2026-11-05'])->assertSuccessful();

        $this->assertFalse(Presensi::where('pegawai_id', $guru->id)->exists());
    }

    private function pegawai(string $nama, Jabatan $jabatan, string $status): Pegawai
    {
        $pegawai = Pegawai::create([
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
            'status_kepegawaian' => $status,
            'tmt_mengajar' => '2020-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
        ]);
        $pegawai->units()->attach($this->unit->id, [
            'jabatan_id' => $jabatan->id,
            'is_primary' => true,
        ]);

        return $pegawai;
    }
}
