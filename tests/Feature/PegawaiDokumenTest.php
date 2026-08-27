<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\PegawaiDokumen;
use App\Models\UnitSekolah;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PegawaiDokumenTest extends TestCase
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

        // Semua test memakai disk lokal palsu — jangan pernah menulis file nyata.
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

    private function makeAdminUnit(UnitSekolah $unit): User
    {
        $admin = User::factory()->create([
            'role' => 'admin_unit',
            'unit_sekolah_id' => $unit->id,
        ]);
        $admin->assignRole('admin_unit');

        return $admin;
    }

    private function makeSuperadmin(): User
    {
        $admin = User::factory()->create(['role' => 'superadmin']);
        $admin->assignRole('superadmin');

        return $admin;
    }

    private function makePegawai(string $nik, UnitSekolah $unit): Pegawai
    {
        $jabatan = Jabatan::create(['nama' => 'Guru']);
        $pegawai = Pegawai::create([
            'nik' => $nik,
            'nama_lengkap' => 'Pegawai '.$nik,
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '1992-03-03',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'kawin',
            'jumlah_tanggungan' => 0,
            'alamat_ktp' => 'Jl. Dokumen Test No. 1',
            'no_hp' => '0812'.substr($nik, -7),
            'status_kepegawaian' => 'honorer',
            'tanggal_mulai_kerja' => '2021-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
        ]);
        $pegawai->units()->attach($unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    private function makeDokumen(Pegawai $pegawai, string $path = 'dokumen/pegawai_1/abc.pdf'): PegawaiDokumen
    {
        Storage::disk('local')->put($path, 'fake-pdf-content');

        return PegawaiDokumen::create([
            'pegawai_id' => $pegawai->id,
            'nama_dokumen' => 'SK Pengangkatan',
            'jenis' => 'SK',
            'file_path' => $path,
            'keterangan' => null,
        ]);
    }

    public function test_superadmin_bisa_upload_dokumen(): void
    {
        $superadmin = $this->makeSuperadmin();
        $pegawai = $this->makePegawai('1111222233334444', $this->unitA);

        $this->actingAs($superadmin, 'web_admin')
            ->from(route('pegawai.show', $pegawai->id))
            ->post(route('pegawai.dokumen.store', $pegawai->id), [
                'nama_dokumen' => 'SK Pengangkatan 2024',
                'jenis' => 'SK',
                'keterangan' => 'Disahkan kepala yayasan',
                'file' => UploadedFile::fake()->create('sk.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('pegawai.show', $pegawai->id));

        $this->assertDatabaseHas('pegawai_dokumen', [
            'pegawai_id' => $pegawai->id,
            'nama_dokumen' => 'SK Pengangkatan 2024',
            'jenis' => 'SK',
        ]);

        $dokumen = PegawaiDokumen::firstOrFail();
        Storage::disk('local')->assertExists($dokumen->file_path);
    }

    public function test_upload_menolak_file_bukan_pdf_gambar(): void
    {
        $superadmin = $this->makeSuperadmin();
        $pegawai = $this->makePegawai('2222333344445555', $this->unitA);

        $this->actingAs($superadmin, 'web_admin')
            ->from(route('pegawai.show', $pegawai->id))
            ->post(route('pegawai.dokumen.store', $pegawai->id), [
                'nama_dokumen' => 'File mencurigakan',
                'jenis' => 'Lainnya',
                'file' => UploadedFile::fake()->create('virus.exe', 100, 'application/octet-stream'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('pegawai_dokumen', 0);
    }

    public function test_superadmin_bisa_download_dokumen(): void
    {
        $superadmin = $this->makeSuperadmin();
        $pegawai = $this->makePegawai('3333444455556666', $this->unitA);
        $dokumen = $this->makeDokumen($pegawai);

        $this->actingAs($superadmin, 'web_admin')
            ->get(route('pegawai.dokumen.download', [$pegawai->id, $dokumen->id]))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename="SK Pengangkatan-Pegawai_3333444455556666-'.now()->format('Ymd').'.pdf"');
    }

    public function test_admin_unit_tidak_bisa_upload_ke_pegawai_unit_lain(): void
    {
        $adminA = $this->makeAdminUnit($this->unitA);
        $pegawaiB = $this->makePegawai('4444555566667777', $this->unitB);

        $this->actingAs($adminA, 'web_admin')
            ->post(route('pegawai.dokumen.store', $pegawaiB->id), [
                'nama_dokumen' => 'Mau akses lintas unit',
                'jenis' => 'SK',
                'file' => UploadedFile::fake()->create('sk.pdf', 100, 'application/pdf'),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('pegawai_dokumen', 0);
    }

    public function test_admin_unit_tidak_bisa_download_dokumen_pegawai_unit_lain(): void
    {
        $adminA = $this->makeAdminUnit($this->unitA);
        $pegawaiB = $this->makePegawai('5555666677778888', $this->unitB);
        $dokumenB = $this->makeDokumen($pegawaiB);

        $this->actingAs($adminA, 'web_admin')
            ->get(route('pegawai.dokumen.download', [$pegawaiB->id, $dokumenB->id]))
            ->assertForbidden();
    }

    public function test_admin_unit_bisa_download_dokumen_pegawai_unit_sendiri(): void
    {
        $adminA = $this->makeAdminUnit($this->unitA);
        $pegawaiA = $this->makePegawai('6666777788889999', $this->unitA);
        $dokumenA = $this->makeDokumen($pegawaiA, 'dokumen/pegawai_2/abc.pdf');

        $this->actingAs($adminA, 'web_admin')
            ->get(route('pegawai.dokumen.download', [$pegawaiA->id, $dokumenA->id]))
            ->assertOk();
    }

    public function test_destroy_menghapus_record_dan_file_fisik(): void
    {
        $superadmin = $this->makeSuperadmin();
        $pegawai = $this->makePegawai('7777888899990000', $this->unitA);
        $dokumen = $this->makeDokumen($pegawai, 'dokumen/pegawai_1/delete.pdf');

        $this->actingAs($superadmin, 'web_admin')
            ->from(route('pegawai.show', $pegawai->id))
            ->delete(route('pegawai.dokumen.destroy', [$pegawai->id, $dokumen->id]))
            ->assertRedirect(route('pegawai.show', $pegawai->id));

        $this->assertDatabaseMissing('pegawai_dokumen', ['id' => $dokumen->id]);
        Storage::disk('local')->assertMissing('dokumen/pegawai_1/delete.pdf');
    }

    public function test_guest_dialihkan_ke_login(): void
    {
        $pegawai = $this->makePegawai('8888999900001111', $this->unitA);
        $dokumen = $this->makeDokumen($pegawai);

        $this->get(route('pegawai.dokumen.download', [$pegawai->id, $dokumen->id]))
            ->assertRedirect(route('login'));
    }
}
