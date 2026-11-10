<?php

namespace Tests\Feature;

use App\Http\Controllers\PenggajianController;
use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\UnitPresensiModeHistory;
use App\Models\UnitSekolah;
use App\Services\PresensiAggregator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollModeHistoryTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private Jabatan $guruJabatan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unit = UnitSekolah::create([
            'nama' => 'SMP Payroll Mode',
            'singkatan' => 'SMPPM',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'jam_masuk_kantor' => '07:00',
        ]);
        $this->guruJabatan = Jabatan::create(['nama' => 'Guru', 'is_guru' => true]);
    }

    public function test_mode_hanya_jadwal_tidak_menghitung_hari_tanpa_jp(): void
    {
        UnitPresensiModeHistory::create([
            'unit_sekolah_id' => $this->unit->id,
            'mode' => UnitPresensiModeHistory::MODE_HANYA_JADWAL,
            'berlaku_mulai' => '2026-11-01',
        ]);
        $pegawai = $this->pegawai('Guru Tanpa Jadwal');

        $counts = $this->counts($pegawai, '2026-11-01', '2026-11-30');

        $this->assertSame(0, $counts['working_days']);
        $this->assertSame(0, $counts['alpa']);
    }

    public function test_perubahan_mode_tengah_bulan_membagi_hari_kerja(): void
    {
        UnitPresensiModeHistory::create([
            'unit_sekolah_id' => $this->unit->id,
            'mode' => UnitPresensiModeHistory::MODE_HANYA_JADWAL,
            'berlaku_mulai' => '2026-11-15',
        ]);
        $pegawai = $this->pegawai('Guru Tanpa Jadwal');

        $counts = $this->counts($pegawai, '2026-11-01', '2026-11-30');

        // 1-14 Nov masih mode kantor: 10 hari kerja. 15-30 Nov tanpa JP: 0.
        $this->assertSame(10, $counts['working_days']);
    }

    public function test_jadwal_mengajar_menjadi_working_day_mode_hanya_jadwal(): void
    {
        UnitPresensiModeHistory::create([
            'unit_sekolah_id' => $this->unit->id,
            'mode' => UnitPresensiModeHistory::MODE_HANYA_JADWAL,
            'berlaku_mulai' => '2026-11-01',
        ]);
        $pegawai = $this->pegawai('Guru Senin');
        $pegawai->jadwals()->create([
            'unit_sekolah_id' => $this->unit->id,
            'hari' => 'Senin',
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '07:45:00',
            'jenis_jadwal' => 'mengajar',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);

        $counts = $this->counts($pegawai, '2026-11-01', '2026-11-30');

        // Senin di November 2026: 2, 9, 16, 23, 30.
        $this->assertSame(5, $counts['working_days']);
        $this->assertSame(5, $counts['alpa']);
    }

    private function counts(Pegawai $pegawai, string $start, string $end): array
    {
        $controller = new class(app(PresensiAggregator::class)) extends PenggajianController
        {
            public function counts(Pegawai $pegawai, $attendance, Carbon $start, Carbon $end): array
            {
                return $this->computeAttendance(
                    $pegawai->loadMissing(['units.presensiModeHistories', 'jabatans', 'mapels', 'jadwals']),
                    $attendance,
                    $start,
                    $end,
                    [],
                );
            }
        };

        return $controller->counts(
            $pegawai,
            collect(),
            Carbon::parse($start),
            Carbon::parse($end),
        );
    }

    private function pegawai(string $nama): Pegawai
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
            'status_kepegawaian' => 'guru_tetap_yayasan',
            'tmt_mengajar' => '2020-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
        ]);
        $pegawai->units()->attach($this->unit->id, [
            'jabatan_id' => $this->guruJabatan->id,
            'is_primary' => true,
        ]);
        $pegawai->load('units.presensiModeHistories');

        return $pegawai;
    }
}
