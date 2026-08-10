<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Jadwal;
use App\Models\KomponenGaji;
use App\Models\Pegawai;
use App\Models\Penggajian;
use App\Models\Presensi;
use App\Models\SkalaMasaBakti;
use App\Models\UnitSekolah;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression guard untuk optimasi createDraft:
 * 5 query scan presensi digabung menjadi 1 query + agregasi PHP.
 * Test ini menghitung ulang semua komponen gaji secara manual
 * dan memastikan angka payroll hasil generate IDENTIK.
 */
class PayrollMergeQueryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function makeSuperadmin(): User
    {
        $admin = User::factory()->create(['role' => 'superadmin']);
        $admin->assignRole('superadmin');

        return $admin;
    }

    private function makeUnit(): UnitSekolah
    {
        return UnitSekolah::create([
            'nama' => 'SMP Payroll Merge Test',
            'singkatan' => 'SMPPM',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'toleransi_menit' => 0,
        ]);
    }

    private function makePegawai(UnitSekolah $unit): Pegawai
    {
        $jabatan = Jabatan::create(['nama' => 'Guru']);
        $pegawai = Pegawai::create([
            'nik' => '8822334455667788',
            'nama_lengkap' => 'Guru Payroll Test',
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'kawin',
            'jumlah_tanggungan' => 2,
            'alamat_ktp' => 'Jl. Test No. 1',
            'no_hp' => '081298765432',
            'status_kepegawaian' => 'tetap',
            'tanggal_mulai_kerja' => '2020-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
        ]);
        $pegawai->units()->attach($unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    public function test_create_draft_menghitung_komponen_identik_setelah_merge_query(): void
    {
        $admin = $this->makeSuperadmin();
        $unit = $this->makeUnit();
        $pegawai = $this->makePegawai($unit);

        // Jadwal Senin 07:00-08:30 (90 menit / durasi_jp 45 = 2 JP per pertemuan)
        $jadwal = Jadwal::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $unit->id,
            'hari' => 'Senin',
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '08:30:00',
            'jenis_jadwal' => 'reguler',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);

        // Nonaktifkan komponen default dari migrasi (PPh 21 - Tetap, BPJS, dll)
        // agar angka test deterministik terhadap fixture di bawah.
        KomponenGaji::query()->update(['is_active' => false]);

        // Komponen gaji (global, tanpa unit_sekolah_id)
        $gajiPokok = KomponenGaji::create(['nama' => 'Gaji Pokok', 'kode' => 'gaji_pokok', 'tipe' => 'pendapatan', 'jenis' => 'fixed', 'nilai_default' => 3000000, 'is_taxable' => true, 'is_active' => true]);
        KomponenGaji::create(['nama' => 'Tunjangan Kehadiran', 'kode' => 'tunjangan_kehadiran', 'tipe' => 'pendapatan', 'jenis' => 'dinamis_kehadiran', 'nilai_default' => 50000, 'is_taxable' => false, 'is_active' => true]);
        KomponenGaji::create(['nama' => 'Potongan Telat', 'kode' => 'kehadiran_telat', 'tipe' => 'potongan', 'jenis' => 'dinamis_kehadiran', 'nilai_default' => 10000, 'is_taxable' => false, 'is_active' => true]);
        KomponenGaji::create(['nama' => 'Potongan Alpa', 'kode' => 'kehadiran_alpa', 'tipe' => 'potongan', 'jenis' => 'dinamis_kehadiran', 'nilai_default' => 20000, 'is_taxable' => false, 'is_active' => true]);
        KomponenGaji::create(['nama' => 'Potongan Sakit', 'kode' => 'kehadiran_sakit', 'tipe' => 'potongan', 'jenis' => 'dinamis_kehadiran', 'nilai_default' => 10000, 'is_taxable' => false, 'is_active' => true]);
        KomponenGaji::create(['nama' => 'Jam Mengajar', 'kode' => 'jam_mengajar', 'tipe' => 'pendapatan', 'jenis' => 'dinamis_jam_mengajar', 'nilai_default' => 30000, 'syarat_bayar_jam_mengajar' => 'hanya_hadir', 'is_taxable' => false, 'is_active' => true]);
        KomponenGaji::create(['nama' => 'Lembur', 'kode' => 'lembur', 'tipe' => 'pendapatan', 'jenis' => 'dinamis_lembur', 'nilai_default' => 25000, 'is_taxable' => false, 'is_active' => true]);
        KomponenGaji::create(['nama' => 'Masa Bakti', 'kode' => 'masa_bakti', 'tipe' => 'pendapatan', 'jenis' => 'dinamis_masa_bakti', 'nilai_default' => 0, 'is_taxable' => false, 'is_active' => true]);

        // Skala masa bakti: 5 tahun → 200.000 (masa kerja pegawai 6 tahun per Juni 2026)
        SkalaMasaBakti::create(['masa_kerja_tahun' => 5, 'nominal_gaji' => 200000]);

        // Presensi Juni 2026 (Juni 2026 bukan bulan berjalan → cutoff = akhir bulan)
        // Senin: 1, 8, 15, 22, 29 → 5 hari kerja
        // Catatan: status/is_lembur/lembur_status ada di $guarded → set via save().
        $p1 = Presensi::create(['pegawai_id' => $pegawai->id, 'jadwal_id' => $jadwal->id, 'unit_sekolah_id' => $unit->id, 'tipe_presensi' => 'mengajar', 'tanggal' => '2026-06-01', 'jam_masuk' => '07:00:00']);
        $p1->status = 'hadir';
        $p1->save();

        $p2 = Presensi::create(['pegawai_id' => $pegawai->id, 'jadwal_id' => $jadwal->id, 'unit_sekolah_id' => $unit->id, 'tipe_presensi' => 'mengajar', 'tanggal' => '2026-06-08', 'jam_masuk' => '07:05:00']);
        $p2->status = 'hadir';
        $p2->save();

        $p3 = Presensi::create(['pegawai_id' => $pegawai->id, 'jadwal_id' => $jadwal->id, 'unit_sekolah_id' => $unit->id, 'tipe_presensi' => 'mengajar', 'tanggal' => '2026-06-15', 'jam_masuk' => '08:30:00']);
        $p3->status = 'telat';
        $p3->save();

        $p4 = Presensi::create(['pegawai_id' => $pegawai->id, 'jadwal_id' => null, 'unit_sekolah_id' => $unit->id, 'tipe_presensi' => 'kantor', 'tanggal' => '2026-06-20', 'persentase_bayar_jam' => 50]);
        $p4->status = 'sakit';
        $p4->save();

        // Lembur disetujui 16:00-18:00 = 2 jam
        $p5 = Presensi::create(['pegawai_id' => $pegawai->id, 'jadwal_id' => null, 'unit_sekolah_id' => $unit->id, 'tipe_presensi' => 'lembur', 'tanggal' => '2026-06-10', 'jam_masuk' => '16:00:00', 'jam_keluar' => '18:00:00']);
        $p5->is_lembur = true;
        $p5->lembur_status = 'disetujui';
        $p5->status = 'hadir';
        $p5->save();

        $this->actingAs($admin, 'web_admin')
            ->post(route('penggajian.run.init'), ['month' => '06', 'year' => '2026'])
            ->assertRedirect(route('penggajian.run.worksheet', ['month' => '06', 'year' => '2026']));

        // ── Perhitungan manual ──
        // attendance: hadir=2, telat=1, sakit=1
        // workingDays (Senin di Juni 2026) = 5
        // alpa = max(0, 5 - (2+1+1)) = 1
        //
        // gaji_pokok (fixed)          = 3.000.000
        // tunjangan_kehadiran         = 50.000 × 3 hari hadir/telat = 150.000
        // potongan_telat              = 10.000 × 1 = 10.000 (potongan)
        // potongan_alpa               = 20.000 × 1 = 20.000 (potongan)
        // jam_mengajar (hanya_hadir)  = 30.000 × (2 JP × 3 pertemuan hadir/telat) = 180.000
        // lembur                      = 25.000 × 2 jam = 50.000
        // masa_bakti (6 tahun ≥ skala 5) = 200.000
        // potongan_sakit (prorata 50%) = 10.000 × 1 hari × min(1, 50/100) = 5.000
        //
        // pendapatan = 3.000.000 + 150.000 + 180.000 + 50.000 + 200.000 = 3.580.000
        // potongan   = 10.000 + 20.000 + 5.000 = 35.000
        // gaji_bersih = 3.545.000
        // total_taxable (hanya gaji_pokok) = 3.000.000

        $penggajian = Penggajian::where('pegawai_id', $pegawai->id)
            ->where('periode_bulan', '06-2026')
            ->first();

        $this->assertNotNull($penggajian, 'Penggajian Juni 2026 harus dibuat.');
        $this->assertSame('draft', $penggajian->status);
        $this->assertSame(3580000.0, (float) $penggajian->total_pendapatan);
        $this->assertSame(35000.0, (float) $penggajian->total_potongan);
        $this->assertSame(3545000.0, (float) $penggajian->gaji_bersih);
        $this->assertSame(3000000.0, (float) $penggajian->total_taxable);

        $details = $penggajian->details()->pluck('nominal', 'nama_komponen');
        $this->assertSame(3000000.0, (float) $details['Gaji Pokok']);
        $this->assertSame(150000.0, (float) $details['Tunjangan Kehadiran']);
        $this->assertSame(10000.0, (float) $details['Potongan Telat']);
        $this->assertSame(20000.0, (float) $details['Potongan Alpa']);
        // Prorata sakit: 10.000 × 1 hari × 50% (persentase_bayar_jam=50) = 5.000
        $this->assertSame(5000.0, (float) $details['Potongan Sakit']);
        $this->assertSame(180000.0, (float) $details['Jam Mengajar']);
        $this->assertSame(50000.0, (float) $details['Lembur']);
        $this->assertSame(200000.0, (float) $details['Masa Bakti']);

        // Pastikan record presensi tetap utuh (tidak terpengaruh proses generate)
        $this->assertDatabaseHas('presensi', ['id' => $p1->id, 'pegawai_id' => $pegawai->id]);
    }

    public function test_create_draft_tetap_menghitung_pegawai_tanpa_presensi(): void
    {
        $admin = $this->makeSuperadmin();
        $unit = $this->makeUnit();
        $pegawai = $this->makePegawai($unit);

        KomponenGaji::query()->update(['is_active' => false]);
        KomponenGaji::create(['nama' => 'Gaji Pokok', 'kode' => 'gaji_pokok', 'tipe' => 'pendapatan', 'jenis' => 'fixed', 'nilai_default' => 2500000, 'is_taxable' => true, 'is_active' => true]);

        $this->actingAs($admin, 'web_admin')
            ->post(route('penggajian.run.init'), ['month' => '06', 'year' => '2026'])
            ->assertRedirect(route('penggajian.run.worksheet', ['month' => '06', 'year' => '2026']));

        $penggajian = Penggajian::where('pegawai_id', $pegawai->id)
            ->where('periode_bulan', '06-2026')
            ->first();

        $this->assertNotNull($penggajian);
        // Tanpa jadwal → workingDays=0, tanpa presensi → semua count=0.
        // Hanya gaji pokok fixed yang masuk.
        $this->assertSame(2500000.0, (float) $penggajian->total_pendapatan);
        $this->assertSame(0.0, (float) $penggajian->total_potongan);
        $this->assertSame(2500000.0, (float) $penggajian->gaji_bersih);
        $this->assertSame(1, $penggajian->details()->count());
    }
}
