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
            'toleransi_slide_menit' => 15,
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

        // Reference data di kolom V-Y sheet utama (bukan hidden sheet terpisah).
        $this->assertSame('_Jabatan', $sheet->getCell('V1')->getValue());
        $this->assertSame('_Pendidikan', $sheet->getCell('W1')->getValue());
        $this->assertSame('_Status', $sheet->getCell('X1')->getValue());
        $this->assertSame('_Unit', $sheet->getCell('Y1')->getValue());

        // Kolom tersembunyi.
        $this->assertFalse($sheet->getColumnDimension('V')->getVisible());
        $this->assertFalse($sheet->getColumnDimension('Y')->getVisible());

        // Named ranges ada di sheet yang SAMA.
        $this->assertNotNull($ss->getNamedRange('DAFTAR_JABATAN'));
        $this->assertNotNull($ss->getNamedRange('DAFTAR_PENDIDIKAN'));
        $this->assertNotNull($ss->getNamedRange('DAFTAR_STATUS'));
        $this->assertNotNull($ss->getNamedRange('DAFTAR_UNIT'));

        // Dropdown validation on data columns: E=Pendidikan, K=Jabatan, P=Status, Q=Unit.
        $this->assertSame('DAFTAR_JABATAN', $sheet->getDataValidation('K2')->getFormula1());
        $this->assertSame('DAFTAR_PENDIDIKAN', $sheet->getDataValidation('E2')->getFormula1());
        $this->assertSame('DAFTAR_STATUS', $sheet->getDataValidation('P2')->getFormula1());
        $this->assertSame('DAFTAR_UNIT', $sheet->getDataValidation('Q2')->getFormula1());
        $this->assertTrue($sheet->getDataValidation('K2')->getShowDropDown());
        $this->assertTrue($sheet->getDataValidation('Q2')->getShowDropDown());

        // Reference data values.
        $this->assertSame(Jabatan::orderBy('nama')->first()->nama, $sheet->getCell('V2')->getValue());
        $this->assertSame('SD/Sederajat', $sheet->getCell('W2')->getValue());
        $this->assertSame('guru_tetap_yayasan', $sheet->getCell('X2')->getValue());
        $this->assertSame(UnitSekolah::orderBy('nama')->first()->nama, $sheet->getCell('Y2')->getValue());
    }

    public function test_import_menolak_jabatan_yang_tidak_ada_dan_menampilkan_daftar_tersedia(): void
    {
        $csv = $this->csv('9999991', 'Budi Santoso', 'JabatanTidakAda');

        $response = $this->actingAs($this->superadmin, 'web_admin')->post(route('pegawai.import'), [
            'unit_sekolah_id' => $this->unit->id,
            'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
        ]);

        $response->assertSessionHasErrors();
        $messages = implode(' ', session('errors')->all());
        $this->assertStringContainsString('tidak ditemukan', $messages);
        $this->assertStringContainsString('Jabatan yang tersedia', $messages);
        $this->assertStringContainsString('Guru Mata Pelajaran', $messages);
    }

    public function test_import_sukses_mencocokkan_jabatan_berdasarkan_nama_di_db(): void
    {
        $csv = $this->csv('9999992', 'Siti Aminah', 'Guru Mata Pelajaran');

        $response = $this->actingAs($this->superadmin, 'web_admin')->post(route('pegawai.import'), [
            'unit_sekolah_id' => $this->unit->id,
            'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
        ]);

        $response->assertSessionHasNoErrors();

        $user = User::where('email', 'siti.aminah9999992@yayasan.com')->first();
        $this->assertNotNull($user);
        $pegawai = $user->pegawai;
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

    public function test_import_menolak_nuptk_duplikat_di_db(): void
    {
        // Pegawai sudah ada dengan NUPTK yang sama.
        Pegawai::create([
            'user_id' => $this->superadmin->id,
            'nuptk' => '1234567890999993',
            'nama_lengkap' => 'Pegawai Lama',
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'Menikah',
            'no_hp' => '081234567890',
            'alamat' => 'Jl. Lama No. 1',
            'status_kepegawaian' => 'guru_tetap_yayasan',
            'tmt_mengajar' => '2020-01-01',
            'pendidikan_terakhir' => 'S1',
            'status_aktif' => 'aktif',
        ]);

        $csv = $this->csv('1234567890999993', 'Budi Baru', 'Guru Mata Pelajaran');

        $response = $this->actingAs($this->superadmin, 'web_admin')->post(route('pegawai.import'), [
            'unit_sekolah_id' => $this->unit->id,
            'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
        ]);

        $response->assertSessionHasErrors();
        $this->assertStringContainsString('NUPTK sudah terdaftar', implode(' ', session('errors')->all()));
        $this->assertDatabaseCount('pegawai', 1);
    }

    public function test_import_unit_per_baris_multi_unit(): void
    {
        $other = $this->makeUnit('SMA');
        $csv = $this->csv('9999994', 'Dewi Lestari', 'Kasir', 'SMA');

        $response = $this->actingAs($this->superadmin, 'web_admin')->post(route('pegawai.import'), [
            'unit_sekolah_id' => $this->unit->id,
            'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
        ]);

        $response->assertSessionHasNoErrors();

        $user = User::where('email', 'dewi.lestari9999994@yayasan.com')->first();
        $pegawai = $user->pegawai;
        $this->assertSame($other->id, $pegawai->units()->first()->id, 'Kolom unit di template menimpa unit modal');
    }

    public function test_import_menolak_unit_yang_tidak_ada(): void
    {
        $csv = $this->csv('9999995', 'Andi Pratama', 'Kasir', 'UnitBogus');

        $response = $this->actingAs($this->superadmin, 'web_admin')->post(route('pegawai.import'), [
            'unit_sekolah_id' => $this->unit->id,
            'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
        ]);

        $response->assertSessionHasErrors();
        $messages = implode(' ', session('errors')->all());
        $this->assertStringContainsString('Unit \'UnitBogus\' tidak ditemukan', $messages);
        $this->assertStringContainsString('Unit yang tersedia', $messages);
    }

    public function test_admin_unit_dipaksa_ke_unitnya_sendiri(): void
    {
        $this->makeUnit('SMA');
        $adminUnit = User::factory()->create(['role' => 'admin_unit', 'unit_sekolah_id' => $this->unit->id]);
        $adminUnit->assignRole('admin_unit');

        // Kolom unit di template berisi 'SMA', tapi admin unit harus tetap ke unitnya sendiri.
        $csv = $this->csv('9999996', 'Rina Wati', 'Kasir', 'SMA');

        $response = $this->actingAs($adminUnit, 'web_admin')->post(route('pegawai.import'), [
            'unit_sekolah_id' => $this->unit->id,
            'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
        ]);

        $response->assertSessionHasNoErrors();

        $user = User::where('email', 'rina.wati9999996@yayasan.com')->first();
        $pegawai = $user->pegawai;
        $this->assertSame($this->unit->id, $pegawai->units()->first()->id, 'Admin unit tidak boleh import ke unit lain');
    }

    public function test_import_menolak_email_kosong_atau_invalid(): void
    {
        // Kolom Email (kolom 14, "EMAIL") wajib.
        $noEmail = "NAMA,TEMPAT LAHIR,TANGGAL LAHIR,JENIS KELAMIN,JENJANG / JURUSAN,TAHUN LULUS,ASAL SEKOLAH,NOMOR SK,TANGGAL SK,TMT MENGAJAR,JABATAN,NUPTK,ALAMAT,EMAIL,KONTAK,STATUS,UNIT SEKOLAH\n"
            .'Budi Santoso,Jakarta,1990-01-01,L,SMA/Sederajat,2015,SMA 1,SK/2020/001,2020-01-01,2020-01-01,Guru Mata Pelajaran,1234567890,Jl A,,081234567890,guru_tetap_yayasan,SMP'."\n";

        $this->actingAs($this->superadmin, 'web_admin')->post(route('pegawai.import'), [
            'unit_sekolah_id' => $this->unit->id,
            'file' => UploadedFile::fake()->createWithContent('import.csv', $noEmail),
        ])->assertSessionHasErrors();

        // Email invalid (bukan format email).
        $badEmail = "NAMA,TEMPAT LAHIR,TANGGAL LAHIR,JENIS KELAMIN,JENJANG / JURUSAN,TAHUN LULUS,ASAL SEKOLAH,NOMOR SK,TANGGAL SK,TMT MENGAJAR,JABATAN,NUPTK,ALAMAT,EMAIL,KONTAK,STATUS,UNIT SEKOLAH\n"
            .'Budi Santoso,Jakarta,1990-01-01,L,SMA/Sederajat,2015,SMA 1,SK/2020/001,2020-01-01,2020-01-01,Guru Mata Pelajaran,1234567891,Jl A,bukan-email,081234567890,guru_tetap_yayasan,SMP'."\n";

        $response = $this->actingAs($this->superadmin, 'web_admin')->post(route('pegawai.import'), [
            'unit_sekolah_id' => $this->unit->id,
            'file' => UploadedFile::fake()->createWithContent('import.csv', $badEmail),
        ]);

        $response->assertSessionHasErrors();
        $this->assertStringContainsString('email', implode(' ', session('errors')->all()));
    }

    public function test_import_parse_jenjang_jurusan_dari_slash(): void
    {
        $header = 'NAMA,TEMPAT LAHIR,TANGGAL LAHIR,JENIS KELAMIN,JENJANG / JURUSAN,TAHUN LULUS,ASAL SEKOLAH,NOMOR SK,TANGGAL SK,TMT MENGAJAR,JABATAN,NUPTK,ALAMAT,EMAIL,KONTAK,STATUS,UNIT SEKOLAH';
        $row = implode(',', [
            'Eka Putri',
            'Jakarta',
            '1990-01-01',
            'L',
            'S2 / B.INDONESIA',
            '2015',
            'UPI',
            'SK/2020/001',
            '2020-01-01',
            '2020-01-01',
            'Guru Mata Pelajaran',
            '9999997',
            'Jl. Alamat No. 1',
            'eka.putri9999997@yayasan.com',
            '081234567890',
            'guru_tetap_yayasan',
            '',
        ]);
        $csv = $header.PHP_EOL.$row;

        $response = $this->actingAs($this->superadmin, 'web_admin')->post(route('pegawai.import'), [
            'unit_sekolah_id' => $this->unit->id,
            'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
        ]);

        $response->assertSessionHasNoErrors();

        $user = User::where('email', 'eka.putri9999997@yayasan.com')->first();
        $pegawai = $user->pegawai;
        $this->assertSame('S2', $pegawai->pendidikan_terakhir);
        $this->assertSame('B.INDONESIA', $pegawai->pendidikan_jurusan);
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
            'toleransi_slide_menit' => 15,
        ]);
    }

    /**
     * Build a 17-column GTYS CSV row.
     * Columns: NAMA, TEMPAT LAHIR, TANGGAL LAHIR, JENIS KELAMIN, JENJANG/JURUSAN,
     *          TAHUN LULUS, ASAL SEKOLAH, NOMOR SK, TANGGAL SK, TMT MENGAJAR,
     *          JABATAN, NUPTK, ALAMAT, EMAIL, KONTAK, STATUS, UNIT SEKOLAH.
     */
    private function csv(string $nuptk, string $nama, string $jabatan, string $unit = ''): string
    {
        $header = 'NAMA,TEMPAT LAHIR,TANGGAL LAHIR,JENIS KELAMIN,JENJANG / JURUSAN,TAHUN LULUS,ASAL SEKOLAH,NOMOR SK,TANGGAL SK,TMT MENGAJAR,JABATAN,NUPTK,ALAMAT,EMAIL,KONTAK,STATUS,UNIT SEKOLAH';

        $emailLocal = strtolower(str_replace(' ', '.', $nama)).$nuptk;
        $row = implode(',', [
            $nama,
            'Jakarta',
            '1990-01-01',
            'L',
            'S1',
            '2015',
            'SMA Negeri 1',
            'SK/2020/001',
            '2020-01-01',
            '2020-01-01',
            $jabatan,
            $nuptk,
            'Jl. Alamat No. 1',
            $emailLocal.'@yayasan.com',
            '081234567890',
            'guru_tetap_yayasan',
            $unit,
        ]);

        return $header.PHP_EOL.$row;
    }
}
