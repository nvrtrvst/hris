<?php

namespace Tests\Feature;

use App\Exports\LaporanRekapKehadiranExport;
use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\UnitPresensiModeHistory;
use App\Models\UnitSekolah;
use App\Models\User;
use App\Services\LaporanPengawasanService;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaporanModeHistoryTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private Jabatan $guruJabatan;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Carbon::setTestNow('2026-11-05 12:00:00');

        $this->unit = UnitSekolah::create([
            'nama' => 'SMP Laporan Mode',
            'singkatan' => 'SMPLM',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'jam_masuk_kantor' => '07:00',
        ]);
        $this->guruJabatan = Jabatan::create(['nama' => 'Guru', 'is_guru' => true]);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('superadmin');

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

    public function test_hari_kerja_rekap_hanya_hari_mengajar(): void
    {
        $guru = $this->pegawai('Guru Senin');
        $guru->jadwals()->create([
            'unit_sekolah_id' => $this->unit->id,
            'hari' => 'Senin',
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '07:45:00',
            'jenis_jadwal' => 'mengajar',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);
        $tanpaJp = $this->pegawai('Guru Tanpa JP');

        $start = Carbon::parse('2026-11-01');
        $end = Carbon::parse('2026-11-30');
        $holidayMap = LaporanRekapKehadiranExport::buildHolidayWeekdayMap([$this->unit->id], $start, $end);

        $this->assertSame(5, LaporanRekapKehadiranExport::hariKerjaByMode($guru, $this->unit, $start, $end, $holidayMap));
        $this->assertSame(0, LaporanRekapKehadiranExport::hariKerjaByMode($tanpaJp, $this->unit, $start, $end, $holidayMap));
    }

    public function test_pengawasan_tidak_menghitung_hari_tanpa_jp(): void
    {
        $this->pegawai('Guru Tanpa JP');

        $data = app(LaporanPengawasanService::class)->build([
            'from' => '2026-11-05',
            'to' => '2026-11-05',
            'unit_sekolah_id' => $this->unit->id,
            'jenis_pegawai' => 'all',
        ], $this->admin);

        $this->assertSame(1, $data['summary']['employees']);
        $this->assertSame(0, $data['summary']['employee_days']);
        $this->assertSame(0, $data['summary']['alpa']);
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
