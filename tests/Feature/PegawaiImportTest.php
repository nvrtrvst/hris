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

    private function csvFile(): UploadedFile
    {
        $csv = "NIK,NIP,Nama,Tmp,Lhr,JK,Agama,St,HP,Alamat,Stk,Tgl,Didik,Jab,Unit,Email\n"
            ."8899776655443322,,Budi Santoso,Solo,1990-01-01,L,Islam,kawin,081299887766,Jl A,tetap,2020-01-01,S1,Guru,,budi.real@yayasan.com\n";

        return UploadedFile::fake()->createWithContent('pegawai.csv', $csv, 'text/csv');
    }

    public function test_password_default_seragam_diterapkan_dan_wajib_ganti(): void
    {
        Jabatan::create(['nama' => 'Guru']);
        $unit = $this->makeUnit();

        Excel::import(new PegawaiImport($unit->id, false, 'SmkNm@160'), $this->csvFile());

        $user = User::where('email', 'budi.real@yayasan.com')->first();
        $this->assertNotNull($user, 'User harus dibuat dari import.');
        $this->assertSame('budi.real@yayasan.com', $user->email, 'Email diambil dari kolom template (bukan fallback NIK).');
        $this->assertTrue(Hash::check('SmkNm@160', $user->password), 'Password harus = default seragam (hash).');
        $this->assertTrue($user->force_password_change, 'User wajib ganti password saat login pertama.');
    }

    public function test_tanpa_password_default_pakai_nik_dan_tidak_wajib_ganti(): void
    {
        Jabatan::create(['nama' => 'Guru']);
        $unit = $this->makeUnit();

        Excel::import(new PegawaiImport($unit->id, false, null), $this->csvFile());

        $user = User::where('email', 'budi.real@yayasan.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('8899776655443322', $user->password), 'Tanpa default: password = NIK.');
        $this->assertFalse($user->force_password_change, 'Tanpa default: tidak wajib ganti password.');
    }

    public function test_email_duplikat_dalam_file_ditolak(): void
    {
        Jabatan::create(['nama' => 'Guru']);
        $unit = $this->makeUnit();

        $csv = "NIK,NIP,Nama,Tmp,Lhr,JK,Agama,St,HP,Alamat,Stk,Tgl,Didik,Jab,Unit,Email\n"
            ."8899776655443322,,Budi Santoso,Solo,1990-01-01,L,Islam,kawin,081299887766,Jl A,tetap,2020-01-01,S1,Guru,,sama@yayasan.com\n"
            ."8899776655443323,,Siti Aminah,Solo,1990-01-01,L,Islam,kawin,081299887766,Jl A,tetap,2020-01-01,S1,Guru,,sama@yayasan.com\n";

        $file = UploadedFile::fake()->createWithContent('pegawai.csv', $csv, 'text/csv');

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        Excel::import(new PegawaiImport($unit->id, false, null), $file);
    }
}
