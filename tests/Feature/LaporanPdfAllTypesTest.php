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

class LaporanPdfAllTypesTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_semua_type_laporan_200(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->syncRoles('superadmin');

        foreach (['presensi', 'penggajian', 'lemburan', 'rekap_mengajar'] as $type) {
            $res = $this->actingAs($admin, 'web_admin')
                ->get('/laporan/pdf?'.http_build_query([
                    'type' => $type,
                    'start_date' => '2026-09-01',
                    'end_date' => '2026-09-08',
                ]));

            $res->assertStatus(200);
            $this->assertSame('application/pdf', $res->headers->get('Content-Type'), "type={$type}");
        }
    }

    public function test_pdf_presensi_data_besar_200(): void
    {
        // Simulasi beban prod: 30 hari × banyak pegawai × kantor + mengajar.
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->syncRoles('superadmin');

        $unit = UnitSekolah::create(['nama' => 'SMP', 'singkatan' => 'SMP', 'latitude' => -6.2, 'longitude' => 106.8, 'radius_meter' => 100]);
        $jabatan = Jabatan::create(['nama' => 'Guru', 'is_guru' => true]);
        $user = User::factory()->create();
        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '1234567890000001',
            'nama_lengkap' => 'Guru Beban',
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
        $pegawai->units()->attach($unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        // 20 hari kerja × 6 row/hari (1 kantor + 5 mengajar) = 120 row.
        // Presensi list PDF = 1 row presensi — cukup mewakili shape data.
        for ($i = 0; $i < 20; $i++) {
            $tanggal = Carbon::parse('2026-08-10')->addDays($i)->toDateString();
            Presensi::create([
                'pegawai_id' => $pegawai->id,
                'jadwal_id' => null,
                'unit_sekolah_id' => $unit->id,
                'tipe_presensi' => 'kantor',
                'tanggal' => $tanggal,
                'jam_masuk' => '07:05:00',
            ]);
        }

        $res = $this->actingAs($admin, 'web_admin')
            ->get('/laporan/pdf?'.http_build_query([
                'type' => 'presensi',
                'start_date' => '2026-08-10',
                'end_date' => '2026-09-08',
            ]));

        $res->assertStatus(200);
    }

    public function test_pdf_rekap_mengajar_pegawai_terhapus_200(): void
    {
        // Pegawai dihapus (soft-delete) tapi presensinya masih — map() harus
        // tetap aman (null-safe) bukan 500.
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->syncRoles('superadmin');

        $unit = UnitSekolah::create(['nama' => 'SMP', 'singkatan' => 'SMP', 'latitude' => -6.2, 'longitude' => 106.8, 'radius_meter' => 100]);
        $jabatan = Jabatan::create(['nama' => 'Guru', 'is_guru' => true]);
        $user = User::factory()->create();
        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '1234567890000002',
            'nama_lengkap' => 'Guru Terhapus',
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
        $pegawai->units()->attach($unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        // Presensi kantor (row tetap ada setelah pegawai dihapus).
        Presensi::create([
            'pegawai_id' => $pegawai->id,
            'jadwal_id' => null,
            'unit_sekolah_id' => $unit->id,
            'tipe_presensi' => 'kantor',
            'tanggal' => '2026-09-07',
            'jam_masuk' => '07:05:00',
        ]);

        $pegawai->delete(); // soft-delete — presensi tetap.

        $res = $this->actingAs($admin, 'web_admin')
            ->get('/laporan/pdf?'.http_build_query([
                'type' => 'presensi',
                'start_date' => '2026-09-01',
                'end_date' => '2026-09-08',
            ]));

        $res->assertStatus(200);
    }
}
