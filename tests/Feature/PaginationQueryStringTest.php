<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\Penggajian;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PaginationQueryStringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function makeAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'superadmin']);
        $admin->assignRole('superadmin');

        return $admin;
    }

    private function makeUnit(): UnitSekolah
    {
        return UnitSekolah::create([
            'nama' => 'SMP Pagination Test',
            'singkatan' => 'SMPPT',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'toleransi_menit' => 0,
        ]);
    }

    private function makePegawai(string $nik): Pegawai
    {
        return Pegawai::create([
            'nik' => $nik,
            'nama_lengkap' => 'Pegawai '.$nik,
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '1992-03-03',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'kawin',
            'jumlah_tanggungan' => 0,
            'alamat_ktp' => 'Jl. Pagination Test No. 1',
            'no_hp' => '0812'.substr($nik, -7),
            'status_kepegawaian' => 'honorer',
            'tanggal_mulai_kerja' => '2021-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
        ]);
    }

    /**
     * Pastikan setiap link pagination (yang punya URL) mempertahankan filter query string.
     * Link disabled (prev/next non-aktif) punya url null dan dilewati.
     */
    private function assertAllLinksContainFilter(array $links, string $expectedQuery, string $message): void
    {
        $this->assertNotEmpty($links);

        foreach ($links as $link) {
            if (! empty($link['url'])) {
                $this->assertStringContainsString($expectedQuery, $link['url'], $message);
            }
        }
    }

    public function test_payroll_filter_periode_persist_di_pagination_links(): void
    {
        $admin = $this->makeAdmin();

        // 11 penggajian periode sama (perPage=10 → 2 halaman).
        // Constraint unik (pegawai_id, periode_bulan) → butuh 11 pegawai berbeda.
        foreach (range(1, 11) as $i) {
            $pegawai = $this->makePegawai('8800112233445'.($i % 10).$i);
            Penggajian::create([
                'pegawai_id' => $pegawai->id,
                'periode_bulan' => '07-2026',
                'tanggal_generate' => '2026-07-31',
            ]);
        }

        $response = $this->actingAs($admin, 'web_admin')
            ->get('/penggajian?periode_bulan=07-2026&page=2');

        $response->assertInertia(function (Assert $page) {
            $page->component('Payroll/Index')
                ->where('penggajians.current_page', 2)
                ->where('penggajians.total', 11)
                ->where('penggajians.last_page', 2)
                ->has('penggajians.data', 1)
                // Server benar-benar memfilter: data halaman 2 tetap periode 07-2026
                ->where('penggajians.data.0.periode_bulan', '07-2026');

            $this->assertAllLinksContainFilter(
                $page->toArray()['props']['penggajians']['links'],
                'periode_bulan=07-2026',
                'Link pagination harus mempertahankan filter periode_bulan.'
            );
        });
    }

    public function test_presensi_filter_tanggal_persist_di_pagination_links(): void
    {
        $admin = $this->makeAdmin();
        $unit = $this->makeUnit();
        $pegawai = $this->makePegawai('7700998877665543');
        $jabatan = Jabatan::create(['nama' => 'Guru']);
        $pegawai->units()->attach($unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        // 11 presensi tanggal berbeda dalam rentang yang difilter (perPage=10 → 2 halaman).
        foreach (range(1, 11) as $i) {
            Presensi::create([
                'pegawai_id' => $pegawai->id,
                'jadwal_id' => null,
                'unit_sekolah_id' => $unit->id,
                'tanggal' => '2026-07-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            ]);
        }

        $response = $this->actingAs($admin, 'web_admin')
            ->get('/presensi?start_date=2026-07-01&end_date=2026-07-31&page=2');

        $response->assertInertia(function (Assert $page) {
            $page->component('Presensi/Index')
                ->where('presensis.current_page', 2)
                ->where('presensis.total', 11)
                ->where('presensis.last_page', 2)
                ->has('presensis.data', 1)
                // Server benar-benar memfilter: tanggal data halaman 2 (urut tanggal desc) masih dalam rentang
                ->where('presensis.data.0.tanggal', '2026-07-01');

            $links = $page->toArray()['props']['presensis']['links'];
            $this->assertAllLinksContainFilter(
                $links,
                'start_date=2026-07-01',
                'Link pagination harus mempertahankan filter start_date.'
            );
            $this->assertAllLinksContainFilter(
                $links,
                'end_date=2026-07-31',
                'Link pagination harus mempertahankan filter end_date.'
            );
        });
    }
}
