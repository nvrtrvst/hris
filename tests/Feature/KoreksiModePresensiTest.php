<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Jabatan;
use App\Models\Jadwal;
use App\Models\Pegawai;
use App\Models\Penggajian;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KoreksiModePresensiTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private User $admin;

    private Pegawai $pegawai;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Carbon::setTestNow('2026-10-26 10:00:00');

        $this->unit = UnitSekolah::create([
            'nama' => 'SMK Uji',
            'singkatan' => 'SMK',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'jam_masuk_kantor' => '07:00',
            'toleransi_menit' => 0,
        ]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('superadmin');

        $jabatan = Jabatan::create(['nama' => 'Guru', 'is_guru' => true]);
        $this->pegawai = Pegawai::create([
            'nik' => '3273'.str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT),
            'nama_lengkap' => 'Guru Uji',
            'jenis_kelamin' => 'L',
            'status_kepegawaian' => 'guru_pemula',
            'status_aktif' => 'aktif',
            'tmt_mengajar' => '2020-01-01',
        ]);
        $this->pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);
    }

    private function jadwal(string $jamMulai = '07:00:00', string $jamSelesai = '08:30:00'): Jadwal
    {
        return Jadwal::create([
            'pegawai_id' => $this->pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'kelas_label' => 'X RPL 1',
            'hari' => 'Senin',
            'jam_mulai' => $jamMulai,
            'jam_selesai' => $jamSelesai,
            'jenis_jadwal' => 'mengajar',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);
    }

    private function lembur(string $status = 'pending'): Presensi
    {
        $presensi = Presensi::create([
            'pegawai_id' => $this->pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'tipe_presensi' => 'lembur',
            'tanggal' => now()->toDateString(),
            'jam_masuk' => '07:00:00',
            'foto_masuk' => 'presensi/lembur-uji.jpg',
            'latitude_masuk' => -6.2,
            'longitude_masuk' => 106.8,
            'jarak_masuk_meter' => 5,
            'akurasi_masuk' => 10,
        ]);
        $presensi->forceFill([
            'is_lembur' => true,
            'lembur_status' => $status,
            'status' => 'hadir',
        ])->save();

        return $presensi->fresh();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'mode' => 'mengajar',
            'jadwal_ids' => [],
            'alasan' => 'Salah menekan mode lembur',
        ], $overrides);
    }

    public function test_opsi_mengembalikan_jadwal_hari_itu(): void
    {
        $jadwal = $this->jadwal();
        $lembur = $this->lembur();

        $this->actingAs($this->admin)
            ->getJson(route('presensi.koreksi-mode.opsi', $lembur->id))
            ->assertOk()
            ->assertJsonPath('blocked', null)
            ->assertJsonPath('jadwals.0.id', $jadwal->id)
            ->assertJsonPath('jadwals.0.sudah_presensi', false);
    }

    public function test_lembur_menjadi_presensi_mengajar(): void
    {
        $jadwal = $this->jadwal();
        $lembur = $this->lembur();

        $response = $this->actingAs($this->admin)
            ->post(route('presensi.koreksi-mode', $lembur->id), $this->payload([
                'jadwal_ids' => [$jadwal->id],
            ]));
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('presensi', ['id' => $lembur->id]);

        $baru = Presensi::where('pegawai_id', $this->pegawai->id)
            ->where('jadwal_id', $jadwal->id)
            ->whereDate('tanggal', now()->toDateString())
            ->first();
        $this->assertNotNull($baru);

        $this->assertFalse((bool) $baru->is_lembur);
        $this->assertSame('07:00:00', $baru->jam_masuk);
        $this->assertSame('08:30:00', $baru->jam_keluar);
        $this->assertSame('hadir', $baru->status);
        $this->assertSame('presensi/lembur-uji.jpg', $baru->foto_masuk);
        $this->assertTrue(AuditLog::where('aksi', 'koreksi_mode_lembur')->exists());
    }

    public function test_lembur_menjadi_presensi_kantor(): void
    {
        $lembur = $this->lembur();

        $this->actingAs($this->admin)
            ->post(route('presensi.koreksi-mode', $lembur->id), $this->payload(['mode' => 'kantor']))
            ->assertSessionHasNoErrors();

        $presensi = Presensi::findOrFail($lembur->id);

        $this->assertFalse((bool) $presensi->is_lembur);
        $this->assertNull($presensi->lembur_status);
        $this->assertSame('kantor', $presensi->tipe_presensi);
        $this->assertNull($presensi->jadwal_id);
        $this->assertSame('07:00:00', $presensi->jam_masuk);
        $this->assertSame('hadir', $presensi->status);
    }

    public function test_lembur_dibatalkan_tanpa_presensi_pengganti(): void
    {
        $lembur = $this->lembur();

        $this->actingAs($this->admin)
            ->post(route('presensi.koreksi-mode', $lembur->id), $this->payload(['mode' => 'batalkan']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('presensi', ['id' => $lembur->id]);
        $this->assertSame(
            0,
            Presensi::where('pegawai_id', $this->pegawai->id)->where('tanggal', now()->toDateString())->count()
        );
    }

    public function test_lembur_disetujui_tidak_bisa_dikoreksi(): void
    {
        $jadwal = $this->jadwal();
        $lembur = $this->lembur('disetujui');

        $this->actingAs($this->admin)
            ->post(route('presensi.koreksi-mode', $lembur->id), $this->payload([
                'jadwal_ids' => [$jadwal->id],
            ]))
            ->assertSessionHasErrors('presensi');

        $this->assertDatabaseHas('presensi', ['id' => $lembur->id, 'is_lembur' => true]);
    }

    public function test_jadwal_yang_sudah_punya_presensi_ditolak(): void
    {
        $jadwal = $this->jadwal();
        $lembur = $this->lembur();

        Presensi::create([
            'pegawai_id' => $this->pegawai->id,
            'jadwal_id' => $jadwal->id,
            'unit_sekolah_id' => $this->unit->id,
            'tipe_presensi' => 'mengajar',
            'tanggal' => now()->toDateString(),
            'jam_masuk' => '07:05:00',
            'status' => 'hadir',
        ]);

        $this->actingAs($this->admin)
            ->post(route('presensi.koreksi-mode', $lembur->id), $this->payload([
                'jadwal_ids' => [$jadwal->id],
            ]))
            ->assertSessionHasErrors('jadwal_ids');

        $this->assertDatabaseHas('presensi', ['id' => $lembur->id, 'is_lembur' => true]);
    }

    public function test_periode_payroll_terkunci_ditolak(): void
    {
        $jadwal = $this->jadwal();
        $lembur = $this->lembur();

        Penggajian::create([
            'pegawai_id' => $this->pegawai->id,
            'periode_bulan' => now()->format('m-Y'),
            'tanggal_generate' => now()->toDateString(),
            'total_pendapatan' => 0,
            'total_potongan' => 0,
            'gaji_bersih' => 0,
            'total_taxable' => 0,
            'status' => 'finalized',
        ]);

        $this->actingAs($this->admin)
            ->post(route('presensi.koreksi-mode', $lembur->id), $this->payload([
                'jadwal_ids' => [$jadwal->id],
            ]))
            ->assertSessionHasErrors('presensi');
    }
}
