<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\UnitSekolah;
use App\Models\User;
use App\Notifications\TembusanIzin;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TembusanIzinTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private UnitSekolah $unitLain;

    private Jabatan $wakasek;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->unit = UnitSekolah::create([
            'nama' => 'SMP Test', 'singkatan' => 'SMP',
            'latitude' => -6.2, 'longitude' => 106.8, 'radius_meter' => 100,
            'durasi_jp' => 45, 'toleransi_menit' => 0,
        ]);
        $this->unitLain = UnitSekolah::create([
            'nama' => 'SD Lain', 'singkatan' => 'SDL',
            'latitude' => -6.3, 'longitude' => 106.9, 'radius_meter' => 100,
        ]);

        $this->wakasek = Jabatan::create(['nama' => 'Wakasek Kurikulum']);
    }

    private function makePegawai(User $user, UnitSekolah $unit, Jabatan $jabatan, string $nama): Pegawai
    {
        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '99001122'.str_pad((string) $user->id, 8, '0', STR_PAD_LEFT),
            'nama_lengkap' => $nama,
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '1995-05-05',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'kawin',
            'jumlah_tanggungan' => 0,
            'alamat' => 'Jl. Test',
            'no_hp' => '081211223344',
            'status_kepegawaian' => 'guru_pemula',
            'tmt_mengajar' => '2022-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
        ]);
        $pegawai->units()->attach($unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    private function flagTembusan(UnitSekolah $unit): void
    {
        DB::table('unit_tembusan')->insert([
            'unit_sekolah_id' => $unit->id,
            'jabatan_id' => $this->wakasek->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ajukanIzin(User $pegawaiUser)
    {
        return $this->actingAs($pegawaiUser, 'web_mobile')
            ->post(route('presensi.izin.store'), [
                'jenis_izin' => 'izin',
                'tanggal_mulai' => now()->format('Y-m-d'),
                'tanggal_selesai' => now()->format('Y-m-d'),
                'alasan' => 'Ada urusan keluarga yang harus diurus hari ini.',
            ]);
    }

    public function test_wakasek_flagged_terima_tembusan_saat_diajukan(): void
    {
        $this->flagTembusan($this->unit);
        $guru = Jabatan::create(['nama' => 'Guru']);
        $wakasekUser = User::factory()->create();
        $this->makePegawai($wakasekUser, $this->unit, $this->wakasek, 'Wakasek Kurikulum');
        $pegawaiUser = User::factory()->create();
        $this->makePegawai($pegawaiUser, $this->unit, $guru, 'Guru Pengaju');

        $this->ajukanIzin($pegawaiUser)->assertRedirect(route('presensi.izin.index'));

        $this->assertDatabaseHas('notifications', [
            'type' => TembusanIzin::class,
            'notifiable_id' => $wakasekUser->id,
        ]);
    }

    public function test_tanpa_flag_tidak_ada_tembusan(): void
    {
        $guru = Jabatan::create(['nama' => 'Guru']);
        $wakasekUser = User::factory()->create();
        $this->makePegawai($wakasekUser, $this->unit, $this->wakasek, 'Wakasek Kurikulum');
        $pegawaiUser = User::factory()->create();
        $this->makePegawai($pegawaiUser, $this->unit, $guru, 'Guru Pengaju');

        $this->ajukanIzin($pegawaiUser)->assertRedirect(route('presensi.izin.index'));

        $this->assertDatabaseMissing('notifications', [
            'type' => TembusanIzin::class,
            'notifiable_id' => $wakasekUser->id,
        ]);
    }

    public function test_unit_lain_tidak_dapat_tembusan(): void
    {
        $this->flagTembusan($this->unit);
        $guru = Jabatan::create(['nama' => 'Guru']);
        $wakasekUser = User::factory()->create();
        $this->makePegawai($wakasekUser, $this->unitLain, $this->wakasek, 'Wakasek Unit Lain');
        $pegawaiUser = User::factory()->create();
        $this->makePegawai($pegawaiUser, $this->unit, $guru, 'Guru Pengaju');

        $this->ajukanIzin($pegawaiUser)->assertRedirect(route('presensi.izin.index'));

        $this->assertDatabaseMissing('notifications', [
            'type' => TembusanIzin::class,
            'notifiable_id' => $wakasekUser->id,
        ]);
    }

    public function test_tembusan_terkirim_saat_disetujui_dan_ditolak(): void
    {
        $this->flagTembusan($this->unit);
        $guru = Jabatan::create(['nama' => 'Guru']);
        $wakasekUser = User::factory()->create();
        $this->makePegawai($wakasekUser, $this->unit, $this->wakasek, 'Wakasek Kurikulum');
        $pegawaiUser = User::factory()->create();
        $pegawai = $this->makePegawai($pegawaiUser, $this->unit, $guru, 'Guru Pengaju');
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $superadmin->assignRole('superadmin');

        $buat = function () use ($pegawai): PengajuanIzin {
            $p = PengajuanIzin::create([
                'pegawai_id' => $pegawai->id,
                'jenis_izin' => 'izin',
                'tanggal_mulai' => now()->format('Y-m-d'),
                'tanggal_selesai' => now()->format('Y-m-d'),
                'alasan' => 'Urusan keluarga yang harus diurus.',
            ]);
            $p->forceFill(['status' => 'pending', 'approval_stage' => 'pending_l1'])->save();

            return $p->fresh();
        };

        $setuju = $buat();

        $this->actingAs($superadmin, 'web_admin')
            ->post(route('pengajuan-izin.approve', $setuju->id))
            ->assertRedirect();
        $this->assertDatabaseHas('notifications', [
            'type' => TembusanIzin::class,
            'notifiable_id' => $wakasekUser->id,
            'data->momen' => 'disetujui',
        ]);

        $tolak = $buat();
        $this->actingAs($superadmin, 'web_admin')
            ->post(route('pengajuan-izin.reject', $tolak->id), ['alasan_penolakan' => 'Belum bisa ditinggal.'])
            ->assertRedirect();
        $this->assertDatabaseHas('notifications', [
            'type' => TembusanIzin::class,
            'notifiable_id' => $wakasekUser->id,
            'data->momen' => 'ditolak',
        ]);
    }
}
