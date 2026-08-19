<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\Penggajian;
use App\Models\UnitSekolah;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Payroll dialihkan ke jabatan dengan flag `is_payroll_operator` (mis. Bendahara).
 * - admin_unit TIDAK lagi punya view_payroll/manage_payroll → tak bisa payroll.
 * - superadmin hanya memantau (view), TIDAK boleh finalize.
 * - operator scoped ke unit pivot pegawai_unit (bisa multi-unit).
 */
class PayrollOperatorAccessTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unitA;

    private UnitSekolah $unitB;

    private User $operator;

    private User $adminUnit;

    private User $superadmin;

    private Pegawai $pegawaiA;

    private Pegawai $pegawaiB;

    private Penggajian $draftA;

    private Penggajian $draftB;

    private string $periode;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->unitA = UnitSekolah::create([
            'nama' => 'TK Operator',
            'singkatan' => 'TK',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'toleransi_menit' => 0,
            'jam_masuk_kantor' => '07:00',
            'jam_pulang_kantor' => '16:00',
            'max_jam_minggu' => 30,
            'toleransi_tap_menit' => 15,
        ]);

        $this->unitB = UnitSekolah::create([
            'nama' => 'SD Lain',
            'singkatan' => 'SD',
            'latitude' => -6.3,
            'longitude' => 106.9,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'toleransi_menit' => 0,
            'jam_masuk_kantor' => '07:00',
            'jam_pulang_kantor' => '16:00',
            'max_jam_minggu' => 30,
            'toleransi_tap_menit' => 15,
        ]);

        $jabatanBendahara = Jabatan::create(['nama' => 'Bendahara', 'is_guru' => false, 'is_payroll_operator' => true]);
        $jabatanGuru = Jabatan::create(['nama' => 'Guru', 'is_guru' => true]);

        $this->superadmin = User::factory()->create(['role' => 'superadmin']);
        $this->superadmin->assignRole('superadmin');

        $this->adminUnit = User::factory()->create(['role' => 'admin_unit', 'unit_sekolah_id' => $this->unitA->id]);
        $this->adminUnit->assignRole('admin_unit');

        $this->operator = User::factory()->create(['role' => 'pegawai']);
        $this->operator->assignRole('pegawai');
        $this->pegawaiA = $this->makePegawai($this->operator, 'Bendahara Unit A');
        $this->pegawaiA->units()->attach($this->unitA->id, ['jabatan_id' => $jabatanBendahara->id, 'is_primary' => true]);

        $userB = User::factory()->create(['role' => 'pegawai']);
        $userB->assignRole('pegawai');
        $this->pegawaiB = $this->makePegawai($userB, 'Guru Unit B');
        $this->pegawaiB->units()->attach($this->unitB->id, ['jabatan_id' => $jabatanGuru->id, 'is_primary' => true]);

        $this->periode = now()->format('m-Y');

        $this->draftA = Penggajian::create([
            'pegawai_id' => $this->pegawaiA->id,
            'periode_bulan' => $this->periode,
            'tanggal_generate' => now()->format('Y-m-d'),
            'total_pendapatan' => 2000000,
            'total_potongan' => 0,
            'gaji_bersih' => 2000000,
            'total_taxable' => 2000000,
            'status' => 'draft',
        ]);

        $this->draftB = Penggajian::create([
            'pegawai_id' => $this->pegawaiB->id,
            'periode_bulan' => $this->periode,
            'tanggal_generate' => now()->format('Y-m-d'),
            'total_pendapatan' => 2000000,
            'total_potongan' => 0,
            'gaji_bersih' => 2000000,
            'total_taxable' => 2000000,
            'status' => 'draft',
        ]);
    }

    private function makePegawai(User $user, string $nama): Pegawai
    {
        return Pegawai::create([
            'user_id' => $user->id,
            'nik' => (string) fake()->unique()->numerify('##########'),
            'nama_lengkap' => $nama,
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'kawin',
            'jumlah_tanggungan' => 1,
            'alamat_ktp' => 'Jl. Test No. 1',
            'no_hp' => '081200000000',
            'status_kepegawaian' => 'tetap',
            'tanggal_mulai_kerja' => '2020-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
        ]);
    }

    public function test_operator_melihat_riwayat_hanya_unitnya(): void
    {
        $this->actingAs($this->operator, 'web_admin')
            ->get(route('penggajian.index', ['periode_bulan' => $this->periode]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Payroll/Index')
                ->has('penggajians.data', 1)
                ->where('penggajians.data.0.pegawai.id', $this->pegawaiA->id));
    }

    public function test_operator_bisa_generate_draft_unitnya(): void
    {
        $month = now()->addMonth()->format('m');
        $year = now()->addMonth()->format('Y');

        $this->actingAs($this->operator, 'web_admin')
            ->post(route('penggajian.run.init'), ['month' => $month, 'year' => $year])
            ->assertRedirect(route('penggajian.run.worksheet', ['month' => $month, 'year' => $year]));

        $this->assertDatabaseHas('penggajian', [
            'pegawai_id' => $this->pegawaiA->id,
            'periode_bulan' => $month.'-'.$year,
        ]);
        $this->assertDatabaseMissing('penggajian', [
            'pegawai_id' => $this->pegawaiB->id,
            'periode_bulan' => $month.'-'.$year,
        ]);
    }

    public function test_admin_unit_tidak_bisa_generate_payroll(): void
    {
        $this->actingAs($this->adminUnit, 'web_admin')
            ->post(route('penggajian.run.init'), [
                'month' => now()->addMonth()->format('m'),
                'year' => now()->addMonth()->format('Y'),
            ])
            ->assertForbidden();
    }

    public function test_superadmin_tidak_bisa_finalize(): void
    {
        [$month, $year] = explode('-', $this->periode);

        $this->actingAs($this->superadmin, 'web_admin')
            ->post(route('penggajian.run.worksheet_finalize', ['month' => $month, 'year' => $year]))
            ->assertForbidden();
    }

    public function test_operator_finalize_hanya_draft_unitnya(): void
    {
        [$month, $year] = explode('-', $this->periode);

        $this->actingAs($this->operator, 'web_admin')
            ->post(route('penggajian.run.worksheet_finalize', ['month' => $month, 'year' => $year]))
            ->assertRedirect(route('penggajian.index'));

        $this->assertSame('finalized', $this->draftA->fresh()->status);
        $this->assertSame('draft', $this->draftB->fresh()->status);
    }

    public function test_operator_bisa_hapus_draft_unitnya(): void
    {
        $this->actingAs($this->operator, 'web_admin')
            ->delete(route('penggajian.destroy', $this->draftA->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('penggajian', ['id' => $this->draftA->id]);
    }

    public function test_operator_tidak_bisa_hapus_draft_unit_lain(): void
    {
        $this->actingAs($this->operator, 'web_admin')
            ->delete(route('penggajian.destroy', $this->draftB->id))
            ->assertForbidden();

        $this->assertDatabaseHas('penggajian', ['id' => $this->draftB->id, 'status' => 'draft']);
    }
}
