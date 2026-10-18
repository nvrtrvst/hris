<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\UnitSekolah;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Riwayat izin di portal mobile: filter, stats, paginasi, dan data pendukung
 * (approver L1 utk pengajuan yang sudah lolos tingkat 1, sisa cuti).
 */
class MobileIzinRiwayatTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private Jabatan $jabatan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unit = UnitSekolah::create([
            'nama' => 'SMP Test',
            'singkatan' => 'SMP',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'toleransi_menit' => 0,
        ]);
        $this->jabatan = Jabatan::create(['nama' => 'Guru']);
    }

    private function makePegawai(string $nama = 'Pegawai Riwayat'): Pegawai
    {
        $user = User::factory()->create();
        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '99887766'.str_pad((string) $user->id, 8, '0', STR_PAD_LEFT),
            'nama_lengkap' => $nama,
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'kawin',
            'jumlah_tanggungan' => 2,
            'alamat' => 'Jl. Test No. 1',
            'no_hp' => '081234567890',
            'status_kepegawaian' => 'guru_tetap_yayasan',
            'tmt_mengajar' => '2020-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
            'jatah_cuti_tahunan' => 12,
        ]);
        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $this->jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    private function makePengajuan(Pegawai $pegawai, array $attrs = []): PengajuanIzin
    {
        // `status` guarded — jangan ikut mass-assign.
        $status = $attrs['status'] ?? 'pending';
        unset($attrs['status']);

        $pengajuan = new PengajuanIzin(array_merge([
            'pegawai_id' => $pegawai->id,
            'jenis_izin' => 'izin',
            'tanggal_mulai' => '2026-10-05',
            'tanggal_selesai' => '2026-10-05',
            'alasan' => 'Keperluan keluarga',
        ], $attrs));
        $pengajuan->status = $status;
        $pengajuan->save();

        return $pengajuan;
    }

    private function props($res): array
    {
        return $res->viewData('page')['props'];
    }

    public function test_hanya_menampilkan_pengajuan_milik_pegawai_sendiri(): void
    {
        $pegawai = $this->makePegawai();
        $lain = $this->makePegawai('Pegawai Lain');
        $this->makePengajuan($pegawai, ['alasan' => 'Punya saya']);
        $this->makePengajuan($lain, ['alasan' => 'Punya orang lain']);

        $props = $this->props($this->actingAs($pegawai->user, 'web_mobile')->get(route('presensi.izin.index')));

        $this->assertCount(1, $props['pengajuan']['data']);
        $this->assertSame('Punya saya', $props['pengajuan']['data'][0]['alasan']);
    }

    public function test_filter_status_menyaring_daftar_tapi_stats_tetap_penuh(): void
    {
        $pegawai = $this->makePegawai();
        $this->makePengajuan($pegawai, ['status' => 'pending', 'alasan' => 'A']);
        $this->makePengajuan($pegawai, ['status' => 'disetujui', 'jenis_izin' => 'cuti', 'alasan' => 'B']);
        $this->makePengajuan($pegawai, ['status' => 'ditolak', 'alasan' => 'C']);

        $props = $this->props($this->actingAs($pegawai->user, 'web_mobile')
            ->get(route('presensi.izin.index', ['status' => 'disetujui'])));

        $this->assertCount(1, $props['pengajuan']['data']);
        $this->assertSame('B', $props['pengajuan']['data'][0]['alasan']);
        $this->assertSame(3, $props['stats']['total']);
        $this->assertSame(1, $props['stats']['disetujui']);
        $this->assertSame('disetujui', $props['filters']['status']);
    }

    public function test_filter_jenis_dan_bulan(): void
    {
        $pegawai = $this->makePegawai();
        $this->makePengajuan($pegawai, ['jenis_izin' => 'cuti', 'tanggal_mulai' => '2026-09-10', 'tanggal_selesai' => '2026-09-10']);
        $this->makePengajuan($pegawai, ['jenis_izin' => 'sakit', 'tanggal_mulai' => '2026-10-10', 'tanggal_selesai' => '2026-10-11']);

        $jenis = $this->props($this->actingAs($pegawai->user, 'web_mobile')
            ->get(route('presensi.izin.index', ['jenis' => 'cuti'])));
        $this->assertCount(1, $jenis['pengajuan']['data']);
        $this->assertSame('cuti', $jenis['pengajuan']['data'][0]['jenis_izin']);

        $bulan = $this->props($this->actingAs($pegawai->user, 'web_mobile')
            ->get(route('presensi.izin.index', ['bulan' => '2026-10'])));
        $this->assertCount(1, $bulan['pengajuan']['data']);
        $this->assertSame('sakit', $bulan['pengajuan']['data'][0]['jenis_izin']);
    }

    public function test_filter_tidak_valid_ditolak(): void
    {
        $pegawai = $this->makePegawai();

        $this->actingAs($pegawai->user, 'web_mobile')
            ->get(route('presensi.izin.index', ['status' => 'ngawur']))
            ->assertSessionHasErrors('status');
    }

    public function test_paginasi_lima_belas_per_halaman(): void
    {
        $pegawai = $this->makePegawai();
        for ($i = 0; $i < 17; $i++) {
            $this->makePengajuan($pegawai, ['tanggal_mulai' => '2026-10-01', 'alasan' => "Alasan {$i}"]);
        }

        $halaman1 = $this->props($this->actingAs($pegawai->user, 'web_mobile')->get(route('presensi.izin.index')));
        $this->assertCount(15, $halaman1['pengajuan']['data']);
        $this->assertNotNull($halaman1['pengajuan']['next_page_url']);

        $halaman2 = $this->props($this->actingAs($pegawai->user, 'web_mobile')
            ->get(route('presensi.izin.index', ['page' => 2])));
        $this->assertCount(2, $halaman2['pengajuan']['data']);
    }

    public function test_pengajuan_lolos_tingkat_1_membawa_nama_approver_l1(): void
    {
        $pegawai = $this->makePegawai();
        $atasan = User::factory()->create(['name' => 'Atasan Langsung']);
        $pengajuan = $this->makePengajuan($pegawai);
        $pengajuan->approval_stage = 'pending_l2';
        $pengajuan->approver_l1_id = $atasan->id;
        $pengajuan->approved_at_l1 = Carbon::parse('2026-10-06 09:00:00');
        $pengajuan->save();

        $props = $this->props($this->actingAs($pegawai->user, 'web_mobile')->get(route('presensi.izin.index')));

        $this->assertSame('pending_l2', $props['pengajuan']['data'][0]['approval_stage']);
        $this->assertSame('Atasan Langsung', $props['pengajuan']['data'][0]['approver_l1']['name']);
    }

    public function test_sisa_cuti_dikirim_ke_halaman(): void
    {
        $pegawai = $this->makePegawai();

        $props = $this->props($this->actingAs($pegawai->user, 'web_mobile')->get(route('presensi.izin.index')));

        $this->assertSame(12, (int) $props['pegawai']['sisa_cuti']);
    }
}
