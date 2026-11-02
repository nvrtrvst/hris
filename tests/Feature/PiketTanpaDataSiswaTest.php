<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Jadwal;
use App\Models\LaporanPiket;
use App\Models\Pegawai;
use App\Models\PiketItemTemplate;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use App\Services\PiketService;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PiketTanpaDataSiswaTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

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
        $this->unit->forceFill(['piket_mode' => 'simple'])->save();
    }

    private function pegawai(string $nama): Pegawai
    {
        $jabatan = Jabatan::firstOrCreate(['nama' => 'Guru']);
        $user = User::factory()->create();
        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '3273'.str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT),
            'nama_lengkap' => $nama,
            'jenis_kelamin' => 'L',
            'status_kepegawaian' => 'guru_tetap_yayasan',
            'status_aktif' => 'aktif',
            'tmt_mengajar' => '2020-01-01',
            'wajib_kantor' => true,
        ]);
        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    private function jadwal(Pegawai $pegawai): Jadwal
    {
        return Jadwal::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'hari' => 'Senin',
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '15:00:00',
            'jenis_jadwal' => 'piket',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);
    }

    private function masuk(Pegawai $pegawai): void
    {
        $presensi = Presensi::create([
            'pegawai_id' => $pegawai->id,
            'tipe_presensi' => 'kantor',
            'tanggal' => now()->toDateString(),
            'jam_masuk' => '06:40:00',
            'unit_sekolah_id' => $this->unit->id,
        ]);
        $presensi->status = 'hadir';
        $presensi->save();
    }

    private function tanpaIntegrasi(): void
    {
        config(['keuangan.url' => '', 'keuangan.key' => '']);
    }

    public function test_halaman_piket_menandai_siswa_tidak_tersedia_dan_mengurangi_item_wajib(): void
    {
        $this->tanpaIntegrasi();

        $pegawai = $this->pegawai('Petugas A');
        $this->masuk($pegawai);
        $this->jadwal($pegawai);

        $this->actingAs($pegawai->user, 'web_mobile')
            ->get(route('presensi.piket.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Mobile/Piket')
                ->where('siswaTersedia', false)
                ->where('piket.wajib', fn ($wajib) => ! $wajib->contains('absen_siswa')));

        config(['keuangan.url' => 'http://keuangan.test', 'keuangan.key' => 'test-key']);

        $this->actingAs($pegawai->user, 'web_mobile')
            ->get(route('presensi.piket.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('siswaTersedia', true)
                ->where('piket.wajib', fn ($wajib) => $wajib->contains('absen_siswa')));
    }

    public function test_submit_mode_simple_tidak_diblokir_item_absen_siswa(): void
    {
        $this->tanpaIntegrasi();

        $pegawai = $this->pegawai('Petugas B');
        $this->masuk($pegawai);
        $jadwal = $this->jadwal($pegawai);

        $sesi = PiketService::sessionFor($pegawai, $jadwal);
        $petugas = PiketService::petugasFor($sesi, $pegawai->id);
        $laporan = LaporanPiket::findOrFail($petugas->laporan_piket_id);

        $items = [];
        foreach ([
            LaporanPiket::ITEM_GERBANG,
            LaporanPiket::ITEM_CEK_ABSEN,
            LaporanPiket::ITEM_KELAS_KOSONG,
            LaporanPiket::ITEM_BUKU,
            LaporanPiket::ITEM_INSIDEN,
        ] as $kode) {
            $items[$kode] = ['done_at' => '16:00:00'];
        }
        $laporan->forceFill(['items' => $items])->save();

        $this->actingAs($pegawai->user, 'web_mobile')
            ->post(route('presensi.piket.submit'))
            ->assertSessionHasNoErrors();

        $this->assertSame(LaporanPiket::STATUS_SUBMITTED, $laporan->fresh()->status);
    }

    public function test_required_codes_area_mengabaikan_absen_siswa_tanpa_integrasi(): void
    {
        $this->tanpaIntegrasi();
        $this->unit->forceFill(['piket_mode' => 'area'])->save();

        PiketItemTemplate::create([
            'unit_sekolah_id' => $this->unit->id,
            'kode' => 'absen_siswa',
            'label' => 'Absen siswa',
            'seksi' => 'pagi',
            'scope' => 'global',
            'tipe' => 'centang',
            'wajib' => true,
            'urutan' => 1,
        ]);

        $pegawai = $this->pegawai('Petugas C');
        $this->masuk($pegawai);
        $jadwal = $this->jadwal($pegawai);

        $sesi = PiketService::sessionFor($pegawai, $jadwal);
        $petugas = PiketService::petugasFor($sesi, $pegawai->id);

        $this->assertNotContains('absen_siswa', PiketService::requiredCodes($this->unit, $petugas));

        config(['keuangan.url' => 'http://keuangan.test', 'keuangan.key' => 'test-key']);

        $this->assertContains('absen_siswa', PiketService::requiredCodes($this->unit, $petugas));
    }

    public function test_endpoint_siswa_terlambat_menolak_dengan_pesan_ramah(): void
    {
        $this->tanpaIntegrasi();

        $pegawai = $this->pegawai('Petugas D');
        $this->masuk($pegawai);
        $this->jadwal($pegawai);

        $this->actingAs($pegawai->user, 'web_mobile')
            ->post(route('presensi.piket.siswa-terlambat'), [])
            ->assertSessionHasErrors('terlambat');
    }
}
