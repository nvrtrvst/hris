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
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class KpiReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_unit_kpi_report_is_scoped_to_its_unit_and_includes_unrecorded_employee(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $unitA = $this->makeUnit('Unit A', 'UA');
        $unitB = $this->makeUnit('Unit B', 'UB');
        $jabatan = Jabatan::create(['nama' => 'Operator KPI', 'is_guru' => false]);
        $pegawaiA = $this->makePegawai('Pegawai A', '1234567890123456', $unitA, $jabatan);
        $this->makePegawai('Pegawai B', '1234567890123457', $unitB, $jabatan);
        $admin = User::factory()->create(['unit_sekolah_id' => $unitA->id]);
        $admin->assignRole('admin_unit');

        $this->actingAs($admin, 'web_admin')
            ->get(route('kpi.index', ['periode' => 'weekly', 'tanggal' => '2026-10-12']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Kpi/Index')
                ->where('periode.start', '2026-10-12')
                ->where('periode.end', '2026-10-18')
                ->has('rows', 1)
                ->where('rows.0.pegawai', 'Pegawai A')
                ->where('rows.0.attendance_rate', null));

        $this->actingAs($admin, 'web_admin')
            ->get(route('kpi.index', ['periode' => 'weekly', 'tanggal' => '2026-10-12', 'export' => 'excel']))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($admin, 'web_admin')
            ->get(route('kpi.index', ['periode' => 'weekly', 'tanggal' => '2026-10-12', 'export' => 'pdf']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_pimpinan_report_is_scoped_to_direct_reports_even_when_unit_id_is_set(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $unit = $this->makeUnit('Unit A', 'UA');
        $jabatanPimpinan = Jabatan::create(['nama' => 'Kepala Tata Usaha', 'is_guru' => false, 'is_supervisor' => true]);
        $jabatanStaff = Jabatan::create(['nama' => 'Staff KPI', 'is_guru' => false]);
        $manager = $this->makePegawai('Pimpinan', '1234567890123456', $unit, $jabatanPimpinan);
        $bawahan = $this->makePegawai('Bawahan', '1234567890123457', $unit, $jabatanStaff);
        $this->makePegawai('Bukan Bawahan', '1234567890123458', $unit, $jabatanStaff);
        $bawahan->forceFill(['atasan_langsung_id' => $manager->id])->save();
        $manager->user->forceFill(['unit_sekolah_id' => $unit->id])->save();
        $manager->user->assignRole('pimpinan');

        $this->actingAs($manager->user, 'web_admin')
            ->get(route('kpi.index', ['periode' => 'daily', 'tanggal' => '2026-10-12']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Kpi/Index')
                ->has('rows', 1)
                ->where('rows.0.pegawai', 'Bawahan'));
    }

    public function test_pegawai_can_view_only_own_kpi_assignments(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $unitA = $this->makeUnit('Unit A', 'UA');
        $unitB = $this->makeUnit('Unit B', 'UB');
        $jabatan = Jabatan::create(['nama' => 'Guru KPI', 'is_guru' => true]);
        $pegawai = $this->makePegawai('Pegawai Sendiri', '1234567890123456', $unitA, $jabatan);
        $pegawai->units()->attach($unitB->id, ['jabatan_id' => $jabatan->id, 'is_primary' => false]);
        $pegawai->user->assignRole('pegawai');
        $this->makePegawai('Pegawai Lain', '1234567890123457', $unitA, $jabatan)->user->assignRole('pegawai');

        $this->actingAs($pegawai->user, 'web_admin')
            ->get(route('kpi.index', ['periode' => 'monthly', 'tanggal' => '2026-10-12']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Kpi/Index')
                ->has('rows', 2)
                ->where('rows.0.pegawai', 'Pegawai Sendiri')
                ->where('rows.1.pegawai', 'Pegawai Sendiri')
                ->where('rows.0.can_evaluate', false));
    }

    public function test_current_month_attendance_uses_elapsed_workdays_not_future_days(): void
    {
        Carbon::setTestNow('2026-10-14 12:00:00');
        $this->seed(RolePermissionSeeder::class);
        $unit = $this->makeUnit('Unit A', 'UA');
        $jabatan = Jabatan::create(['nama' => 'Admin KPI', 'is_guru' => false]);
        $pegawai = $this->makePegawai('Pegawai KPI', '1234567890123456', $unit, $jabatan);
        DB::table('pegawai')->where('id', $pegawai->id)->update(['wajib_kantor' => true]);
        Presensi::forceCreate([
            'pegawai_id' => $pegawai->id, 'unit_sekolah_id' => $unit->id,
            'tanggal' => '2026-10-01', 'jam_masuk' => '08:00:00',
            'status' => 'hadir', 'is_lembur' => false,
        ]);
        $admin = User::factory()->create(['unit_sekolah_id' => $unit->id]);
        $admin->assignRole('admin_unit');

        $this->actingAs($admin, 'web_admin')
            ->get(route('kpi.index', ['periode' => 'monthly', 'tanggal' => '2026-10-14']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('rows.0.attendance_rate', 10));

        Carbon::setTestNow();
    }

    private function makeUnit(string $nama, string $singkatan): UnitSekolah
    {
        return UnitSekolah::create([
            'nama' => $nama, 'singkatan' => $singkatan,
            'latitude' => -6.2, 'longitude' => 106.8,
            'radius_meter' => 100, 'durasi_jp' => 45, 'toleransi_menit' => 0,
        ]);
    }

    private function makePegawai(string $nama, string $nik, UnitSekolah $unit, Jabatan $jabatan): Pegawai
    {
        $user = User::factory()->create();
        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => $nik,
            'nama_lengkap' => $nama,
            'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L', 'agama' => 'Islam',
            'status_pernikahan' => 'kawin', 'jumlah_tanggungan' => 0,
            'alamat' => 'Jl. Uji No. 1', 'no_hp' => '081234567890',
            'status_kepegawaian' => 'guru_tidak_tetap', 'tmt_mengajar' => '2020-01-01',
            'status_aktif' => 'aktif', 'pendidikan_terakhir' => 'S1',
        ]);
        $pegawai->units()->attach($unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }
}
