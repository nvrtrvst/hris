<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Jadwal;
use App\Models\KomponenGaji;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\Penggajian;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Model payroll baru: gaji fixed + potongan rate + transport harian kantor.
 * Juni 2026 dipakai karena Senin-nya jelas: 1, 8, 15, 22, 29 (5 hari kerja
 * untuk 1 jadwal Senin).
 */
class PayrollBaruTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->unit = UnitSekolah::create([
            'nama' => 'SMP Payroll Baru',
            'singkatan' => 'SMPB',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'toleransi_menit' => 0,
        ]);

        $this->admin = User::factory()->create(['role' => 'superadmin']);
        $this->admin->assignRole('superadmin');
    }

    private function makePegawai(string $status, string $nama, int $nikSuffix): Pegawai
    {
        $jabatan = Jabatan::create(['nama' => 'Guru '.$nama]);
        $user = User::factory()->create();
        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '88001100'.str_pad((string) $nikSuffix, 8, '0', STR_PAD_LEFT),
            'nama_lengkap' => $nama,
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'kawin',
            'jumlah_tanggungan' => 0,
            'alamat' => 'Jl. Test',
            'no_hp' => '081200000001',
            'status_kepegawaian' => $status,
            'tmt_mengajar' => '2020-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
        ]);
        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    private function makeKomponen(array $attrs): KomponenGaji
    {
        return KomponenGaji::create(array_merge([
            'tipe' => 'pendapatan',
            'is_taxable' => false,
            'is_active' => true,
            'urutan' => 50,
            'tampil_di_matrix' => true,
        ], $attrs));
    }

    private function makeJadwalSenin(Pegawai $pegawai): Jadwal
    {
        return Jadwal::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'hari' => 'Senin',
            'jam_mulai' => '08:00:00',
            'jam_selesai' => '08:45:00',
            'jenis_jadwal' => 'mengajar',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);
    }

    private function masukKantor(Pegawai $pegawai, string $tanggal): void
    {
        $p = Presensi::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'tipe_presensi' => 'kantor',
            'tanggal' => $tanggal,
            'jam_masuk' => '07:00:00',
            'jam_keluar' => '15:00:00',
        ]);
        $p->status = 'hadir';
        $p->save();
    }

    private function runDraft(): void
    {
        $this->actingAs($this->admin, 'web_admin')
            ->post(route('penggajian.run.init'), ['month' => '06', 'year' => '2026'])
            ->assertRedirect(route('penggajian.run.worksheet', ['month' => '06', 'year' => '2026']));
    }

    private function details(Pegawai $pegawai): array
    {
        $gajian = Penggajian::where('pegawai_id', $pegawai->id)->where('periode_bulan', '06-2026')->firstOrFail();

        return [$gajian, $gajian->details()->pluck('nominal', 'nama_komponen')->all()];
    }

    public function test_tetap_rate_bawah_90_potong_dan_berumus(): void
    {
        $pegawai = $this->makePegawai('guru_tetap_yayasan', 'Guru Tetap', 1);
        $this->makeKomponen(['nama' => 'Gaji Pokok', 'kode' => 'gaji_pokok', 'jenis' => 'fixed', 'nilai_default' => 3000000, 'unit_sekolah_id' => $this->unit->id, 'applies_to_status_kepegawaian' => 'tetap']);
        $this->makeKomponen(['nama' => 'Tunjangan Transport SMPB', 'kode' => 'transport_harian', 'jenis' => 'dinamis_kehadiran', 'nilai_default' => 25000, 'unit_sekolah_id' => $this->unit->id, 'applies_to_status_kepegawaian' => 'tetap']);
        $this->makeJadwalSenin($pegawai);
        // 4 dari 5 Senin hadir kantor → rate 80%.
        foreach (['2026-06-01', '2026-06-08', '2026-06-15', '2026-06-22'] as $tgl) {
            $this->masukKantor($pegawai, $tgl);
        }

        $this->runDraft();
        [$gajian, $rows] = $this->details($pegawai);

        $this->assertEquals(600000, (float) ($rows['Potongan Kehadiran — Gaji × 80% (4/5 hari)'] ?? 0));
        $this->assertEquals(100000, (float) ($rows['Transport (4 × Rp 25.000)'] ?? 0));
        // Bersih = agregat baris (termasuk potongan statutori bawaan seeder).
        $this->assertEquals((float) $gajian->total_pendapatan - (float) $gajian->total_potongan, (float) $gajian->gaji_bersih);
    }

    public function test_tetap_rate_di_atas_90_tanpa_potongan(): void
    {
        $pegawai = $this->makePegawai('guru_tetap_yayasan', 'Guru Rajin', 2);
        $this->makeKomponen(['nama' => 'Gaji Pokok', 'kode' => 'gaji_pokok', 'jenis' => 'fixed', 'nilai_default' => 3000000, 'unit_sekolah_id' => $this->unit->id, 'applies_to_status_kepegawaian' => 'tetap']);
        $this->makeJadwalSenin($pegawai);
        foreach (['2026-06-01', '2026-06-08', '2026-06-15', '2026-06-22', '2026-06-29'] as $tgl) {
            $this->masukKantor($pegawai, $tgl);
        }

        $this->runDraft();
        [, $rows] = $this->details($pegawai);

        $potongan = array_filter(array_keys($rows), fn ($k) => str_contains($k, 'Potongan Kehadiran'));
        $this->assertSame([], array_values($potongan));
    }

    public function test_setengah_hari_masuk_rate(): void
    {
        $pegawai = $this->makePegawai('guru_tetap_yayasan', 'Guru Setengah', 3);
        $this->makeKomponen(['nama' => 'Gaji Pokok', 'kode' => 'gaji_pokok', 'jenis' => 'fixed', 'nilai_default' => 3000000, 'unit_sekolah_id' => $this->unit->id, 'applies_to_status_kepegawaian' => 'tetap']);
        $this->makeJadwalSenin($pegawai);
        foreach (['2026-06-01', '2026-06-08', '2026-06-15', '2026-06-22'] as $tgl) {
            $this->masukKantor($pegawai, $tgl);
        }
        // 29 Juni: izin + tugas (flag) → 0,5. Rate = 4,5/5 = 90% → tetap potong (strict).
        $izin = PengajuanIzin::create([
            'pegawai_id' => $pegawai->id,
            'jenis_izin' => 'izin',
            'tanggal_mulai' => '2026-06-29',
            'tanggal_selesai' => '2026-06-29',
            'alasan' => 'Urusan keluarga dan tugas dinas.',
        ]);
        $izin->forceFill(['status' => 'disetujui', 'approval_stage' => 'approved', 'dihitung_setengah' => true])->save();

        $this->runDraft();
        [, $rows] = $this->details($pegawai);

        $this->assertEquals(300000, (float) ($rows['Potongan Kehadiran — Gaji × 90% (4,5/5 hari)'] ?? 0));
    }

    public function test_honorer_potong_per_hari_dinamis(): void
    {
        $pegawai = $this->makePegawai('guru_tidak_tetap', 'Guru Honor', 4);
        $this->makeKomponen(['nama' => 'Honor Honorer', 'kode' => 'honor_honorer', 'jenis' => 'fixed', 'nilai_default' => 2000000, 'unit_sekolah_id' => $this->unit->id, 'applies_to_status_kepegawaian' => 'honorer']);
        $this->makeJadwalSenin($pegawai);
        foreach (['2026-06-01', '2026-06-08', '2026-06-15'] as $tgl) {
            $this->masukKantor($pegawai, $tgl);
        }

        $this->runDraft();
        [$gajian, $rows] = $this->details($pegawai);

        // (2jt ÷ 5 × 2) = 800rb. Tanpa transport (tetap saja).
        $this->assertEquals(800000, (float) ($rows['Potongan Honorer — (Rp 2.000.000 ÷ 5 hari × 2 hari)'] ?? 0));
        $transport = array_filter(array_keys($rows), fn ($k) => str_starts_with($k, 'Transport ('));
        $this->assertSame([], array_values($transport));
        $this->assertEquals(2000000 - 800000, (float) $gajian->gaji_bersih);
    }

    public function test_sakit_tanpa_bukti_ditolak(): void
    {
        $pegawai = $this->makePegawai('guru_tetap_yayasan', 'Guru Sakit', 5);

        $this->actingAs($pegawai->user, 'web_mobile')
            ->post(route('presensi.izin.store'), [
                'jenis_izin' => 'sakit',
                'tanggal_mulai' => now()->format('Y-m-d'),
                'tanggal_selesai' => now()->format('Y-m-d'),
                'alasan' => 'Demam tinggi sejak malam.',
            ])
            ->assertSessionHasErrors('bukti_foto');
    }

    public function test_approve_bisa_pasang_flag_setengah(): void
    {
        $pegawai = $this->makePegawai('guru_tetap_yayasan', 'Guru Flag', 6);
        $izin = PengajuanIzin::create([
            'pegawai_id' => $pegawai->id,
            'jenis_izin' => 'izin',
            'tanggal_mulai' => now()->format('Y-m-d'),
            'tanggal_selesai' => now()->format('Y-m-d'),
            'alasan' => 'Urusan keluarga dan tugas dinas.',
        ]);
        $izin->forceFill(['status' => 'pending', 'approval_stage' => 'pending_l1'])->save();

        $this->actingAs($this->admin, 'web_admin')
            ->post(route('pengajuan-izin.approve', $izin->id), ['dihitung_setengah' => true])
            ->assertRedirect();

        $this->assertTrue((bool) $izin->fresh()->dihitung_setengah);
    }
}
