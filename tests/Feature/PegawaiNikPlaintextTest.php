<?php

namespace Tests\Feature;

use App\Models\Pegawai;
use App\Models\UnitSekolah;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * getNikPlaintext() harus mengembalikan NIK asli walau data di DB
 * double-encrypted (saving hook + cast encrypted — pola legacy) — dipakai
 * endpoint pegawai.nik-asli, form edit, dan export.
 */
class PegawaiNikPlaintextTest extends TestCase
{
    use RefreshDatabase;

    private function makePegawai(string $nik): Pegawai
    {
        $unit = UnitSekolah::create(['nama' => 'SD Uji', 'singkatan' => 'SD']);
        $user = User::create([
            'name' => 'Uji NIK',
            'email' => 'uji'.uniqid().'@yayasan.com',
            'password' => bcrypt('password'),
            'unit_sekolah_id' => $unit->id,
        ]);

        return Pegawai::create([
            'user_id' => $user->id,
            'nik' => $nik,
            'nama_lengkap' => 'Uji NIK',
            'jenis_kelamin' => 'L',
            'status_kepegawaian' => 'tetap',
            'status_aktif' => 'aktif',
            'tanggal_mulai_kerja' => '2020-01-01',
        ]);
    }

    public function test_get_nik_plaintext_handles_double_encryption(): void
    {
        $nik = '3273000000001234';
        $pegawai = $this->makePegawai($nik);

        // Simulasikan double-encrypt legacy: tulis encrypt(encrypt(nik)) mentah
        // ke DB (bypass hook agar nik_hash tidak berubah — tidak penting di sini).
        $raw = Crypt::encryptString(Crypt::encryptString($nik));
        Pegawai::withoutEvents(fn () => Pegawai::where('id', $pegawai->id)->update(['nik' => $raw]));

        $fresh = Pegawai::find($pegawai->id);
        $this->assertSame($nik, $fresh->getNikPlaintext());
    }

    public function test_get_nik_plaintext_returns_null_on_corrupt_data(): void
    {
        $pegawai = $this->makePegawai('3273000000001234');
        Pegawai::withoutEvents(fn () => Pegawai::where('id', $pegawai->id)->update(['nik' => 'bukan-ciphertext']));

        $fresh = Pegawai::find($pegawai->id);
        $this->assertNull($fresh->getNikPlaintext());
    }

    public function test_nik_asli_endpoint_returns_plaintext_for_superadmin(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $pegawai = $this->makePegawai('3273000000005678');

        $superadmin = User::first();
        $superadmin->assignRole('superadmin');

        $this->actingAs($superadmin)
            ->getJson(route('pegawai.nik-asli', $pegawai))
            ->assertOk()
            ->assertJson(['nik' => '3273000000005678']);
    }
}
