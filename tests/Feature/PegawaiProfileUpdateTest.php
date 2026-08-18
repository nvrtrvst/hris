<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\UnitSekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PegawaiProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_data_update_persists_and_syncs_user(): void
    {
        $unit = UnitSekolah::create(['nama' => 'SD Uji', 'singkatan' => 'SD']);
        $jabatan = Jabatan::firstOrCreate(['nama' => 'Guru Mata Pelajaran'], ['is_guru' => true]);

        $user = User::factory()->create(['name' => 'Nama Lama', 'email' => 'peg@yayasan.com', 'unit_sekolah_id' => $unit->id]);
        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '3273000000000001',
            'nama_lengkap' => 'Nama Lama',
            'jenis_kelamin' => 'L',
            'status_kepegawaian' => 'honorer',
            'status_aktif' => 'aktif',
            'tanggal_mulai_kerja' => '2020-01-01',
            'alamat_ktp' => 'Alamat Lama',
        ]);
        $pegawai->units()->attach($unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        // Update parsial: hanya beberapa field dikirim; field lain tidak berubah.
        $this->actingAs($user, 'web_mobile')
            ->patch(route('presensi.profile.data.update'), [
                'nama_lengkap' => 'Nama Baru',
                'email' => 'baru@yayasan.com',
                'no_hp' => '081200000099',
                'alamat_ktp' => '',
            ])
            ->assertRedirect(route('presensi.profile.edit'));

        $pegawai->refresh();
        $user->refresh();

        $this->assertSame('Nama Baru', $pegawai->nama_lengkap);
        $this->assertSame('081200000099', $pegawai->no_hp);
        // Alamat KTP kosong = biarkan apa adanya (tidak dihapus).
        $this->assertSame('Alamat Lama', $pegawai->alamat_ktp);
        // User tersinkron (nama & email login).
        $this->assertSame('Nama Baru', $user->name);
        $this->assertSame('baru@yayasan.com', $user->email);
    }

    public function test_profile_data_update_validates_email_unique(): void
    {
        $unit = UnitSekolah::create(['nama' => 'SD Uji', 'singkatan' => 'SD']);
        $other = User::factory()->create(['email' => 'lain@yayasan.com']);
        $user = User::factory()->create(['email' => 'peg@yayasan.com', 'unit_sekolah_id' => $unit->id]);
        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '3273000000000001',
            'nama_lengkap' => 'Nama',
            'jenis_kelamin' => 'L',
            'status_kepegawaian' => 'tetap',
            'status_aktif' => 'aktif',
            'tanggal_mulai_kerja' => '2020-01-01',
        ]);

        $this->actingAs($user, 'web_mobile')
            ->patch(route('presensi.profile.data.update'), ['email' => 'lain@yayasan.com'])
            ->assertSessionHasErrors('email');
    }
}
