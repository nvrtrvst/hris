<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Jadwal;
use App\Models\Pegawai;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use App\Services\PresensiGuruReportService;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PresensiGuruReportTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private Pegawai $guru;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->unit = UnitSekolah::create([
            'nama' => 'SMK Presensi Guru',
            'singkatan' => 'SMKPG',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'jam_masuk_kantor' => '07:00:00',
            'jam_pulang_kantor' => '15:00:00',
        ]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('superadmin');
        $this->guru = $this->pegawai('Guru Presensi');
    }

    public function test_report_menghitung_jp_aktual_dan_persentase(): void
    {
        Carbon::setTestNow('2026-11-03 12:00:00');
        $date = Carbon::parse('2026-11-03');
        $day = $date->locale('id')->translatedFormat('l');
        $jadwal = $this->jadwal($day, '07:00:00', '07:45:00');

        $presensi = Presensi::create([
            'pegawai_id' => $this->guru->id,
            'jadwal_id' => $jadwal->id,
            'unit_sekolah_id' => $this->unit->id,
            'tipe_presensi' => 'mengajar',
            'tanggal' => $date->toDateString(),
            'jam_masuk' => '07:00:00',
            'jam_keluar' => '07:45:00',
        ]);
        $presensi->forceFill(['status' => 'hadir'])->save();

        $data = app(PresensiGuruReportService::class)->build(
            $this->guru,
            $this->unit,
            '2026-11',
            '2025/2026',
            1,
        );

        $row = collect($data['rows'])->firstWhere('tanggal_iso', '2026-11-03');

        $this->assertNotNull($row);
        $this->assertSame(1, $row['jumlah_jp']);
        $this->assertSame(1, $row['scheduled_jp']);
        $this->assertSame(100, $row['ph']);
        $this->assertSame(100, $data['summary']['b']);

        Carbon::setTestNow();
    }

    public function test_halaman_dan_pdf_presensi_guru_bisa_diakses(): void
    {
        $date = Carbon::parse('2026-11-03');
        $day = $date->locale('id')->translatedFormat('l');
        $jadwal = $this->jadwal($day, '07:00:00', '07:45:00');

        $presensi = Presensi::create([
            'pegawai_id' => $this->guru->id,
            'jadwal_id' => $jadwal->id,
            'unit_sekolah_id' => $this->unit->id,
            'tipe_presensi' => 'mengajar',
            'tanggal' => $date->toDateString(),
            'jam_masuk' => '07:00:00',
        ]);
        $presensi->forceFill(['status' => 'hadir'])->save();

        $params = [
            'unit_sekolah_id' => $this->unit->id,
            'pegawai_id' => $this->guru->id,
            'periode' => '2026-11',
            'tahun_ajaran' => '2025/2026',
            'semester' => 1,
        ];

        $this->actingAs($this->admin, 'web_admin')
            ->get(route('laporan.presensi-guru'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Laporan/PresensiGuru'));

        $this->actingAs($this->admin, 'web_admin')
            ->get(route('laporan.presensi-guru.preview', $params))
            ->assertOk()
            ->assertJsonPath('pegawai.id', $this->guru->id);

        $this->actingAs($this->admin, 'web_admin')
            ->get(route('laporan.presensi-guru.pdf', $params))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    private function pegawai(string $nama): Pegawai
    {
        $jabatan = Jabatan::firstOrCreate(['nama' => 'Guru Mata Pelajaran'], ['is_guru' => true]);
        $user = User::factory()->create();

        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '3273'.str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT),
            'nama_lengkap' => $nama,
            'jenis_kelamin' => 'L',
            'status_kepegawaian' => 'guru_pemula',
            'status_aktif' => 'aktif',
            'tmt_mengajar' => '2020-01-01',
        ]);
        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    private function jadwal(string $hari, string $mulai, string $selesai): Jadwal
    {
        return Jadwal::create([
            'pegawai_id' => $this->guru->id,
            'unit_sekolah_id' => $this->unit->id,
            'hari' => $hari,
            'jam_mulai' => $mulai,
            'jam_selesai' => $selesai,
            'jenis_jadwal' => 'mengajar',
            'tahun_ajaran' => '2025/2026',
            'semester' => 1,
        ]);
    }
}
