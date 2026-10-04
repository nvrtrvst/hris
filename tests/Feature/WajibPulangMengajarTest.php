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

class WajibPulangMengajarTest extends TestCase
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

    private function buatFotoPagi(string $tanggal): void
    {
        Presensi::create([
            'pegawai_id' => $this->pegawai->id,
            'jadwal_id' => null,
            'unit_sekolah_id' => $this->unit->id,
            'tipe_presensi' => 'kantor',
            'tanggal' => $tanggal,
            'jam_masuk' => '07:05:00',
        ]);
    }

    private function slide(array $extra = [])
    {
        return $this->actingAs($this->pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.slide'), array_merge([
                'latitude' => -6.2,
                'longitude' => 106.8,
                'accuracy' => 15,
            ], $extra));
    }

    public function test_flag_on_prefill_dimatikan_dan_keluar_dibatasi_window(): void
    {
        Carbon::setTestNow('2026-08-21 12:00:00'); // Kamis
        $jadwal = $this->makeJadwal('07:00', '15:00');
        $this->buatFotoPagi('2026-08-21');

        $this->slide(['jadwal_id' => $jadwal->id])->assertOk()->assertJson(['success' => true]);

        // (a) flag ON: pre-fill hilang — anchor & cover rows jam_keluar null.
        $anchor = Presensi::where('pegawai_id', $this->pegawai->id)->where('jadwal_id', $jadwal->id)->first();
        $this->assertNotNull($anchor);
        $this->assertNotNull($anchor->jam_masuk);
        $this->assertNull($anchor->jam_keluar);

        // (b) keluar sebelum sesiEnd - 20 menit (14:40) → 422 + pesan window.
        $res = $this->slide(['jadwal_id' => $jadwal->id, 'tipe' => 'keluar'])->assertStatus(422);
        $this->assertSame('Tombol pulang tersedia pukul 14:40.', $res->json('message'));
        $this->assertNull($anchor->fresh()->jam_keluar);

        Carbon::setTestNow();
    }

    public function test_flag_on_keluar_dalam_window_hanya_mengisi_row_terakhir(): void
    {
        // 2 JP sesi: 13:00-13:45 + 13:45-14:30. Window = 14:30-20 = 14:10.
        Carbon::setTestNow('2026-08-21 14:20:00'); // Kamis
        $j1 = $this->makeJadwal('13:00', '13:45');
        $j2 = $this->makeJadwal('13:45', '14:30');
        $this->buatFotoPagi('2026-08-21');

        $this->slide(['jadwal_id' => $j1->id])->assertOk()->assertJson(['success' => true]);

        $row1 = Presensi::where('jadwal_id', $j1->id)->where('tanggal', '2026-08-21')->first();
        $row2 = Presensi::where('jadwal_id', $j2->id)->where('tanggal', '2026-08-21')->first();
        $this->assertNotNull($row1);
        $this->assertNotNull($row2, 'JP cover (auto-cover) ikut tercatat.');
        $this->assertNull($row1->jam_keluar, 'Anchor tanpa pre-fill saat flag ON.');
        $this->assertNull($row2->jam_keluar, 'Cover row tanpa pre-fill saat flag ON.');

        // Dalam window → sukses, jam_keluar hanya di row JP terakhir.
        $this->slide(['jadwal_id' => $j1->id, 'tipe' => 'keluar'])->assertOk()->assertJson(['success' => true]);
        $this->assertNull($row1->fresh()->jam_keluar, 'Row tengah/anchor tetap kosong (by design).');
        $this->assertNotNull($row2->fresh()->jam_keluar, 'Row JP terakhir yang terkunci.');

        // Idempoten: kedua kalinya ditolak.
        $this->slide(['jadwal_id' => $j1->id, 'tipe' => 'keluar'])->assertStatus(422);

        Carbon::setTestNow();
    }

    public function test_flag_off_parity_prefill_jam_keluar_dari_jadwal(): void
    {
        $this->unit->update(['wajib_pulang_mengajar' => false]);

        Carbon::setTestNow('2026-08-21 12:00:00'); // Kamis
        $jadwal = $this->makeJadwal('07:00', '15:00');
        $this->buatFotoPagi('2026-08-21');

        $this->slide(['jadwal_id' => $jadwal->id])->assertOk()->assertJson(['success' => true]);

        $anchor = Presensi::where('jadwal_id', $jadwal->id)->where('tanggal', '2026-08-21')->first();
        $this->assertNotNull($anchor->jam_keluar, 'Flag OFF: prefill lama tetap ada.');
        $this->assertSame('15:00', substr($anchor->jam_keluar, 0, 5));

        Carbon::setTestNow();
    }
}
