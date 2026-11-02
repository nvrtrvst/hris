<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Jadwal;
use App\Models\LaporanPiket;
use App\Models\Pegawai;
use App\Models\PiketPetugas;
use App\Models\PiketSesi;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LaporanKetidakhadiranPiketTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Carbon::setTestNow('2026-10-26 16:00:00');

        $this->unit = UnitSekolah::create([
            'nama' => 'SMK Uji',
            'singkatan' => 'SMK',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
        ]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('superadmin');
    }

    private function pegawai(string $nama): Pegawai
    {
        $jabatan = Jabatan::firstOrCreate(['nama' => 'Guru']);
        $pegawai = Pegawai::create([
            'nik' => '3273'.str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT),
            'nama_lengkap' => $nama,
            'jenis_kelamin' => 'L',
            'status_kepegawaian' => 'guru_tetap_yayasan',
            'status_aktif' => 'aktif',
            'tmt_mengajar' => '2020-01-01',
        ]);
        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    private function jadwal(Pegawai $pegawai, string $selesai = '15:40:00'): Jadwal
    {
        return Jadwal::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'hari' => 'Senin',
            'jam_mulai' => '07:00:00',
            'jam_selesai' => $selesai,
            'jenis_jadwal' => 'piket',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);
    }

    private function presensi(Pegawai $pegawai, string $status = 'hadir', bool $tugasLuar = false): void
    {
        $presensi = Presensi::create([
            'pegawai_id' => $pegawai->id,
            'tipe_presensi' => 'kantor',
            'tanggal' => now()->toDateString(),
            'jam_masuk' => '06:40:00',
            'unit_sekolah_id' => $this->unit->id,
        ]);
        $presensi->forceFill(['status' => $status, 'is_tugas_luar' => $tugasLuar])->save();
    }

    private function laporan(Pegawai $pegawai, string $status = LaporanPiket::STATUS_DRAFT, ?string $submittedAt = null): void
    {
        $sesi = PiketSesi::create([
            'unit_sekolah_id' => $this->unit->id,
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '07:00',
            'jam_selesai' => '15:40',
            'status' => 'incomplete',
        ]);

        $laporan = LaporanPiket::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'sesi_id' => $sesi->id,
            'tanggal' => now()->toDateString(),
            'status' => $status,
            'submitted_at' => $submittedAt ? Carbon::parse($submittedAt) : null,
            'items' => [],
        ]);

        PiketPetugas::create([
            'sesi_id' => $sesi->id,
            'pegawai_id' => $pegawai->id,
            'peran' => 'area',
            'laporan_piket_id' => $laporan->id,
        ]);
    }

    private function tampilkan(string $status): TestResponse
    {
        return $this->actingAs($this->admin)->get(route('laporan.piket', [
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'status' => $status,
        ]));
    }

    public function test_hadir_tanpa_laporan_dan_draft_dihitung_alpa(): void
    {
        $tanpaLaporan = $this->pegawai('Tanpa Laporan');
        $this->presensi($tanpaLaporan);
        $this->jadwal($tanpaLaporan);

        $draft = $this->pegawai('Masih Draft');
        $this->presensi($draft);
        $this->jadwal($draft);
        $this->laporan($draft);

        $this->tampilkan('alpa_piket')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Laporan/Piket')
                ->where('ketidakhadiran.alpa', 2)
                ->where('ketidakhadiran.terlambat', 0)
                ->where('ketidakhadiran.rows', fn ($rows) => $rows->count() === 2
                    && $rows->every(fn ($r) => $r['kategori'] === 'alpa_piket')));
    }

    public function test_submitted_sebelum_jam_selesai_tidak_masuk_daftar(): void
    {
        $pegawai = $this->pegawai('Tepat Waktu');
        $this->presensi($pegawai);
        $this->jadwal($pegawai);
        $this->laporan($pegawai, LaporanPiket::STATUS_SUBMITTED, '2026-10-26 15:00:00');

        $this->tampilkan('semua')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('ketidakhadiran.alpa', 0)
                ->where('ketidakhadiran.terlambat', 0)
                ->where('ketidakhadiran.rows', fn ($rows) => $rows->count() === 0));
    }

    public function test_submitted_setelah_jam_selesai_dihitung_terlambat_lapor(): void
    {
        $pegawai = $this->pegawai('Telat Lapor');
        $this->presensi($pegawai);
        $this->jadwal($pegawai);
        $this->laporan($pegawai, LaporanPiket::STATUS_SUBMITTED, '2026-10-26 16:10:00');

        $this->tampilkan('terlambat_lapor')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('ketidakhadiran.alpa', 0)
                ->where('ketidakhadiran.terlambat', 1)
                ->where('ketidakhadiran.rows.0.kategori', 'terlambat_lapor')
                ->where('ketidakhadiran.rows.0.submitted_at', '16:10'));
    }

    public function test_presensi_izin_dan_tugas_luar_dikecualikan(): void
    {
        $izin = $this->pegawai('Sedang Izin');
        $this->presensi($izin, 'izin');
        $this->jadwal($izin);

        $tugasLuar = $this->pegawai('Tugas Luar');
        $this->presensi($tugasLuar, 'hadir', true);
        $this->jadwal($tugasLuar);

        $this->tampilkan('alpa_piket')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('ketidakhadiran.rows', fn ($rows) => $rows->count() === 0));
    }

    public function test_slot_hari_ini_yang_belum_selesai_belum_dinilai(): void
    {
        $pegawai = $this->pegawai('Piket Sore');
        $this->presensi($pegawai);
        $this->jadwal($pegawai, '17:00:00');

        $this->tampilkan('alpa_piket')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('ketidakhadiran.rows', fn ($rows) => $rows->count() === 0));
    }

    public function test_export_excel_dan_pdf(): void
    {
        $pegawai = $this->pegawai('Tanpa Laporan');
        $this->presensi($pegawai);
        $this->jadwal($pegawai);

        $params = [
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'status' => 'alpa_piket',
        ];

        $this->actingAs($this->admin)->get(route('laporan.piket.alpa.excel', $params))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($this->admin)->get(route('laporan.piket.alpa.pdf', $params))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
