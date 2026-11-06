<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Jadwal;
use App\Models\Pegawai;
use App\Models\Presensi;
use App\Models\TugasLuar;
use App\Models\UnitSekolah;
use App\Models\User;
use App\Services\TugasLuarCoverageService;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TugasLuarCoverageTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->unit = UnitSekolah::create([
            'nama' => 'SMP Coverage',
            'singkatan' => 'SMPC',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'toleransi_menit' => 0,
            'toleransi_slide_menit' => 15,
        ]);

        $this->admin = User::factory()->create(['role' => 'superadmin']);
        $this->admin->assignRole('superadmin');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_check_in_terjadwal_cover_jp_yang_overlap(): void
    {
        Carbon::setTestNow('2026-08-21 08:00:00');

        $pegawai = $this->pegawai();
        $jp1 = $this->jadwal($pegawai, '09:00:00', '10:00:00');
        $jp2 = $this->jadwal($pegawai, '11:00:00', '12:00:00');
        $tugas = $this->tugasLuar($pegawai, '08:30:00', '10:30:00');

        $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.store'), [
                'tipe' => 'masuk',
                'foto' => $this->fotoBase64(),
                'latitude' => 0,
                'longitude' => 0,
                'accuracy' => 15,
                'is_tugas_luar' => true,
                'tujuan' => 'Kegiatan sekolah',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $day = Presensi::where('pegawai_id', $pegawai->id)->whereNull('jadwal_id')->firstOrFail();
        $this->assertTrue($day->is_tugas_luar);
        $this->assertSame('disetujui', $day->tugas_luar_status);

        $coverage = Presensi::where('pegawai_id', $pegawai->id)->whereNotNull('jadwal_id')->get();
        $this->assertCount(1, $coverage);
        $this->assertSame($jp1->id, $coverage->first()->jadwal_id);
        $this->assertSame('hadir', $coverage->first()->status);
        $this->assertFalse($coverage->first()->is_tugas_luar);
        $this->assertSame($tugas->id, $coverage->first()->tugas_luar_id);
        $this->assertSame('09:00:00', $coverage->first()->jam_masuk);
        $this->assertSame('10:00:00', $coverage->first()->jam_keluar);
        $this->assertFalse($coverage->contains('jadwal_id', $jp2->id));
    }

    public function test_pending_baru_cover_setelah_admin_approve(): void
    {
        Carbon::setTestNow('2026-08-21 08:00:00');

        $pegawai = $this->pegawai();
        $this->jadwal($pegawai, '09:00:00', '10:00:00');
        $this->jadwal($pegawai, '10:00:00', '11:00:00');

        $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.store'), [
                'tipe' => 'masuk',
                'foto' => $this->fotoBase64(),
                'latitude' => 0,
                'longitude' => 0,
                'accuracy' => 15,
                'is_tugas_luar' => true,
                'tujuan' => 'Rapat mendadak',
            ])
            ->assertOk();

        $day = Presensi::where('pegawai_id', $pegawai->id)->whereNull('jadwal_id')->firstOrFail();
        $this->assertSame('pending', $day->tugas_luar_status);
        $this->assertSame(0, Presensi::where('pegawai_id', $pegawai->id)->whereNotNull('jadwal_id')->count());

        $this->actingAs($this->admin, 'web_admin')
            ->post(route('presensi.approveTugasLuar', $day->id))
            ->assertRedirect();

        $this->assertSame(2, Presensi::where('pegawai_id', $pegawai->id)->whereNotNull('jadwal_id')->count());
        $this->assertSame(2, Presensi::where('pegawai_id', $pegawai->id)->whereNotNull('jadwal_id')->where('status', 'hadir')->count());
    }

    public function test_cover_tidak_menimpa_presensi_lain_dan_mengubah_alpa(): void
    {
        Carbon::setTestNow('2026-08-21 08:00:00');

        $pegawai = $this->pegawai();
        $jp1 = $this->jadwal($pegawai, '09:00:00', '10:00:00');
        $jp2 = $this->jadwal($pegawai, '10:00:00', '11:00:00');
        $jp3 = $this->jadwal($pegawai, '11:00:00', '12:00:00');
        $tugas = $this->tugasLuar($pegawai, '08:00:00', '12:00:00');
        $day = $this->dayRow($pegawai, $tugas);

        $hadir = $this->presensi($pegawai, $jp1->id, 'hadir', '09:05:00');
        $alpa = $this->presensi($pegawai, $jp2->id, 'alpa');
        $izin = $this->presensi($pegawai, $jp3->id, 'izin');

        app(TugasLuarCoverageService::class)->cover($day);

        $this->assertSame('09:05:00', $hadir->fresh()->jam_masuk);
        $this->assertSame('hadir', $hadir->fresh()->status);
        $this->assertSame('hadir', $alpa->fresh()->status);
        $this->assertSame('10:00:00', $alpa->fresh()->jam_masuk);
        $this->assertSame('izin', $izin->fresh()->status);
        $this->assertNull($izin->fresh()->jam_masuk);
    }

    public function test_clear_menghapus_hanya_baris_cover(): void
    {
        Carbon::setTestNow('2026-08-21 08:00:00');

        $pegawai = $this->pegawai();
        $this->jadwal($pegawai, '09:00:00', '10:00:00');
        $tugas = $this->tugasLuar($pegawai, '09:00:00', '10:00:00');
        $day = $this->dayRow($pegawai, $tugas);

        app(TugasLuarCoverageService::class)->cover($day);
        $this->assertSame(1, Presensi::where('pegawai_id', $pegawai->id)->whereNotNull('jadwal_id')->count());

        app(TugasLuarCoverageService::class)->clear($tugas);

        $this->assertSame(0, Presensi::where('pegawai_id', $pegawai->id)->whereNotNull('jadwal_id')->count());
        $this->assertNotNull($day->fresh());
    }

    public function test_dashboard_menampilkan_jp_penugasan_di_kelas_kosong(): void
    {
        Carbon::setTestNow('2026-11-06 09:00:00');

        $pegawai = $this->pegawai();
        $this->jadwal($pegawai, '09:00:00', '10:00:00');
        $tugas = $this->tugasLuar($pegawai, '09:00:00', '10:00:00', 'Kegiatan sekolah');
        $day = $this->dayRow($pegawai, $tugas);

        app(TugasLuarCoverageService::class)->cover($day);

        $this->actingAs($this->admin, 'web_admin')
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('kelasKosongMengajar', 1)
                ->where('kelasKosongMengajar.0.penugasan', true)
                ->where('kelasKosongMengajar.0.tujuan_penugasan', 'Kegiatan sekolah')
                ->where('kelasKosongSummary.jp_kosong', 0)
                ->has('kelasKosongSummary.jp_penugasan'));
    }

    private function pegawai(): Pegawai
    {
        $jabatan = Jabatan::firstOrCreate(['nama' => 'Guru Mata Pelajaran'], ['is_guru' => true]);
        $user = User::factory()->create();

        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '3273'.str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT),
            'nama_lengkap' => 'Guru Coverage',
            'jenis_kelamin' => 'L',
            'status_kepegawaian' => 'guru_pemula',
            'status_aktif' => 'aktif',
            'tmt_mengajar' => '2020-01-01',
            'wajib_kantor' => false,
        ]);
        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    private function jadwal(Pegawai $pegawai, string $mulai, string $selesai): Jadwal
    {
        return Jadwal::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'kelas_label' => 'X Coverage',
            'hari' => $this->hariIndo(Carbon::now()),
            'jam_mulai' => $mulai,
            'jam_selesai' => $selesai,
            'jenis_jadwal' => 'mengajar',
            'tahun_ajaran' => $this->tahunAjaran(Carbon::now()),
            'semester' => Carbon::now()->month >= 7 ? 1 : 2,
        ]);
    }

    private function tugasLuar(Pegawai $pegawai, string $mulai, string $selesai, string $tujuan = 'Kegiatan sekolah'): TugasLuar
    {
        return TugasLuar::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'tanggal' => Carbon::now()->toDateString(),
            'jam_mulai' => $mulai,
            'jam_selesai' => $selesai,
            'tujuan' => $tujuan,
            'created_by' => $this->admin->id,
        ]);
    }

    private function dayRow(Pegawai $pegawai, TugasLuar $tugas): Presensi
    {
        $day = Presensi::create([
            'pegawai_id' => $pegawai->id,
            'jadwal_id' => null,
            'unit_sekolah_id' => $this->unit->id,
            'tipe_presensi' => 'tugas_luar',
            'tanggal' => Carbon::now()->toDateString(),
            'jam_masuk' => '08:00:00',
            'is_tugas_luar' => true,
            'tugas_luar_status' => 'disetujui',
            'tugas_luar_id' => $tugas->id,
            'tujuan' => $tugas->tujuan,
        ]);
        $day->status = 'hadir';
        $day->save();

        return $day;
    }

    private function presensi(Pegawai $pegawai, int $jadwalId, string $status, ?string $jamMasuk = null): Presensi
    {
        $presensi = Presensi::create([
            'pegawai_id' => $pegawai->id,
            'jadwal_id' => $jadwalId,
            'unit_sekolah_id' => $this->unit->id,
            'tipe_presensi' => 'mengajar',
            'tanggal' => Carbon::now()->toDateString(),
            'jam_masuk' => $jamMasuk,
        ]);
        $presensi->status = $status;
        $presensi->save();

        return $presensi;
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

    private function hariIndo(Carbon $tanggal): string
    {
        return [
            'Sunday' => 'Minggu',
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
        ][$tanggal->format('l')];
    }

    private function tahunAjaran(Carbon $tanggal): string
    {
        return $tanggal->month >= 7
            ? $tanggal->year.'/'.($tanggal->year + 1)
            : ($tanggal->year - 1).'/'.$tanggal->year;
    }
}
