<?php

namespace Tests\Feature;

use App\Http\Controllers\PenggajianController;
use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\UnitSekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LengkapiDataOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_pegawai_can_complete_onboarding_via_self_service(): void
    {
        $unit = UnitSekolah::create(['nama' => 'SD Uji', 'singkatan' => 'SD']);
        $jabatan = Jabatan::firstOrCreate(['nama' => 'Guru Mata Pelajaran'], ['is_guru' => true]);

        $user = User::factory()->create(['unit_sekolah_id' => $unit->id]);
        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '3273000000000001',
            'nama_lengkap' => 'Sebelum',
            'jenis_kelamin' => 'L',
            'status_kepegawaian' => 'guru_pemula',
            'status_aktif' => 'aktif',
            'tmt_mengajar' => null,
            'alamat' => null,
            'nama_bank' => null,
            'no_rekening' => null,
        ]);
        $pegawai->units()->attach($unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        $payload = [
            'nik' => '3273000000000001',
            'nama_lengkap' => 'Sesudah',
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '1995-05-05',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'Belum Menikah',
            'jumlah_tanggungan' => 0,
            'alamat' => 'Jl. Contoh No.1',
            'no_hp' => '081234567890',
            'status_kepegawaian' => 'guru_pemula',
            'tmt_mengajar' => '2024-01-15',
            'pendidikan_terakhir' => 'S1',
            'nama_bank' => 'BRI',
            'no_rekening' => '1234567890',
        ];

        $this->actingAs($user, 'web_mobile')
            ->post(route('presensi.lengkapi-data.store'), $payload)
            ->assertRedirect();

        $pegawai->refresh();
        $this->assertSame('Sesudah', $pegawai->nama_lengkap);
        $this->assertSame('2024-01-15', $pegawai->tmt_mengajar->toDateString());
        $this->assertSame('Bandung', $pegawai->tempat_lahir);
        $this->assertSame('081234567890', $pegawai->no_hp);
        $this->assertTrue($pegawai->isDataComplete(), 'Onboarding harus menandai data lengkap setelah submit sukses.');
    }

    public function test_missing_tmt_mengajar_blocks_onboarding(): void
    {
        $unit = UnitSekolah::create(['nama' => 'SD Uji', 'singkatan' => 'SD']);
        $jabatan = Jabatan::firstOrCreate(['nama' => 'Guru Mata Pelajaran'], ['is_guru' => true]);

        $user = User::factory()->create(['unit_sekolah_id' => $unit->id]);
        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '3273000000000002',
            'nama_lengkap' => 'User Tanpa TMT',
            'jenis_kelamin' => 'P',
            'status_kepegawaian' => 'guru_pemula',
            'status_aktif' => 'aktif',
            'tmt_mengajar' => null,
        ]);
        $pegawai->units()->attach($unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        $payload = $this->validPayload();
        unset($payload['tmt_mengajar']);

        $this->actingAs($user, 'web_mobile')
            ->from(route('presensi.lengkapi-data'))
            ->post(route('presensi.lengkapi-data.store'), $payload)
            ->assertSessionHasErrors('tmt_mengajar');

        $this->assertNull($pegawai->fresh()->tmt_mengajar, 'Gagal submit tidak boleh menulis ke DB.');
    }

    public function test_old_field_name_tanggal_mulai_kerja_is_no_longer_accepted(): void
    {
        $unit = UnitSekolah::create(['nama' => 'SD Uji', 'singkatan' => 'SD']);
        $jabatan = Jabatan::firstOrCreate(['nama' => 'Guru Mata Pelajaran'], ['is_guru' => true]);

        $user = User::factory()->create(['unit_sekolah_id' => $unit->id]);
        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '3273000000000003',
            'nama_lengkap' => 'Old Field User',
            'jenis_kelamin' => 'L',
            'status_kepegawaian' => 'guru_pemula',
            'status_aktif' => 'aktif',
            'tmt_mengajar' => null,
        ]);
        $pegawai->units()->attach($unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        // FE lama kirim field tanggal_mulai_kerja — BE harus TOLAK karena kolom sudah di-rename.
        $payload = $this->validPayload();
        $payload['tanggal_mulai_kerja'] = $payload['tmt_mengajar'];
        unset($payload['tmt_mengajar']);

        $this->actingAs($user, 'web_mobile')
            ->from(route('presensi.lengkapi-data'))
            ->post(route('presensi.lengkapi-data.store'), $payload)
            ->assertSessionHasErrors('tmt_mengajar');
    }

    public function test_admin_portal_lengkapi_data_uses_same_field_name(): void
    {
        $unit = UnitSekolah::create(['nama' => 'SD Uji', 'singkatan' => 'SD']);
        $jabatan = Jabatan::firstOrCreate(['nama' => 'Guru Mata Pelajaran'], ['is_guru' => true]);

        $user = User::factory()->create(['unit_sekolah_id' => $unit->id]);
        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '3273000000000004',
            'nama_lengkap' => 'Admin Onboard',
            'jenis_kelamin' => 'L',
            'status_kepegawaian' => 'guru_pemula',
            'status_aktif' => 'aktif',
            'tmt_mengajar' => null,
        ]);
        $pegawai->units()->attach($unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        $this->actingAs($user, 'web_admin')
            ->post(route('lengkapi-data.store'), $this->validPayload())
            ->assertRedirect();

        $this->assertSame('2024-01-15', $pegawai->fresh()->tmt_mengajar->toDateString());
    }

    public function test_reference_source_fields_are_real_pegawai_columns(): void
    {
        $whitelist = PenggajianController::referenceSourceFields();
        $this->assertNotEmpty($whitelist, 'Whitelist tidak boleh kosong.');

        foreach ($whitelist as $field) {
            $this->assertTrue(
                Schema::hasColumn('pegawai', $field),
                "referenceSourceFields() berisi '{$field}' yang BUKAN kolom di tabel pegawai. Cek rename migration."
            );
        }

        // Hardcoded: kolom ini dulunya bernama tanggal_mulai_kerja sebelum di-rename.
        $this->assertNotContains(
            'tanggal_mulai_kerja',
            $whitelist,
            'tanggal_mulai_kerja sudah di-rename ke tmt_mengajar — whitelist tidak boleh menyimpan nama lama.'
        );
        $this->assertContains('tmt_mengajar', $whitelist);
    }

    private function validPayload(): array
    {
        return [
            'nik' => '3273000000000099',
            'nama_lengkap' => 'Tester Lengkap',
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '1995-05-05',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'Belum Menikah',
            'jumlah_tanggungan' => 0,
            'alamat' => 'Jl. Contoh No.1',
            'no_hp' => '081234567890',
            'status_kepegawaian' => 'guru_pemula',
            'tmt_mengajar' => '2024-01-15',
            'pendidikan_terakhir' => 'S1',
            'nama_bank' => 'BRI',
            'no_rekening' => '1234567890',
        ];
    }
}
