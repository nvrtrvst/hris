<?php

namespace Tests\Feature;

use App\Constants\BobotKehadiran;
use App\Models\Jabatan;
use App\Models\KomponenGaji;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\Penggajian;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bobot kehadiran dinamis per unit:
 * - sakit dgn surat dokter dibayar penuh, tanpa surat bobot ½ (default).
 * - sakit potongan komponen hanya menghitung hari tanpa surat.
 * - potongan kehadiran (hari nol non-guru) ikut berbobot.
 * - flag surat disimpan dari pengajuan mobile + bisa diverifikasi admin.
 */
class BobotKehadiranTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->unit = UnitSekolah::create([
            'nama' => 'SMP Bobot',
            'singkatan' => 'SMBB',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'toleransi_menit' => 0,
        ]);

        $this->admin = User::factory()->create(['role' => 'superadmin']);
        $this->admin->assignRole('superadmin');
    }

    public function test_unit_bobot_logika_murni(): void
    {
        $b = BobotKehadiran::DEFAULTS;

        // Setengah diasumsikan subset izin — tidak dihitung dobel.
        $this->assertSame(9.5, BobotKehadiran::hadirEq(5, 1, 0, 2, 3, 2, 1, $b));
        $this->assertSame(9.5, BobotKehadiran::hadirEq(5, 1, 0, 2, 3, 0, 1, $b)); // tanpa setengah sama (izin 3×0.5=1.5)
        $this->assertSame(100, BobotKehadiran::persenBerbobot(25, 22));
        $this->assertSame(0, BobotKehadiran::persenBerbobot(5, 0));

        $split = BobotKehadiran::splitSakitSurat(
            ['2026-06-01', '2026-06-05'],
            [['2026-06-01', '2026-06-02']]
        );
        $this->assertSame(['surat' => 1, 'tanpa' => 1], $split);
        $this->assertSame(['surat' => 0, 'tanpa' => 0], BobotKehadiran::splitSakitSurat([], []));
    }

    public function test_unit_utama_bobot_dipakai_else_default(): void
    {
        $unit = new UnitSekolah(['bobot_sakit_tanpa_surat' => 1.0, 'bobot_izin' => 0.0]);
        $bobot = $unit->bobotHadiran();
        $this->assertSame(1.0, $bobot['sakit_tanpa_surat']);
        $this->assertSame(0.0, $bobot['izin']);
        $this->assertSame(1.0, $bobot['sakit_surat']);
        $this->assertSame(1.0, $bobot['cuti']);

        $default = (new UnitSekolah)->bobotHadiran();
        $this->assertSame(BobotKehadiran::DEFAULTS, $default);
    }

    public function test_sakit_tanpa_surat_hanya_dibayar_setengah(): void
    {
        $pegawai = $this->makePegawai();
        KomponenGaji::query()->update(['is_active' => false]);
        $this->makeKomponen(['nama' => 'Gaji Pokok', 'kode' => 'gaji_pokok', 'jenis' => 'fixed', 'nilai_default' => 1000000]);
        $this->makeKomponen(['nama' => 'Potongan Sakit', 'kode' => 'kehadiran_sakit', 'tipe' => 'potongan', 'jenis' => 'dinamis_kehadiran', 'nilai_default' => 100000]);

        // 2 hari sakit tanpa pengajuan surat (persentase 100%/hari → prorata multiplier 1).
        foreach (['2026-06-02', '2026-06-03'] as $tgl) {
            $p = Presensi::create([
                'pegawai_id' => $pegawai->id,
                'unit_sekolah_id' => $this->unit->id,
                'tipe_presensi' => 'kantor',
                'tanggal' => $tgl,
                'persentase_bayar_jam' => 100,
            ]);
            $p->status = 'sakit';
            $p->save();
        }

        $this->runDraft();
        [, $rows] = $this->details($pegawai);

        // Komponen sakit: 100.000 × 2 hari tanpa surat × 1 = 200.000
        $this->assertEquals(200000, (float) ($rows['Potongan Sakit'] ?? 0));

        // Hari kerja Juni 2026 = 22. hadirEq = 2 × 0.5 = 1 → hari nol = 21.
        // Potongan kehadiran = (1.000.000 ÷ 22) × 21 = 954.545,45
        $this->assertEquals(954545.45, (float) ($rows['Potongan Kehadiran — (Rp 1.000.000 ÷ 22 hari × 21 hari)'] ?? 0));
    }

    public function test_sakit_dengan_surat_dokter_dibayar_penuh(): void
    {
        $pegawai = $this->makePegawai();
        KomponenGaji::query()->update(['is_active' => false]);
        $this->makeKomponen(['nama' => 'Gaji Pokok', 'kode' => 'gaji_pokok', 'jenis' => 'fixed', 'nilai_default' => 1000000]);
        $this->makeKomponen(['nama' => 'Potongan Sakit', 'kode' => 'kehadiran_sakit', 'tipe' => 'potongan', 'jenis' => 'dinamis_kehadiran', 'nilai_default' => 100000]);

        PengajuanIzin::create([
            'pegawai_id' => $pegawai->id,
            'jenis_izin' => 'sakit',
            'tanggal_mulai' => '2026-06-02',
            'tanggal_selesai' => '2026-06-03',
            'alasan' => 'Demam tinggi perlu perawatan dokter.',
            'dengan_surat_dokter' => true,
        ])->forceFill(['status' => 'disetujui'])->save();
        foreach (['2026-06-02', '2026-06-03'] as $tgl) {
            $p = Presensi::create([
                'pegawai_id' => $pegawai->id,
                'unit_sekolah_id' => $this->unit->id,
                'tipe_presensi' => 'kantor',
                'tanggal' => $tgl,
                'persentase_bayar_jam' => 100,
            ]);
            $p->status = 'sakit';
            $p->save();
        }

        $this->runDraft();
        [, $rows] = $this->details($pegawai);

        // Dengan surat → tidak ada potongan komponen sakit.
        $this->assertEquals(0, (float) ($rows['Potongan Sakit'] ?? 0));

        // hadirEq = 2 × 1.0 = 2 → hari nol = 20 → (1.000.000 ÷ 22) × 20.
        $this->assertEquals(909090.91, (float) ($rows['Potongan Kehadiran — (Rp 1.000.000 ÷ 22 hari × 20 hari)'] ?? 0));
    }

    public function test_sakit_wajib_tugas_tanpa_tugas_hanya_setengah(): void
    {
        $this->unit->update(['sakit_wajib_tugas' => true]);
        $pegawai = $this->makePegawai();
        KomponenGaji::query()->update(['is_active' => false]);
        $this->makeKomponen(['nama' => 'Gaji Pokok', 'kode' => 'gaji_pokok', 'jenis' => 'fixed', 'nilai_default' => 1000000]);
        $this->makeKomponen(['nama' => 'Potongan Sakit', 'kode' => 'kehadiran_sakit', 'tipe' => 'potongan', 'jenis' => 'dinamis_kehadiran', 'nilai_default' => 100000]);

        PengajuanIzin::create([
            'pegawai_id' => $pegawai->id,
            'jenis_izin' => 'sakit',
            'tanggal_mulai' => '2026-06-02',
            'tanggal_selesai' => '2026-06-03',
            'alasan' => 'Demam tinggi perlu perawatan dokter.',
            'dengan_surat_dokter' => true,
        ])->forceFill(['status' => 'disetujui'])->save();

        foreach (['2026-06-02', '2026-06-03'] as $tgl) {
            $p = Presensi::create([
                'pegawai_id' => $pegawai->id,
                'unit_sekolah_id' => $this->unit->id,
                'tipe_presensi' => 'kantor',
                'tanggal' => $tgl,
                'persentase_bayar_jam' => 100,
            ]);
            $p->status = 'sakit';
            $p->save();
        }

        $this->runDraft();
        [, $rows] = $this->details($pegawai);

        $this->assertEquals(200000, (float) ($rows['Potongan Sakit'] ?? 0));
        $this->assertEquals(954545.45, (float) (collect($rows)->first(fn ($v, $k) => str_starts_with($k, 'Potongan Kehadiran'))));
    }

    public function test_sakit_wajib_tugas_dengan_tugas_dibayar_penuh(): void
    {
        $this->unit->update(['sakit_wajib_tugas' => true]);
        $pegawai = $this->makePegawai();
        KomponenGaji::query()->update(['is_active' => false]);
        $this->makeKomponen(['nama' => 'Gaji Pokok', 'kode' => 'gaji_pokok', 'jenis' => 'fixed', 'nilai_default' => 1000000]);
        $this->makeKomponen(['nama' => 'Potongan Sakit', 'kode' => 'kehadiran_sakit', 'tipe' => 'potongan', 'jenis' => 'dinamis_kehadiran', 'nilai_default' => 100000]);

        PengajuanIzin::create([
            'pegawai_id' => $pegawai->id,
            'jenis_izin' => 'sakit',
            'tanggal_mulai' => '2026-06-02',
            'tanggal_selesai' => '2026-06-03',
            'alasan' => 'Demam tinggi perlu perawatan dokter.',
            'dengan_surat_dokter' => true,
        ])->forceFill(['status' => 'disetujui', 'tugas_terverifikasi' => true])->save();

        foreach (['2026-06-02', '2026-06-03'] as $tgl) {
            $p = Presensi::create([
                'pegawai_id' => $pegawai->id,
                'unit_sekolah_id' => $this->unit->id,
                'tipe_presensi' => 'kantor',
                'tanggal' => $tgl,
                'persentase_bayar_jam' => 100,
            ]);
            $p->status = 'sakit';
            $p->save();
        }

        $this->runDraft();
        [, $rows] = $this->details($pegawai);

        $this->assertEquals(0, (float) ($rows['Potongan Sakit'] ?? 0));
        $this->assertEquals(909090.91, (float) (collect($rows)->first(fn ($v, $k) => str_starts_with($k, 'Potongan Kehadiran'))));
    }

    public function test_bobot_unit_kustom_menimpa_default(): void
    {
        $this->unit->update(['bobot_sakit_tanpa_surat' => 0.25, 'bobot_alpa' => 0.0]);

        $pegawai = $this->makePegawai();
        KomponenGaji::query()->update(['is_active' => false]);
        $this->makeKomponen(['nama' => 'Gaji Pokok', 'kode' => 'gaji_pokok', 'jenis' => 'fixed', 'nilai_default' => 1000000]);

        foreach (['2026-06-02', '2026-06-03'] as $tgl) {
            $p = Presensi::create([
                'pegawai_id' => $pegawai->id,
                'unit_sekolah_id' => $this->unit->id,
                'tipe_presensi' => 'kantor',
                'tanggal' => $tgl,
                'persentase_bayar_jam' => 100,
            ]);
            $p->status = 'sakit';
            $p->save();
        }

        $this->runDraft();

        // hadirEq = 2 × 0.25 = 0.5 → hari nol = 21.5 → (1.000.000 ÷ 22) × 21.5.
        [, $rows] = $this->details($pegawai);
        $this->assertEquals(977272.73, (float) (collect($rows)->first(fn ($v, $k) => str_starts_with($k, 'Potongan Kehadiran'))));
    }

    public function test_pengajuan_mobile_sakit_dengan_flag_surat_tersimpan(): void
    {
        $pegawai = $this->makePegawai();

        $this->actingAs($pegawai->user, 'web_mobile')
            ->post(route('presensi.izin.store'), [
                'jenis_izin' => 'sakit',
                'tanggal_mulai' => now()->format('Y-m-d'),
                'tanggal_selesai' => now()->format('Y-m-d'),
                'alasan' => 'Pusing berat perlu kontrol dokter.',
                'bukti_foto' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
                'dengan_surat_dokter' => true,
            ])
            ->assertRedirect(route('presensi.izin.index'));

        $izin = PengajuanIzin::where('pegawai_id', $pegawai->id)->firstOrFail();
        $this->assertTrue((bool) $izin->dengan_surat_dokter);

        // Non-sakit flag diabaikan.
        $this->actingAs($pegawai->user, 'web_mobile')
            ->post(route('presensi.izin.store'), [
                'jenis_izin' => 'izin',
                'tanggal_mulai' => now()->addWeek()->format('Y-m-d'),
                'tanggal_selesai' => now()->addWeek()->format('Y-m-d'),
                'alasan' => 'Urusan keluarga perlu ditangani.',
                'dengan_surat_dokter' => true,
            ])
            ->assertRedirect();

        $izinNonsakit = PengajuanIzin::where('pegawai_id', $pegawai->id)->where('jenis_izin', 'izin')->firstOrFail();
        $this->assertFalse((bool) $izinNonsakit->dengan_surat_dokter);
    }

    public function test_approve_bisa_verifikasi_flag_surat_dokter(): void
    {
        $pegawai = $this->makePegawai();
        $izin = PengajuanIzin::create([
            'pegawai_id' => $pegawai->id,
            'jenis_izin' => 'sakit',
            'tanggal_mulai' => now()->format('Y-m-d'),
            'tanggal_selesai' => now()->format('Y-m-d'),
            'alasan' => 'Demam tinggi perlu perawatan dokter.',
        ]);
        $izin->forceFill([
            'status' => 'pending',
            'approval_stage' => 'pending_l1',
            'approver_l1_id' => $this->admin->id,
        ])->save();

        $this->actingAs($this->admin, 'web_admin')
            ->post(route('pengajuan-izin.approve', $izin->id), ['dengan_surat_dokter' => true])
            ->assertRedirect();

        $this->assertTrue((bool) $izin->fresh()->dengan_surat_dokter);
    }

    public function test_approve_bisa_verifikasi_tugas_sakit_bersurat(): void
    {
        $pegawai = $this->makePegawai();
        $izin = PengajuanIzin::create([
            'pegawai_id' => $pegawai->id,
            'jenis_izin' => 'sakit',
            'tanggal_mulai' => now()->format('Y-m-d'),
            'tanggal_selesai' => now()->format('Y-m-d'),
            'alasan' => 'Demam tinggi perlu perawatan dokter.',
        ]);
        $izin->forceFill([
            'status' => 'pending',
            'approval_stage' => 'pending_l1',
            'approver_l1_id' => $this->admin->id,
        ])->save();

        $this->actingAs($this->admin, 'web_admin')
            ->post(route('pengajuan-izin.approve', $izin->id), [
                'dengan_surat_dokter' => true,
                'tugas_terverifikasi' => true,
            ])
            ->assertRedirect();

        $fresh = $izin->fresh();
        $this->assertTrue((bool) $fresh->dengan_surat_dokter);
        $this->assertTrue((bool) $fresh->tugas_terverifikasi);
        $this->assertSame($this->admin->id, (int) $fresh->tugas_diverifikasi_oleh);
    }

    private function makePegawai(): Pegawai
    {
        $jabatan = Jabatan::create(['nama' => 'Staf Bobot']);
        $user = User::factory()->create();
        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '88001100000999',
            'nama_lengkap' => 'Staf Bobot',
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'kawin',
            'jumlah_tanggungan' => 0,
            'alamat' => 'Jl. Test',
            'no_hp' => '081200000009',
            'status_kepegawaian' => 'pegawai_tetap_yayasan',
            'tmt_mengajar' => '2020-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
        ]);
        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    private function makeKomponen(array $attrs): KomponenGaji
    {
        return KomponenGaji::create(array_merge([
            'tipe' => 'pendapatan',
            'is_taxable' => false,
            'is_active' => true,
            'urutan' => 50,
            'tampil_di_matrix' => true,
        ], $attrs));
    }

    private function runDraft(): void
    {
        $this->actingAs($this->admin, 'web_admin')
            ->post(route('penggajian.run.init'), ['month' => '06', 'year' => '2026'])
            ->assertRedirect();
    }

    private function details(Pegawai $pegawai): array
    {
        $gajian = Penggajian::where('pegawai_id', $pegawai->id)->where('periode_bulan', '06-2026')->firstOrFail();

        return [$gajian, $gajian->details()->pluck('nominal', 'nama_komponen')->all()];
    }
}
