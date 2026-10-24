<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\KpiEvaluation;
use App\Models\KpiTemplate;
use App\Models\Pegawai;
use App\Models\PegawaiUnit;
use App\Models\UnitSekolah;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class KpiEvaluationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unit_supervisor_finalizes_monthly_score_with_manual_evidence(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $unit = UnitSekolah::create([
            'nama' => 'Unit KPI', 'singkatan' => 'KPI',
            'latitude' => -6.2, 'longitude' => 106.8,
            'radius_meter' => 100, 'durasi_jp' => 45, 'toleransi_menit' => 0,
        ]);
        $jabatan = Jabatan::create(['nama' => 'Operator KPI', 'is_guru' => false]);
        $pegawaiUser = User::factory()->create();
        $pegawaiUser->assignRole('pegawai');
        $pegawai = Pegawai::create([
            'user_id' => $pegawaiUser->id,
            'nik' => '1234567890123456',
            'nama_lengkap' => 'Pegawai KPI',
            'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L', 'agama' => 'Islam',
            'status_pernikahan' => 'kawin', 'jumlah_tanggungan' => 0,
            'alamat' => 'Jl. Uji No. 1', 'no_hp' => '081234567890',
            'status_kepegawaian' => 'guru_tidak_tetap', 'tmt_mengajar' => '2020-01-01',
            'status_aktif' => 'aktif', 'pendidikan_terakhir' => 'S1',
        ]);
        $pegawai->units()->attach($unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        $template = KpiTemplate::create([
            'jabatan_id' => $jabatan->id, 'unit_sekolah_id' => null,
            'nama' => 'Operator KPI', 'is_active' => true,
        ]);
        $rubric = $template->indicators()->create([
            'kode' => 'akurasi', 'nama' => 'Akurasi', 'jenis' => 'rubric', 'sumber' => 'manual',
            'bobot' => 50, 'rubrik' => [1 => 'Rendah', 2 => 'Cukup', 3 => 'Baik', 4 => 'Unggul'],
            'wajib_bukti' => true, 'urutan' => 1,
        ]);
        $numeric = $template->indicators()->create([
            'kode' => 'tugas', 'nama' => 'Tugas selesai', 'jenis' => 'numeric', 'sumber' => 'manual',
            'target' => 10, 'arah' => 'higher', 'bobot' => 50, 'satuan' => 'tugas',
            'wajib_bukti' => true, 'urutan' => 2,
        ]);

        $evaluator = User::factory()->create(['unit_sekolah_id' => $unit->id]);
        $evaluator->assignRole('admin_unit');

        $this->actingAs($evaluator, 'web_admin')
            ->post(route('kpi.evaluations.store'), [
                'pegawai_id' => $pegawai->id,
                'unit_sekolah_id' => $unit->id,
                'jabatan_id' => $jabatan->id,
                'periode_mulai' => '2026-09-01',
                'periode_selesai' => '2026-09-30',
                'finalize' => false,
                'items' => [
                    $rubric->id => ['level_rubrik' => 3],
                    $numeric->id => ['nilai_aktual' => 8],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($evaluator, 'web_admin')
            ->post(route('kpi.evaluations.store'), [
                'pegawai_id' => $pegawai->id,
                'unit_sekolah_id' => $unit->id,
                'jabatan_id' => $jabatan->id,
                'periode_mulai' => '2026-09-01',
                'periode_selesai' => '2026-09-30',
                'ringkasan' => 'Pekerjaan sesuai target.',
                'finalize' => true,
                'items' => [
                    $rubric->id => ['level_rubrik' => 3, 'bukti' => 'Audit berkas September'],
                    $numeric->id => ['nilai_aktual' => 8, 'bukti' => '8 dari 10 tiket selesai'],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('kpi_evaluations', [
            'pegawai_id' => $pegawai->id,
            'status' => 'finalized',
            'nilai_akhir' => 82.50,
        ]);
        $this->assertDatabaseHas('kpi_evaluation_items', [
            'kpi_evaluation_id' => 1,
            'nama_snapshot' => 'Akurasi',
            'nilai' => 85.00,
            'bukti' => 'Audit berkas September',
        ]);

        $rubric->update(['nama' => 'Akurasi versi baru', 'bobot' => 40, 'rubrik' => [1 => 'Baru 1', 2 => 'Baru 2', 3 => 'Baru 3', 4 => 'Baru 4']]);
        $assignment = PegawaiUnit::where('pegawai_id', $pegawai->id)->where('unit_sekolah_id', $unit->id)->firstOrFail();
        $this->actingAs($evaluator, 'web_admin')
            ->get(route('kpi.evaluations.edit', ['assignment' => $assignment->id, 'tanggal' => '2026-09-15']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Kpi/Evaluate')
                ->where('indicators.0.nama', 'Akurasi')
                ->where('indicators.0.bobot', '50.00')
                ->where('indicators.0.rubrik.3', 'Baik'));

        $this->assertTrue($pegawaiUser->can('view_kpi_self'));
        $this->assertFalse($pegawaiUser->can('view_kpi'));
        $this->assertNotNull($pegawaiUser->pegawai);

        $this->actingAs($pegawaiUser, 'web_admin')
            ->get(route('kpi.index', ['periode' => 'monthly', 'tanggal' => '2026-09-15']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Kpi/Index')
                ->has('rows', 1)
                ->where('rows.0.score', '82.50')
                ->where('rows.0.can_evaluate', false));

        $this->actingAs($pegawaiUser, 'web_admin')
            ->get(route('kpi.evaluations.edit', ['assignment' => $assignment->id, 'tanggal' => '2026-09-15']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Kpi/Evaluate')
                ->where('can_edit', false)
                ->where('can_respond', true));

        $this->actingAs($pegawaiUser, 'web_admin')
            ->post(route('kpi.evaluations.respond', KpiEvaluation::firstOrFail()), ['tanggapan_pegawai' => 'Terima kasih atas evaluasinya.'])
            ->assertRedirect();
        $this->assertDatabaseHas('kpi_evaluations', [
            'id' => KpiEvaluation::first()->id,
            'tanggapan_pegawai' => 'Terima kasih atas evaluasinya.',
        ]);

        $this->actingAs($pegawaiUser, 'web_mobile')
            ->get(route('presensi.kpi'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Mobile/Kpi')
                ->has('assignments', 1)
                ->where('assignments.0.evaluations.0.nilai_akhir', '82.50')
                ->where('assignments.0.evaluations.0.tanggapan_pegawai', 'Terima kasih atas evaluasinya.'));

        $this->actingAs($evaluator, 'web_admin')
            ->post(route('kpi.evaluations.store'), [
                'pegawai_id' => $pegawai->id,
                'unit_sekolah_id' => $unit->id,
                'jabatan_id' => $jabatan->id,
                'periode_mulai' => '2026-09-01',
                'periode_selesai' => '2026-09-30',
                'finalize' => false,
                'items' => [0 => []],
            ])
            ->assertSessionHasErrors('evaluasi');

        $otherUnit = UnitSekolah::create([
            'nama' => 'Unit Lain', 'singkatan' => 'UL',
            'latitude' => -6.21, 'longitude' => 106.81,
            'radius_meter' => 100, 'durasi_jp' => 45, 'toleransi_menit' => 0,
        ]);
        $otherPosition = Jabatan::create(['nama' => 'Jabatan Lain', 'is_guru' => false]);
        $pegawai->units()->attach($otherUnit->id, ['jabatan_id' => $otherPosition->id]);

        $this->actingAs($evaluator, 'web_admin')
            ->post(route('kpi.evaluations.store'), [
                'pegawai_id' => $pegawai->id,
                'unit_sekolah_id' => $otherUnit->id,
                'jabatan_id' => $otherPosition->id,
                'periode_mulai' => '2026-10-01',
                'periode_selesai' => '2026-10-31',
                'items' => [0 => []],
            ])
            ->assertForbidden();

        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');
        $evaluation = KpiEvaluation::firstOrFail();
        $this->actingAs($superadmin, 'web_admin')
            ->post(route('kpi.evaluations.reopen', $evaluation))
            ->assertRedirect();
        $this->assertDatabaseHas('kpi_evaluations', ['id' => $evaluation->id, 'status' => 'draft', 'difinalisasi_pada' => null]);
    }
}
