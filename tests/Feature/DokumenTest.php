<?php

namespace Tests\Feature;

use App\Models\Dokumen;
use App\Models\UnitSekolah;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DokumenTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unitA;

    private UnitSekolah $unitB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->unitA = $this->makeUnit('SMP Test A', 'SMPA');
        $this->unitB = $this->makeUnit('SMP Test B', 'SMPB');

        Storage::fake('local');
    }

    private function makeUnit(string $nama, string $singkatan): UnitSekolah
    {
        return UnitSekolah::create([
            'nama' => $nama,
            'singkatan' => $singkatan,
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'toleransi_menit' => 0,
        ]);
    }

    private function makeUser(string $role, ?UnitSekolah $unit = null): User
    {
        $user = User::factory()->create([
            'role' => $role,
            'unit_sekolah_id' => $unit?->id,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function makeDokumen(?UnitSekolah $unit, array $overrides = []): Dokumen
    {
        $folder = $unit ? 'unit_'.$unit->id : 'yayasan';
        $path = 'dokumen/'.$folder.'/'.uniqid().'.pdf';
        Storage::disk('local')->put($path, 'fake-pdf-content');

        return Dokumen::create(array_merge([
            'unit_sekolah_id' => $unit?->id,
            'judul' => 'Akta Yayasan',
            'kategori' => 'legal',
            'file_path' => $path,
            'file_name' => 'akta.pdf',
        ], $overrides));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'judul' => 'Akta Yayasan',
            'kategori' => 'legal',
            'file' => UploadedFile::fake()->create('akta.pdf', 100, 'application/pdf'),
        ], $overrides);
    }

    public function test_superadmin_bisa_upload_dokumen_yayasan(): void
    {
        $superadmin = $this->makeUser('superadmin');

        $this->actingAs($superadmin, 'web_admin')
            ->from(route('dokumen.index'))
            ->post(route('dokumen.store'), $this->payload())
            ->assertRedirect(route('dokumen.index'));

        $this->assertDatabaseHas('dokumen', [
            'judul' => 'Akta Yayasan',
            'unit_sekolah_id' => null,
            'kategori' => 'legal',
        ]);

        $dokumen = Dokumen::firstOrFail();
        Storage::disk('local')->assertExists($dokumen->file_path);
    }

    public function test_admin_unit_bisa_upload_dokumen_unit_sendiri(): void
    {
        $admin = $this->makeUser('admin_unit', $this->unitA);

        $this->actingAs($admin, 'web_admin')
            ->from(route('dokumen.index'))
            ->post(route('dokumen.store'), $this->payload([
                'unit_sekolah_id' => $this->unitA->id,
            ]))
            ->assertRedirect(route('dokumen.index'));

        $this->assertDatabaseHas('dokumen', [
            'judul' => 'Akta Yayasan',
            'unit_sekolah_id' => $this->unitA->id,
        ]);
    }

    public function test_admin_unit_tidak_bisa_upload_dokumen_yayasan(): void
    {
        $admin = $this->makeUser('admin_unit', $this->unitA);

        $this->actingAs($admin, 'web_admin')
            ->post(route('dokumen.store'), $this->payload())
            ->assertForbidden();

        $this->assertDatabaseCount('dokumen', 0);
    }

    public function test_admin_unit_tidak_bisa_upload_dokumen_unit_lain(): void
    {
        $admin = $this->makeUser('admin_unit', $this->unitA);

        $this->actingAs($admin, 'web_admin')
            ->post(route('dokumen.store'), $this->payload([
                'unit_sekolah_id' => $this->unitB->id,
            ]))
            ->assertForbidden();

        $this->assertDatabaseCount('dokumen', 0);
    }

    public function test_admin_unit_hanya_melihat_dokumen_yayasan_dan_unit_sendiri(): void
    {
        $admin = $this->makeUser('admin_unit', $this->unitA);
        $this->makeDokumen(null, ['judul' => 'Dokumen Yayasan']);
        $this->makeDokumen($this->unitA, ['judul' => 'Dokumen Unit A']);
        $this->makeDokumen($this->unitB, ['judul' => 'Dokumen Unit B']);

        $this->actingAs($admin, 'web_admin')
            ->get(route('dokumen.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dokumen/Index')
                ->has('dokumens.data', 2)
                ->where('dokumens.data.0.judul', 'Dokumen Unit A')
                ->where('dokumens.data.1.judul', 'Dokumen Yayasan'));
    }

    public function test_pimpinan_bisa_melihat_tapi_tidak_bisa_mutasi(): void
    {
        $pimpinan = $this->makeUser('pimpinan', $this->unitA);
        $dokumen = $this->makeDokumen($this->unitA);

        $this->actingAs($pimpinan, 'web_admin')
            ->get(route('dokumen.index'))
            ->assertOk();

        $this->actingAs($pimpinan, 'web_admin')
            ->post(route('dokumen.store'), $this->payload([
                'unit_sekolah_id' => $this->unitA->id,
            ]))
            ->assertForbidden();

        $this->actingAs($pimpinan, 'web_admin')
            ->put(route('dokumen.update', $dokumen->id), [
                'judul' => 'Diubah',
                'kategori' => 'legal',
                'unit_sekolah_id' => $this->unitA->id,
            ])
            ->assertForbidden();

        $this->actingAs($pimpinan, 'web_admin')
            ->delete(route('dokumen.destroy', $dokumen->id))
            ->assertForbidden();
    }

    public function test_upload_menolak_file_tidak_didukung(): void
    {
        $superadmin = $this->makeUser('superadmin');

        $this->actingAs($superadmin, 'web_admin')
            ->post(route('dokumen.store'), $this->payload([
                'file' => UploadedFile::fake()->create('virus.exe', 100, 'application/octet-stream'),
            ]))
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('dokumen', 0);
    }

    public function test_download_terproteksi_scope(): void
    {
        $admin = $this->makeUser('admin_unit', $this->unitA);
        $superadmin = $this->makeUser('superadmin');
        $dokumenA = $this->makeDokumen($this->unitA);
        $dokumenB = $this->makeDokumen($this->unitB);

        $this->actingAs($admin, 'web_admin')
            ->get(route('dokumen.download', $dokumenB->id))
            ->assertForbidden();

        $this->actingAs($admin, 'web_admin')
            ->get(route('dokumen.download', $dokumenA->id))
            ->assertOk();

        $this->actingAs($superadmin, 'web_admin')
            ->get(route('dokumen.download', $dokumenB->id))
            ->assertOk();
    }

    public function test_update_mengganti_file_dan_menghapus_file_lama(): void
    {
        $admin = $this->makeUser('admin_unit', $this->unitA);
        $dokumen = $this->makeDokumen($this->unitA);
        $oldPath = $dokumen->file_path;

        $this->actingAs($admin, 'web_admin')
            ->put(route('dokumen.update', $dokumen->id), [
                'judul' => 'Akta Yayasan Revisi',
                'kategori' => 'kelembagaan',
                'unit_sekolah_id' => $this->unitA->id,
                'file' => UploadedFile::fake()->create('revisi.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect();

        $dokumen->refresh();

        $this->assertSame('Akta Yayasan Revisi', $dokumen->judul);
        $this->assertSame('kelembagaan', $dokumen->kategori);
        $this->assertNotSame($oldPath, $dokumen->file_path);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($dokumen->file_path);
    }

    public function test_destroy_menghapus_record_dan_file(): void
    {
        $admin = $this->makeUser('admin_unit', $this->unitA);
        $dokumen = $this->makeDokumen($this->unitA);
        $path = $dokumen->file_path;

        $this->actingAs($admin, 'web_admin')
            ->delete(route('dokumen.destroy', $dokumen->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('dokumen', ['id' => $dokumen->id]);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_filter_status_kedaluwarsa(): void
    {
        $superadmin = $this->makeUser('superadmin');
        $this->makeDokumen(null, [
            'judul' => 'Sudah Kedaluwarsa',
            'tanggal_kedaluwarsa' => now()->subDay()->toDateString(),
        ]);
        $this->makeDokumen(null, [
            'judul' => 'Masih Berlaku',
            'tanggal_kedaluwarsa' => now()->addYear()->toDateString(),
        ]);

        $this->actingAs($superadmin, 'web_admin')
            ->get(route('dokumen.index', ['status' => 'expired']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dokumen/Index')
                ->has('dokumens.data', 1)
                ->where('dokumens.data.0.judul', 'Sudah Kedaluwarsa'));
    }

    public function test_guest_dialihkan_ke_login(): void
    {
        $this->get(route('dokumen.index'))->assertRedirect(route('login'));
    }
}
