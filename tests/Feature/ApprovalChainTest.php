<?php

namespace Tests\Feature;

use App\Models\ApprovalPolicy;
use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\UnitSekolah;
use App\Models\User;
use App\Services\ApprovalChain;
use App\Services\ApprovalService;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalChainTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private Jabatan $jabatanGuru;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->unit = UnitSekolah::create([
            'nama' => 'SD Uji',
            'singkatan' => 'SD',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
        ]);

        $this->jabatanGuru = Jabatan::create(['nama' => 'Guru', 'is_guru' => true]);
    }

    private function user(string $name): User
    {
        return User::factory()->create(['name' => $name]);
    }

    private function pegawai(string $nama, string $jabatanNama, ?User $user = null, ?Pegawai $atasan = null): Pegawai
    {
        $jabatan = Jabatan::firstOrCreate(['nama' => $jabatanNama]);

        $pegawai = Pegawai::create([
            'user_id' => $user?->id,
            'nik' => '3273'.str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT),
            'nama_lengkap' => $nama,
            'jenis_kelamin' => 'L',
            'status_kepegawaian' => 'guru_tetap_yayasan',
            'status_aktif' => 'aktif',
            'tmt_mengajar' => '2020-01-01',
            'wajib_kantor' => true,
        ]);

        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        if ($atasan) {
            $pegawai->forceFill(['atasan_langsung_id' => $atasan->id])->save();
        }

        return $pegawai->fresh();
    }

    public function test_guru_hanya_satu_tahap_meski_cuti_panjang(): void
    {
        $kepsekUser = $this->user('Kepsek');
        $kepsek = $this->pegawai('Kepsek', 'Kepala Sekolah', $kepsekUser);

        $ketua = $this->user('Ketua Yayasan');
        $ketuaPegawai = $this->pegawai('Ketua Yayasan', 'Ketua Yayasan', $ketua);
        config(['approval.yayasan_head_jabatan_ids' => [$ketuaPegawai->jabatans()->first()->id]]);
        $kepsek->forceFill(['atasan_langsung_id' => $ketuaPegawai->id])->save();

        $guruUser = $this->user('Guru');
        $guru = $this->pegawai('Guru', 'Guru', $guruUser, $kepsek);

        $chain = ApprovalChain::resolve($guru, 'cuti', now(), 5);

        $this->assertSame($kepsekUser->id, $chain['l1']);
        $this->assertNull($chain['l2'], 'Yayasan tidak boleh menjadi tahap kedua.');
        $this->assertFalse($chain['auto']);
    }

    public function test_staf_tu_cuti_panjang_dua_tahap(): void
    {
        $kepsekUser = $this->user('Kepsek');
        $kepsek = $this->pegawai('Kepsek', 'Kepala Sekolah', $kepsekUser);

        $katuUser = $this->user('Kepala TU');
        $katu = $this->pegawai('Kepala TU', 'Kepala Tata Usaha', $katuUser, $kepsek);

        $stafUser = $this->user('Staf TU');
        $staf = $this->pegawai('Staf TU', 'Tenaga Administrasi (TU)', $stafUser, $katu);

        $panjang = ApprovalChain::resolve($staf, 'cuti', now(), 5);
        $this->assertSame($katuUser->id, $panjang['l1']);
        $this->assertSame($kepsekUser->id, $panjang['l2']);

        $pendek = ApprovalChain::resolve($staf, 'cuti', now(), 2);
        $this->assertSame($katuUser->id, $pendek['l1']);
        $this->assertNull($pendek['l2']);
    }

    public function test_kepsek_disetujui_ketua_yayasan(): void
    {
        $ketuaUser = $this->user('Ketua Yayasan');
        $ketua = $this->pegawai('Ketua Yayasan', 'Ketua Yayasan', $ketuaUser);
        config(['approval.yayasan_head_jabatan_ids' => [$ketua->jabatans()->first()->id]]);

        $kepsekUser = $this->user('Kepsek');
        $kepsek = $this->pegawai('Kepsek', 'Kepala Sekolah', $kepsekUser, $ketua);

        $chain = ApprovalChain::resolve($kepsek, 'izin', now(), 1);

        $this->assertSame($ketuaUser->id, $chain['l1']);
        $this->assertNull($chain['l2']);
    }

    public function test_ketua_yayasan_tanpa_persetujuan(): void
    {
        $ketuaUser = $this->user('Ketua Yayasan');
        $ketua = $this->pegawai('Ketua Yayasan', 'Ketua Yayasan', $ketuaUser);
        config(['approval.yayasan_head_jabatan_ids' => [$ketua->jabatans()->first()->id]]);

        $chain = ApprovalChain::resolve($ketua, 'cuti', now(), 5);

        $this->assertNull($chain['l1']);
        $this->assertTrue($chain['auto']);
    }

    public function test_due_at_mengikuti_hari_kerja_unit(): void
    {
        $ketua = ApprovalPolicy::for('izin');
        $this->assertSame(2, $ketua->sla_days);

        // Jumat + unit tanpa Sabtu → Senin.
        $jumat = Carbon::parse('2026-10-23 09:00:00');
        $tanpaSabtu = ApprovalChain::dueAt($jumat, 1, $this->unit);
        $this->assertSame('2026-10-26', $tanpaSabtu->toDateString());

        // Unit dengan jam Sabtu → Sabtu.
        $this->unit->forceFill([
            'jam_kerja_sabtu_mulai' => '07:00:00',
            'jam_kerja_sabtu_selesai' => '12:00:00',
        ])->save();

        $denganSabtu = ApprovalChain::dueAt($jumat, 1, $this->unit->fresh());
        $this->assertSame('2026-10-24', $denganSabtu->toDateString());
    }

    public function test_penyetuju_tidak_sesuai_tahap_ditolak(): void
    {
        $kepsekUser = $this->user('Kepsek');
        $kepsekUser->assignRole('admin_unit');
        $kepsekUser->forceFill(['unit_sekolah_id' => $this->unit->id])->save();
        $kepsek = $this->pegawai('Kepsek', 'Kepala Sekolah', $kepsekUser);

        $guruUser = $this->user('Guru');
        $guru = $this->pegawai('Guru', 'Guru', $guruUser, $kepsek);

        $pengajuan = PengajuanIzin::create([
            'pegawai_id' => $guru->id,
            'jenis_izin' => 'izin',
            'tanggal_mulai' => now()->toDateString(),
            'tanggal_selesai' => now()->toDateString(),
            'alasan' => 'Keperluan keluarga.',
        ]);
        $pengajuan->forceFill([
            'status' => 'pending',
            'approval_stage' => 'pending_l1',
            'approver_l1_id' => $guruUser->id,
        ])->save();

        $this->actingAs($kepsekUser)
            ->post(route('pengajuan-izin.approve', $pengajuan->id))
            ->assertForbidden();
    }

    public function test_eskalasi_ke_tahap_berikutnya_setelah_batas_waktu(): void
    {
        $kepsekUser = $this->user('Kepsek');
        $kepsek = $this->pegawai('Kepsek', 'Kepala Sekolah', $kepsekUser);

        $katuUser = $this->user('Kepala TU');
        $katu = $this->pegawai('Kepala TU', 'Kepala Tata Usaha', $katuUser, $kepsek);

        $stafUser = $this->user('Staf TU');
        $staf = $this->pegawai('Staf TU', 'Tenaga Administrasi (TU)', $stafUser, $katu);

        $pengajuan = PengajuanIzin::create([
            'pegawai_id' => $staf->id,
            'jenis_izin' => 'izin',
            'tanggal_mulai' => now()->toDateString(),
            'tanggal_selesai' => now()->toDateString(),
            'alasan' => 'Keperluan keluarga.',
        ]);
        $pengajuan->forceFill([
            'status' => 'pending',
            'approval_stage' => 'pending_l1',
            'approver_l1_id' => $katuUser->id,
            'approver_l2_id' => $kepsekUser->id,
            'due_at' => now()->subHour(),
        ])->save();

        $this->assertTrue(ApprovalService::escalate($pengajuan->fresh()));
        $this->assertSame('pending_l2', $pengajuan->fresh()->approval_stage);
    }
}
