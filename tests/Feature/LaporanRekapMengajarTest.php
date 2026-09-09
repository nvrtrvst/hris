<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Jadwal;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\PegawaiMapel;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaporanRekapMengajarTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->unit = UnitSekolah::create([
            'nama' => 'SMP Test',
            'singkatan' => 'SMP',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
        ]);

        $this->superadmin = User::factory()->create();
        $this->superadmin->syncRoles('superadmin');
    }

    private function makeGuru(string $nama): Pegawai
    {
        $jabatan = Jabatan::create(['nama' => 'Guru', 'is_guru' => true]);
        $user = User::factory()->create();
        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '1234567890'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT),
            'nama_lengkap' => $nama,
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'Belum Menikah',
            'alamat' => 'Jl. Test No. 1',
            'no_hp' => '081234567890',
            'status_kepegawaian' => 'guru_tetap_yayasan',
            'tmt_mengajar' => '2020-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
            'wajib_kantor' => true,
        ]);
        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    private function makeJadwal(Pegawai $pegawai, PegawaiMapel $pm, string $hari, string $mulai, string $selesai): Jadwal
    {
        return Jadwal::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'pegawai_mapel_id' => $pm->id,
            'kelas_label' => '7-A',
            'hari' => $hari,
            'jam_mulai' => $mulai,
            'jam_selesai' => $selesai,
            'jenis_jadwal' => 'mengajar',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);
    }

    private function makePresensiMengajar(Pegawai $pegawai, Jadwal $jadwal, string $tanggal, string $status, ?string $jamMasuk = null): Presensi
    {
        // `status` di $guarded model — set setelah create (pola FinalizeAlpa).
        $presensi = Presensi::create([
            'pegawai_id' => $pegawai->id,
            'jadwal_id' => $jadwal->id,
            'unit_sekolah_id' => $this->unit->id,
            'tipe_presensi' => 'mengajar',
            'tanggal' => $tanggal,
            'jam_masuk' => $jamMasuk ?? ($status === 'hadir' ? '07:55:00' : null),
        ]);
        $presensi->status = $status;
        $presensi->save();

        return $presensi;
    }

    public function test_rekap_per_guru_dengan_breakdown_mingguan(): void
    {
        // 2 minggu: Senin 31/8 + Senin 7/9 (periode 31/8 - 9/9).
        // Guru A: minggu 1 = 2 JP hadir; minggu 2 = 1 hadir + 1 telat.
        $guru = $this->makeGuru('Guru A');
        $mapel = MataPelajaran::create(['nama' => 'Matematika']);
        $pm = PegawaiMapel::create([
            'pegawai_id' => $guru->id,
            'mata_pelajaran_id' => $mapel->id,
            'unit_sekolah_id' => $this->unit->id,
        ]);

        $senin1 = Carbon::parse('2026-08-31'); // Senin
        $senin2 = Carbon::parse('2026-09-07'); // Senin

        $j1 = $this->makeJadwal($guru, $pm, 'Senin', '08:00:00', '08:45:00');
        $j2 = $this->makeJadwal($guru, $pm, 'Senin', '08:45:00', '09:30:00');

        $this->makePresensiMengajar($guru, $j1, $senin1->toDateString(), 'hadir');
        $this->makePresensiMengajar($guru, $j2, $senin1->toDateString(), 'hadir');
        $this->makePresensiMengajar($guru, $j1, $senin2->toDateString(), 'hadir');
        $this->makePresensiMengajar($guru, $j2, $senin2->toDateString(), 'telat', '08:50:00');

        $res = $this->actingAs($this->superadmin)
            ->getJson('/laporan/preview?'.http_build_query([
                'type' => 'rekap_mengajar',
                'start_date' => '2026-08-31',
                'end_date' => '2026-09-09',
            ]))
            ->assertOk();

        $headings = $res->json('headings');
        $row = $res->json('data.0');

        // Headings: 9 kolom dasar + 2 kolom minggu.
        $this->assertCount(11, $headings);
        $this->assertSame('Nama Guru', $headings[0]);
        $this->assertSame('JP Terjadwal', $headings[4]);
        $this->assertStringstartsWith('M', $headings[9]);

        // Row guru A: 4 terjadwal, 3 hadir, 1 telat, 0 alpa, 100% (hadir+telat).
        $this->assertSame('Guru A', $row[0]);
        $this->assertSame(4, $row[4]);
        $this->assertSame(3, $row[5]);
        $this->assertSame(1, $row[6]);
        $this->assertSame(0, $row[7]);
        $this->assertSame('100%', $row[8]);

        // Minggu 1: 2/2 (telat 0); minggu 2: 2/2 (telat 1).
        $this->assertSame('2/2 (telat 0)', $row[9]);
        $this->assertSame('2/2 (telat 1)', $row[10]);
    }

    public function test_rekap_exclude_presensi_kantor(): void
    {
        $guru = $this->makeGuru('Guru B');
        $mapel = MataPelajaran::create(['nama' => 'IPA']);
        $pm = PegawaiMapel::create([
            'pegawai_id' => $guru->id,
            'mata_pelajaran_id' => $mapel->id,
            'unit_sekolah_id' => $this->unit->id,
        ]);
        $j1 = $this->makeJadwal($guru, $pm, 'Senin', '08:00:00', '08:45:00');
        $this->makePresensiMengajar($guru, $j1, '2026-09-07', 'hadir');

        // Presensi kantor — tidak boleh masuk rekap mengajar.
        Presensi::create([
            'pegawai_id' => $guru->id,
            'jadwal_id' => null,
            'unit_sekolah_id' => $this->unit->id,
            'tipe_presensi' => 'kantor',
            'tanggal' => '2026-09-07',
            'jam_masuk' => '07:05:00',
            'status' => 'hadir',
        ]);

        $res = $this->actingAs($this->superadmin)
            ->getJson('/laporan/preview?'.http_build_query([
                'type' => 'rekap_mengajar',
                'start_date' => '2026-09-01',
                'end_date' => '2026-09-09',
            ]))
            ->assertOk();

        $this->assertCount(1, $res->json('data'));
        $this->assertSame(1, $res->json('data.0.4')); // JP terjadwal hanya 1 (mengajar).
    }

    public function test_filter_tipe_kantor_di_laporan_presensi(): void
    {
        $guru = $this->makeGuru('Guru C');
        $mapel = MataPelajaran::create(['nama' => 'B. Inggris']);
        $pm = PegawaiMapel::create([
            'pegawai_id' => $guru->id,
            'mata_pelajaran_id' => $mapel->id,
            'unit_sekolah_id' => $this->unit->id,
        ]);
        $j1 = $this->makeJadwal($guru, $pm, 'Senin', '08:00:00', '08:45:00');

        Presensi::create([
            'pegawai_id' => $guru->id,
            'jadwal_id' => null,
            'unit_sekolah_id' => $this->unit->id,
            'tipe_presensi' => 'kantor',
            'tanggal' => '2026-09-07',
            'jam_masuk' => '07:05:00',
            'status' => 'hadir',
        ]);
        $this->makePresensiMengajar($guru, $j1, '2026-09-07', 'hadir');

        // Tanpa filter: 2 row.
        $res = $this->actingAs($this->superadmin)
            ->getJson('/laporan/preview?'.http_build_query([
                'type' => 'presensi',
                'start_date' => '2026-09-01',
                'end_date' => '2026-09-09',
            ]))
            ->assertOk();
        $this->assertCount(2, $res->json('data'));

        // Filter kantor: 1 row (tipe = Kantor).
        $res = $this->actingAs($this->superadmin)
            ->getJson('/laporan/preview?'.http_build_query([
                'type' => 'presensi',
                'start_date' => '2026-09-01',
                'end_date' => '2026-09-09',
                'tipe_filter' => 'kantor',
            ]))
            ->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertSame('Kantor', $res->json('data.0.4'));

        // Filter mengajar: 1 row (tipe = Mengajar).
        $res = $this->actingAs($this->superadmin)
            ->getJson('/laporan/preview?'.http_build_query([
                'type' => 'presensi',
                'start_date' => '2026-09-01',
                'end_date' => '2026-09-09',
                'tipe_filter' => 'mengajar',
            ]))
            ->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertSame('Mengajar', $res->json('data.0.4'));
    }
}
