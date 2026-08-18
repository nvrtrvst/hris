<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\UnitSekolah;
use App\Models\User;
use Database\Seeders\JabatanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PegawaiTemplateImportTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(JabatanSeeder::class);

        $this->unit = UnitSekolah::create([
            'nama' => 'SMP Import Test',
            'singkatan' => 'SMPIT',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'toleransi_menit' => 0,
            'toleransi_tap_menit' => 15,
        ]);

        $this->superadmin = User::factory()->create(['role' => 'superadmin']);
        $this->superadmin->assignRole('superadmin');
    }

    public function test_template_diunduh_sebagai_file_excel(): void
    {
        $response = $this->actingAs($this->superadmin, 'web_admin')->get(route('pegawai.template'));

        $response->assertOk();
        $this->assertStringContainsString('spreadsheet', $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
    }

    public function test_import_menolak_jabatan_yang_tidak_ada_dan_menampilkan_daftar_tersedia(): void
    {
        $csv = $this->csv('1234567890999991', 'Budi Santoso', 'JabatanTidakAda');

        $response = $this->actingAs($this->superadmin, 'web_admin')->post(route('pegawai.import'), [
            'unit_sekolah_id' => $this->unit->id,
            'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
        ]);

        $response->assertSessionHasErrors();
        $messages = implode(' ', session('errors')->all());
        $this->assertStringContainsString('tidak ditemukan', $messages);
        // Petunjuk daftar jabatan tersedia ikut tampil
        $this->assertStringContainsString('Jabatan yang tersedia', $messages);
        $this->assertStringContainsString('Guru Mata Pelajaran', $messages);
        $this->assertDatabaseMissing('pegawai', ['nik_hash' => Pegawai::nikHash('1234567890999991')]);
    }

    public function test_import_sukses_mencocokkan_jabatan_berdasarkan_nama_di_db(): void
    {
        $csv = $this->csv('1234567890999992', 'Siti Aminah', 'Guru Mata Pelajaran');

        $response = $this->actingAs($this->superadmin, 'web_admin')->post(route('pegawai.import'), [
            'unit_sekolah_id' => $this->unit->id,
            'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
        ]);

        $response->assertSessionHasNoErrors();

        $pegawai = Pegawai::where('nik_hash', Pegawai::nikHash('1234567890999992'))->first();
        $this->assertNotNull($pegawai);
        $this->assertNotNull($pegawai->user, 'User login seharusnya dibuat');

        $pivot = DB::table('pegawai_unit')->where('pegawai_id', $pegawai->id)->first();
        $this->assertNotNull($pivot);
        $this->assertEquals($this->unit->id, $pivot->unit_sekolah_id);
        $this->assertEquals(1, $pivot->is_primary);
        $this->assertEquals(
            Jabatan::where('nama', 'Guru Mata Pelajaran')->value('id'),
            $pivot->jabatan_id,
            'Jabatan harus di-attach dengan id dari master data, bukan nama mentah'
        );
    }

    public function test_import_menolak_nik_duplikat_di_db(): void
    {
        // Pegawai sudah ada lebih dulu (NIK tersimpan terenkripsi + nik_hash)
        Pegawai::create([
            'user_id' => $this->superadmin->id,
            'nik' => '1234567890999993',
            'nama_lengkap' => 'Pegawai Lama',
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'Menikah',
            'no_hp' => '081234567890',
            'alamat_ktp' => 'Jl. Lama No. 1',
            'status_kepegawaian' => 'tetap',
            'tanggal_mulai_kerja' => '2020-01-01',
            'pendidikan_terakhir' => 'S1',
            'status_aktif' => 'aktif',
        ]);

        $csv = $this->csv('1234567890999993', 'Budi Baru', 'Guru Mata Pelajaran');

        $response = $this->actingAs($this->superadmin, 'web_admin')->post(route('pegawai.import'), [
            'unit_sekolah_id' => $this->unit->id,
            'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
        ]);

        $response->assertSessionHasErrors();
        $this->assertStringContainsString('NIK sudah terdaftar', implode(' ', session('errors')->all()));
        $this->assertDatabaseCount('pegawai', 1);
    }

    private function csv(string $nik, string $nama, string $jabatan): string
    {
        $header = 'NIK,NIP,Nama Lengkap,Tempat Lahir,Tanggal Lahir,Jenis Kelamin,Agama,Status Pernikahan,No HP,Alamat KTP,Status Kepegawaian,Tanggal Mulai Kerja,Pendidikan Terakhir,Nama Jabatan';

        $row = implode(',', [
            $nik,
            '',
            $nama,
            'Jakarta',
            '1990-01-01',
            'L',
            'Islam',
            'Menikah',
            '081234567890',
            'Jl. Alamat No. 1',
            'tetap',
            '2020-01-01',
            'SMA/SMK',
            $jabatan,
        ]);

        return $header.PHP_EOL.$row;
    }
}
