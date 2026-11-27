<?php

namespace Tests\Feature;

use App\Models\HariLibur;
use App\Models\Jabatan;
use App\Models\Jadwal;
use App\Models\LaporanPiket;
use App\Models\LaporanTindakLanjut;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\Presensi;
use App\Models\TugasLuar;
use App\Models\UnitPresensiModeHistory;
use App\Models\UnitSekolah;
use App\Models\User;
use App\Services\LaporanPemenuhanExportRows;
use App\Services\LaporanPengawasanService;
use App\Services\LaporanTindakLanjutAccess;
use App\Services\TugasLuarCoverageService;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LaporanPengawasanTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private User $admin;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        Carbon::setTestNow('2026-11-03 18:00:00');
        $this->date = '2026-11-03';

        $this->unit = UnitSekolah::create([
            'nama' => 'SMK Pengawasan',
            'singkatan' => 'SMKP',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'jam_masuk_kantor' => '07:00:00',
            'jam_pulang_kantor' => '15:00:00',
        ]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('superadmin');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_service_menghitung_alpa_jp_dan_kelas_belum_terpantau(): void
    {
        $guruHadir = $this->pegawai('Guru Hadir', true);
        $guruAlpa = $this->pegawai('Guru Alpa', true);
        $guruIzin = $this->pegawai('Guru Izin', true);
        $guruTanpaJp = $this->pegawai('Guru Tanpa JP', true);
        $guruPiket = $this->pegawai('Guru Piket', true);
        $staf = $this->pegawai('Staf TU', false);

        $hadir = $this->jadwal($guruHadir, 'Kelas Hadir');
        $alpa = $this->jadwal($guruAlpa, 'Kelas Alpa');
        $izin = $this->jadwal($guruIzin, 'Kelas Izin');
        $tanpaJp = $this->jadwal($guruTanpaJp, 'Kelas Tanpa JP');
        $piket = $this->jadwal($guruPiket, 'Kelas Piket');

        $this->presensi($guruHadir, $hadir->id, 'hadir');
        $this->presensi($guruTanpaJp, null, 'hadir');

        $izin = PengajuanIzin::create([
            'pegawai_id' => $guruIzin->id,
            'jenis_izin' => 'izin',
            'tanggal_mulai' => $this->date,
            'tanggal_selesai' => $this->date,
            'alasan' => 'Keperluan keluarga',
        ]);
        $izin->forceFill(['status' => 'disetujui'])->save();

        LaporanPiket::create([
            'pegawai_id' => $guruPiket->id,
            'unit_sekolah_id' => $this->unit->id,
            'tanggal' => $this->date,
            'status' => LaporanPiket::STATUS_SUBMITTED,
            'items' => [
                'kelas_kosong' => [
                    'entries' => [[
                        'slot_jadwal_id' => $piket->id,
                        'kelas' => 'Kelas Piket',
                        'guru_pegawai_id' => $guruPiket->id,
                        'validasi' => 'cocok',
                    ]],
                ],
            ],
        ]);

        $data = app(LaporanPengawasanService::class)->build([
            'from' => $this->date,
            'to' => $this->date,
            'unit_sekolah_id' => $this->unit->id,
            'jenis_pegawai' => 'all',
        ], $this->admin);

        $this->assertSame(6, $data['summary']['employees']);
        $this->assertSame(6, $data['summary']['employee_days']);
        $this->assertSame(1, $data['period']['days']);
        $this->assertSame(2, $data['summary']['hadir']);
        $this->assertSame(3, $data['summary']['alpa']);
        $this->assertSame(1, $data['summary']['izin_sakit_cuti']);
        $this->assertSame(1, $data['summary']['hadir_tanpa_jp']);
        $this->assertSame(3, $data['summary']['kelas_belum']);
        $this->assertEquals(1, $data['summary']['actual_jp']);
        $this->assertEquals(5, $data['summary']['scheduled_jp']);
        $this->assertCount(3, $data['details']['alpa']);
        $this->assertCount(4, $data['class_issues']);

        $alpaRow = collect($data['matrix'])->firstWhere('pegawai_id', $guruAlpa->id);
        $this->assertSame('Belum tercatat 1 hari: 03/11/2026', $alpaRow['temuan']);
        $this->assertSame('alpa', $alpaRow['temuan_detail'][0]['key']);
        $this->assertSame('2026-11-03', $alpaRow['temuan_detail'][0]['items'][0]['tanggal']);
    }

    public function test_jp_blok_dan_bahasa_temuan_memakai_hari(): void
    {
        $guru = $this->pegawai('Guru Blok', true);
        $this->jadwal($guru, 'Kelas Blok 1', '07:00:00', '07:45:00');
        $this->jadwal($guru, 'Kelas Blok 2', '07:45:00', '09:15:00');

        $this->presensi($guru, Jadwal::where('pegawai_id', $guru->id)->first()->id, 'hadir');

        $data = app(LaporanPengawasanService::class)->build([
            'from' => $this->date,
            'to' => $this->date,
            'unit_sekolah_id' => $this->unit->id,
            'jenis_pegawai' => 'all',
        ], $this->admin);

        $this->assertSame(1, $data['period']['days']);
        $this->assertEquals(1, $data['summary']['actual_jp']);
        $this->assertEquals(3, $data['summary']['scheduled_jp']);
        $this->assertSame(1, $data['summary']['jp_kurang']);

        $row = collect($data['matrix'])->firstWhere('pegawai_id', $guru->id);
        $this->assertSame('JP kurang 1 hari: 03/11/2026 (1/3 JP)', $row['temuan']);
        $this->assertSame('jp_kurang', $row['temuan_detail'][0]['key']);
        $this->assertSame('2026-11-03', $row['temuan_detail'][0]['items'][0]['tanggal']);

        $this->actingAs($this->admin, 'web_admin')
            ->get(route('laporan.pengawasan.pdf', [
                'unit_sekolah_id' => $this->unit->id,
                'from' => $this->date,
                'to' => $this->date,
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_kelas_penugasan_tetap_muncul_tanpa_menghitung_kelas_belum(): void
    {
        $guru = $this->pegawai('Guru Penugasan', true);
        $jadwal = $this->jadwal($guru, 'Kelas Penugasan');

        $tugas = TugasLuar::create([
            'pegawai_id' => $guru->id,
            'unit_sekolah_id' => $this->unit->id,
            'tanggal' => $this->date,
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '07:45:00',
            'tujuan' => 'Kegiatan sekolah',
            'created_by' => $this->admin->id,
        ]);

        $day = Presensi::create([
            'pegawai_id' => $guru->id,
            'jadwal_id' => null,
            'unit_sekolah_id' => $this->unit->id,
            'tipe_presensi' => 'tugas_luar',
            'tanggal' => $this->date,
            'jam_masuk' => '07:00:00',
            'is_tugas_luar' => true,
            'tugas_luar_status' => 'disetujui',
            'tugas_luar_id' => $tugas->id,
            'tujuan' => $tugas->tujuan,
        ]);
        $day->status = 'hadir';
        $day->save();

        app(TugasLuarCoverageService::class)->cover($day);

        $data = app(LaporanPengawasanService::class)->build([
            'from' => $this->date,
            'to' => $this->date,
            'unit_sekolah_id' => $this->unit->id,
            'jenis_pegawai' => 'all',
        ], $this->admin);

        $this->assertEquals(1, $data['summary']['actual_jp']);
        $this->assertSame(0, $data['summary']['kelas_belum']);
        $this->assertCount(1, $data['class_issues']);
        $this->assertSame('penugasan', $data['class_issues'][0]['status']);
        $this->assertSame('Penugasan', $data['class_issues'][0]['sumber']);
        $this->assertSame($jadwal->kelas_label, $data['class_issues'][0]['kelas']);
    }

    public function test_halaman_preview_pdf_dan_excel_bisa_diakses(): void
    {
        $guru = $this->pegawai('Guru Laporan', true);
        $jadwal = $this->jadwal($guru, 'Kelas Laporan');
        $this->presensi($guru, $jadwal->id, 'hadir');

        $params = [
            'unit_sekolah_id' => $this->unit->id,
            'from' => $this->date,
            'to' => $this->date,
        ];

        $this->actingAs($this->admin, 'web_admin')
            ->get(route('laporan.pengawasan'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Laporan/Pengawasan'));

        $this->actingAs($this->admin, 'web_admin')
            ->get(route('laporan.pengawasan.preview', $params))
            ->assertOk()
            ->assertJsonPath('summary.employees', 1)
            ->assertJsonPath('summary.actual_jp', 1)
            ->assertJsonPath('summary.scheduled_jp', 1);

        $this->actingAs($this->admin, 'web_admin')
            ->get(route('laporan.pengawasan.pdf', $params))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($this->admin, 'web_admin')
            ->get(route('laporan.pengawasan.excel', $params))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    private function report(): array
    {
        return app(LaporanPengawasanService::class)->build(['from' => $this->date, 'to' => $this->date, 'unit_sekolah_id' => $this->unit->id], $this->admin);
    }

    public function test_klarifikasi_version_audit_dan_perubahan_sumber(): void
    {
        $pegawai = $this->pegawai('Perlu klarifikasi', false);
        $data = $this->report();
        $finding = collect($data['followups'])->firstWhere('jenis', 'belum_tercatat');
        $this->assertNotNull($finding);
        $payload = ['finding_key' => $finding['key'], 'unit_sekolah_id' => $this->unit->id,
            'tanggal' => $this->date, 'version' => 0, 'status' => 'selesai', 'penanggung_jawab_id' => $this->admin->id,
            'catatan' => 'Sudah diklarifikasi kepada pegawai.', 'hasil' => 'alpa_terkonfirmasi'];
        $this->actingAs($this->admin, 'web_admin')->postJson(route('laporan.pengawasan.tindak-lanjut'), $payload)->assertOk()->assertJsonPath('record.version', 1);
        $this->postJson(route('laporan.pengawasan.tindak-lanjut'), $payload)->assertStatus(409);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => LaporanTindakLanjut::class, 'aksi' => 'create']);
        $this->assertSame(1, $this->report()['decision_summary']['alpa_terkonfirmasi']);
        $this->presensi($pegawai, null, 'hadir');
        $updated = $this->report();
        $this->assertSame(0, $updated['decision_summary']['alpa_terkonfirmasi']);
        $this->assertTrue(collect($updated['followups'])->firstWhere('key', $finding['key'])['source_changed']);
        $payload['version'] = 1;
        $payload['status'] = 'diproses';
        $payload['hasil'] = null;
        $this->postJson(route('laporan.pengawasan.tindak-lanjut'), $payload)->assertOk()->assertJsonCount(2, 'record.history');
    }

    public function test_pending_tidak_dinyatakan_alpa(): void
    {
        $pegawai = $this->pegawai('Izin pending', false);
        PengajuanIzin::create(['pegawai_id' => $pegawai->id, 'jenis_izin' => 'izin', 'tanggal_mulai' => $this->date, 'tanggal_selesai' => $this->date, 'alasan' => 'Menunggu keputusan kepala sekolah']);
        $data = $this->report();
        $this->assertSame(0, $data['decision_summary']['alpa_terkonfirmasi']);
        $this->assertSame(0, $data['decision_summary']['belum_tercatat']);
        $this->assertSame(1, $data['matrix'][0]['menunggu_keputusan']);
        $this->assertSame(1, $data['decision_summary']['administrasi_pending']);
    }

    public function test_hari_berjalan_dan_jadwal_mendatang_tidak_menjadi_temuan(): void
    {
        Carbon::setTestNow('2026-11-03 06:00:00');
        $guru = $this->pegawai('Belum mulai', true);
        $this->jadwal($guru, 'Kelas pagi');
        $this->assertSame(0, $this->report()['decision_summary']['kejadian']);
        $future = app(LaporanPengawasanService::class)->build(['from' => '2026-11-04', 'to' => '2026-11-04', 'unit_sekolah_id' => $this->unit->id], $this->admin);
        $this->assertSame(0, $future['decision_summary']['kejadian']);
    }

    public function test_unit_lain_dan_pimpinan_umum_tidak_boleh_menulis(): void
    {
        $this->pegawai('Pegawai unit', false);
        $finding = collect($this->report()['followups'])->first();
        $other = User::factory()->create(['unit_sekolah_id' => null]);
        $other->assignRole('admin_unit');
        $payload = ['finding_key' => $finding['key'], 'unit_sekolah_id' => $this->unit->id, 'tanggal' => $this->date,
            'version' => 0, 'status' => 'belum_ditindaklanjuti'];
        $this->actingAs($other, 'web_admin')->postJson(route('laporan.pengawasan.tindak-lanjut'), $payload)->assertForbidden();
        $other->syncRoles(['pimpinan']);
        $this->postJson(route('laporan.pengawasan.tindak-lanjut'), $payload)->assertForbidden();
        $this->actingAs($this->admin, 'web_admin')->postJson(route('laporan.pengawasan.tindak-lanjut'), array_merge($payload, ['status' => 'selesai']))->assertUnprocessable();
    }

    public function test_kepala_sekolah_bisa_mengelola_tindak_lanjut(): void
    {
        $kepsek = $this->pegawai('Kepala', false);
        $jabatan = Jabatan::firstOrCreate(['nama' => 'Kepala Sekolah'], ['is_guru' => false]);
        $kepsek->units()->updateExistingPivot($this->unit->id, ['jabatan_id' => $jabatan->id]);
        $user = $kepsek->user;
        $user->assignRole('pimpinan');
        $this->assertTrue(app(LaporanTindakLanjutAccess::class)->canManage($user));
    }

    public function test_kuota_minggu_berjalan_sementara_dan_identitas_stabil(): void
    {
        $pegawai = $this->pegawai('Guru kuota', true);
        $this->unit->update(['min_hari_minggu' => 4, 'min_hari_berlaku_mulai' => '2026-11-02']);
        UnitPresensiModeHistory::create(['unit_sekolah_id' => $this->unit->id, 'mode' => 'hanya_jadwal', 'berlaku_mulai' => '2026-11-02']);
        $this->assertEmpty(collect($this->report()['followups'])->where('jenis', 'kuota_kurang'));
        Carbon::setTestNow('2026-11-09 18:00:00');
        $filters = ['from' => '2026-11-02', 'to' => '2026-11-08', 'unit_sekolah_id' => $this->unit->id];
        $report = app(LaporanPengawasanService::class)->build($filters, $this->admin);
        $finding = collect($report['followups'])->firstWhere('jenis', 'kuota_kurang');
        $this->assertNotNull($finding);
        $short = app(LaporanPengawasanService::class)->build(array_merge($filters, ['from' => '2026-11-08']), $this->admin);
        $this->assertSame($finding['key'], collect($short['followups'])->firstWhere('jenis', 'kuota_kurang')['key']);
    }

    public function test_lintas_unit_tidak_menggandakan_jumlah_pegawai(): void
    {
        $pegawai = $this->pegawai('Lintas unit', true);
        $unit = UnitSekolah::create(['nama' => 'Unit kedua', 'singkatan' => 'U2', 'latitude' => 0, 'longitude' => 0, 'radius_meter' => 50]);
        $pegawai->units()->attach($unit->id, ['jabatan_id' => $pegawai->jabatans->first()->id, 'is_primary' => false]);
        $slot = $this->jadwal($pegawai, 'Kelas unit pertama');
        $this->presensi($pegawai, $slot->id, 'hadir');
        $report = app(LaporanPengawasanService::class)->build(['from' => $this->date, 'to' => $this->date], $this->admin);
        $this->assertCount(2, $report['matrix']);
        $this->assertSame(1, $report['summary']['employees']);
        $second = collect($report['matrix'])->firstWhere('unit_id', $unit->id);
        $this->assertSame(0, $second['hadir']);
        $this->assertEquals(0, $second['actual_jp']);
    }

    public function test_hari_libur_dan_periode_kosong(): void
    {
        $pegawai = $this->pegawai('Hari libur', true);
        $this->jadwal($pegawai, 'Kelas libur');
        HariLibur::create(['tanggal' => $this->date, 'nama' => 'Libur unit', 'unit_sekolah_id' => $this->unit->id]);
        $report = $this->report();
        $this->assertSame(0, $report['decision_summary']['kejadian']);
        $this->assertSame(0, $report['matrix'][0]['target_hari']);
        $this->assertNull($report['comparison']['metrics']['actual_jp']['delta_pp']);
        $rows = app(LaporanPemenuhanExportRows::class)->build($report);
        $this->assertContains(['Pegawai perlu klarifikasi', 0, 'Temuan terbuka', 0], $rows);
    }

    public function test_petugas_lintas_unit_dan_kunci_palsu_ditolak(): void
    {
        $this->pegawai('Temuan', false);
        $finding = collect($this->report()['followups'])->first();
        $outsider = User::factory()->create();
        $outsider->assignRole('admin_unit');
        $payload = ['finding_key' => $finding['key'], 'unit_sekolah_id' => $this->unit->id, 'tanggal' => $this->date,
            'version' => 0, 'status' => 'diproses', 'penanggung_jawab_id' => $outsider->id];
        $this->actingAs($this->admin, 'web_admin')->postJson(route('laporan.pengawasan.tindak-lanjut'), $payload)->assertUnprocessable();
        $payload['penanggung_jawab_id'] = $this->admin->id;
        $payload['finding_key'] = str_repeat('a', 64);
        $this->postJson(route('laporan.pengawasan.tindak-lanjut'), $payload)->assertUnprocessable();
    }

    private function pegawai(string $nama, bool $isGuru): Pegawai
    {
        $jabatan = Jabatan::firstOrCreate(
            ['nama' => $isGuru ? 'Guru Mata Pelajaran' : 'Tenaga Administrasi'],
            ['is_guru' => $isGuru],
        );
        $user = User::factory()->create();

        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '3273'.str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT),
            'nama_lengkap' => $nama,
            'jenis_kelamin' => 'L',
            'status_kepegawaian' => $isGuru ? 'guru_pemula' : 'tenaga_kependidikan',
            'status_aktif' => 'aktif',
            'tmt_mengajar' => '2020-01-01',
        ]);
        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    private function jadwal(Pegawai $guru, string $kelas, string $mulai = '07:00:00', string $selesai = '07:45:00'): Jadwal
    {
        return Jadwal::create([
            'pegawai_id' => $guru->id,
            'unit_sekolah_id' => $this->unit->id,
            'kelas_label' => $kelas,
            'hari' => Carbon::parse($this->date)->locale('id')->translatedFormat('l'),
            'jam_mulai' => $mulai,
            'jam_selesai' => $selesai,
            'jenis_jadwal' => 'mengajar',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);
    }

    private function presensi(Pegawai $pegawai, ?int $jadwalId, string $status): void
    {
        $presensi = Presensi::create([
            'pegawai_id' => $pegawai->id,
            'jadwal_id' => $jadwalId,
            'unit_sekolah_id' => $this->unit->id,
            'tipe_presensi' => $jadwalId ? 'mengajar' : 'harian',
            'tanggal' => $this->date,
            'jam_masuk' => '06:55:00',
        ]);
        $presensi->forceFill(['status' => $status])->save();
    }
}
