<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\UnitSekolah;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PengajuanIzinDefaultTanggalTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    private Pegawai $pegawai;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $unit = UnitSekolah::create([
            'nama' => 'Unit Tanggal Default',
            'singkatan' => 'UTD',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'toleransi_menit' => 0,
            'toleransi_slide_menit' => 15,
        ]);

        $this->superadmin = User::factory()->create(['role' => 'superadmin']);
        $this->superadmin->assignRole('superadmin');

        $jabatan = Jabatan::create(['nama' => 'Guru Tanggal', 'is_guru' => true]);
        $this->pegawai = Pegawai::create([
            'user_id' => User::factory()->create()->id,
            'nik' => '3333333333',
            'nama_lengkap' => 'Pegawai Tanggal',
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '1992-03-03',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'kawin',
            'jumlah_tanggungan' => 0,
            'alamat' => 'Jl. Tanggal Test No. 1',
            'no_hp' => '0812333333',
            'status_kepegawaian' => 'guru_pemula',
            'tmt_mengajar' => '2021-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
        ]);
        $this->pegawai->units()->attach($unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        // Pengajuan hari ini (masuk default) & 7 hari lalu (harus tersembunyi di default).
        $this->makePengajuan(Carbon::today(), 'Pengajuan yang jatuh hari ini untuk tes default');
        $this->makePengajuan(Carbon::today()->subDays(7), 'Pengajuan tujuh hari lalu untuk tes default');
    }

    private function makePengajuan(Carbon $tanggal, string $alasan): PengajuanIzin
    {
        return PengajuanIzin::create([
            'pegawai_id' => $this->pegawai->id,
            'jenis_izin' => 'cuti',
            'tanggal_mulai' => $tanggal->toDateString(),
            'tanggal_selesai' => $tanggal->toDateString(),
            'alasan' => $alasan,
        ]);
    }

    public function test_tanpa_param_tanggal_default_hanya_tampilkan_pengajuan_hari_ini(): void
    {
        $this->actingAs($this->superadmin)
            ->get('/pengajuan-izin?tab=semua')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PengajuanIzin/Index')
                ->has('pengajuans.data', 1)
                ->where('pengajuans.data.0.tanggal_mulai', Carbon::today()->startOfDay()->toISOString())
                ->where('filters.tanggal', null)
                ->etc());
    }

    public function test_tanggal_dikosongkan_menampilkan_semua_pengajuan(): void
    {
        $this->actingAs($this->superadmin)
            ->get('/pengajuan-izin?tab=semua&tanggal=')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PengajuanIzin/Index')
                ->has('pengajuans.data', 2)
                ->where('filters.tanggal', '')
                ->etc());
    }
}
