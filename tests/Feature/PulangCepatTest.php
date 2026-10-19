<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\PengajuanPulangCepat;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use App\Notifications\PulangCepatBaru;
use App\Notifications\StatusPulangCepat;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PulangCepatTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private Pegawai $pegawai;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-21 10:00:00'); // Kamis

        $this->seed(RolePermissionSeeder::class);

        $this->unit = UnitSekolah::create([
            'nama' => 'SD Uji Pulang Cepat',
            'singkatan' => 'SD',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'toleransi_menit' => 0,
            'wajib_pulang_mengajar' => true,
            'pulang_sebelum_menit' => 20,
            'jam_pulang_kantor' => '15:00:00',
        ]);

        $jabatan = Jabatan::create(['nama' => 'Guru']);
        $user = User::factory()->create();
        $this->pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '3273'.str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT),
            'nama_lengkap' => 'Guru Pulang Cepat',
            'jenis_kelamin' => 'L',
            'status_kepegawaian' => 'guru_tetap_yayasan',
            'status_aktif' => 'aktif',
            'tmt_mengajar' => '2020-01-01',
            'wajib_kantor' => true,
        ]);
        $this->pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        $this->superadmin = User::factory()->create();
        $this->superadmin->assignRole('superadmin');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Regresi: kolom identitas pegawai bernama `nuptk` (dulu `nip` sebelum
     * migrasi rename). Nama kolom yang salah bikin 500 di MySQL.
     */
    public function test_halaman_admin_menampilkan_nuptk_pegawai(): void
    {
        $this->pegawai->update(['nuptk' => '1234567890123456']);
        $this->postStore();

        $res = $this->actingAs($this->superadmin, 'web_admin')->get(route('pulang-cepat.index'));

        $res->assertOk();
        $props = $res->viewData('page')['props'];
        $this->assertSame('1234567890123456', $props['pengajuans']['data'][0]['pegawai']['nuptk']);
    }

    /**
     * Realisasi pulang (jam_keluar + penanda pulang awal) ikut terkirim ke
     * halaman admin & mobile - bukan cuma status approval, supaya admin dan
     * pegawai bisa memastikan yang bersangkutan benar-benar sudah pulang.
     */
    public function test_realisasi_pulang_terkirim_ke_admin_dan_mobile(): void
    {
        Notification::fake();

        Presensi::create([
            'pegawai_id' => $this->pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'tipe_presensi' => 'kantor',
            'tanggal' => '2026-08-21',
            'jam_masuk' => '07:00:00',
            'jam_keluar' => '13:00:00',
        ]);
        $this->postStore()->assertRedirect();

        // Belum disetujui: pulang awal tercatat, tapi belum berizin.
        $mobile = $this->actingAs($this->pegawai->user, 'web_mobile')
            ->get(route('presensi.pulang-cepat.index'));
        $mobile->assertOk();
        $realisasi = $mobile->viewData('page')['props']['pengajuan'][0]['realisasi'];
        $this->assertSame('13:00', $realisasi['jam_keluar']);
        $this->assertTrue($realisasi['is_pulang_awal']);
        $this->assertSame(120, $realisasi['menit_pulang_awal']);
        $this->assertFalse($realisasi['pulang_awal_diizinkan']);

        $this->actingAs($this->superadmin, 'web_admin')
            ->post(route('pulang-cepat.approve', PengajuanPulangCepat::firstOrFail()->id))
            ->assertRedirect();

        $admin = $this->actingAs($this->superadmin, 'web_admin')->get(route('pulang-cepat.index'));
        $admin->assertOk();
        $adminRealisasi = $admin->viewData('page')['props']['pengajuans']['data'][0]['realisasi'];
        $this->assertSame('13:00', $adminRealisasi['jam_keluar']);
        $this->assertTrue($adminRealisasi['is_pulang_awal']);
        $this->assertTrue($adminRealisasi['pulang_awal_diizinkan']);
    }

    /**
     * Disetujui tapi pegawai belum presensi keluar -> realisasi null
     * (FE menerjemahkannya jadi "Belum tercatat pulang").
     */
    public function test_realisasi_null_bila_presensi_keluar_belum_ada(): void
    {
        Notification::fake();

        $this->postStore()->assertRedirect();
        $this->actingAs($this->superadmin, 'web_admin')
            ->post(route('pulang-cepat.approve', PengajuanPulangCepat::firstOrFail()->id))
            ->assertRedirect();

        $res = $this->actingAs($this->pegawai->user, 'web_mobile')
            ->get(route('presensi.pulang-cepat.index'));

        $res->assertOk();
        $row = $res->viewData('page')['props']['pengajuan'][0];
        $this->assertArrayHasKey('realisasi', $row);
        $this->assertNull($row['realisasi']);
    }

    private function postStore(array $extra = [])
    {
        return $this->actingAs($this->pegawai->user, 'web_mobile')
            ->post(route('presensi.pulang-cepat.store'), array_merge([
                'tanggal' => '2026-08-21',
                'jam_diminta' => '14:00',
                'alasan' => 'keperluan_pribadi',
                'alasan_detail' => 'Ada urusan mendesak.',
            ], $extra));
    }

    public function test_store_sukses_nomor_terbentuk_dan_notifikasi_terkirim(): void
    {
        Notification::fake();

        $this->postStore()->assertRedirect(route('presensi.pulang-cepat.index'));

        $this->assertDatabaseHas('pengajuan_pulang_cepats', [
            'pegawai_id' => $this->pegawai->id,
            'nomor' => 'PPC/2026/08/0001',
            'status' => 'pending',
        ]);
        $this->assertSame('14:00', substr(PengajuanPulangCepat::firstOrFail()->jam_diminta, 0, 5));
        Notification::assertSentTo($this->superadmin, PulangCepatBaru::class);
    }

    public function test_tanggal_masa_lalu_ditolak(): void
    {
        $this->postStore(['tanggal' => '2026-08-20'])->assertSessionHasErrors('tanggal');
    }

    public function test_duplikat_pending_ditolak(): void
    {
        $this->postStore()->assertRedirect();

        $this->postStore()->assertSessionHasErrors('tanggal');
    }

    public function test_approve_menandai_presensi_sebagai_diizinkan(): void
    {
        Notification::fake();

        $presensi = Presensi::create([
            'pegawai_id' => $this->pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'tipe_presensi' => 'kantor',
            'tanggal' => '2026-08-21',
            'jam_masuk' => '07:00:00',
            'jam_keluar' => '13:00:00',
        ]);
        $this->assertTrue($presensi->fresh()->is_pulang_awal);
        $this->assertFalse($presensi->fresh()->pulang_awal_diizinkan);

        $this->postStore()->assertRedirect();
        $pengajuan = PengajuanPulangCepat::firstOrFail();

        $this->actingAs($this->superadmin, 'web_admin')
            ->post(route('pulang-cepat.approve', $pengajuan->id))
            ->assertRedirect();

        $this->assertDatabaseHas('pengajuan_pulang_cepats', [
            'id' => $pengajuan->id,
            'status' => 'disetujui',
            'approver_id' => $this->superadmin->id,
        ]);
        $this->assertTrue($presensi->fresh()->pulang_awal_diizinkan);
        Notification::assertSentTo($this->pegawai->user, StatusPulangCepat::class);
    }

    public function test_reject_mengubah_status_dan_alasan(): void
    {
        Notification::fake();

        $this->postStore()->assertRedirect();
        $pengajuan = PengajuanPulangCepat::firstOrFail();

        $this->actingAs($this->superadmin, 'web_admin')
            ->postJson(route('pulang-cepat.reject', $pengajuan->id))
            ->assertStatus(422);

        $this->actingAs($this->superadmin, 'web_admin')
            ->post(route('pulang-cepat.reject', $pengajuan->id), ['alasan_penolakan' => 'Tidak mendesak.'])
            ->assertRedirect();

        $this->assertDatabaseHas('pengajuan_pulang_cepats', [
            'id' => $pengajuan->id,
            'status' => 'ditolak',
            'rejected_by' => $this->superadmin->id,
            'alasan_penolakan' => 'Tidak mendesak.',
        ]);
        Notification::assertSentTo($this->pegawai->user, StatusPulangCepat::class);
    }

    public function test_cetak_owner_dan_superadmin_bisa_orang_lain_403(): void
    {
        $this->postStore()->assertRedirect();
        $pengajuan = PengajuanPulangCepat::firstOrFail();

        $this->actingAs($this->pegawai->user, 'web_mobile')
            ->get(route('presensi.pulang-cepat.cetak', $pengajuan->id))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        // unduh=1 -> attachment (opsi Unduh PDF di mobile), bukan inline.
        $unduh = $this->actingAs($this->pegawai->user, 'web_mobile')
            ->get(route('presensi.pulang-cepat.cetak', ['id' => $pengajuan->id, 'unduh' => 1]));
        $unduh->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('attachment', $unduh->headers->get('content-disposition'));

        $this->actingAs($this->superadmin, 'web_admin')
            ->get(route('pulang-cepat.cetak', $pengajuan->id))
            ->assertOk();

        $orangLain = User::factory()->create();
        $orangLain->assignRole('pegawai');
        $this->actingAs($orangLain, 'web_mobile')
            ->get(route('presensi.pulang-cepat.cetak', $pengajuan->id))
            ->assertStatus(403);
    }
}
