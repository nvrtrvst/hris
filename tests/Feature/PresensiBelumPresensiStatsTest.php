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
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PresensiBelumPresensiStatsTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private User $superadmin;

    private Jabatan $jabatan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->unit = UnitSekolah::create([
            'nama' => 'SMP Belum Presensi',
            'singkatan' => 'SMPBP',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
        ]);

        $this->jabatan = Jabatan::firstOrCreate(['nama' => 'Guru'], ['is_guru' => true]);

        $this->superadmin = User::factory()->create(['role' => 'superadmin']);
        $this->superadmin->assignRole('superadmin');
    }

    public function test_admin_menghitung_pegawai_tanpa_presensi_dan_search(): void
    {
        $hadir = $this->buatPegawai('Alpha Hadir');
        $this->buatPegawai('Beta Belum');
        $this->buatPegawai('Gamma Belum');
        $this->presensi($hadir);

        $this->actingAs($this->superadmin, 'web_admin')
            ->get(route('presensi.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.hadir', 1)
                ->where('stats.belum_presensi', 2)
            );

        $this->actingAs($this->superadmin, 'web_admin')
            ->get(route('presensi.index', ['search' => 'Beta']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.total', 0)
                ->where('stats.belum_presensi', 1)
            );

        $this->actingAs($this->superadmin, 'web_admin')
            ->get(route('presensi.index', ['status_filter' => 'belum_presensi']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('presensis.data', 2)
                ->where('stats.belum_presensi', 2)
            );
    }

    public function test_pimpinan_hanya_menghitung_bawahan_langsung(): void
    {
        $pimpinanUser = User::factory()->create(['unit_sekolah_id' => $this->unit->id]);
        $pimpinanUser->assignRole('pimpinan');
        $pimpinan = $this->buatPegawai('Kepala Unit', $pimpinanUser);
        $bawahan = $this->buatPegawai('Bawahan Langsung');
        $bawahan->update(['atasan_langsung_id' => $pimpinan->id]);
        $this->buatPegawai('Bukan Bawahan');

        $this->actingAs($pimpinanUser, 'web_admin')
            ->get(route('presensi.index', ['status_filter' => 'belum_presensi']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('is_personal', false)
                ->has('presensis.data', 1)
                ->where('presensis.data.0.pegawai_id', $bawahan->id)
                ->where('stats.belum_presensi', 1)
            );
    }

    private function buatPegawai(string $nama, ?User $user = null): Pegawai
    {
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
        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $this->jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    private function presensi(Pegawai $pegawai): Presensi
    {
        $presensi = Presensi::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'tipe_presensi' => 'kantor',
            'tanggal' => Carbon::today()->toDateString(),
            'jam_masuk' => '07:00:00',
            'jam_keluar' => '15:00:00',
        ]);
        $presensi->forceFill(['status' => 'hadir'])->save();

        return $presensi;
    }
}
