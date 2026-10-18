<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Jadwal;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\PegawaiMapel;
use App\Models\PengajuanPulangCepat;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PulangAwalTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private Pegawai $pegawai;

    private PegawaiMapel $pm;

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
            'toleransi_menit' => 0,
            'toleransi_slide_menit' => 15,
            'wajib_pulang_mengajar' => true,
            'pulang_sebelum_menit' => 20,
            'jam_pulang_kantor' => '15:00:00',
        ]);

        $jabatan = Jabatan::create(['nama' => 'Guru']);
        $user = User::factory()->create();
        $this->pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '1234567890'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT),
            'nama_lengkap' => 'Guru Tetap',
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'kawin',
            'jumlah_tanggungan' => 2,
            'alamat' => 'Jl. Test No. 1',
            'no_hp' => '081234567890',
            'status_kepegawaian' => 'guru_tetap_yayasan',
            'tmt_mengajar' => '2020-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
            'wajib_kantor' => true,
        ]);
        $this->pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        $mapel = MataPelajaran::create(['nama' => 'Matematika']);
        $this->pm = PegawaiMapel::create([
            'pegawai_id' => $this->pegawai->id,
            'mata_pelajaran_id' => $mapel->id,
            'unit_sekolah_id' => $this->unit->id,
        ]);
    }

    private function makeJadwal(string $mulai, string $selesai): Jadwal
    {
        return Jadwal::create([
            'pegawai_id' => $this->pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'pegawai_mapel_id' => $this->pm->id,
            'hari' => Carbon::now()->locale('id')->dayName,
            'jam_mulai' => $mulai,
            'jam_selesai' => $selesai,
            'jenis_jadwal' => 'mengajar',
            'kelas_label' => 'X-A',
            'tahun_ajaran' => '2026/2027',
            'semester' => 'Ganjil',
        ]);
    }

    private function buatPresensi(array $extra = []): Presensi
    {
        return Presensi::create(array_merge([
            'pegawai_id' => $this->pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'tipe_presensi' => 'kantor',
            'tanggal' => '2026-08-21',
            'jam_masuk' => '07:00:00',
        ], $extra));
    }

    public function test_mengajar_dipotong_pulang_sebelum_menit(): void
    {
        $j = $this->makeJadwal('07:00', '15:00'); // expected 15:00 - 20 = 14:40
        $p = $this->buatPresensi(['jadwal_id' => $j->id, 'tipe_presensi' => 'mengajar', 'jam_keluar' => '14:00:00']);

        $this->assertTrue($p->fresh()->is_pulang_awal);
        $this->assertSame(40, $p->fresh()->menit_pulang_awal);
        $this->assertFalse($p->fresh()->pulang_awal_diizinkan);
    }

    public function test_kantor_pakai_jam_pulang_kantor(): void
    {
        UnitSekolah::where('id', $this->unit->id)->update(['jam_pulang_kantor' => '16:00:00']);
        $p = $this->buatPresensi(['jam_keluar' => '14:00:00']);

        $this->assertTrue($p->fresh()->is_pulang_awal);
        $this->assertSame(120, $p->fresh()->menit_pulang_awal);
    }

    public function test_toleransi_dikurangi_dari_selisih(): void
    {
        UnitSekolah::where('id', $this->unit->id)->update(['toleransi_menit' => 45]);
        $j = $this->makeJadwal('07:00', '15:00');
        // Selisih 40 menit - toleransi 45 → tidak ditandai.
        $p = $this->buatPresensi(['jadwal_id' => $j->id, 'tipe_presensi' => 'mengajar', 'jam_keluar' => '14:00:00']);

        $this->assertFalse($p->fresh()->is_pulang_awal);
        $this->assertNull($p->fresh()->menit_pulang_awal);
    }

    public function test_lembur_dan_tugas_luar_di_skip(): void
    {
        $j = $this->makeJadwal('07:00', '15:00');

        // is_lembur guarded → set langsung sebelum save pertama (deteksi ikut jalan sekali).
        $lembur = new Presensi([
            'pegawai_id' => $this->pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'tipe_presensi' => 'mengajar',
            'tanggal' => '2026-08-21',
            'jam_masuk' => '07:00:00',
            'jam_keluar' => '10:00:00',
        ]);
        $lembur->is_lembur = true;
        $lembur->save();

        $tugas = $this->buatPresensi(['jadwal_id' => $j->id, 'tipe_presensi' => 'mengajar', 'is_tugas_luar' => true, 'jam_keluar' => '10:00:00']);

        $this->assertFalse($lembur->fresh()->is_pulang_awal, 'Lembur tidak dihitung pulang awal.');
        $this->assertFalse($tugas->fresh()->is_pulang_awal, 'Tugas luar tidak dihitung pulang awal.');
    }

    public function test_izin_disetujui_menandai_pulang_awal_diizinkan(): void
    {
        $izin = PengajuanPulangCepat::create([
            'pegawai_id' => $this->pegawai->id,
            'tanggal' => '2026-08-21',
            'jam_diminta' => '14:00',
            'alasan' => 'keperluan_pribadi',
            'nomor' => 'PPC/2026/08/0001',
        ]);
        $izin->status = 'disetujui';
        $izin->save();

        $j = $this->makeJadwal('07:00', '15:00');
        $p = $this->buatPresensi(['jadwal_id' => $j->id, 'tipe_presensi' => 'mengajar', 'jam_keluar' => '14:00:00']);

        $this->assertTrue($p->fresh()->is_pulang_awal);
        $this->assertTrue($p->fresh()->pulang_awal_diizinkan);
    }

    public function test_gate_keluar_mengajar_terbuka_bila_ada_izin_disetujui(): void
    {
        Carbon::setTestNow('2026-08-21 12:00:00'); // Kamis
        $jadwal = $this->makeJadwal('07:00', '15:00');
        $this->buatPresensi(['jam_masuk' => '07:05:00']);

        $izin = PengajuanPulangCepat::create([
            'pegawai_id' => $this->pegawai->id,
            'tanggal' => '2026-08-21',
            'jam_diminta' => '12:00',
            'alasan' => 'keperluan_pribadi',
            'nomor' => 'PPC/2026/08/0002',
        ]);
        $izin->status = 'disetujui';
        $izin->save();

        $this->actingAs($this->pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.slide'), ['jadwal_id' => $jadwal->id, 'latitude' => -6.2, 'longitude' => 106.8, 'accuracy' => 15])
            ->assertOk()->assertJson(['success' => true]);

        // Tanpa izin sebelumnya gate menolak (lihat WajibPulangMengajarTest); dengan izin → boleh.
        $this->actingAs($this->pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.slide'), ['jadwal_id' => $jadwal->id, 'tipe' => 'keluar', 'latitude' => -6.2, 'longitude' => 106.8, 'accuracy' => 15])
            ->assertOk()->assertJson(['success' => true]);

        $row = Presensi::where('jadwal_id', $jadwal->id)->where('tanggal', '2026-08-21')->first();
        $this->assertNotNull($row->jam_keluar);
        $this->assertTrue($row->fresh()->pulang_awal_diizinkan);

        Carbon::setTestNow();
    }
}
