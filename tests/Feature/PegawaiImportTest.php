<?php

namespace Tests\Feature;

use App\Imports\PegawaiImport;
use App\Models\Jabatan;
use App\Models\UnitSekolah;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class PegawaiImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function makeUnit(): UnitSekolah
    {
        return UnitSekolah::create([
            'nama' => 'SMP Import Test',
            'singkatan' => 'SMPI',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'toleransi_menit' => 0,
        ]);
    }

    /**
     * Template GTYS 17 kolom (A..Q).
     * Kolom: NAMA, TEMPAT LAHIR, TANGGAL LAHIR, JENIS KELAMIN,
     *        JENJANG / JURUSAN, TAHUN LULUS, ASAL SEKOLAH, NOMOR SK,
     *        TANGGAL SK, TMT MENGAJAR, JABATAN, NUPTK, ALAMAT, EMAIL,
     *        KONTAK, STATUS, UNIT SEKOLAH.
     */
    private function csvFile(): UploadedFile
    {
        $csv = "NAMA,TEMPAT LAHIR,TANGGAL LAHIR,JENIS KELAMIN,JENJANG / JURUSAN,TAHUN LULUS,ASAL SEKOLAH,NOMOR SK,TANGGAL SK,TMT MENGAJAR,JABATAN,NUPTK,ALAMAT,EMAIL,KONTAK,STATUS,UNIT SEKOLAH\n"
            ."Budi Santoso,Solo,1990-01-01,L,S1 / PENDIDIKAN,2015,UPI,SK/2020/001,2020-01-01,2020-01-01,Guru,1234567890,Jl A,budi.real@yayasan.com,081299887766,guru_tetap_yayasan,\n";

        return UploadedFile::fake()->createWithContent('pegawai.csv', $csv, 'text/csv');
    }

    public function test_password_default_seragam_diterapkan_dan_wajib_ganti(): void
    {
        Jabatan::create(['nama' => 'Guru']);
        $unit = $this->makeUnit();

        Excel::import(new PegawaiImport($unit->id, false, 'SmkNm@160'), $this->csvFile());

        $user = User::where('email', 'budi.real@yayasan.com')->first();
        $this->assertNotNull($user, 'User harus dibuat dari import.');
        $this->assertSame('budi.real@yayasan.com', $user->email, 'Email diambil dari kolom template (bukan fallback NUPTK).');
        $this->assertTrue(Hash::check('SmkNm@160', $user->password), 'Password harus = default seragam (hash).');
        $this->assertTrue($user->force_password_change, 'User wajib ganti password saat login pertama.');
    }

    public function test_tanpa_password_default_username_dari_nuptk_dan_tidak_wajib_ganti(): void
    {
        Jabatan::create(['nama' => 'Guru']);
        $unit = $this->makeUnit();

        Excel::import(new PegawaiImport($unit->id, false, null), $this->csvFile());

        $user = User::where('email', 'budi.real@yayasan.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('1234567890', $user->password), 'Tanpa default: password = NUPTK.');
        $this->assertSame('1234567890', $user->username, 'Username = NUPTK.');
        $this->assertFalse($user->force_password_change, 'Tanpa default: tidak wajib ganti password.');
    }

    public function test_email_duplikat_dalam_file_ditolak(): void
    {
        Jabatan::create(['nama' => 'Guru']);
        $unit = $this->makeUnit();

        $csv = "NAMA,TEMPAT LAHIR,TANGGAL LAHIR,JENIS KELAMIN,JENJANG / JURUSAN,TAHUN LULUS,ASAL SEKOLAH,NOMOR SK,TANGGAL SK,TMT MENGAJAR,JABATAN,NUPTK,ALAMAT,EMAIL,KONTAK,STATUS,UNIT SEKOLAH\n"
            ."Budi Santoso,Solo,1990-01-01,L,S1,2015,UPI,SK1,2020-01-01,2020-01-01,Guru,1111111111,Jl A,sama@yayasan.com,081299887766,guru_tetap_yayasan,\n"
            ."Siti Aminah,Solo,1990-01-01,L,S1,2015,UPI,SK2,2020-01-01,2020-01-01,Guru,2222222222,Jl A,sama@yayasan.com,081299887767,guru_tidak_tetap,\n";

        $file = UploadedFile::fake()->createWithContent('pegawai.csv', $csv, 'text/csv');

        $this->expectException(ValidationException::class);
        Excel::import(new PegawaiImport($unit->id, false, null), $file);
    }
}
