<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\PengajuanKoreksi;
use App\Models\Penggajian;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use App\Notifications\KoreksiBaru;
use App\Notifications\StatusKoreksi;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PengajuanKoreksiTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private Pegawai $pegawai;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-21 10:00:00'); // Kamis — tanggal independen

        $this->seed(RolePermissionSeeder::class);

        $this->unit = UnitSekolah::create([
            'nama' => 'SD Uji Koreksi',
            'singkatan' => 'SD',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
        ]);

        $jabatan = Jabatan::create(['nama' => 'Guru']);
        $user = User::factory()->create();
        $this->pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '3273'.str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT),
            'nama_lengkap' => 'Guru Koreksi',
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

    private function makePresensi(string $tanggal, string $jamMasuk = '07:05:00', ?Pegawai $pegawai = null): Presensi
    {
        return Presensi::create([
            'pegawai_id' => $pegawai?->id ?? $this->pegawai->id,
            'jadwal_id' => null,
            'unit_sekolah_id' => $this->unit->id,
            'tipe_presensi' => 'kantor',
            'tanggal' => $tanggal,
            'jam_masuk' => $jamMasuk,
        ]);
    }

    private function postStore(array $extra = [])
    {
        return $this->actingAs($this->pegawai->user, 'web_mobile')
            ->post(route('presensi.koreksi.store'), array_merge([
                'nilai_baru' => '15:30',
                'alasan' => 'lupa_presensi',
                'alasan_detail' => 'Lupa absen pulang karena langsung rapat.',
            ], $extra));
    }

    private function buatKoreksiDisetujui(int $urutan): PengajuanKoreksi
    {
        $presensi = $this->makePresensi('2026-08-0'.$urutan, '07:0'.$urutan);
        $koreksi = PengajuanKoreksi::create([
            'pegawai_id' => $this->pegawai->id,
            'presensi_id' => $presensi->id,
            'tanggal' => $presensi->tanggal,
            'nilai_baru' => '15:00',
            'alasan' => 'lupa_presensi',
            'nomor' => sprintf('KOR/2026/08/%04d', $urutan),
        ]);
        $koreksi->status = 'disetujui';
        $koreksi->approved_at = now();
        $koreksi->save();

        return $koreksi;
    }

    public function test_store_sukkses_nomor_terbentuk_dan_notifikasi_terkirim(): void
    {
        Notification::fake();
        $presensi = $this->makePresensi('2026-08-20');

        $response = $this->postStore(['presensi_id' => $presensi->id]);

        $response->assertRedirect(route('presensi.koreksi.index'));
        $this->assertDatabaseHas('pengajuan_koreksis', [
            'pegawai_id' => $this->pegawai->id,
            'presensi_id' => $presensi->id,
            'nomor' => 'KOR/2026/08/0001',
            'nilai_baru' => '15:30',
            'status' => 'pending',
        ]);
        Notification::assertSentTo($this->superadmin, KoreksiBaru::class);
    }

    public function test_store_presensi_pegawai_lain_ditolak_403(): void
    {
        $pegawaiLain = Pegawai::create([
            'user_id' => User::factory()->create()->id,
            'nik' => '3273999999999999',
            'nama_lengkap' => 'Orang Lain',
            'jenis_kelamin' => 'P',
            'status_kepegawaian' => 'honorer',
            'status_aktif' => 'aktif',
            'tmt_mengajar' => '2021-01-01',
            'wajib_kantor' => true,
        ]);
        $presensi = $this->makePresensi('2026-08-20', '07:05:00', $pegawaiLain);

        $this->postStore(['presensi_id' => $presensi->id])->assertStatus(403);
    }

    public function test_nilai_baru_tidak_setelah_jam_masuk_ditolak(): void
    {
        $presensi = $this->makePresensi('2026-08-20', '07:05:00');

        $this->postStore(['presensi_id' => $presensi->id, 'nilai_baru' => '07:05'])
            ->assertSessionHasErrors('nilai_baru');
    }

    public function test_duplikat_pending_ditolak(): void
    {
        $presensi = $this->makePresensi('2026-08-20');
        $this->postStore(['presensi_id' => $presensi->id])->assertRedirect();

        $this->postStore(['presensi_id' => $presensi->id])
            ->assertSessionHasErrors('presensi_id');
    }

    public function test_payroll_terkunci_ditolak(): void
    {
        Penggajian::create([
            'pegawai_id' => $this->pegawai->id,
            'periode_bulan' => '08-2026',
            'tanggal_generate' => '2026-08-21',
            'status' => 'paid',
        ]);
        $presensi = $this->makePresensi('2026-08-20');

        $this->postStore(['presensi_id' => $presensi->id])
            ->assertSessionHasErrors('presensi_id');
    }

    public function test_lewat_kuota_tanpa_penjelasan_ditolak_dengan_penjelasan_sukkses(): void
    {
        $this->buatKoreksiDisetujui(1);
        $this->buatKoreksiDisetujui(2);
        $this->buatKoreksiDisetujui(3);
        $presensi = $this->makePresensi('2026-08-20');

        $this->postStore(['presensi_id' => $presensi->id])
            ->assertSessionHasErrors('penjelasan_khusus');

        $response = $this->postStore([
            'presensi_id' => $presensi->id,
            'penjelasan_khusus' => 'Bulan ini tiga kali HP rusak, lampirkan nota servis.',
        ]);
        $response->assertRedirect(route('presensi.koreksi.index'));
        $this->assertDatabaseHas('pengajuan_koreksis', [
            'presensi_id' => $presensi->id,
            'status' => 'pending',
            'penjelasan_khusus' => 'Bulan ini tiga kali HP rusak, lampirkan nota servis.',
        ]);
    }

    public function test_approve_memperbarui_presensi_dan_mencatat_audit(): void
    {
        Notification::fake();
        $presensi = $this->makePresensi('2026-08-20');
        $this->postStore(['presensi_id' => $presensi->id])->assertRedirect();
        $koreksi = PengajuanKoreksi::firstOrFail();

        $this->actingAs($this->superadmin, 'web_admin')
            ->post(route('koreksi-presensi.approve', $koreksi->id))
            ->assertRedirect();

        $this->assertDatabaseHas('presensi', [
            'id' => $presensi->id,
            'jam_keluar' => '15:30:00',
        ]);
        $this->assertDatabaseHas('pengajuan_koreksis', [
            'id' => $koreksi->id,
            'status' => 'disetujui',
            'approver_id' => $this->superadmin->id,
        ]);
        $this->assertDatabaseHas('audit_presensi', [
            'presensi_id' => $presensi->id,
            'aksi' => 'koreksi_jam_keluar',
            'nilai_baru' => '15:30',
        ]);
        Notification::assertSentTo($this->pegawai->user, StatusKoreksi::class);
    }

    public function test_approve_kedua_kali_ditolak(): void
    {
        $presensi = $this->makePresensi('2026-08-20');
        $this->postStore(['presensi_id' => $presensi->id])->assertRedirect();
        $koreksi = PengajuanKoreksi::firstOrFail();

        $this->actingAs($this->superadmin, 'web_admin')
            ->post(route('koreksi-presensi.approve', $koreksi->id))
            ->assertRedirect();

        $this->actingAs($this->superadmin, 'web_admin')
            ->postJson(route('koreksi-presensi.approve', $koreksi->id))
            ->assertStatus(422)
            ->assertJsonValidationErrors('error');
    }

    public function test_reject_mengubah_status_dan_alasan(): void
    {
        Notification::fake();
        $presensi = $this->makePresensi('2026-08-20');
        $this->postStore(['presensi_id' => $presensi->id])->assertRedirect();
        $koreksi = PengajuanKoreksi::firstOrFail();

        $this->actingAs($this->superadmin, 'web_admin')
            ->postJson(route('koreksi-presensi.reject', $koreksi->id))
            ->assertStatus(422);

        $this->actingAs($this->superadmin, 'web_admin')
            ->post(route('koreksi-presensi.reject', $koreksi->id), [
                'alasan_penolakan' => 'Bukti lokasi tidak meyakinkan.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pengajuan_koreksis', [
            'id' => $koreksi->id,
            'status' => 'ditolak',
            'rejected_by' => $this->superadmin->id,
            'alasan_penolakan' => 'Bukti lokasi tidak meyakinkan.',
        ]);
        $this->assertNull($presensi->fresh()->jam_keluar);
        Notification::assertSentTo($this->pegawai->user, StatusKoreksi::class);
    }

    public function test_cetak_owner_dan_superadmin_bisa_orang_lain_403(): void
    {
        $presensi = $this->makePresensi('2026-08-20');
        $this->postStore(['presensi_id' => $presensi->id])->assertRedirect();
        $koreksi = PengajuanKoreksi::firstOrFail();

        $this->actingAs($this->pegawai->user, 'web_mobile')
            ->get(route('presensi.koreksi.cetak', $koreksi->id))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($this->superadmin, 'web_admin')
            ->get(route('koreksi-presensi.cetak', $koreksi->id))
            ->assertOk();

        $orangLain = User::factory()->create();
        $orangLain->assignRole('pegawai');
        $this->actingAs($orangLain, 'web_mobile')
            ->get(route('presensi.koreksi.cetak', $koreksi->id))
            ->assertStatus(403);
    }
}
