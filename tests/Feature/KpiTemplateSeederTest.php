<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\KpiTemplate;
use Database\Seeders\JabatanSeeder;
use Database\Seeders\KpiTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KpiTemplateSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_kpi_templates_cover_each_seeded_position_with_full_weights(): void
    {
        $this->seed(JabatanSeeder::class);
        $this->seed(KpiTemplateSeeder::class);

        $this->assertSame(Jabatan::count(), KpiTemplate::count());

        foreach (KpiTemplate::with('indicators')->get() as $template) {
            $this->assertGreaterThan(0, $template->indicators->count(), $template->nama);
            $this->assertSame(100.0, round((float) $template->indicators->sum('bobot'), 2), $template->nama);
        }
    }

    public function test_teacher_and_non_teacher_positions_receive_different_templates(): void
    {
        $this->seed(JabatanSeeder::class);
        $this->seed(KpiTemplateSeeder::class);

        $guru = KpiTemplate::with('indicators')->whereHas('jabatan', fn ($q) => $q->where('nama', 'Guru Mata Pelajaran'))->firstOrFail();
        $bendahara = KpiTemplate::with('indicators')->whereHas('jabatan', fn ($q) => $q->where('nama', 'Bendahara'))->firstOrFail();

        $this->assertNotSame(
            $guru->indicators->pluck('kode')->sort()->values()->all(),
            $bendahara->indicators->pluck('kode')->sort()->values()->all(),
        );
    }
}
