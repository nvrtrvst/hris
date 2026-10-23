<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LemburApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function boot(): array
    {
        $this->seed(RolePermissionSeeder::class);

        $unit = UnitSekolah::create([
            'nama' => 'Unit Uji', 'singkatan' => 'UU',
            'latitude' => -6.2, 'longitude' => 106.8,
            'radius_meter' => 100, 'durasi_jp' => 45, 'toleransi_menit' => 0,
        ]);

        $jabatan = Jabatan::create(['nama' => 'Guru']);
        $pegawai = Pegawai::create([
            'nik' => '1234567890123456',
            'nama_lengkap' => 'Pegawai Uji',
            'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '1992-03-03',
            'jenis_kelamin' => 'L', 'agama' => 'Islam',
            'status_pernikahan' => 'kawin', 'jumlah_tanggungan' => 0,
            'alamat' => 'Jl. Test No. 1', 'no_hp' => '081234567890',
            'status_kepegawaian' => 'guru_pemula', 'tmt_mengajar' => '2021-01-01',
            'status_aktif' => 'aktif', 'pendidikan_terakhir' => 'S1',
        ]);
        $pegawai->units()->attach($unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        $presensi = Presensi::forceCreate([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $unit->id,
            'tanggal' => '2026-10-01',
            'jam_masuk' => '17:00:00',
            'jam_keluar' => '20:00:00',
            'is_lembur' => true,
            'lembur_status' => 'pending',
            'status' => 'hadir',
        ]);

        $super = User::factory()->create(['role' => 'superadmin']);
        $super->assignRole('superadmin');

        return [$presensi, $super];
    }

    public function test_approve_lembur_persists(): void
    {
        [$presensi, $super] = $this->boot();

        $this->actingAs($super, 'web_admin')
            ->post(route('presensi.approveLembur', $presensi->id))
            ->assertRedirect();

        $this->assertSame('disetujui', $presensi->fresh()->lembur_status);
    }

    public function test_reject_lembur_persists(): void
    {
        [$presensi, $super] = $this->boot();

        $this->actingAs($super, 'web_admin')
            ->post(route('presensi.rejectLembur', $presensi->id))
            ->assertRedirect();

        $this->assertSame('ditolak', $presensi->fresh()->lembur_status);
    }
}
