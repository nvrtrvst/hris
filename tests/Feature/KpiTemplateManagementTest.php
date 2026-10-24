<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\KpiTemplate;
use App\Models\UnitSekolah;
use App\Models\User;
use Database\Seeders\JabatanSeeder;
use Database\Seeders\KpiTemplateSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KpiTemplateManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_weights_must_total_one_hundred(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(JabatanSeeder::class);
        $this->seed(KpiTemplateSeeder::class);
        $template = KpiTemplate::with('indicators')->whereHas('jabatan', fn ($q) => $q->where('nama', 'Guru Mata Pelajaran'))->firstOrFail();
        $admin = $this->superadmin();
        $indicators = $template->indicators->map(fn ($indicator) => [
            'id' => $indicator->id,
            'nama' => $indicator->nama,
            'deskripsi' => $indicator->deskripsi,
            'bobot' => $indicator->bobot,
            'target' => $indicator->target,
            'arah' => $indicator->arah,
            'wajib_bukti' => $indicator->wajib_bukti,
        ])->values()->all();
        $indicators[0]['bobot'] = (float) $indicators[0]['bobot'] - 1;

        $this->actingAs($admin, 'web_admin')
            ->put(route('kpi.templates.update', $template), ['nama' => $template->nama, 'indicators' => $indicators])
            ->assertSessionHasErrors('indicators');
    }

    public function test_superadmin_can_create_unit_override_from_default_template(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(JabatanSeeder::class);
        $this->seed(KpiTemplateSeeder::class);
        $unit = UnitSekolah::create([
            'nama' => 'Unit KPI', 'singkatan' => 'KPI',
            'latitude' => -6.2, 'longitude' => 106.8,
            'radius_meter' => 100, 'durasi_jp' => 45, 'toleransi_menit' => 0,
        ]);
        $jabatan = Jabatan::where('nama', 'Guru Kelas')->firstOrFail();
        $base = KpiTemplate::withCount('indicators')->where('jabatan_id', $jabatan->id)->whereNull('unit_sekolah_id')->firstOrFail();

        $this->actingAs($this->superadmin(), 'web_admin')
            ->post(route('kpi.templates.override'), ['template_id' => $base->id, 'unit_sekolah_id' => $unit->id])
            ->assertRedirect();

        $override = KpiTemplate::where('jabatan_id', $jabatan->id)->where('unit_sekolah_id', $unit->id)->withCount('indicators')->firstOrFail();
        $this->assertSame($base->indicators_count, $override->indicators_count);
    }

    public function test_new_job_title_gets_a_default_kpi_template(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = $this->superadmin();

        $this->actingAs($admin, 'web_admin')
            ->post(route('jabatan.store'), ['nama' => 'Petugas Arsip', 'is_guru' => false])
            ->assertRedirect();

        $jabatan = Jabatan::where('nama', 'Petugas Arsip')->firstOrFail();
        $this->assertDatabaseHas('kpi_templates', ['jabatan_id' => $jabatan->id, 'unit_sekolah_id' => null]);
        $this->assertGreaterThan(0, KpiTemplate::where('jabatan_id', $jabatan->id)->first()->indicators()->count());
    }

    private function superadmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        return $user;
    }
}
