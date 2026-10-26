<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Jadwal;
use App\Models\LaporanPiket;
use App\Models\Pegawai;
use App\Models\PiketArea;
use App\Models\PiketItemTemplate;
use App\Models\PiketPenugasan;
use App\Models\PiketPos;
use App\Models\PiketSesi;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use App\Services\PiketService;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PiketV2Test extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private User $admin;

    private PiketArea $area;

    private PiketPos $gerbang;

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
            'durasi_jp' => 45,
            'toleransi_menit' => 0,
        ]);
        $this->unit->forceFill(['piket_mode' => 'area'])->save();

        $this->admin = User::factory()->create();
        $this->admin->assignRole('superadmin');

        $this->area = PiketArea::create([
            'unit_sekolah_id' => $this->unit->id,
            'nama' => 'Kampus 1',
            'urutan' => 1,
        ]);

        $this->gerbang = PiketPos::create([
            'unit_sekolah_id' => $this->unit->id,
            'area_id' => $this->area->id,
            'nama' => 'Gerbang Kampus 1',
            'jam_mulai' => '06:30',
            'jam_selesai' => '07:15',
            'is_wajib' => true,
            'aktif' => true,
        ]);

        PiketItemTemplate::create([
            'unit_sekolah_id' => $this->unit->id,
            'kode' => 'kelas_kosong',
            'label' => 'Kelas kosong',
            'seksi' => 'kbm',
            'scope' => 'area',
            'tipe' => 'centang',
            'wajib' => true,
            'urutan' => 1,
        ]);
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
            'wajib_kantor' => true,
        ]);
        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    private function jadwal(Pegawai $pegawai): Jadwal
    {
        return Jadwal::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'hari' => 'Senin',
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '15:00:00',
            'jenis_jadwal' => 'piket',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);
    }

    private function masuk(Pegawai $pegawai): void
    {
        $p = Presensi::create([
            'pegawai_id' => $pegawai->id,
            'tipe_presensi' => 'kantor',
            'tanggal' => now()->toDateString(),
            'jam_masuk' => '06:40:00',
            'unit_sekolah_id' => $this->unit->id,
        ]);
        $p->status = 'hadir';
        $p->save();
    }

    private function geo(): array
    {
        return ['latitude' => -6.2, 'longitude' => 106.8, 'accuracy' => 15];
    }

    public function test_sesi_menautkan_petugas_dan_laporan(): void
    {
        Carbon::setTestNow('2026-10-26 07:00:00');

        $a = $this->pegawai('Petugas A');
        $b = $this->pegawai('Petugas B');
        $this->masuk($a);
        $this->masuk($b);
        $jadwalA = $this->jadwal($a);
        $this->jadwal($b);

        PiketPenugasan::create([
            'unit_sekolah_id' => $this->unit->id,
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
            'pegawai_id' => $a->id,
            'area_id' => $this->area->id,
            'peran' => 'area',
            'aktif' => true,
        ]);
        PiketPenugasan::create([
            'unit_sekolah_id' => $this->unit->id,
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
            'pegawai_id' => $b->id,
            'area_id' => $this->area->id,
            'pos_id' => $this->gerbang->id,
            'peran' => 'gerbang',
            'aktif' => true,
        ]);

        $this->actingAs($a->user, 'web_mobile')
            ->get(route('presensi.piket.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Mobile/Piket')
                ->where('piket.v2', true)
                ->where('piket.area.nama', 'Kampus 1')
                ->where('piket.pos.0.nama', 'Gerbang Kampus 1')
                ->has('piket.team', 2)
            );

        $sesi = PiketSesi::where('unit_sekolah_id', $this->unit->id)->firstOrFail();
        $this->assertSame(2, $sesi->petugas()->count());
        $this->assertDatabaseHas('laporan_piket', ['pegawai_id' => $a->id, 'sesi_id' => $sesi->id]);
    }

    public function test_pos_wajib_memblokir_kirim_dan_override_membuka(): void
    {
        Carbon::setTestNow('2026-10-26 16:00:00');

        $a = $this->pegawai('Petugas A');
        $this->masuk($a);
        $jadwal = $this->jadwal($a);

        PiketPenugasan::create([
            'unit_sekolah_id' => $this->unit->id,
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
            'pegawai_id' => $a->id,
            'area_id' => $this->area->id,
            'peran' => 'area',
            'aktif' => true,
        ]);

        $sesi = PiketService::sessionFor($a, $jadwal);
        $petugas = PiketService::petugasFor($sesi, $a->id);
        $laporan = LaporanPiket::find($petugas->laporan_piket_id);
        $laporan->forceFill([
            'items' => [
                'kelas_kosong' => ['done_at' => '15:30', 'nihil' => true],
            ],
        ])->save();

        $this->assertDatabaseHas('laporan_piket', ['pegawai_id' => $a->id, 'tanggal' => '2026-10-26']);
        $this->assertSame(1, LaporanPiket::count());

        $this->actingAs($a->user, 'web_mobile')
            ->post(route('presensi.piket.submit'))
            ->assertSessionHasErrors('laporan');

        $this->assertSame(LaporanPiket::STATUS_DRAFT, $laporan->fresh()->status);

        $this->actingAs($this->admin, 'web_admin')
            ->post(route('piket.sesi.pos.layani', [$sesi->id, $this->gerbang->id]), [
                'petugas_id' => $a->id,
                'alasan' => 'Petugas gerbang berhalangan, digantikan.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('piket_pos_log', [
            'sesi_id' => $sesi->id,
            'pos_id' => $this->gerbang->id,
            'sumber' => 'override',
        ]);

        $this->actingAs($a->user, 'web_mobile')
            ->post(route('presensi.piket.submit'))
            ->assertSessionHasNoErrors();

        $this->assertSame(LaporanPiket::STATUS_SUBMITTED, $laporan->fresh()->status);
        $this->assertSame('complete', $sesi->fresh()->status);
    }

    public function test_petugas_tidak_bisa_mencatat_pos_area_lain(): void
    {
        Carbon::setTestNow('2026-10-26 07:00:00');

        $lain = PiketArea::create([
            'unit_sekolah_id' => $this->unit->id,
            'nama' => 'Kampus 2',
            'urutan' => 2,
        ]);
        $posLain = PiketPos::create([
            'unit_sekolah_id' => $this->unit->id,
            'area_id' => $lain->id,
            'nama' => 'Gerbang Kampus 2',
            'jam_mulai' => '06:30',
            'jam_selesai' => '07:15',
            'is_wajib' => false,
            'aktif' => true,
        ]);

        $a = $this->pegawai('Petugas A');
        $this->masuk($a);
        $this->jadwal($a);
        PiketPenugasan::create([
            'unit_sekolah_id' => $this->unit->id,
            'pegawai_id' => $a->id,
            'area_id' => $this->area->id,
            'peran' => 'area',
            'aktif' => true,
        ]);

        $this->actingAs($a->user, 'web_mobile')
            ->post(route('presensi.piket.pos'), [
                'pos_id' => $posLain->id,
                'nihil' => true,
                ...$this->geo(),
            ])
            ->assertForbidden();
    }
}
