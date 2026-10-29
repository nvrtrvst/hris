<?php

namespace Tests\Feature;

use App\Models\AbsenSiswaDetail;
use App\Models\AbsenSiswaSesi;
use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\PegawaiKelasBinaan;
use App\Models\PiketSesi;
use App\Models\PiketSiswaTerlambat;
use App\Models\UnitSekolah;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AbsenSiswaReportTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->unit = UnitSekolah::create([
            'nama' => 'SMP Laporan',
            'singkatan' => 'SMPL',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'toleransi_menit' => 0,
        ]);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('superadmin');
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
        ]);
        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    private function sesi(string $class, string $tanggal, array $siswa, ?Pegawai $petugas = null): AbsenSiswaSesi
    {
        $petugas ??= $this->pegawai('Petugas '.$class);

        $sesi = AbsenSiswaSesi::create([
            'unit_sekolah_id' => $this->unit->id,
            'pegawai_id' => $petugas->id,
            'tanggal' => $tanggal,
            'class_id' => $class,
            'class_label' => $class,
            'done_at' => now(),
        ]);

        foreach ($siswa as $row) {
            AbsenSiswaDetail::create([
                'absen_siswa_sesi_id' => $sesi->id,
                'nis' => $row['nis'],
                'nama_snapshot' => $row['nama'],
                'status' => $row['status'],
                'keterangan' => $row['keterangan'] ?? null,
            ]);
        }

        return $sesi;
    }

    public function test_laporan_harian_menampilkan_siswa_tidak_hadir(): void
    {
        $tanggal = now()->toDateString();
        $this->sesi('X A', $tanggal, [
            ['nis' => '001', 'nama' => 'Siswa Satu', 'status' => 'hadir'],
            ['nis' => '002', 'nama' => 'Siswa Dua', 'status' => 'telat'],
            ['nis' => '003', 'nama' => 'Siswa Tiga', 'status' => 'alpa'],
        ]);

        $this->actingAs($this->admin, 'web_admin')
            ->get(route('laporan.absen-siswa', ['mode' => 'daily']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Laporan/AbsenSiswa')
                ->where('summary.siswa', 3)
                ->where('summary.hadir', 1)
                ->where('summary.telat', 1)
                ->where('summary.alpa', 1)
                ->has('sessions', 1)
                ->has('sessions.0.non_hadir', 2)
            );
    }

    public function test_keterlambatan_gerbang_dihitung_terpisah_dari_status_kelas(): void
    {
        $tanggal = now()->toDateString();
        $this->sesi('X A', $tanggal, [
            ['nis' => '001', 'nama' => 'Siswa Satu', 'status' => 'hadir'],
        ]);

        $piketSesi = PiketSesi::create([
            'unit_sekolah_id' => $this->unit->id,
            'tanggal' => $tanggal,
            'jam_mulai' => '07:00',
            'jam_selesai' => '15:00',
            'status' => 'open',
        ]);
        PiketSiswaTerlambat::create([
            'unit_sekolah_id' => $this->unit->id,
            'piket_sesi_id' => $piketSesi->id,
            'tanggal' => $tanggal,
            'class_id' => 'A',
            'class_label' => 'X A',
            'nis' => '001',
            'nama_snapshot' => 'Siswa Satu',
            'jam_terlambat' => '07:15:00',
        ]);

        $this->actingAs($this->admin, 'web_admin')
            ->get(route('laporan.absen-siswa', ['mode' => 'daily']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.hadir', 1)
                ->where('summary.telat', 0)
                ->where('summary.terlambat_gerbang', 1)
            );
    }

    public function test_laporan_periode_menghitung_sesi_dan_persentase(): void
    {
        $this->sesi('X A', '2026-10-01', [
            ['nis' => '001', 'nama' => 'Siswa Satu', 'status' => 'hadir'],
            ['nis' => '002', 'nama' => 'Siswa Dua', 'status' => 'telat'],
        ]);
        $this->sesi('X A', '2026-10-02', [
            ['nis' => '001', 'nama' => 'Siswa Satu', 'status' => 'alpa'],
            ['nis' => '002', 'nama' => 'Siswa Dua', 'status' => 'hadir'],
        ]);

        $this->actingAs($this->admin, 'web_admin')
            ->get(route('laporan.absen-siswa', [
                'mode' => 'period',
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-31',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.sesi', 2)
                ->where('summary.siswa', 2)
                ->where('students.0.nis', '001')
                ->where('students.0.sesi_tercatat', 2)
                ->where('students.0.hadir', 1)
                ->where('students.0.alpa', 1)
                ->where('students.0.persen', 50)
            );
    }

    public function test_wali_kelas_hanya_melihat_kelas_binaan(): void
    {
        $wali = $this->pegawai('Wali Kelas');
        $wali->user->assignRole('wali_kelas');
        PegawaiKelasBinaan::create([
            'pegawai_id' => $wali->id,
            'unit_sekolah_id' => $this->unit->id,
            'class_id' => 'A',
            'class_label' => 'X A',
            'tahun_ajaran' => '2026/2027',
            'semester' => '1',
            'aktif' => true,
        ]);

        $this->sesi('X A', now()->toDateString(), [['nis' => '001', 'nama' => 'Satu', 'status' => 'hadir']]);
        $this->sesi('X B', now()->toDateString(), [['nis' => '002', 'nama' => 'Dua', 'status' => 'alpa']]);

        $this->actingAs($wali->user, 'web_admin')
            ->get(route('laporan.absen-siswa', ['mode' => 'daily']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('sessions', 1)
                ->where('sessions.0.class_label', 'X A')
                ->where('classes.0', 'X A')
            );
    }

    public function test_detail_siswa_menampilkan_baris_harian(): void
    {
        $this->sesi('X A', '2026-10-01', [['nis' => '001', 'nama' => 'Siswa Satu', 'status' => 'hadir']]);
        $this->sesi('X A', '2026-10-02', [['nis' => '001', 'nama' => 'Siswa Satu', 'status' => 'sakit', 'keterangan' => 'Demam']]);

        $this->actingAs($this->admin, 'web_admin')
            ->getJson(route('laporan.absen-siswa.detail', [
                'nis' => '001',
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-02',
            ]))
            ->assertOk()
            ->assertJsonCount(2, 'rows')
            ->assertJsonPath('rows.1.status', 'sakit')
            ->assertJsonPath('rows.1.keterangan', 'Demam');
    }

    public function test_export_excel_dan_pdf(): void
    {
        $this->sesi('X A', now()->toDateString(), [
            ['nis' => '001', 'nama' => 'Siswa Satu', 'status' => 'alpa'],
        ]);

        $this->actingAs($this->admin, 'web_admin')
            ->get(route('laporan.absen-siswa.export', ['mode' => 'daily']))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($this->admin, 'web_admin')
            ->get(route('laporan.absen-siswa.pdf', ['mode' => 'daily']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_mapping_wali_kelas_menyimpan_dan_menonaktifkan(): void
    {
        $wali = $this->pegawai('Wali Kelas');

        $this->actingAs($this->admin, 'web_admin')
            ->post(route('wali-kelas.store'), [
                'unit_sekolah_id' => $this->unit->id,
                'pegawai_id' => $wali->id,
                'class_id' => 'A',
                'class_label' => 'X A',
                'tahun_ajaran' => '2026/2027',
                'semester' => '1',
            ])
            ->assertRedirect();

        $mapping = PegawaiKelasBinaan::firstOrFail();
        $this->assertTrue($mapping->aktif);
        $this->assertSame($wali->id, $mapping->pegawai_id);

        $this->actingAs($this->admin, 'web_admin')
            ->delete(route('wali-kelas.nonaktifkan', $mapping->id))
            ->assertRedirect();

        $this->assertFalse($mapping->fresh()->aktif);
    }
}
