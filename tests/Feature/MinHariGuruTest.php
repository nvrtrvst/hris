<?php

namespace Tests\Feature;

use App\Exports\LaporanRekapKehadiranHarianExport;
use App\Http\Controllers\MobileController;
use App\Models\HariLibur;
use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\Presensi;
use App\Models\UnitPresensiModeHistory;
use App\Models\UnitSekolah;
use App\Models\User;
use App\Support\HariKerja;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

class MinHariGuruTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private Jabatan $guruJabatan;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-05 07:00:00');

        $this->unit = UnitSekolah::create([
            'nama' => 'SMP Kuota Test',
            'singkatan' => 'SKT',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'jam_masuk_kantor' => '07:00',
            'jam_pulang_kantor' => '15:00',
            'min_hari_minggu' => 4,
            'min_hari_berlaku_mulai' => '2026-11-02',
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

    public function test_full_week_target_quota(): void
    {
        $pegawai = $this->pegawai('Guru Penuh');

        $pegawai->jadwals()->create([
            'unit_sekolah_id' => $this->unit->id,
            'hari' => 'Senin',
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '07:45:00',
            'jenis_jadwal' => 'mengajar',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);
        $pegawai->jadwals()->create([
            'unit_sekolah_id' => $this->unit->id,
            'hari' => 'Rabu',
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '07:45:00',
            'jenis_jadwal' => 'mengajar',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);

        $start = Carbon::parse('2026-11-02');
        $end = Carbon::parse('2026-11-08');
        $holidayMap = [
            'national_dates' => [],
            'unit_dates' => [],
        ];

        $weeks = HariKerja::mingguKerjaGuru($pegawai, $this->unit, $start, $end, $holidayMap);
        $week = $weeks[0];

        $this->assertSame(4, $week['target']);
        $this->assertSame(4, $week['hari_kerja']);
        $this->assertCount(2, $week['segments'][0]['scheduled_dates']);
        $this->assertCount(5, $week['segments'][0]['quota_dates']);
    }

    public function test_holiday_deduction_lowers_target(): void
    {
        $pegawai = $this->pegawai('Guru Libur');

        HariLibur::create([
            'unit_sekolah_id' => null,
            'tanggal' => '2026-11-05',
            'nama' => 'Libur Nasional',
        ]);
        HariLibur::create([
            'unit_sekolah_id' => null,
            'tanggal' => '2026-11-06',
            'nama' => 'Libur Nasional',
        ]);

        $start = Carbon::parse('2026-11-02');
        $end = Carbon::parse('2026-11-08');
        $holidayMap = [
            'national_dates' => ['2026-11-05', '2026-11-06'],
            'unit_dates' => [],
        ];

        $weeks = HariKerja::mingguKerjaGuru($pegawai, $this->unit, $start, $end, $holidayMap);
        $week = $weeks[0];

        $this->assertSame(3, $week['target']);
        $this->assertSame(3, $week['hari_kerja']);
    }

    public function test_approved_leave_counts_toward_quota(): void
    {
        $pegawai = $this->pegawai('Guru Izin');

        Presensi::forceCreate([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'jadwal_id' => null,
            'tanggal' => '2026-11-04',
            'jam_masuk' => null,
            'jam_keluar' => null,
            'status' => 'izin',
            'is_lembur' => false,
            'tipe_presensi' => 'kantor',
        ]);

        $kuota = $this->invokeKuotaMingguGuru($pegawai, Carbon::parse('2026-11-05'));

        $this->assertTrue($kuota['aktif']);
        $this->assertSame(4, $kuota['target']);
        $this->assertSame(1, $kuota['terpenuhi']);
        $this->assertSame(3, $kuota['kurang']);
    }

    public function test_mid_week_effective_date_prorates_target(): void
    {
        $this->unit->update(['min_hari_berlaku_mulai' => '2026-11-04']);

        $pegawai = $this->pegawai('Guru Tengah Minggu');

        $start = Carbon::parse('2026-11-02');
        $end = Carbon::parse('2026-11-08');
        $holidayMap = [
            'national_dates' => [],
            'unit_dates' => [],
        ];

        $weeks = HariKerja::mingguKerjaGuru($pegawai, $this->unit, $start, $end, $holidayMap);
        $week = $weeks[0];

        $this->assertSame(3, $week['target']);
        $this->assertSame(3, $week['hari_kerja']);
    }

    public function test_mobile_gate_blocks_office_checkin_when_quota_full(): void
    {
        $pegawai = $this->pegawai('Guru Kuota Penuh');

        foreach (['2026-11-02', '2026-11-03', '2026-11-04', '2026-11-05'] as $tanggal) {
            Presensi::forceCreate([
                'pegawai_id' => $pegawai->id,
                'unit_sekolah_id' => $this->unit->id,
                'jadwal_id' => null,
                'tanggal' => $tanggal,
                'jam_masuk' => '07:00:00',
                'jam_keluar' => null,
                'status' => 'hadir',
                'is_lembur' => false,
                'tipe_presensi' => 'kantor',
            ]);
        }

        $kuota = $this->invokeKuotaMingguGuru($pegawai, Carbon::parse('2026-11-05'));

        $this->assertTrue($kuota['aktif']);
        $this->assertSame(4, $kuota['target']);
        $this->assertSame(4, $kuota['terpenuhi']);
        $this->assertSame(0, $kuota['kurang']);

        $method = new ReflectionMethod($this->makeMobileController(), 'assertKuotaKantor');
        $method->setAccessible(true);

        $this->expectException(ValidationException::class);
        $method->invoke($this->makeMobileController(), $pegawai);
    }

    public function test_mobile_gate_allows_checkin_when_quota_not_full(): void
    {
        $pegawai = $this->pegawai('Guru Kuota Belum Penuh');

        Presensi::forceCreate([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'jadwal_id' => null,
            'tanggal' => '2026-11-02',
            'jam_masuk' => '07:00:00',
            'jam_keluar' => null,
            'status' => 'hadir',
            'is_lembur' => false,
            'tipe_presensi' => 'kantor',
        ]);

        $kuota = $this->invokeKuotaMingguGuru($pegawai, Carbon::parse('2026-11-05'));

        $this->assertTrue($kuota['aktif']);
        $this->assertSame(4, $kuota['target']);
        $this->assertSame(1, $kuota['terpenuhi']);
        $this->assertSame(3, $kuota['kurang']);

        $method = new ReflectionMethod($this->makeMobileController(), 'assertKuotaKantor');
        $method->setAccessible(true);

        $method->invoke($this->makeMobileController(), $pegawai);

        $this->assertTrue(true, 'Check-in diizinkan saat kuota belum penuh');
    }

    public function test_matriks_harian_menandai_hari_bebas_dan_wajib(): void
    {
        $pegawai = $this->pegawai('Guru Matriks');

        $export = new LaporanRekapKehadiranHarianExport('2026-11-02', '2026-11-08', $this->unit->id, null, null, null);
        $row = $export->collection()->first();

        // 5 hari kerja (Sen–Jum), kuota 4 → 1 hari bebas (B), 4 hari wajib (A).
        $this->assertSame('A', $row['cells']['2026-11-02']);
        $this->assertSame('A', $row['cells']['2026-11-03']);
        $this->assertSame('A', $row['cells']['2026-11-04']);
        $this->assertSame('A', $row['cells']['2026-11-05']);
        $this->assertSame('B', $row['cells']['2026-11-06']);
        $this->assertSame('L', $row['cells']['2026-11-07'], 'Akhir pekan = libur');
        $this->assertSame('L', $row['cells']['2026-11-08'], 'Akhir pekan = libur');
    }

    public function test_matriks_harian_semua_hari_kerja_bebas_saat_hadir_mencukupi_kuota(): void
    {
        $pegawai = $this->pegawai('Guru Hadir Cukup');

        foreach (['2026-11-02', '2026-11-03', '2026-11-04', '2026-11-05'] as $tanggal) {
            Presensi::forceCreate([
                'pegawai_id' => $pegawai->id,
                'unit_sekolah_id' => $this->unit->id,
                'jadwal_id' => null,
                'tanggal' => $tanggal,
                'jam_masuk' => '07:00:00',
                'jam_keluar' => '08:00:00',
                'status' => 'hadir',
                'is_lembur' => false,
                'tipe_presensi' => 'kantor',
            ]);
        }

        $export = new LaporanRekapKehadiranHarianExport('2026-11-02', '2026-11-08', $this->unit->id, null, null, null);
        $row = $export->collection()->first();

        $this->assertSame('H', $row['cells']['2026-11-02']);
        $this->assertSame('B', $row['cells']['2026-11-06'], 'Kuota terpenuhi → hari sisanya bebas');
    }

    private function pegawai(string $nama): Pegawai
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
            'alamat' => 'Jl. Uji',
            'no_hp' => '08123456789',
            'status_kepegawaian' => 'pegawai_tetap_yayasan',
            'tmt_mengajar' => '2020-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
        ]);
        $pegawai->units()->attach($this->unit->id, [
            'jabatan_id' => $this->guruJabatan->id,
            'is_primary' => true,
        ]);
        $pegawai->load('units', 'jabatans');

        return $pegawai->setRelation('user', $user);
    }

    private function invokeKuotaMingguGuru(Pegawai $pegawai, Carbon $tanggal): array
    {
        $method = new ReflectionMethod($this->makeMobileController(), 'kuotaMingguGuru');
        $method->setAccessible(true);

        return $method->invoke($this->makeMobileController(), $pegawai, $tanggal);
    }

    private function makeMobileController(): MobileController
    {
        return app(MobileController::class);
    }
}
