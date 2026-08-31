<?php

namespace Tests\Feature;

use App\Exports\PegawaiTemplateExport;
use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\UnitSekolah;
use App\Models\User;
use Database\Seeders\JabatanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
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

    public function test_template_dropdown_bersumber_dari_kolom_tersembunyi(): void
    {
        Excel::store(new PegawaiTemplateExport, 'tpl_check.xlsx', 'local');
        $ss = IOFactory::load(Storage::disk('local')->path('tpl_check.xlsx'));
        $sheet = $ss->getSheet(0);
        Storage::disk('local')->delete('tpl_check.xlsx');

        // Reference data ada di kolom R-U sheet utama (bukan hidden sheet terpisah)
        $this->assertSame('_Jabatan', $sheet->getCell('R1')->getValue());
        $this->assertSame('_Pendidikan', $sheet->getCell('S1')->getValue());
        $this->assertSame('_Status', $sheet->getCell('T1')->getValue());
        $this->assertSame('_Unit', $sheet->getCell('U1')->getValue());

        // Kolom tersembunyi
        $this->assertFalse($sheet->getColumnDimension('R')->getVisible());
        $this->assertFalse($sheet->getColumnDimension('U')->getVisible());

        // Named ranges exist on the SAME sheet
        $this->assertNotNull($ss->getNamedRange('DAFTAR_JABATAN'));
        $this->assertNotNull($ss->getNamedRange('DAFTAR_PENDIDIKAN'));
        $this->assertNotNull($ss->getNamedRange('DAFTAR_STATUS'));
        $this->assertNotNull($ss->getNamedRange('DAFTAR_UNIT'));

        // Dropdown validation on data columns
        $this->assertSame('DAFTAR_JABATAN', $sheet->getDataValidation('N2')->getFormula1());
        $this->assertSame('DAFTAR_PENDIDIKAN', $sheet->getDataValidation('M2')->getFormula1());
        $this->assertSame('DAFTAR_STATUS', $sheet->getDataValidation('K2')->getFormula1());
        $this->assertSame('DAFTAR_UNIT', $sheet->getDataValidation('O2')->getFormula1());
        $this->assertTrue($sheet->getDataValidation('N2')->getShowDropDown());
        $this->assertTrue($sheet->getDataValidation('O2')->getShowDropDown());

        // Reference data values
        $this->assertSame(Jabatan::orderBy('nama')->first()->nama, $sheet->getCell('R2')->getValue());
        $this->assertSame('SD/Sederajat', $sheet->getCell('S2')->getValue());
        $this->assertSame('tetap', $sheet->getCell('T2')->getValue());
        $this->assertSame(UnitSekolah::orderBy('nama')->first()->nama, $sheet->getCell('U2')->getValue());
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

    public function test_import_unit_per_baris_multi_unit(): void
    {
        $other = $this->makeUnit('SMA');
        $csv = $this->csv('1234567890999994', 'Dewi Lestari', 'Kasir', 'SMA');

        $response = $this->actingAs($this->superadmin, 'web_admin')->post(route('pegawai.import'), [
            'unit_sekolah_id' => $this->unit->id,
            'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
        ]);

        $response->assertSessionHasNoErrors();

        $pegawai = Pegawai::where('nik_hash', Pegawai::nikHash('1234567890999994'))->first();
        $this->assertNotNull($pegawai);
        $this->assertSame($other->id, $pegawai->units()->first()->id, 'Kolom unit di template menimpa unit modal');
    }

    public function test_import_menolak_unit_yang_tidak_ada(): void
    {
        $csv = $this->csv('1234567890999995', 'Andi Pratama', 'Kasir', 'UnitBogus');

        $response = $this->actingAs($this->superadmin, 'web_admin')->post(route('pegawai.import'), [
            'unit_sekolah_id' => $this->unit->id,
            'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
        ]);

        $response->assertSessionHasErrors();
        $messages = implode(' ', session('errors')->all());
        $this->assertStringContainsString('Unit \'UnitBogus\' tidak ditemukan', $messages);
        $this->assertStringContainsString('Unit yang tersedia', $messages);
        $this->assertDatabaseMissing('pegawai', ['nik_hash' => Pegawai::nikHash('1234567890999995')]);
    }

    public function test_admin_unit_dipaksa_ke_unitnya_sendiri(): void
    {
        $this->makeUnit('SMA');
        $adminUnit = User::factory()->create(['role' => 'admin_unit', 'unit_sekolah_id' => $this->unit->id]);
        $adminUnit->assignRole('admin_unit');

        // Kolom unit di template berisi 'SMA', tapi admin unit tetap harus masuk unitnya sendiri.
        $csv = $this->csv('1234567890999996', 'Rina Wati', 'Kasir', 'SMA');

        $response = $this->actingAs($adminUnit, 'web_admin')->post(route('pegawai.import'), [
            'unit_sekolah_id' => $this->unit->id,
            'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
        ]);

        $response->assertSessionHasNoErrors();

        $pegawai = Pegawai::where('nik_hash', Pegawai::nikHash('1234567890999996'))->first();
        $this->assertNotNull($pegawai);
        $this->assertSame($this->unit->id, $pegawai->units()->first()->id, 'Admin unit tidak boleh import ke unit lain');
    }

    public function test_import_menolak_email_kosong_atau_invalid(): void
    {
        // Tanpa kolom Email (15 kolom)
        $noEmail = "NIK,NIP,Nama Lengkap,Tempat Lahir,Tanggal Lahir,Jenis Kelamin,Agama,Status Pernikahan,No HP,Alamat KTP,Status Kepegawaian,Tanggal Mulai Kerja,Pendidikan Terakhir,Nama Jabatan,Unit Sekolah\n"
            .'1234567890999998,,Budi Santoso,Jakarta,1990-01-01,L,Islam,Menikah,081234567890,Jl A,tetap,2020-01-01,SMA/SMK,Guru Mata Pelajaran,SMP'."\n";

        $this->actingAs($this->superadmin, 'web_admin')->post(route('pegawai.import'), [
            'unit_sekolah_id' => $this->unit->id,
            'file' => UploadedFile::fake()->createWithContent('import.csv', $noEmail),
        ])->assertSessionHasErrors();

        // Email invalid
        $badEmail = "NIK,NIP,Nama Lengkap,Tempat Lahir,Tanggal Lahir,Jenis Kelamin,Agama,Status Pernikahan,No HP,Alamat KTP,Status Kepegawaian,Tanggal Mulai Kerja,Pendidikan Terakhir,Nama Jabatan,Unit Sekolah,Email\n"
            .'1234567890999999,,Budi Santoso,Jakarta,1990-01-01,L,Islam,Menikah,081234567890,Jl A,tetap,2020-01-01,SMA/SMK,Guru Mata Pelajaran,SMP,bukan-email'."\n";

        $response = $this->actingAs($this->superadmin, 'web_admin')->post(route('pegawai.import'), [
            'unit_sekolah_id' => $this->unit->id,
            'file' => UploadedFile::fake()->createWithContent('import.csv', $badEmail),
        ]);

        $response->assertSessionHasErrors();
        $this->assertStringContainsString('email', implode(' ', session('errors')->all()));
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

    private function csv(string $nik, string $nama, string $jabatan, string $unit = ''): string
    {
        $header = 'NIK,NIP,Nama Lengkap,Tempat Lahir,Tanggal Lahir,Jenis Kelamin,Agama,Status Pernikahan,No HP,Alamat KTP,Status Kepegawaian,Tanggal Mulai Kerja,Pendidikan Terakhir,Nama Jabatan,Unit Sekolah,Email';

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
            $unit,
            $nik.'@yayasan.com',
        ]);

        return $header.PHP_EOL.$row;
    }
}
