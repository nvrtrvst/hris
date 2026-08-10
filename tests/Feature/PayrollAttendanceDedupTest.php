<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\KomponenGaji;
use App\Models\Pegawai;
use App\Models\Penggajian;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollAttendanceDedupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function makeAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'superadmin']);
        $admin->assignRole('superadmin');

        return $admin;
    }

    /**
     * Buat pegawai honorer dengan komponen tunjangan kehadiran
     * dan presensi: 2 record hadir di tanggal sama + 1 record hadir tanggal lain
     * + 1 record lembur disetujui (harus TIDAK dihitung).
     */
    private function seedPayrollScenario(): array
    {
        $unit = UnitSekolah::create([
            'nama' => 'SMP Test',
            'singkatan' => 'SMP',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'toleransi_menit' => 0,
        ]);

        $jabatan = Jabatan::create(['nama' => 'Guru']);

        $pegawai = Pegawai::create([
            'nik' => '7777666655554443',
            'nama_lengkap' => 'Guru Honorer Test',
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '1992-03-03',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'kawin',
            'jumlah_tanggungan' => 1,
            'alamat_ktp' => 'Jl. Payroll No. 1',
            'no_hp' => '081233344455',
            'status_kepegawaian' => 'honorer',
            'tanggal_mulai_kerja' => '2021-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
        ]);
        $pegawai->units()->attach($unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        $tunjangan = KomponenGaji::create([
            'nama' => 'Tunjangan Kehadiran',
            'kode' => 'tunjangan_kehadiran',
            'tipe' => 'pendapatan',
            'jenis' => 'dinamis_kehadiran',
            'nilai_default' => 20000,
            'unit_sekolah_id' => null,
            'is_taxable' => false,
            'is_active' => true,
            'urutan' => 10,
            'tampil_di_matrix' => true,
        ]);

        $potonganTelat = KomponenGaji::create([
            'nama' => 'Potongan Telat',
            'kode' => 'kehadiran_telat',
            'tipe' => 'potongan',
            'jenis' => 'dinamis_kehadiran',
            'nilai_default' => 10000,
            'unit_sekolah_id' => null,
            'is_taxable' => false,
            'is_active' => true,
            'urutan' => 20,
            'tampil_di_matrix' => true,
        ]);

        $potonganAlpa = KomponenGaji::create([
            'nama' => 'Potongan Alpa',
            'kode' => 'kehadiran_alpa',
            'tipe' => 'potongan',
            'jenis' => 'dinamis_kehadiran',
            'nilai_default' => 1000,
            'unit_sekolah_id' => null,
            'is_taxable' => false,
            'is_active' => true,
            'urutan' => 30,
            'tampil_di_matrix' => true,
        ]);

        // Nonaktifkan komponen default dari migrasi (Honor Mengajar dkk)
        // agar test fokus pada dedup tunjangan kehadiran.
        KomponenGaji::where('kode', 'honor_mengajar')->update(['is_active' => false]);

        // 3 jadwal mengajar reguler (utk FK presensi.jadwal_id)
        $jadwal1 = $pegawai->jadwals()->create([
            'unit_sekolah_id' => $unit->id,
            'hari' => 'Senin',
            'jam_mulai' => '07:30:00',
            'jam_selesai' => '08:15:00',
            'jenis_jadwal' => 'mengajar',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);
        $jadwal2 = $pegawai->jadwals()->create([
            'unit_sekolah_id' => $unit->id,
            'hari' => 'Senin',
            'jam_mulai' => '08:30:00',
            'jam_selesai' => '09:15:00',
            'jenis_jadwal' => 'mengajar',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);
        $jadwal3 = $pegawai->jadwals()->create([
            'unit_sekolah_id' => $unit->id,
            'hari' => 'Senin',
            'jam_mulai' => '09:30:00',
            'jam_selesai' => '10:15:00',
            'jenis_jadwal' => 'mengajar',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);

        // 2 record hadir di tanggal SAMA (2 jadwal berbeda) → 1 hari kalender
        $p1 = Presensi::create([
            'pegawai_id' => $pegawai->id,
            'jadwal_id' => $jadwal1->id,
            'unit_sekolah_id' => $unit->id,
            'tipe_presensi' => 'mengajar',
            'tanggal' => '2026-07-06',
            'jam_masuk' => '07:55:00',
        ]);
        $p1->status = 'hadir';
        $p1->save();

        $p2 = Presensi::create([
            'pegawai_id' => $pegawai->id,
            'jadwal_id' => $jadwal2->id,
            'unit_sekolah_id' => $unit->id,
            'tipe_presensi' => 'mengajar',
            'tanggal' => '2026-07-06',
            'jam_masuk' => '09:00:00',
        ]);
        $p2->status = 'hadir';
        $p2->save();

        // 1 record telat tanggal lain → hari kalender kedua
        $p3 = Presensi::create([
            'pegawai_id' => $pegawai->id,
            'jadwal_id' => $jadwal3->id,
            'unit_sekolah_id' => $unit->id,
            'tipe_presensi' => 'mengajar',
            'tanggal' => '2026-07-13',
            'jam_masuk' => '08:30:00',
        ]);
        $p3->status = 'telat';
        $p3->save();

        // Lembur disetujui — TIDAK boleh menambah hari kehadiran / count hadir
        $lembur = Presensi::create([
            'pegawai_id' => $pegawai->id,
            'jadwal_id' => null,
            'unit_sekolah_id' => $unit->id,
            'tipe_presensi' => 'lembur',
            'tanggal' => '2026-07-20',
            'jam_masuk' => '18:00:00',
            'jam_keluar' => '20:00:00',
        ]);
        $lembur->status = 'hadir';
        $lembur->is_lembur = true;
        $lembur->lembur_status = 'disetujui';
        $lembur->save();

        return compact('pegawai', 'unit', 'tunjangan', 'potonganTelat', 'potonganAlpa');
    }

    public function test_tunjangan_kehadiran_dibayar_per_hari_kalender(): void
    {
        $admin = $this->makeAdmin();
        $scenario = $this->seedPayrollScenario();

        $res = $this->actingAs($admin, 'web_admin')
            ->post(route('penggajian.run.init'), [
                'month' => '07',
                'year' => '2026',
            ]);

        $res->assertRedirect(route('penggajian.run.worksheet', ['month' => '07', 'year' => '2026']));

        $penggajian = Penggajian::where('pegawai_id', $scenario['pegawai']->id)
            ->where('periode_bulan', '07-2026')
            ->firstOrFail();

        // 2 hari kalender (06 & 13) × 20000 = 40000 — BUKAN 3 record × 20000
        $tunjanganDetail = $penggajian->details()->where('nama_komponen', 'Tunjangan Kehadiran')->first();
        $this->assertSame(40000.0, (float) $tunjanganDetail->nominal);

        // 1 telat × 10000 = 10000 (lembur tidak menambah)
        $telatDetail = $penggajian->details()->where('nama_komponen', 'Potongan Telat')->first();
        $this->assertSame(10000.0, (float) $telatDetail->nominal);

        // Alpa: 3 jadwal Senin × 4 Senin (Juli 2026: 6,13,20,27) = 12 hari kerja.
        // hadir 2 + telat 1 = 3 terisi → alpa = 12 - 3 = 9.
        // Jika record lembur ikut dihitung hadir, alpa jadi 8 — assertion ini
        // mengunci filter is_lembur=false di attendanceRaw (bukan hanya presentDays).
        $alpaDetail = $penggajian->details()->where('nama_komponen', 'Potongan Alpa')->first();
        $this->assertSame(9000.0, (float) $alpaDetail->nominal);

        // Verifikasi lembur tidak dihitung sebagai hadir: total pendapatan hanya tunjangan
        $this->assertSame(40000.0, (float) $penggajian->total_pendapatan);
    }

    public function test_regenerate_draft_menimpa_draft_lama(): void
    {
        $admin = $this->makeAdmin();
        $scenario = $this->seedPayrollScenario();

        $this->actingAs($admin, 'web_admin')->post(route('penggajian.run.init'), [
            'month' => '07',
            'year' => '2026',
        ]);

        $this->actingAs($admin, 'web_admin')->post(route('penggajian.run.init'), [
            'month' => '07',
            'year' => '2026',
        ]);

        $count = Penggajian::where('pegawai_id', $scenario['pegawai']->id)
            ->where('periode_bulan', '07-2026')
            ->count();
        $this->assertSame(1, $count, 'Draft lama harus ditimpa, bukan dobel.');
    }

    public function test_pegawai_nonaktif_tidak_digenerate(): void
    {
        $admin = $this->makeAdmin();
        $scenario = $this->seedPayrollScenario();
        $scenario['pegawai']->update(['status_aktif' => 'nonaktif']);

        $this->actingAs($admin, 'web_admin')->post(route('penggajian.run.init'), [
            'month' => '07',
            'year' => '2026',
        ]);

        $this->assertDatabaseMissing('penggajian', [
            'pegawai_id' => $scenario['pegawai']->id,
            'periode_bulan' => '07-2026',
        ]);
    }

    public function test_generate_payroll_tanpa_presensi_tetap_berjalan(): void
    {
        $admin = $this->makeAdmin();
        $unit = UnitSekolah::create([
            'nama' => 'SD Test',
            'singkatan' => 'SD',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'toleransi_menit' => 0,
        ]);
        $jabatan = Jabatan::create(['nama' => 'Guru']);

        $pegawai = Pegawai::create([
            'nik' => '1111222233334444',
            'nama_lengkap' => 'Guru Baru',
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1998-08-08',
            'jenis_kelamin' => 'P',
            'agama' => 'Islam',
            'status_pernikahan' => 'belum kawin',
            'jumlah_tanggungan' => 0,
            'alamat_ktp' => 'Jl. Baru No. 1',
            'no_hp' => '081255566677',
            'status_kepegawaian' => 'honorer',
            'tanggal_mulai_kerja' => '2025-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
        ]);
        $pegawai->units()->attach($unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        $this->actingAs($admin, 'web_admin')
            ->post(route('penggajian.run.init'), ['month' => '07', 'year' => '2026'])
            ->assertRedirect(route('penggajian.run.worksheet', ['month' => '07', 'year' => '2026']));

        // Draft tetap dibuat walau tanpa presensi (tanpa detail kehadiran)
        $this->assertDatabaseHas('penggajian', [
            'pegawai_id' => $pegawai->id,
            'periode_bulan' => '07-2026',
            'status' => 'draft',
        ]);
    }
}
