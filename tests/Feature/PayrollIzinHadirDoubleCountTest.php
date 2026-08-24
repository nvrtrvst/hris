<?php

namespace Tests\Feature;

use App\Models\HariLibur;
use App\Models\Jabatan;
use App\Models\Jadwal;
use App\Models\KomponenGaji;
use App\Models\Pegawai;
use App\Models\Penggajian;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression guard untuk fix anti-deadlock (presensi_key + unique index):
 * record izin ter-approve dibuat via generatePresensi dengan jadwal_id NULL →
 * presensi_key NULL. Saat pegawai absen real di tanggal yang sama, record kedua
 * dibuat (presensi_key 'M{tanggal}-{jadwal_id}'). Kedua record tidak boleh
 * double-count di payroll (hadir + izin di hari yang sama = salah).
 *
 * Sebelum fix anti-deadlock, lookup absen by (pegawai_id, tanggal) menimpa
 * record izin → hanya 1 record. Setelah fix, lookup by presensi_key tidak
 * menemukan record izin → 2 record. Aggregator harus membuang record pasif
 * (izin/sakit/cuti/alpa) saat tanggal yang sama punya kehadiran fisik.
 */
class PayrollIzinHadirDoubleCountTest extends TestCase
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
            'nama' => 'SMP Izin Hadir Test',
            'singkatan' => 'SMPIH',
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
            'nik' => '8899776655443322',
            'nama_lengkap' => 'Guru Izin Hadir Test',
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'kawin',
            'jumlah_tanggungan' => 2,
            'alamat_ktp' => 'Jl. Test No. 2',
            'no_hp' => '081299887766',
            'status_kepegawaian' => 'tetap',
            'tanggal_mulai_kerja' => '2020-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
        ]);
        $pegawai->units()->attach($unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    public function test_izin_dan_absen_real_di_tanggal_sama_tidak_double_count(): void
    {
        $admin = $this->makeSuperadmin();
        $unit = $this->makeUnit();
        $pegawai = $this->makePegawai($unit);

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

        KomponenGaji::query()->update(['is_active' => false]);
        KomponenGaji::create(['nama' => 'Gaji Pokok', 'kode' => 'gaji_pokok', 'tipe' => 'pendapatan', 'jenis' => 'fixed', 'nilai_default' => 2000000, 'is_taxable' => true, 'is_active' => true]);
        KomponenGaji::create(['nama' => 'Tunjangan Kehadiran', 'kode' => 'tunjangan_kehadiran', 'tipe' => 'pendapatan', 'jenis' => 'dinamis_kehadiran', 'nilai_default' => 50000, 'is_taxable' => false, 'is_active' => true]);
        KomponenGaji::create(['nama' => 'Potongan Izin', 'kode' => 'kehadiran_izin', 'tipe' => 'potongan', 'jenis' => 'dinamis_kehadiran', 'nilai_default' => 25000, 'is_taxable' => false, 'is_active' => true]);

        // 1 Juni 2026 = Senin. Pegawai punya record izin (seperti generatePresensi:
        // jadwal_id NULL, tipe default 'mengajar' → presensi_key NULL) DI TANGGAL SAMA
        // dengan absen real (jadwal terisi → presensi_key 'M2026-06-01-{id}').
        $izin = Presensi::create([
            'pegawai_id' => $pegawai->id,
            'jadwal_id' => null,
            'unit_sekolah_id' => $unit->id,
            'tanggal' => '2026-06-01',
            'tipe_presensi' => 'mengajar',
        ]);
        $izin->status = 'izin';
        $izin->save();

        $hadir = Presensi::create([
            'pegawai_id' => $pegawai->id,
            'jadwal_id' => $jadwal->id,
            'unit_sekolah_id' => $unit->id,
            'tanggal' => '2026-06-01',
            'jam_masuk' => '07:00:00',
            'tipe_presensi' => 'mengajar',
        ]);
        $hadir->status = 'hadir';
        $hadir->save();

        // Pastikan skenario benar-benar menghasilkan 2 record di tanggal sama
        $this->assertSame(2, Presensi::where('pegawai_id', $pegawai->id)->where('tanggal', '2026-06-01')->count());

        $this->actingAs($admin, 'web_admin')
            ->post(route('penggajian.run.init'), ['month' => '06', 'year' => '2026'])
            ->assertRedirect(route('penggajian.run.worksheet', ['month' => '06', 'year' => '2026']));

        $penggajian = Penggajian::where('pegawai_id', $pegawai->id)
            ->where('periode_bulan', '06-2026')
            ->first();

        $this->assertNotNull($penggajian, 'Penggajian Juni 2026 harus dibuat.');

        $details = $penggajian->details()->pluck('nominal', 'nama_komponen');

        // Kehadiran fisik menang: hari itu dihitung HADIR (1x tunjangan), TIDAK kena potongan izin.
        $this->assertSame(50000.0, (float) $details['Tunjangan Kehadiran'], 'Tunjangan kehadiran harus 1 hari (hadir).');
        $this->assertSame(0.0, (float) ($details['Potongan Izin'] ?? 0.0), 'Tidak boleh kena potongan izin saat hadir di tanggal sama.');
    }

    public function test_alpa_record_tidak_double_count(): void
    {
        $admin = $this->makeSuperadmin();
        $unit = $this->makeUnit();
        $pegawai = $this->makePegawai($unit);

        Jadwal::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $unit->id,
            'hari' => 'Senin',
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '08:30:00',
            'jenis_jadwal' => 'reguler',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);

        KomponenGaji::query()->update(['is_active' => false]);
        KomponenGaji::create(['nama' => 'Gaji Pokok', 'kode' => 'gaji_pokok', 'tipe' => 'pendapatan', 'jenis' => 'fixed', 'nilai_default' => 2000000, 'is_taxable' => true, 'is_active' => true]);
        KomponenGaji::create(['nama' => 'Potongan Alpa', 'kode' => 'kehadiran_alpa', 'tipe' => 'potongan', 'jenis' => 'dinamis_kehadiran', 'nilai_default' => 30000, 'is_taxable' => false, 'is_active' => true]);

        // 1 Juni 2026 = Senin. 1 record alpa (hasil FinalizeAlpa).
        Presensi::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $unit->id,
            'tanggal' => '2026-06-01',
            'status' => 'alpa',
            'keterangan' => 'Auto-mark alpa',
        ]);

        $this->actingAs($admin, 'web_admin')
            ->post(route('penggajian.run.init'), ['month' => '06', 'year' => '2026'])
            ->assertRedirect(route('penggajian.run.worksheet', ['month' => '06', 'year' => '2026']));

        $penggajian = Penggajian::where('pegawai_id', $pegawai->id)
            ->where('periode_bulan', '06-2026')
            ->first();

        $this->assertNotNull($penggajian, 'Penggajian Juni 2026 harus dibuat.');

        $details = $penggajian->details()->pluck('nominal', 'nama_komponen');

        // Juni 2026 punya 5 Senin (working days = 5). 1 di antaranya sudah record alpa,
        // 4 sisanya no-show (gap). Total alpa = 5, BUKAN 6 (double-count lama: record+gab).
        // Rate 30000 × 5 = 150000.
        $this->assertSame(150000.0, (float) ($details['Potongan Alpa'] ?? 0.0), 'Alpa tidak boleh double-count (harus 5 hari × 30000).');
    }

    public function test_hari_libur_mengurangi_hari_kerja_alpa(): void
    {
        $admin = $this->makeSuperadmin();
        $unit = $this->makeUnit();
        $pegawai = $this->makePegawai($unit);

        Jadwal::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $unit->id,
            'hari' => 'Senin',
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '08:30:00',
            'jenis_jadwal' => 'reguler',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);

        KomponenGaji::query()->update(['is_active' => false]);
        KomponenGaji::create(['nama' => 'Gaji Pokok', 'kode' => 'gaji_pokok', 'tipe' => 'pendapatan', 'jenis' => 'fixed', 'nilai_default' => 2000000, 'is_taxable' => true, 'is_active' => true]);
        KomponenGaji::create(['nama' => 'Potongan Alpa', 'kode' => 'kehadiran_alpa', 'tipe' => 'potongan', 'jenis' => 'dinamis_kehadiran', 'nilai_default' => 30000, 'is_taxable' => false, 'is_active' => true]);

        // 1 Juni 2026 = Senin = hari libur nasional (Hari Lahir Pancasila).
        // Pegawai TIDAK absen sama sekali → hari itu harus dikecualikan dari working days.
        HariLibur::create([
            'tanggal' => '2026-06-01',
            'nama' => 'Hari Lahir Pancasila',
            'tipe' => 'nasional',
            'unit_sekolah_id' => null,
        ]);

        $this->actingAs($admin, 'web_admin')
            ->post(route('penggajian.run.init'), ['month' => '06', 'year' => '2026'])
            ->assertRedirect(route('penggajian.run.worksheet', ['month' => '06', 'year' => '2026']));

        $penggajian = Penggajian::where('pegawai_id', $pegawai->id)
            ->where('periode_bulan', '06-2026')
            ->first();

        $this->assertNotNull($penggajian, 'Penggajian Juni 2026 harus dibuat.');

        $details = $penggajian->details()->pluck('nominal', 'nama_komponen');

        // Juni 2026 punya 5 Senin, tapi 1 Juni libur → working days = 4.
        // Tanpa presensi: 4 hari no-show = 4 alpa. Rate 30000 × 4 = 120000.
        // (Sebelum fix: working days 5 → alpa 5 × 30000 = 150000, salah.)
        $this->assertSame(120000.0, (float) ($details['Potongan Alpa'] ?? 0.0), 'Hari libur harus mengurangi working days (4 hari × 30000).');
    }
}
