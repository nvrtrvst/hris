<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TapKeluarTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

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
            'toleransi_tap_menit' => 15,
        ]);
    }

    private function makePegawaiTetap(): Pegawai
    {
        $jabatan = Jabatan::create(['nama' => 'Guru']);
        $user = User::factory()->create();
        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '1234567890'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT),
            'nama_lengkap' => 'Guru Tetap',
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'kawin',
            'jumlah_tanggungan' => 2,
            'alamat_ktp' => 'Jl. Test No. 1',
            'no_hp' => '081234567890',
            'status_kepegawaian' => 'tetap',
            'tanggal_mulai_kerja' => '2020-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
            'wajib_kantor' => true,
        ]);

        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    public function test_tap_masuk_lalu_keluar_mengisi_jam_keluar(): void
    {
        Carbon::setTestNow('2026-08-21 12:00:00');
        $hari = Carbon::now()->locale('id')->dayName;
        $pegawai = $this->makePegawaiTetap();
        $mapel = MataPelajaran::create(['nama' => 'Matematika']);
        $jadwal = \App\Models\Jadwal::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'mata_pelajaran_id' => $mapel->id,
            'hari' => $hari,
            'jam_mulai' => '07:00',
            'jam_selesai' => '15:00',
            'jenis_jadwal' => 'reguler',
            'kelas_label' => 'X-A',
            'tahun_ajaran' => '2026/2027',
            'semester' => 'Ganjil',
        ]);

        // Butuh foto pagi (kantor) dulu
        Presensi::create([
            'pegawai_id' => $pegawai->id,
            'jadwal_id' => null,
            'unit_sekolah_id' => $this->unit->id,
            'tipe_presensi' => 'kantor',
            'tanggal' => '2026-08-21',
            'jam_masuk' => '07:05:00',
        ]);

        $payload = [
            'jadwal_id' => $jadwal->id,
            'latitude' => -6.2,
            'longitude' => 106.8,
            'accuracy' => 15,
        ];

        $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.tap'), $payload)
            ->assertOk()->assertJson(['success' => true]);

        $mengajar = Presensi::where('pegawai_id', $pegawai->id)->where('jadwal_id', $jadwal->id)->first();
        $this->assertNotNull($mengajar);
        $this->assertNotNull($mengajar->jam_masuk);
        $this->assertNull($mengajar->jam_keluar);

        $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.tap'), array_merge($payload, ['tipe' => 'keluar']))
            ->assertOk()->assertJson(['success' => true]);

        $this->assertNotNull($mengajar->fresh()->jam_keluar);
        Carbon::setTestNow();
    }

    public function test_tap_keluar_tanpa_masuk_ditolak(): void
    {
        Carbon::setTestNow('2026-08-21 12:00:00');
        $hari = Carbon::now()->locale('id')->dayName;
        $pegawai = $this->makePegawaiTetap();
        $mapel = MataPelajaran::create(['nama' => 'Matematika']);
        $jadwal = \App\Models\Jadwal::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'mata_pelajaran_id' => $mapel->id,
            'hari' => $hari,
            'jam_mulai' => '07:00',
            'jam_selesai' => '15:00',
            'jenis_jadwal' => 'reguler',
            'kelas_label' => 'X-A',
            'tahun_ajaran' => '2026/2027',
            'semester' => 'Ganjil',
        ]);

        Presensi::create([
            'pegawai_id' => $pegawai->id,
            'jadwal_id' => null,
            'unit_sekolah_id' => $this->unit->id,
            'tipe_presensi' => 'kantor',
            'tanggal' => '2026-08-21',
            'jam_masuk' => '07:05:00',
        ]);

        $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.tap'), [
                'jadwal_id' => $jadwal->id,
                'latitude' => -6.2,
                'longitude' => 106.8,
                'accuracy' => 15,
                'tipe' => 'keluar',
            ])
            ->assertStatus(422);

        Carbon::setTestNow();
    }
}
