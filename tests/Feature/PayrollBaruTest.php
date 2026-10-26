<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Jadwal;
use App\Models\KomponenGaji;
use App\Models\LaporanPiket;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\PegawaiMapel;
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

    private function masukJadwal(Pegawai $pegawai, Jadwal $jadwal, string $tanggal): void
    {
        $p = Presensi::create([
            'pegawai_id' => $pegawai->id,
            'jadwal_id' => $jadwal->id,
            'unit_sekolah_id' => $this->unit->id,
            'tipe_presensi' => 'mengajar',
            'tanggal' => $tanggal,
            'jam_masuk' => $jadwal->jam_mulai,
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

    public function test_generate_per_unit(): void
    {
        $unitB = UnitSekolah::create(['nama' => 'TK Per Unit', 'singkatan' => 'TKPU', 'latitude' => -6.2, 'longitude' => 106.8, 'radius_meter' => 100]);
        $guruA = $this->makePegawai('guru_tetap_yayasan', 'Guru Unit A', 21);
        $guruB = $this->makePegawai('guru_tetap_yayasan', 'Guru Unit B', 22);
        $guruB->units()->detach($this->unit->id);
        $guruB->units()->attach($unitB->id, ['jabatan_id' => Jabatan::first()->id, 'is_primary' => true]);

        $this->actingAs($this->admin, 'web_admin')
            ->post(route('penggajian.run.init'), ['month' => '06', 'year' => '2026', 'unit_sekolah_id' => $this->unit->id])
            ->assertRedirect(route('penggajian.run.worksheet', ['month' => '06', 'year' => '2026', 'unit' => $this->unit->id]));

        $ids = Penggajian::where('periode_bulan', '06-2026')->where('status', 'draft')->pluck('pegawai_id')->all();
        $this->assertContains($guruA->id, $ids, 'Pegawai unit terpilih ikut di-generate');
        $this->assertNotContains($guruB->id, $ids, 'Pegawai unit lain tidak ikut');
    }

    public function test_generate_unit_diluar_scope_admin_unit_ditolak(): void
    {
        $unitB = UnitSekolah::create(['nama' => 'TK Lain', 'singkatan' => 'TKL', 'latitude' => -6.2, 'longitude' => 106.8, 'radius_meter' => 100]);
        $adminUnit = User::factory()->create(['role' => 'admin_unit', 'unit_sekolah_id' => $this->unit->id]);
        $adminUnit->assignRole('admin_unit');

        $this->actingAs($adminUnit, 'web_admin')
            ->post(route('penggajian.run.init'), ['month' => '06', 'year' => '2026', 'unit_sekolah_id' => $unitB->id])
            ->assertForbidden();
    }

    public function test_guru_dibayar_per_jp_tanpa_potongan_kehadiran(): void
    {
        $pegawai = $this->makePegawai('guru_tetap_yayasan', 'Guru JP', 1);
        KomponenGaji::query()->update(['is_active' => false]);
        $this->makeKomponen(['nama' => 'Honor Mengajar', 'kode' => 'honor_mengajar', 'jenis' => 'dinamis_jam_mengajar', 'nilai_default' => 50000, 'unit_sekolah_id' => $this->unit->id, 'applies_to_status_kepegawaian' => 'guru', 'is_taxable' => true]);
        $jadwal = $this->makeJadwalSenin($pegawai);
        foreach (['2026-06-01', '2026-06-08', '2026-06-15', '2026-06-22', '2026-06-29'] as $tgl) {
            $this->masukJadwal($pegawai, $jadwal, $tgl);
        }

        $this->runDraft();
        [, $rows] = $this->details($pegawai);

        // 5 pertemuan × 1 JP (45 menit / durasi_jp 45) × 50.000 = 250.000
        $this->assertEquals(250000, (float) ($rows['Honor Mengajar'] ?? 0));
        $potongan = array_filter(array_keys($rows), fn ($k) => str_contains($k, 'Potongan Kehadiran'));
        $this->assertSame([], array_values($potongan));
    }

    public function test_guru_tak_dipotong_kehadiran_meski_bolos(): void
    {
        $pegawai = $this->makePegawai('guru_tetap_yayasan', 'Guru Bolos', 2);
        KomponenGaji::query()->update(['is_active' => false]);
        $this->makeKomponen(['nama' => 'Honor Mengajar', 'kode' => 'honor_mengajar', 'jenis' => 'dinamis_jam_mengajar', 'nilai_default' => 50000, 'unit_sekolah_id' => $this->unit->id, 'applies_to_status_kepegawaian' => 'guru', 'is_taxable' => true]);
        $this->makeJadwalSenin($pegawai);
        $this->masukKantor($pegawai, '2026-06-01');

        $this->runDraft();
        [, $rows] = $this->details($pegawai);

        $potongan = array_filter(array_keys($rows), fn ($k) => str_contains($k, 'Potongan Kehadiran'));
        $this->assertSame([], array_values($potongan));
    }

    public function test_guru_tidak_tetap_tidak_dapat_transport(): void
    {
        $pegawai = $this->makePegawai('guru_tidak_tetap', 'Guru GTT', 3);
        KomponenGaji::query()->update(['is_active' => false]);
        $this->makeKomponen(['nama' => 'Honor Mengajar', 'kode' => 'honor_mengajar', 'jenis' => 'dinamis_jam_mengajar', 'nilai_default' => 50000, 'unit_sekolah_id' => $this->unit->id, 'applies_to_status_kepegawaian' => 'guru', 'is_taxable' => true]);
        $this->makeKomponen(['nama' => 'Tunjangan Transport '.$this->unit->singkatan, 'kode' => 'transport_harian', 'jenis' => 'dinamis_kehadiran', 'nilai_default' => 25000, 'unit_sekolah_id' => $this->unit->id, 'applies_to_status_kepegawaian' => 'tetap']);
        $this->makeJadwalSenin($pegawai);
        foreach (['2026-06-01', '2026-06-08', '2026-06-15', '2026-06-22', '2026-06-29'] as $tgl) {
            $this->masukKantor($pegawai, $tgl);
        }

        $this->runDraft();
        [, $rows] = $this->details($pegawai);

        $transport = array_filter(array_keys($rows), fn ($k) => str_starts_with($k, 'Transport ('));
        $this->assertSame([], array_values($transport));
    }

    public function test_non_guru_potongan_harian_dan_transport(): void
    {
        $pegawai = $this->makePegawai('pegawai_tetap_yayasan', 'Satpam', 4);
        KomponenGaji::query()->update(['is_active' => false]);
        $this->makeKomponen(['nama' => 'Gaji Pokok', 'kode' => 'gaji_pokok', 'jenis' => 'fixed', 'nilai_default' => 2000000, 'unit_sekolah_id' => $this->unit->id, 'applies_to_status_kepegawaian' => 'non_guru']);
        $this->makeKomponen(['nama' => 'Tunjangan Transport '.$this->unit->singkatan, 'kode' => 'transport_harian', 'jenis' => 'dinamis_kehadiran', 'nilai_default' => 25000, 'unit_sekolah_id' => $this->unit->id, 'applies_to_status_kepegawaian' => 'tetap']);
        Jadwal::create([
            'pegawai_id' => $pegawai->id, 'unit_sekolah_id' => $this->unit->id,
            'hari' => 'Senin', 'jam_mulai' => '07:00:00', 'jam_selesai' => '15:00:00',
            'jenis_jadwal' => 'shift_satpam', 'tahun_ajaran' => '2026/2027', 'semester' => 1,
        ]);
        foreach (['2026-06-01', '2026-06-08', '2026-06-15', '2026-06-22'] as $tgl) {
            $this->masukKantor($pegawai, $tgl);
        }

        $this->runDraft();
        [, $rows] = $this->details($pegawai);

        $this->assertEquals(2000000, (float) ($rows['Gaji Pokok'] ?? 0));
        $this->assertEquals(400000, (float) ($rows['Potongan Kehadiran — (Rp 2.000.000 ÷ 5 hari × 1 hari)'] ?? 0));
        $this->assertEquals(100000, (float) ($rows['Transport (4 × Rp 25.000)'] ?? 0));
    }

    public function test_non_guru_tanpa_jadwal_hari_kerja_dari_unit(): void
    {
        $pegawai = $this->makePegawai('pegawai_tetap_yayasan', 'Staf', 5);
        KomponenGaji::query()->update(['is_active' => false]);
        $this->makeKomponen(['nama' => 'Gaji Pokok', 'kode' => 'gaji_pokok', 'jenis' => 'fixed', 'nilai_default' => 2000000, 'unit_sekolah_id' => $this->unit->id, 'applies_to_status_kepegawaian' => 'non_guru']);

        $this->runDraft();
        [, $rows] = $this->details($pegawai);

        // Tanpa jadwal → hari kerja Sen–Jum Juni 2026 (22 hari); 0 hadir → potong penuh.
        $potongan = array_values(array_filter(array_keys($rows), fn ($k) => str_contains($k, 'Potongan Kehadiran')));
        $this->assertCount(1, $potongan);
        $this->assertEquals(2000000, (float) $rows[$potongan[0]]);
    }

    public function test_piket_prorata_dibatasi_satu_jp(): void
    {
        $pegawai = $this->makePegawai('guru_tetap_yayasan', 'Guru Piket', 6);
        KomponenGaji::query()->update(['is_active' => false]);
        $this->makeKomponen(['nama' => 'Honor Piket', 'kode' => 'honor_piket', 'jenis' => 'dinamis_piket', 'nilai_default' => 35000, 'unit_sekolah_id' => $this->unit->id, 'applies_to_status_kepegawaian' => 'guru', 'is_taxable' => true]);
        $jadwal = Jadwal::create([
            'pegawai_id' => $pegawai->id, 'unit_sekolah_id' => $this->unit->id,
            'hari' => 'Senin', 'jam_mulai' => '07:00:00', 'jam_selesai' => '07:45:00',
            'jenis_jadwal' => 'piket', 'tahun_ajaran' => '2026/2027', 'semester' => 1,
        ]);
        foreach (['2026-06-01', '2026-06-08', '2026-06-15', '2026-06-22'] as $tgl) {
            LaporanPiket::create([
                'pegawai_id' => $pegawai->id, 'jadwal_id' => $jadwal->id,
                'unit_sekolah_id' => $this->unit->id, 'tanggal' => $tgl, 'status' => 'submitted',
            ]);
        }

        $this->runDraft();
        [, $rows] = $this->details($pegawai);

        // 35.000 × (4/5) = 28.000
        $this->assertEquals(28000, (float) ($rows['Honor Piket'] ?? 0));
    }

    public function test_ekskul_dibatasi_240rb_per_ekskul(): void
    {
        $pegawai = $this->makePegawai('guru_tetap_yayasan', 'Guru Ekskul', 7);
        KomponenGaji::query()->update(['is_active' => false]);
        $mapel = MataPelajaran::create(['nama' => 'Futsal', 'unit_sekolah_id' => $this->unit->id]);
        $pm = PegawaiMapel::create(['pegawai_id' => $pegawai->id, 'mata_pelajaran_id' => $mapel->id, 'unit_sekolah_id' => $this->unit->id]);
        $this->makeKomponen(['nama' => 'Honor Ekskul', 'kode' => 'honor_ekskul_futsal', 'jenis' => 'dinamis_ekskul', 'nilai_default' => 60000, 'unit_sekolah_id' => $this->unit->id, 'applies_to_status_kepegawaian' => 'guru', 'mata_pelajaran_id' => $mapel->id, 'is_taxable' => true]);
        $jadwal = Jadwal::create([
            'pegawai_id' => $pegawai->id, 'unit_sekolah_id' => $this->unit->id,
            'pegawai_mapel_id' => $pm->id, 'hari' => 'Senin',
            'jam_mulai' => '14:00:00', 'jam_selesai' => '15:00:00',
            'jenis_jadwal' => 'ekskul', 'tahun_ajaran' => '2026/2027', 'semester' => 1,
        ]);
        foreach (['2026-06-01', '2026-06-08', '2026-06-15', '2026-06-22', '2026-06-29'] as $tgl) {
            $this->masukJadwal($pegawai, $jadwal, $tgl);
        }

        $this->runDraft();
        [, $rows] = $this->details($pegawai);

        // 5 pertemuan × 60.000 = 300.000 → cap 240.000
        $this->assertEquals(240000, (float) ($rows['Honor Ekskul'] ?? 0));
    }

    public function test_pph_dari_taxable_bpjs_dari_gaji_pokok(): void
    {
        $pegawai = $this->makePegawai('pegawai_tetap_yayasan', 'Staf Pajak', 8);
        KomponenGaji::query()->update(['is_active' => false]);
        $this->makeKomponen(['nama' => 'Gaji Pokok', 'kode' => 'gaji_pokok', 'jenis' => 'fixed', 'nilai_default' => 2000000, 'unit_sekolah_id' => $this->unit->id, 'applies_to_status_kepegawaian' => 'non_guru']);
        $this->makeKomponen(['nama' => 'Tunjangan Kinerja', 'kode' => 'tunjangan_kinerja', 'jenis' => 'fixed', 'nilai_default' => 1000000, 'unit_sekolah_id' => $this->unit->id, 'applies_to_status_kepegawaian' => 'non_guru', 'is_taxable' => true]);
        $this->makeKomponen(['nama' => 'PPh 21', 'kode' => 'pph21', 'tipe' => 'potongan', 'jenis' => 'persentase', 'nilai_default' => 5, 'persentase_base' => 'taxable', 'applies_to_status_kepegawaian' => 'tetap', 'unit_sekolah_id' => $this->unit->id]);
        $this->makeKomponen(['nama' => 'BPJS Kesehatan', 'kode' => 'bpjs_kes', 'tipe' => 'potongan', 'jenis' => 'persentase', 'nilai_default' => 1, 'persentase_base' => 'gaji_pokok', 'applies_to_status_kepegawaian' => 'tetap', 'unit_sekolah_id' => $this->unit->id]);

        $this->runDraft();
        [, $rows] = $this->details($pegawai);

        // PPh = 5% × taxable (1jt) = 50.000 ; BPJS = 1% × gaji_pokok (2jt) = 20.000
        $this->assertEquals(50000, (float) ($rows['PPh 21'] ?? 0));
        $this->assertEquals(20000, (float) ($rows['BPJS Kesehatan'] ?? 0));
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
        $izin->forceFill([
            'status' => 'pending',
            'approval_stage' => 'pending_l1',
            'approver_l1_id' => $this->admin->id,
        ])->save();

        $this->actingAs($this->admin, 'web_admin')
            ->post(route('pengajuan-izin.approve', $izin->id), ['dihitung_setengah' => true])
            ->assertRedirect();

        $this->assertTrue((bool) $izin->fresh()->dihitung_setengah);
    }
}
