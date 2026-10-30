<?php

namespace Tests\Feature;

use App\Exports\LaporanPresensiExport;
use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use App\Services\PresensiPersonalReportService;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PresensiPersonalViewTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private User $user;

    private Pegawai $pegawai;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->unit = UnitSekolah::create([
            'nama' => 'SMP Personal',
            'singkatan' => 'SMPP',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
        ]);

        $this->user = User::factory()->create(['unit_sekolah_id' => $this->unit->id]);
        $this->user->assignRole('pegawai');

        $this->pegawai = $this->buatPegawai('Pegawai Personal', $this->user);
    }

    public function test_pegawai_biasa_hanya_melihat_statistik_dirinya(): void
    {
        $this->buatPegawai('Pegawai Lain 1');
        $this->buatPegawai('Pegawai Lain 2');
        $this->buatPegawai('Pegawai Lain 3');

        $this->actingAs($this->user, 'web_admin')
            ->get(route('presensi.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Presensi/Index')
                ->where('is_personal', true)
                ->where('stats.total', 0)
                ->where('stats.hadir', 0)
                ->where('stats.belum_presensi', 1)
                ->where('pegawai.id', $this->pegawai->id)
            );
    }

    public function test_presensi_pribadi_tidak_menghitung_pegawai_lain(): void
    {
        $this->buatPegawai('Pegawai Lain');

        $presensi = Presensi::create([
            'pegawai_id' => $this->pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'tipe_presensi' => 'kantor',
            'tanggal' => now()->toDateString(),
            'jam_masuk' => '07:00:00',
            'jam_keluar' => '15:00:00',
        ]);
        $presensi->forceFill(['status' => 'hadir'])->save();

        $this->actingAs($this->user, 'web_admin')
            ->get(route('presensi.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('is_personal', true)
                ->where('stats.total', 1)
                ->where('stats.hadir', 1)
                ->where('stats.belum_presensi', 0)
            );
    }

    public function test_filter_belum_presensi_personal_tidak_membocorkan_pegawai_lain(): void
    {
        $this->buatPegawai('Pegawai Lain');

        $this->actingAs($this->user, 'web_admin')
            ->get(route('presensi.index', ['status_filter' => 'belum_presensi']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('is_personal', true)
                ->has('presensis.data', 1)
                ->where('presensis.data.0.pegawai_id', $this->pegawai->id)
            );
    }

    public function test_export_personal_hanya_mengambil_presensi_sendiri(): void
    {
        $lain = $this->buatPegawai('Pegawai Lain');
        $today = now()->toDateString();

        $this->presensi($this->pegawai, $today);
        $this->presensi($lain, $today);

        $export = new LaporanPresensiExport($today, $today, null, null, null, null, null, null, $this->pegawai->id);
        $rows = $export->collection();

        $this->assertCount(1, $rows);
        $this->assertSame($this->pegawai->id, $rows->first()->pegawai_id);
    }

    public function test_export_personal_pdf_dan_excel_bisa_diakses_tanpa_view_presensi(): void
    {
        $today = now()->toDateString();
        $this->presensi($this->pegawai, $today);

        $this->actingAs($this->user, 'web_admin')
            ->get(route('presensi.laporan-saya.excel', ['start_date' => $today, 'end_date' => $today]))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($this->user, 'web_admin')
            ->get(route('presensi.laporan-saya.pdf', ['start_date' => $today, 'end_date' => $today]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_export_personal_ditolak_tanpa_data_pegawai(): void
    {
        $user = User::factory()->create();
        $user->assignRole('pegawai');

        $this->actingAs($user, 'web_admin')
            ->get(route('presensi.laporan-saya.pdf', ['start_date' => today()->toDateString(), 'end_date' => today()->toDateString()]))
            ->assertForbidden();
    }

    public function test_summary_personal_memakai_status_efektif_per_hari(): void
    {
        $today = Carbon::today();

        $this->presensi($this->pegawai, $today->toDateString(), 'alpa', 'lainnya');
        $this->presensi($this->pegawai, $today->toDateString(), 'hadir');

        $summary = app(PresensiPersonalReportService::class)
            ->summary($this->pegawai, $today, $today);

        $this->assertSame(1, $summary['total']);
        $this->assertSame(1, $summary['hadir']);
        $this->assertSame(0, $summary['alpa']);
    }

    private function presensi(Pegawai $pegawai, string $tanggal, string $status = 'hadir', string $tipe = 'kantor'): Presensi
    {
        $presensi = Presensi::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'tipe_presensi' => $tipe,
            'tanggal' => $tanggal,
            'jam_masuk' => '07:00:00',
            'jam_keluar' => '15:00:00',
        ]);
        $presensi->forceFill(['status' => $status])->save();

        return $presensi;
    }

    private function buatPegawai(string $nama, ?User $user = null): Pegawai
    {
        $jabatan = Jabatan::firstOrCreate(['nama' => 'Guru'], ['is_guru' => true]);
        $user ??= User::factory()->create(['unit_sekolah_id' => $this->unit->id]);

        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '3273'.str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT),
            'nama_lengkap' => $nama,
            'jenis_kelamin' => 'L',
            'status_kepegawaian' => 'guru_pemula',
            'status_aktif' => 'aktif',
            'tmt_mengajar' => '2020-01-01',
        ]);
        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }
}
