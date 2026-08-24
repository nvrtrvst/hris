<?php

namespace Tests\Feature;

use App\Models\HariLibur;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HariLiburTest extends TestCase
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

    private function makePegawai(): User
    {
        $user = User::factory()->create(['role' => 'pegawai']);
        $user->assignRole('pegawai');

        return $user;
    }

    public function test_index_menampilkan_daftar(): void
    {
        HariLibur::create(['tanggal' => '2026-01-01', 'nama' => 'Tahun Baru', 'tipe' => 'nasional', 'unit_sekolah_id' => null]);
        HariLibur::create(['tanggal' => '2026-12-25', 'nama' => 'Natal', 'tipe' => 'nasional', 'unit_sekolah_id' => null]);

        $this->actingAs($this->makeAdmin(), 'web_admin')
            ->get(route('hari-libur.index', ['year' => 2026]))
            ->assertOk()
                ->assertInertia(fn ($page) => $page->component('HariLibur/Index')
                ->has('holidays'));
    }

    public function test_store_membuat_hari_libur_nasional(): void
    {
        $this->actingAs($this->makeAdmin(), 'web_admin')
            ->post(route('hari-libur.store'), [
                'tanggal' => '2026-08-17',
                'nama' => 'Kemerdekaan RI',
                'tipe' => 'nasional',
                'unit_sekolah_id' => '',
                'keterangan' => 'Libur nasional',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('hari_libur', [
            'nama' => 'Kemerdekaan RI',
            'tipe' => 'nasional',
            'unit_sekolah_id' => null,
        ]);
    }

    public function test_store_validasi_gagal_tanpa_tanggal(): void
    {
        $this->actingAs($this->makeAdmin(), 'web_admin')
            ->post(route('hari-libur.store'), [
                'nama' => 'X',
                'tipe' => 'nasional',
            ])
            ->assertSessionHasErrors('tanggal');
    }

    public function test_update_mengubah_hari_libur(): void
    {
        $hl = HariLibur::create(['tanggal' => '2026-08-17', 'nama' => 'Lama', 'tipe' => 'nasional', 'unit_sekolah_id' => null]);

        $this->actingAs($this->makeAdmin(), 'web_admin')
            ->put(route('hari-libur.update', $hl), [
                'tanggal' => '2026-08-17',
                'nama' => 'Baru',
                'tipe' => 'nasional',
                'unit_sekolah_id' => '',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('hari_libur', ['id' => $hl->id, 'nama' => 'Baru']);
    }

    public function test_destroy_menghapus_hari_libur(): void
    {
        $hl = HariLibur::create(['tanggal' => '2026-08-17', 'nama' => 'X', 'tipe' => 'nasional', 'unit_sekolah_id' => null]);

        $this->actingAs($this->makeAdmin(), 'web_admin')
            ->delete(route('hari-libur.destroy', $hl))
            ->assertRedirect();

        $this->assertDatabaseMissing('hari_libur', ['id' => $hl->id]);
    }

    public function test_otorisasi_ditolak_untuk_non_admin(): void
    {
        $this->actingAs($this->makePegawai(), 'web_admin')
            ->post(route('hari-libur.store'), [
                'tanggal' => '2026-08-17',
                'nama' => 'X',
                'tipe' => 'nasional',
                'unit_sekolah_id' => '',
            ])
            ->assertForbidden();
    }

    public function test_import_lokal_memuat_json_bundel(): void
    {
        $json = json_decode(file_get_contents(database_path('seeders/data/hari_libur_nasional.json')), true);
        $expected = collect($json)->filter(fn ($h) => substr($h['tanggal'], 0, 4) === '2026')->count();

        $this->actingAs($this->makeAdmin(), 'web_admin')
            ->post(route('hari-libur.import'), ['year' => 2026])
            ->assertRedirect();

        $this->assertSame($expected, HariLibur::whereYear('tanggal', 2026)->count());
        $this->assertTrue(HariLibur::whereDate('tanggal', '2026-01-01')->where('tipe', 'nasional')->exists());
    }

    public function test_import_lokal_validasi_tahun_wajib(): void
    {
        $this->actingAs($this->makeAdmin(), 'web_admin')
            ->post(route('hari-libur.import'), [])
            ->assertSessionHasErrors('year');
    }

    public function test_sync_api_mengambil_dan_menyimpan(): void
    {
        Http::fake([
            '*' => Http::response([
                ['holiday_date' => '2026-12-25', 'holiday_name' => 'Hari Raya Natal', 'is_national_holiday' => true, 'is_joint_holiday' => false],
                ['holiday_date' => '2026-07-07', 'holiday_name' => 'Cuti Bersama', 'is_national_holiday' => false, 'is_joint_holiday' => true],
                ['holiday_date' => 'bukan-tanggal', 'holiday_name' => 'Rusak'],
            ], 200),
        ]);

        $this->actingAs($this->makeAdmin(), 'web_admin')
            ->post(route('hari-libur.sync-api'), ['year' => 2026])
            ->assertRedirect();

        // Data valid tersimpan (nasional + cuti bersama)
        $this->assertTrue(HariLibur::whereDate('tanggal', '2026-12-25')->where('nama', 'Hari Raya Natal')->where('tipe', 'nasional')->exists());
        $this->assertTrue(HariLibur::whereDate('tanggal', '2026-07-07')->where('nama', 'Cuti Bersama')->where('tipe', 'cuti_bersama')->exists());
        // Data rusak (format tanggal salah) dibuang untuk cegah polusi
        $this->assertDatabaseMissing('hari_libur', ['nama' => 'Rusak']);
    }

    public function test_sync_api_gagal_jaringan_tidak_crash(): void
    {
        Http::fake([
            '*' => fn () => throw new ConnectionException('timeout'),
        ]);

        $this->actingAs($this->makeAdmin(), 'web_admin')
            ->post(route('hari-libur.sync-api'), ['year' => 2026])
            ->assertRedirect();

        $this->assertSame(0, HariLibur::whereYear('tanggal', 2026)->count());
    }
}
