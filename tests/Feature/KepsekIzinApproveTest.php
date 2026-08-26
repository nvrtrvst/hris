<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\UnitSekolah;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KepsekIzinApproveTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $sd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->sd = UnitSekolah::create([
            'nama' => 'SD Uji',
            'singkatan' => 'SD',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
        ]);
    }

    private function makePegawai(string $nama, string $jabatanNama, ?User $user = null): Pegawai
    {
        $jabatan = Jabatan::firstOrCreate(['nama' => $jabatanNama], ['is_guru' => str_contains($jabatanNama, 'Guru')]);
        $pegawai = Pegawai::create([
            'user_id' => $user?->id,
            'nik' => '3273'.str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT),
            'nama_lengkap' => $nama,
            'jenis_kelamin' => 'L',
            'status_kepegawaian' => 'tetap',
            'status_aktif' => 'aktif',
            'tanggal_mulai_kerja' => '2020-01-01',
            'wajib_kantor' => true,
        ]);
        $pegawai->units()->attach($this->sd->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    private function pendingIzin(Pegawai $pegawai): PengajuanIzin
    {
        $izin = PengajuanIzin::create([
            'pegawai_id' => $pegawai->id,
            'jenis_izin' => 'izin',
            'tanggal_mulai' => '2026-08-10',
            'tanggal_selesai' => '2026-08-10',
            'alasan' => 'Keperluan keluarga yang mendesak.',
        ]);
        $izin->approval_stage = 'pending_l1';
        $izin->status = 'pending';
        $izin->save();

        return $izin;
    }

    public function test_kepsek_unit_head_can_approve_teacher_izin(): void
    {
        $kepsekUser = User::factory()->create();
        $kepsekUser->assignRole('admin_unit');
        $kepsekUser->unit_sekolah_id = $this->sd->id;
        $kepsekUser->save();
        $this->makePegawai('Kepsek SD', 'Kepala Sekolah', $kepsekUser);

        $guru = $this->makePegawai('Guru Reza', 'Guru Mata Pelajaran');
        $izin = $this->pendingIzin($guru);

        $response = $this->actingAs($kepsekUser)
            ->post(route('pengajuan-izin.approve', $izin->id), ['catatan_approval' => 'Ok']);

        $response->assertRedirect();
        $izin->refresh();
        $this->assertSame('approved', $izin->approval_stage);
        $this->assertSame('disetujui', $izin->status);
        $this->assertDatabaseHas('presensi', [
            'pegawai_id' => $guru->id,
            'tanggal' => '2026-08-10',
            'unit_sekolah_id' => $this->sd->id,
            'status' => 'izin',
        ]);
    }

    public function test_approve_succeeds_when_pegawai_has_no_unit(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('superadmin');

        $pegawai = Pegawai::create([
            'nik' => '3273'.str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT),
            'nama_lengkap' => 'Tanpa Unit',
            'jenis_kelamin' => 'L',
            'status_kepegawaian' => 'tetap',
            'status_aktif' => 'aktif',
            'tanggal_mulai_kerja' => '2020-01-01',
            'wajib_kantor' => true,
        ]);
        $izin = $this->pendingIzin($pegawai);

        $response = $this->actingAs($admin)
            ->post(route('pengajuan-izin.approve', $izin->id));

        $response->assertRedirect();
        $izin->refresh();
        $this->assertSame('approved', $izin->approval_stage);
        $this->assertSame('disetujui', $izin->status);
        $this->assertDatabaseMissing('presensi', [
            'pegawai_id' => $pegawai->id,
            'tanggal' => '2026-08-10',
        ]);
    }
}
