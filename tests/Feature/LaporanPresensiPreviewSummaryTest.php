<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaporanPresensiPreviewSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_summary_uses_all_rows_not_only_first_five_hundred(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $unit = UnitSekolah::create([
            'nama' => 'Unit Uji', 'singkatan' => 'UU',
            'latitude' => -6.2, 'longitude' => 106.8,
            'radius_meter' => 100, 'durasi_jp' => 45, 'toleransi_menit' => 0,
        ]);
        $jabatan = Jabatan::create(['nama' => 'Guru', 'is_guru' => true]);
        $user = User::factory()->create();
        $pegawai = Pegawai::create([
            'user_id' => $user->id, 'nik' => '1234567890123456', 'nama_lengkap' => 'Guru Uji',
            'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '1990-01-01', 'jenis_kelamin' => 'L', 'agama' => 'Islam',
            'status_pernikahan' => 'kawin', 'jumlah_tanggungan' => 0, 'alamat' => 'Jl. Uji', 'no_hp' => '081234567890',
            'status_kepegawaian' => 'guru_tetap_yayasan', 'tmt_mengajar' => '2020-01-01', 'status_aktif' => 'aktif', 'pendidikan_terakhir' => 'S1',
        ]);
        $pegawai->units()->attach($unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        $start = Carbon::parse('2024-01-01');
        for ($i = 0; $i < 501; $i++) {
            Presensi::forceCreate([
                'pegawai_id' => $pegawai->id,
                'unit_sekolah_id' => $unit->id,
                'tanggal' => $start->copy()->addDays($i)->toDateString(),
                'jam_masuk' => '07:00:00',
                'is_lembur' => false,
                'status' => $i === 500 ? 'telat' : 'hadir',
            ]);
        }

        $admin = User::factory()->create();
        $admin->assignRole('superadmin');

        $this->actingAs($admin, 'web_admin')
            ->getJson(route('laporan.preview', [
                'type' => 'presensi',
                'start_date' => '2024-01-01',
                'end_date' => $start->copy()->addDays(500)->toDateString(),
                'unit_sekolah_id' => $unit->id,
            ]))
            ->assertOk()
            ->assertJsonPath('total', 501)
            ->assertJsonPath('data.0.0', '01/01/2024')
            ->assertJsonPath('summary.total', 501)
            ->assertJsonPath('summary.telat', 1);
    }
}
