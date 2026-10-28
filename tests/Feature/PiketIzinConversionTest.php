<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\LaporanPiket;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\UnitSekolah;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PiketIzinConversionTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private User $tu;

    private Pegawai $guru;

    private LaporanPiket $laporan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Notification::fake();
        Event::fake();

        Carbon::setTestNow('2026-10-20 10:00:00');

        $this->unit = UnitSekolah::create([
            'nama' => 'SD Test',
            'singkatan' => 'SD',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
        ]);

        $this->tu = User::factory()->create(['unit_sekolah_id' => $this->unit->id]);
        $this->tu->assignRole('tu_kependidikan');

        $this->guru = $this->pegawai('Guru Sakit');
        $petugas = $this->pegawai('Petugas Piket');

        $this->laporan = LaporanPiket::create([
            'pegawai_id' => $petugas->id,
            'unit_sekolah_id' => $this->unit->id,
            'tanggal' => '2026-10-20',
            'status' => LaporanPiket::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'items' => [
                LaporanPiket::ITEM_CEK_ABSEN => [
                    'entries' => [[
                        'guru_pegawai_id' => $this->guru->id,
                        'nama_guru' => $this->guru->nama_lengkap,
                        'keterangan' => 'Sakit, surat dokter diterima piket.',
                        'created_at' => '07:00:00',
                        'sumber' => 'daftar',
                    ]],
                ],
            ],
        ]);
    }

    private function pegawai(string $nama): Pegawai
    {
        $jabatan = Jabatan::firstOrCreate(['nama' => 'Guru'], ['is_guru' => true]);
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

    public function test_tu_bisa_membuat_izin_dari_entry_piket(): void
    {
        $this->actingAs($this->tu, 'web_admin')
            ->post(route('laporan-piket.buat-izin', $this->laporan), [
                'entry_index' => 0,
                'jenis_izin' => 'izin',
                'tanggal_mulai' => '2026-10-20',
                'tanggal_selesai' => '2026-10-20',
                'alasan' => 'Guru tidak masuk karena keperluan keluarga.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $izin = PengajuanIzin::firstOrFail();
        $this->assertSame($this->guru->id, $izin->pegawai_id);
        $this->assertSame('piket', $izin->sumber);
        $this->assertSame($this->tu->id, $izin->created_by);
        $this->assertSame('pending', $izin->status);

        $this->laporan->refresh();
        $this->assertSame($izin->id, $this->laporan->items['cek_absen']['entries'][0]['pengajuan_izin_id']);
    }

    public function test_izin_piket_tidak_auto_approve(): void
    {
        $this->actingAs($this->tu, 'web_admin')
            ->post(route('laporan-piket.buat-izin', $this->laporan), [
                'entry_index' => 0,
                'jenis_izin' => 'izin',
                'tanggal_mulai' => '2026-10-20',
                'tanggal_selesai' => '2026-10-20',
                'alasan' => 'Guru tidak masuk karena keperluan keluarga.',
            ]);

        $izin = PengajuanIzin::firstOrFail();
        $this->assertSame('pending', $izin->status);
        $this->assertNotSame('approved', $izin->approval_stage);
    }

    public function test_sakit_wajib_bukti(): void
    {
        $this->actingAs($this->tu, 'web_admin')
            ->post(route('laporan-piket.buat-izin', $this->laporan), [
                'entry_index' => 0,
                'jenis_izin' => 'sakit',
                'tanggal_mulai' => '2026-10-20',
                'tanggal_selesai' => '2026-10-20',
                'alasan' => 'Guru sakit dan memerlukan istirahat.',
            ])
            ->assertSessionHasErrors('bukti_foto');

        $this->assertDatabaseCount('pengajuan_izins', 0);
    }

    public function test_entry_manual_ditolak(): void
    {
        $items = $this->laporan->items;
        $items['cek_absen']['entries'][0]['guru_pegawai_id'] = null;
        $items['cek_absen']['entries'][0]['nama_guru'] = 'Guru Manual';
        $this->laporan->forceFill(['items' => $items])->save();

        $this->actingAs($this->tu, 'web_admin')
            ->post(route('laporan-piket.buat-izin', $this->laporan), [
                'entry_index' => 0,
                'jenis_izin' => 'izin',
                'tanggal_mulai' => '2026-10-20',
                'tanggal_selesai' => '2026-10-20',
                'alasan' => 'Guru tidak masuk karena keperluan keluarga.',
            ])
            ->assertSessionHasErrors('guru_pegawai_id');

        $this->assertDatabaseCount('pengajuan_izins', 0);
    }

    public function test_entry_tidak_bisa_dikonversi_dua_kali(): void
    {
        $payload = [
            'entry_index' => 0,
            'jenis_izin' => 'izin',
            'tanggal_mulai' => '2026-10-20',
            'tanggal_selesai' => '2026-10-20',
            'alasan' => 'Guru tidak masuk karena keperluan keluarga.',
        ];

        $this->actingAs($this->tu, 'web_admin')
            ->post(route('laporan-piket.buat-izin', $this->laporan), $payload)
            ->assertSessionHasNoErrors();

        $this->actingAs($this->tu, 'web_admin')
            ->post(route('laporan-piket.buat-izin', $this->laporan), $payload)
            ->assertSessionHasErrors('entry_index');

        $this->assertDatabaseCount('pengajuan_izins', 1);
    }

    public function test_tu_bisa_membuka_laporan_piket_dan_pengajuan_izin(): void
    {
        $this->assertTrue($this->tu->can('view_piket'));
        $this->assertTrue($this->tu->can('create_izin_from_piket'));

        $this->actingAs($this->tu, 'web_admin')
            ->get(route('laporan.piket'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Laporan/Piket'));

        $this->actingAs($this->tu, 'web_admin')
            ->get(route('pengajuan-izin.index'))
            ->assertOk();
    }

    public function test_user_tanpa_permission_tidak_bisa_membuat_izin(): void
    {
        $lain = User::factory()->create(['unit_sekolah_id' => $this->unit->id]);
        $lain->assignRole('pimpinan');

        $this->actingAs($lain, 'web_admin')
            ->post(route('laporan-piket.buat-izin', $this->laporan), [
                'entry_index' => 0,
                'jenis_izin' => 'izin',
                'tanggal_mulai' => '2026-10-20',
                'tanggal_selesai' => '2026-10-20',
                'alasan' => 'Guru tidak masuk karena keperluan keluarga.',
            ])
            ->assertForbidden();
    }
}
