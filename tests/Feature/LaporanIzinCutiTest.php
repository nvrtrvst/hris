<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LaporanIzinCutiTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private User $superadmin;

    private Pegawai $pegawai;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Notification::fake();

        $this->unit = UnitSekolah::create(['nama' => 'SMP', 'singkatan' => 'SMP', 'latitude' => -6.2, 'longitude' => 106.8, 'radius_meter' => 100]);
        $jabatan = Jabatan::create(['nama' => 'Guru', 'is_guru' => true]);
        $this->superadmin = User::factory()->create();
        $this->superadmin->syncRoles('superadmin');

        $user = User::factory()->create();
        $this->pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '1234567890000099',
            'nama_lengkap' => 'Guru Izin',
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'Belum Menikah',
            'alamat' => 'Jl. Test',
            'no_hp' => '081234567890',
            'status_kepegawaian' => 'guru_tetap_yayasan',
            'tmt_mengajar' => '2020-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
        ]);
        $this->pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);
    }

    public function test_sakit_dan_cuti_approved_muncul_di_laporan_filter_kantor(): void
    {
        // Tanggal berbeda: 1 pegawai hanya bisa punya 1 row presensi per
        // tanggal (updateOrCreate di generatePresensi menimpa).
        $tanggalSakit = Carbon::today()->toDateString();
        $tanggalCuti = Carbon::today()->addDay()->toDateString();

        foreach ([['sakit', $tanggalSakit], ['cuti', $tanggalCuti]] as [$jenis, $tanggal]) {
            $pengajuan = PengajuanIzin::create([
                'pegawai_id' => $this->pegawai->id,
                'jenis_izin' => $jenis,
                'tanggal_mulai' => $tanggal,
                'tanggal_selesai' => $tanggal,
                'alasan' => 'Uji coba laporan '.$jenis,
            ]);
            $pengajuan->forceFill(['status' => 'pending', 'approval_stage' => 'pending_l1'])->save();

            $this->actingAs($this->superadmin, 'web_admin')
                ->post(route('pengajuan-izin.approve', $pengajuan->id))
                ->assertRedirect();
        }

        $res = $this->actingAs($this->superadmin, 'web_admin')
            ->getJson('/laporan/preview?'.http_build_query([
                'type' => 'presensi',
                'start_date' => $tanggalSakit,
                'end_date' => $tanggalCuti,
                'tipe_filter' => 'kantor',
            ]))
            ->assertOk();

        $this->assertNotEmpty($res->json('data'));
        $statuses = collect($res->json('data'))->map(fn ($r) => $r[8])->all();
        $this->assertContains('Sakit', $statuses);
        $this->assertContains('Cuti', $statuses);
    }

    public function test_izin_approved_muncul_di_laporan(): void
    {
        $tanggal = Carbon::today()->toDateString();

        $pengajuan = PengajuanIzin::create([
            'pegawai_id' => $this->pegawai->id,
            'jenis_izin' => 'izin',
            'tanggal_mulai' => $tanggal,
            'tanggal_selesai' => $tanggal,
            'alasan' => 'Uji coba laporan izin',
        ]);
        $pengajuan->forceFill(['status' => 'pending', 'approval_stage' => 'pending_l1'])->save();

        // Approve via route (superadmin single-stage).
        $this->actingAs($this->superadmin, 'web_admin')
            ->post(route('pengajuan-izin.approve', $pengajuan->id))
            ->assertRedirect();

        $this->assertSame('approved', $pengajuan->fresh()->approval_stage);
        $this->assertDatabaseHas('presensi', [
            'pegawai_id' => $this->pegawai->id,
            'tanggal' => $tanggal,
            'status' => 'izin',
        ]);

        // Laporan tanpa filter tipe: row izin harus tampil.
        $res = $this->actingAs($this->superadmin, 'web_admin')
            ->getJson('/laporan/preview?'.http_build_query([
                'type' => 'presensi',
                'start_date' => $tanggal,
                'end_date' => $tanggal,
            ]))
            ->assertOk();

        $statuses = collect($res->json('data'))->map(fn ($r) => $r[10])->all();
        $this->assertContains('Izin', $statuses, 'Row izin tidak muncul di laporan tanpa filter');

        // Laporan tipe kantor: row izin juga harus tampil (tipe 'kantor' kini
        // di-set eksplisit oleh generatePresensi). Kolom mapel/kelas hilang
        // saat filter kantor → Status di index 8.
        $res = $this->actingAs($this->superadmin, 'web_admin')
            ->getJson('/laporan/preview?'.http_build_query([
                'type' => 'presensi',
                'start_date' => $tanggal,
                'end_date' => $tanggal,
                'tipe_filter' => 'kantor',
            ]))
            ->assertOk();

        $this->assertNotEmpty($res->json('data'));
        $statuses = collect($res->json('data'))->map(fn ($r) => $r[8])->all();
        $this->assertContains('Izin', $statuses, 'Row izin tidak muncul di laporan filter kantor');
    }
}
