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
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * SimulasiInertia: Inertia v2 mengirim body multipart asli saat ada File.
 * PHP tidak mem-parse multipart untuk HTTP PUT, sehingga asal pakai put()
 * Laravel menerima body kosong (semua field required gagal validasi).
 * FE (Pegawai/Edit.jsx) memakai POST + _method=PUT — test ini menegakkan itu.
 */
class PegawaiFotoUpdateTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private User $superadmin;

    private Pegawai $pegawai;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(JabatanSeeder::class);

        $this->unit = UnitSekolah::create([
            'nama' => 'SMP Uji Foto',
            'singkatan' => 'SMUF',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'toleransi_menit' => 0,
            'toleransi_slide_menit' => 15,
        ]);

        $this->superadmin = User::factory()->create(['role' => 'superadmin']);
        $this->superadmin->assignRole('superadmin');

        $user = User::factory()->create(['role' => 'pegawai', 'email' => 'uji@yayasan.com']);
        $user->assignRole('pegawai');

        $this->pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '3273000000000141',
            'nama_lengkap' => 'Nama Awal',
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'no_hp' => '081234567890',
            'alamat' => 'Jl. Uji No. 1',
            'status_kepegawaian' => 'guru_tetap_yayasan',
            'status_aktif' => 'aktif',
            'tmt_mengajar' => '2020-01-01',
            'pendidikan_terakhir' => 'S1',
            'jumlah_tanggungan' => 0,
        ]);
        $this->pegawai->units()->attach($this->unit->id, [
            'jabatan_id' => Jabatan::where('nama', 'Guru Mata Pelajaran')->firstOrFail()->id,
            'is_primary' => true,
        ]);
    }

    public function test_update_pegawai_dengan_foto_via_post_method_spoof_sukses(): void
    {
        Storage::fake('presensi');
        $foto = UploadedFile::fake()->image('foto-baru.jpg', 200, 200);

        $this->actingAs($this->superadmin, 'web_admin')
            ->post(route('pegawai.update', $this->pegawai->id), $this->payloadUtama() + [
                '_method' => 'PUT',
                'foto' => $foto,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('pegawai.show', $this->pegawai->id));

        $this->pegawai->refresh();

        $this->assertSame('Nama Uji Foto', $this->pegawai->nama_lengkap);
        $this->assertNotNull($this->pegawai->foto);
        // Simpan foto ke disk 'presensi' sebelum menghapus foto lama
        Storage::disk('presensi')->assertExists($this->pegawai->foto);

        $user = $this->pegawai->user;
        $this->assertSame($user->id, User::whereKey($user->id)->where('unit_sekolah_id', $this->unit->id)->value('id'));
    }

    public function test_update_pegawai_tanpa_foto_tetap_sukses(): void
    {
        $this->actingAs($this->superadmin, 'web_admin')
            ->post(route('pegawai.update', $this->pegawai->id), $this->payloadUtama() + [
                '_method' => 'PUT',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('pegawai.show', $this->pegawai->id));

        $this->pegawai->refresh();

        $this->assertNull($this->pegawai->foto);
        $this->assertSame('Nama Uji Foto', $this->pegawai->nama_lengkap);
    }

    public function test_hapus_foto_melepas_path_foto(): void
    {
        Storage::fake('presensi');

        // Isi foto terlebih dulu: pakai alur POST spoofing yang juga dipakai FE.
        $foto = UploadedFile::fake()->image('foto-awal.jpg', 200, 200);
        $this->actingAs($this->superadmin, 'web_admin')
            ->post(route('pegawai.update', $this->pegawai->id), $this->payloadUtama() + [
                '_method' => 'PUT',
                'foto' => $foto,
            ])
            ->assertSessionHasNoErrors();

        $this->pegawai->refresh();
        $pathLama = $this->pegawai->foto;
        Storage::disk('presensi')->assertExists($pathLama);

        $this->actingAs($this->superadmin, 'web_admin')
            ->post(route('pegawai.update', $this->pegawai->id), $this->payloadUtama() + [
                '_method' => 'PUT',
                'hapus_foto' => true, // checkbox FE kirim nilai boolean
            ])
            ->assertSessionHasNoErrors();

        $this->pegawai->refresh();

        $this->assertNull($this->pegawai->foto);
        // File fisik dihapus MENGECUALI pegawai punya foto lama, tidak menggantung
        Storage::disk('presensi')->assertMissing($pathLama);
    }

    /**
     * Payload minimal untuk lolos semua field required di PegawaiController::update.
     */
    private function payloadUtama(): array
    {
        return [
            'nik' => '3273000000000141',
            'nama_lengkap' => 'Nama Uji Foto',
            'email' => 'uji@yayasan.com',
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'alamat' => 'Jl. Uji No. 1',
            'no_hp' => '081234567890',
            'status_kepegawaian' => 'guru_tetap_yayasan',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
            'lokasi_pilihan' => 'primary',
            'units' => [[
                'unit_sekolah_id' => $this->unit->id,
                'jabatan_id' => Jabatan::where('nama', 'Guru Mata Pelajaran')->firstOrFail()->id,
                'is_primary' => true,
            ]],
        ];
    }
}
