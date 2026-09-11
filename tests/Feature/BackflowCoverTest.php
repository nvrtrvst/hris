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

/**
 * Backflow retroactive cover JP lanjutan honorer flow A
 * via artisan command presensi:backflow-cover.
 *
 * Forward-only: JP sebelum anchor TIDAK di-cover (tetap alpa).
 * Keterangan provenance: "auto-cover dari slide JP #X pukul HH:MM:SS".
 */
class BackflowCoverTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->unit = UnitSekolah::create([
            'nama' => 'SMK Test',
            'singkatan' => 'SMK',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 40,
            'toleransi_menit' => 0,
            'toleransi_slide_menit' => 15,
        ]);
    }

    private function makePegawaiGtty(): Pegawai
    {
        $jabatan = Jabatan::create(['nama' => 'Guru']);
        $user = User::factory()->create();
        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '1234567890'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT),
            'nama_lengkap' => 'Guru GTTY',
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'kawin',
            'jumlah_tanggungan' => 2,
            'alamat' => 'Jl. Test No. 1',
            'no_hp' => '081234567890',
            'status_kepegawaian' => 'GTTY',
            'tmt_mengajar' => '2020-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
            'wajib_kantor' => false,
        ]);
        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    private function makeMapel(Pegawai $pegawai): PegawaiMapel
    {
        $mapel = MataPelajaran::create(['nama' => 'Matematika', 'kode' => 'mat']);

        return PegawaiMapel::create([
            'pegawai_id' => $pegawai->id,
            'mata_pelajaran_id' => $mapel->id,
            'unit_sekolah_id' => $this->unit->id,
        ]);
    }

    private function makeJadwal(Pegawai $pegawai, PegawaiMapel $pm, string $hari, string $mulai, string $selesai): Jadwal
    {
        return Jadwal::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'pegawai_mapel_id' => $pm->id,
            'kelas_label' => 'X MPLB 4',
            'hari' => $hari,
            'jam_mulai' => $mulai,
            'jam_selesai' => $selesai,
            'jenis_jadwal' => 'mengajar',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);
    }

    public function test_dry_run_does_not_write(): void
    {
        Carbon::setTestNow('2026-09-11 10:00:00'); // Jumat (test pakai jumat utk GTTY yg tak ada jadwal existing di seeder)

        $pegawai = $this->makePegawaiGtty();
        $pm = $this->makeMapel($pegawai);
        $jp1 = $this->makeJadwal($pegawai, $pm, 'Jumat', '08:30', '09:10');
        $jp2 = $this->makeJadwal($pegawai, $pm, 'Jumat', '09:10', '09:40');
        $jp3 = $this->makeJadwal($pegawai, $pm, 'Jumat', '09:40', '10:20');

        // Anchor row untuk JP1 (HADIR, jam_masuk aktual user).
        Presensi::create([
            'pegawai_id' => $pegawai->id,
            'jadwal_id' => $jp1->id,
            'unit_sekolah_id' => $this->unit->id,
            'tanggal' => '2026-09-11',
            'tipe_presensi' => 'mengajar',
            'jam_masuk' => '08:35:00',
            'jam_keluar' => '09:10:00',
            'status' => 'hadir',
            'is_lembur' => false,
        ]);

        $this->artisan('presensi:backflow-cover', ['--date' => '2026-09-11'])
            ->assertExitCode(0);

        // Dry-run: JP2/JP3 belum ada.
        $this->assertDatabaseMissing('presensi', ['pegawai_id' => $pegawai->id, 'jadwal_id' => $jp2->id]);
        $this->assertDatabaseMissing('presensi', ['pegawai_id' => $pegawai->id, 'jadwal_id' => $jp3->id]);
    }

    public function test_execute_inserts_cover_rows_for_anchor_chain(): void
    {
        Carbon::setTestNow('2026-09-11 10:00:00');

        $pegawai = $this->makePegawaiGtty();
        $pm = $this->makeMapel($pegawai);
        $jp1 = $this->makeJadwal($pegawai, $pm, 'Jumat', '08:30', '09:10');
        $jp2 = $this->makeJadwal($pegawai, $pm, 'Jumat', '09:10', '09:40');
        $jp3 = $this->makeJadwal($pegawai, $pm, 'Jumat', '09:40', '10:20');

        Presensi::create([
            'pegawai_id' => $pegawai->id,
            'jadwal_id' => $jp1->id,
            'unit_sekolah_id' => $this->unit->id,
            'tanggal' => '2026-09-11',
            'tipe_presensi' => 'mengajar',
            'jam_masuk' => '08:35:00',
            'jam_keluar' => '09:10:00',
            'status' => 'hadir',
            'is_lembur' => false,
        ]);

        $this->artisan('presensi:backflow-cover', ['--date' => '2026-09-11', '--execute' => true])
            ->assertExitCode(0);

        // JP2 & JP3 row hadir dengan jam dari jadwal + keterangan provenance.
        $row2 = Presensi::where('pegawai_id', $pegawai->id)->where('jadwal_id', $jp2->id)->first();
        $this->assertNotNull($row2);
        $this->assertSame('hadir', $row2->status);
        $this->assertSame('09:10', $row2->jam_masuk);
        $this->assertSame('09:40', $row2->jam_keluar);
        $this->assertSame('mengajar', $row2->tipe_presensi);
        $this->assertSame("auto-cover dari slide JP #{$jp1->id} pukul 08:35:00", $row2->keterangan);

        $row3 = Presensi::where('pegawai_id', $pegawai->id)->where('jadwal_id', $jp3->id)->first();
        $this->assertNotNull($row3);
        $this->assertSame('hadir', $row3->status);
        $this->assertSame('09:40', $row3->jam_masuk);
        $this->assertSame('10:20', $row3->jam_keluar);
    }

    public function test_forward_only_skips_jps_before_anchor(): void
    {
        Carbon::setTestNow('2026-09-11 10:00:00');

        $pegawai = $this->makePegawaiGtty();
        $pm = $this->makeMapel($pegawai);
        $jp1 = $this->makeJadwal($pegawai, $pm, 'Jumat', '08:30', '09:10');
        $jp2 = $this->makeJadwal($pegawai, $pm, 'Jumat', '09:10', '09:40');
        $jp3 = $this->makeJadwal($pegawai, $pm, 'Jumat', '09:40', '10:20');

        // Anchor di JP2 — JP1 harus tetap tanpa row (forward-only).
        Presensi::create([
            'pegawai_id' => $pegawai->id,
            'jadwal_id' => $jp2->id,
            'unit_sekolah_id' => $this->unit->id,
            'tanggal' => '2026-09-11',
            'tipe_presensi' => 'mengajar',
            'jam_masuk' => '09:12:00',
            'jam_keluar' => '09:40:00',
            'status' => 'hadir',
            'is_lembur' => false,
        ]);

        $this->artisan('presensi:backflow-cover', ['--date' => '2026-09-11', '--execute' => true])
            ->assertExitCode(0);

        $this->assertDatabaseMissing('presensi', ['pegawai_id' => $pegawai->id, 'jadwal_id' => $jp1->id]);
        $this->assertDatabaseHas('presensi', ['pegawai_id' => $pegawai->id, 'jadwal_id' => $jp2->id]);
        $this->assertDatabaseHas('presensi', ['pegawai_id' => $pegawai->id, 'jadwal_id' => $jp3->id]);
    }

    public function test_skips_when_anchor_has_no_jam_masuk(): void
    {
        Carbon::setTestNow('2026-09-11 10:00:00');

        $pegawai = $this->makePegawaiGtty();
        $pm = $this->makeMapel($pegawai);
        $jp1 = $this->makeJadwal($pegawai, $pm, 'Jumat', '08:30', '09:10');
        $jp2 = $this->makeJadwal($pegawai, $pm, 'Jumat', '09:10', '09:40');

        // Row JP1 ada tapi tanpa jam_masuk (mis. izin) → JP2 TIDAK boleh ke-cover.
        Presensi::create([
            'pegawai_id' => $pegawai->id,
            'jadwal_id' => $jp1->id,
            'unit_sekolah_id' => $this->unit->id,
            'tanggal' => '2026-09-11',
            'tipe_presensi' => 'kantor',
            'status' => 'izin',
            'is_lembur' => false,
        ]);

        $this->artisan('presensi:backflow-cover', ['--date' => '2026-09-11', '--execute' => true])
            ->assertExitCode(0);

        $this->assertDatabaseMissing('presensi', ['pegawai_id' => $pegawai->id, 'jadwal_id' => $jp2->id]);
    }
}
