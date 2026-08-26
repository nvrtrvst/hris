<?php

namespace Tests\Feature;

use App\Helpers\ApprovalHelper;
use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\UnitSekolah;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalHelperTest extends TestCase
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

    public function test_determine_approvers_uses_unit_head_when_atasan_null(): void
    {
        $kepsekUser = User::factory()->create();
        $this->makePegawai('Kepsek SD', 'Kepala Sekolah', $kepsekUser);
        $guru = $this->makePegawai('Guru Reza', 'Guru Mata Pelajaran');

        $result = ApprovalHelper::determineApprovers($guru);

        $this->assertSame($kepsekUser->id, $result['l1_id']);
        $this->assertTrue($result['has_l1']);
        $this->assertNull($result['l2_id']);
    }

    public function test_is_unit_head_true_for_kepsek_false_for_guru(): void
    {
        $kepsekUser = User::factory()->create();
        $this->makePegawai('Kepsek SD', 'Kepala Sekolah', $kepsekUser);
        $guruUser = User::factory()->create();
        $this->makePegawai('Guru Reza', 'Guru Mata Pelajaran', $guruUser);

        $this->assertTrue(ApprovalHelper::isUnitHead($kepsekUser, $this->sd->id));
        $this->assertFalse(ApprovalHelper::isUnitHead($guruUser, $this->sd->id));
    }

    public function test_head_unit_ids_includes_kepsek_unit(): void
    {
        $kepsekUser = User::factory()->create();
        $this->makePegawai('Kepsek SD', 'Kepala Sekolah', $kepsekUser);

        $this->assertContains($this->sd->id, ApprovalHelper::headUnitIds($kepsekUser));
    }
}
