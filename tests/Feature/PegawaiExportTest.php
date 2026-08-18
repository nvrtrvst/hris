<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\UnitSekolah;
use App\Models\User;
use Database\Seeders\JabatanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class PegawaiExportTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unitA;

    private UnitSekolah $unitB;

    private User $superadmin;

    private User $adminUnit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(JabatanSeeder::class);

        $this->unitA = $this->makeUnit('SMP Export');
        $this->unitB = $this->makeUnit('SMA Export');

        $this->superadmin = User::factory()->create(['role' => 'superadmin']);
        $this->superadmin->assignRole('superadmin');

        $this->adminUnit = User::factory()->create(['role' => 'admin_unit', 'unit_sekolah_id' => $this->unitA->id]);
        $this->adminUnit->assignRole('admin_unit');

        $this->makePegawai('1111111111111111', 'Guru Pendidik', $this->unitA, 'Guru Mata Pelajaran', $this->superadmin);
        $this->makePegawai('2222222222222222', 'Staf Tendik', $this->unitB, 'Tenaga Administrasi (TU)', $this->adminUnit);
    }

    public function test_export_superadmin_mengandung_nik_plaintext(): void
    {
        $response = $this->actingAs($this->superadmin, 'web_admin')->get(route('pegawai.export'));

        $response->assertOk();
        $this->assertStringContainsString('spreadsheet', $response->headers->get('content-type'));

        $sheet = $this->loadSheet($response);
        $this->assertSame('NIK', $sheet->getCell('A1')->getValue());
        $this->assertSame('Nama Lengkap', $sheet->getCell('C1')->getValue());

        // Superadmin punya view_sensitive_data → NIK asli 16 digit
        $values = $this->columnValues($sheet, 1);
        $this->assertContains('1111111111111111', $values);
        $this->assertContains('2222222222222222', $values);
    }

    public function test_export_admin_unit_nik_masked_dan_terbatas_unitnya(): void
    {
        $response = $this->actingAs($this->adminUnit, 'web_admin')->get(route('pegawai.export'));

        $response->assertOk();
        $sheet = $this->loadSheet($response);

        // Hanya pegawai unit A (1 baris data) — pegawai unit B tidak bocor
        $names = $this->columnValues($sheet, 3);
        $this->assertContains('Guru Pendidik', $names);
        $this->assertNotContains('Staf Tendik', $names);

        // NIK ter-mask (mengandung *), bukan plaintext
        $niks = $this->columnValues($sheet, 1);
        $this->assertNotEmpty($niks);
        $this->assertStringContainsString('*', $niks[0]);
        $this->assertNotContains('1111111111111111', $niks);
    }

    public function test_export_mengikuti_filter_jenis(): void
    {
        $response = $this->actingAs($this->superadmin, 'web_admin')->get(route('pegawai.export', ['jenis_filter' => 'kependidikan']));

        $sheet = $this->loadSheet($response);
        $names = $this->columnValues($sheet, 3);

        $this->assertContains('Staf Tendik', $names);
        $this->assertNotContains('Guru Pendidik', $names);
    }

    public function test_export_mengikuti_filter_unit(): void
    {
        $response = $this->actingAs($this->superadmin, 'web_admin')->get(route('pegawai.export', ['unit_sekolah_id' => $this->unitB->id]));

        $sheet = $this->loadSheet($response);
        $names = $this->columnValues($sheet, 3);

        $this->assertContains('Staf Tendik', $names);
        $this->assertNotContains('Guru Pendidik', $names);
    }

    private function loadSheet($response)
    {
        $file = $response->baseResponse->getFile();

        return IOFactory::load($file->getPathname())->getSheet(0);
    }

    private function columnValues($sheet, int $col): array
    {
        $values = [];
        $highest = $sheet->getHighestDataRow();
        for ($row = 2; $row <= $highest; $row++) {
            $values[] = (string) $sheet->getCellByColumnAndRow($col, $row)->getValue();
        }

        return $values;
    }

    private function makeUnit(string $nama): UnitSekolah
    {
        return UnitSekolah::create([
            'nama' => $nama,
            'singkatan' => strtoupper(substr($nama, 0, 3)),
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'toleransi_menit' => 0,
            'toleransi_tap_menit' => 15,
        ]);
    }

    private function makePegawai(string $nik, string $nama, UnitSekolah $unit, string $jabatanNama, User $user): Pegawai
    {
        $jabatan = Jabatan::where('nama', $jabatanNama)->firstOrFail();

        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => $nik,
            'nip' => $nik,
            'nama_lengkap' => $nama,
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'Menikah',
            'no_hp' => '081234567890',
            'alamat_ktp' => 'Jl. Contoh No. 1',
            'status_kepegawaian' => 'tetap',
            'tanggal_mulai_kerja' => '2020-01-01',
            'pendidikan_terakhir' => 'S1',
            'status_aktif' => 'aktif',
            'jumlah_tanggungan' => 2,
        ]);
        $pegawai->units()->attach($unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }
}
