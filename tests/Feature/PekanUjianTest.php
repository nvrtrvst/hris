<?php

namespace Tests\Feature;

use App\Exports\LaporanPresensiExport;
use App\Models\Jabatan;
use App\Models\Jadwal;
use App\Models\KomponenGaji;
use App\Models\Pegawai;
use App\Models\PekanUjian;
use App\Models\Penggajian;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PekanUjianTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private Pegawai $guru;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->unit = UnitSekolah::create([
            'nama' => 'SMP Pekan UAS',
            'singkatan' => 'SMPPU',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
        ]);
        $this->guru = $this->makePegawai('Guru UAS');
    }

    public function test_jadwal_efektif_menyembunyikan_mengajar_dan_menampilkan_pengawas(): void
    {
        $pekan = PekanUjian::create([
            'unit_sekolah_id' => $this->unit->id,
            'nama' => 'UAS Ganjil',
            'tanggal_mulai' => '2026-11-02',
            'tanggal_selesai' => '2026-11-06',
        ]);

        $mengajar = $this->jadwal('Senin', '07:00:00', '07:45:00', 'mengajar');
        $pengawas = $this->jadwal('Senin', '07:00:00', '08:30:00', 'pengawas_ujian', $pekan->id);
        $piket = $this->jadwal('Senin', '09:00:00', '10:00:00', 'piket');

        $dalamPeriode = Jadwal::where('pegawai_id', $this->guru->id)->efektifPada('2026-11-02')->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$pengawas->id, $piket->id], $dalamPeriode);

        $luarPeriode = Jadwal::where('pegawai_id', $this->guru->id)->efektifPada('2026-11-09')->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$mengajar->id, $piket->id], $luarPeriode);

        $efektifPegawai = $this->guru->jadwalsEfektifPada('2026-11-03');
        $this->assertTrue($efektifPegawai->contains('id', $pengawas->id));
        $this->assertTrue($efektifPegawai->contains('id', $piket->id));
        $this->assertFalse($efektifPegawai->contains('id', $mengajar->id));
    }

    public function test_mobile_dashboard_jadwal_dan_absen_memakai_jadwal_efektif(): void
    {
        Carbon::setTestNow('2026-11-02 07:00:00');
        Cache::flush();

        $pekan = PekanUjian::create([
            'unit_sekolah_id' => $this->unit->id,
            'nama' => 'UAS Ganjil',
            'tanggal_mulai' => '2026-11-02',
            'tanggal_selesai' => '2026-11-06',
        ]);

        $this->jadwal('Senin', '07:00:00', '07:45:00', 'mengajar');
        $pengawas = $this->jadwal('Senin', '07:00:00', '08:30:00', 'pengawas_ujian', $pekan->id);

        $this->actingAs($this->guru->user, 'web_mobile')
            ->get(route('presensi.dashboard'))
            ->assertInertia(fn ($page) => $page
                ->component('Mobile/Dashboard')
                ->where('jadwalsHariIni', fn ($rows) => collect($rows)->pluck('id')->all() === [$pengawas->id]));

        $this->actingAs($this->guru->user, 'web_mobile')
            ->get(route('presensi.jadwal'))
            ->assertInertia(fn ($page) => $page
                ->component('Mobile/Jadwal')
                ->where('jadwalPerHari.Senin', fn ($rows) => collect($rows)->pluck('id')->all() === [$pengawas->id]));

        $this->actingAs($this->guru->user, 'web_mobile')
            ->get(route('presensi.absen'))
            ->assertInertia(fn ($page) => $page
                ->component('Mobile/Absen')
                ->where('jadwals', fn ($rows) => collect($rows)->pluck('id')->all() === [$pengawas->id]));

        Carbon::setTestNow();
    }

    public function test_finalize_alpa_hanya_menandai_pengawas_di_pekan_ujian(): void
    {
        $pekan = PekanUjian::create([
            'unit_sekolah_id' => $this->unit->id,
            'nama' => 'UAS Ganjil',
            'tanggal_mulai' => '2026-11-02',
            'tanggal_selesai' => '2026-11-06',
        ]);

        $mengajar = $this->jadwal('Kamis', '07:00:00', '07:45:00', 'mengajar');
        $pengawas = $this->jadwal('Kamis', '07:00:00', '08:30:00', 'pengawas_ujian', $pekan->id);

        $this->artisan('presensi:finalize-alpa', ['--date' => '2026-11-05'])->assertSuccessful();

        $this->assertFalse(Presensi::where('jadwal_id', $mengajar->id)->exists());
        $this->assertDatabaseHas('presensi', [
            'pegawai_id' => $this->guru->id,
            'jadwal_id' => $pengawas->id,
            'status' => 'alpa',
            'tanggal' => '2026-11-05',
        ]);
    }

    public function test_honor_pengawasan_dihitung_dari_sesi_hadir(): void
    {
        $admin = User::factory()->create(['role' => 'superadmin']);
        $admin->assignRole('superadmin');

        $pekan = PekanUjian::create([
            'unit_sekolah_id' => $this->unit->id,
            'nama' => 'UAS Ganjil',
            'tanggal_mulai' => '2026-11-02',
            'tanggal_selesai' => '2026-11-06',
        ]);

        $pengawas = $this->jadwal('Senin', '07:00:00', '08:30:00', 'pengawas_ujian', $pekan->id);

        $komponen = KomponenGaji::create([
            'kode' => 'honor_pengawas_ujian',
            'nama' => 'Honor Pengawas UAS',
            'jenis' => 'dinamis_pengawasan',
            'tipe' => 'pendapatan',
            'nilai_default' => 50000,
            'unit_sekolah_id' => $this->unit->id,
            'is_active' => true,
            'is_taxable' => false,
            'urutan' => 10,
            'tampil_di_matrix' => true,
        ]);

        $presensi = Presensi::create([
            'pegawai_id' => $this->guru->id,
            'jadwal_id' => $pengawas->id,
            'unit_sekolah_id' => $this->unit->id,
            'tanggal' => '2026-11-02',
            'jam_masuk' => '07:00:00',
            'tipe_presensi' => 'mengajar',
        ]);
        $presensi->status = 'hadir';
        $presensi->save();

        $this->actingAs($admin, 'web_admin')
            ->post(route('penggajian.run.init'), ['month' => '11', 'year' => '2026'])
            ->assertRedirect(route('penggajian.run.worksheet', ['month' => '11', 'year' => '2026']));

        $penggajian = Penggajian::where('pegawai_id', $this->guru->id)
            ->where('periode_bulan', '11-2026')
            ->firstOrFail();
        $detail = $penggajian->details()->where('komponen_gaji_id', $komponen->id)->firstOrFail();

        $this->assertSame(50000.0, (float) $detail->nominal);
    }

    public function test_laporan_presensi_melabeli_pengawas_ujian(): void
    {
        $pekan = PekanUjian::create([
            'unit_sekolah_id' => $this->unit->id,
            'nama' => 'UAS Ganjil',
            'tanggal_mulai' => '2026-11-02',
            'tanggal_selesai' => '2026-11-06',
        ]);

        $pengawas = $this->jadwal('Senin', '12:00:00', '13:30:00', 'pengawas_ujian', $pekan->id);

        $presensi = Presensi::create([
            'pegawai_id' => $this->guru->id,
            'jadwal_id' => $pengawas->id,
            'unit_sekolah_id' => $this->unit->id,
            'tanggal' => '2026-11-02',
            'jam_masuk' => '12:38:19',
            'tipe_presensi' => 'mengajar',
        ]);
        $presensi->status = 'hadir';
        $presensi->save();

        $export = new LaporanPresensiExport(
            '2026-11-02', '2026-11-02',
            null, null, null, null, null, 'pengawas_ujian'
        );

        $heads = $export->headings();
        $this->assertContains('Kelas / Ruang', $heads);

        $row = $export->map($export->collection()->firstOrFail());
        $this->assertSame('Pengawasan', $row[4]);
        $this->assertSame('Pengawas UAS', $row[5]);
        $this->assertSame('Ruang 1', $row[7]);
    }

    public function test_pekan_ujian_menolak_rentang_yang_overlap(): void
    {
        $admin = User::factory()->create(['role' => 'superadmin']);
        $admin->assignRole('superadmin');

        PekanUjian::create([
            'unit_sekolah_id' => $this->unit->id,
            'nama' => 'UAS Pertama',
            'tanggal_mulai' => '2027-01-04',
            'tanggal_selesai' => '2027-01-08',
        ]);

        $this->actingAs($admin)
            ->post(route('pekan-ujian.store'), [
                'unit_sekolah_id' => $this->unit->id,
                'nama' => 'UAS Bentrok',
                'tanggal_mulai' => '2027-01-07',
                'tanggal_selesai' => '2027-01-12',
            ])
            ->assertSessionHasErrors('tanggal_mulai');

        $this->actingAs($admin)
            ->post(route('pekan-ujian.store'), [
                'unit_sekolah_id' => $this->unit->id,
                'nama' => 'UAS Berikutnya',
                'tanggal_mulai' => '2027-01-11',
                'tanggal_selesai' => '2027-01-15',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pekan_ujian', ['nama' => 'UAS Berikutnya']);
    }

    private function jadwal(string $hari, string $mulai, string $selesai, string $jenis, ?int $pekanUjianId = null): Jadwal
    {
        return Jadwal::create([
            'pegawai_id' => $this->guru->id,
            'unit_sekolah_id' => $this->unit->id,
            'kelas_label' => $jenis === 'pengawas_ujian' ? 'Ruang 1' : '7A',
            'hari' => $hari,
            'jam_mulai' => $mulai,
            'jam_selesai' => $selesai,
            'jenis_jadwal' => $jenis,
            'pekan_ujian_id' => $pekanUjianId,
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);
    }

    private function makePegawai(string $nama): Pegawai
    {
        $user = User::factory()->create();
        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '1234567890'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT),
            'nama_lengkap' => $nama,
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'kawin',
            'jumlah_tanggungan' => 0,
            'alamat' => 'Jl. Test No. 1',
            'no_hp' => '081234567890',
            'status_kepegawaian' => 'guru_tetap_yayasan',
            'tmt_mengajar' => '2020-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
            'wajib_kantor' => true,
        ]);

        $jabatan = Jabatan::create(['nama' => 'Guru '.$nama, 'is_guru' => true]);
        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }
}
