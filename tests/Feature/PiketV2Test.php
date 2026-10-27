<?php

namespace Tests\Feature;

use App\Models\AbsenSiswaRevisi;
use App\Models\AbsenSiswaSesi;
use App\Models\Jabatan;
use App\Models\Jadwal;
use App\Models\LaporanPiket;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\PiketArea;
use App\Models\PiketItemTemplate;
use App\Models\PiketPenugasan;
use App\Models\PiketPos;
use App\Models\PiketRound;
use App\Models\PiketSesi;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use App\Services\PiketService;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PiketV2Test extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private User $admin;

    private PiketArea $area;

    private PiketPos $gerbang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->unit = UnitSekolah::create([
            'nama' => 'SMK Test',
            'singkatan' => 'SMK',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'toleransi_menit' => 0,
        ]);
        $this->unit->forceFill(['piket_mode' => 'area'])->save();

        $this->admin = User::factory()->create();
        $this->admin->assignRole('superadmin');

        $this->area = PiketArea::create([
            'unit_sekolah_id' => $this->unit->id,
            'nama' => 'Kampus 1',
            'urutan' => 1,
        ]);

        $this->gerbang = PiketPos::create([
            'unit_sekolah_id' => $this->unit->id,
            'area_id' => $this->area->id,
            'nama' => 'Gerbang Kampus 1',
            'jam_mulai' => '06:30',
            'jam_selesai' => '07:15',
            'is_wajib' => true,
            'aktif' => true,
        ]);

        PiketItemTemplate::create([
            'unit_sekolah_id' => $this->unit->id,
            'kode' => 'kelas_kosong',
            'label' => 'Kelas kosong',
            'seksi' => 'kbm',
            'scope' => 'area',
            'tipe' => 'centang',
            'wajib' => true,
            'urutan' => 1,
        ]);
    }

    private function pegawai(string $nama): Pegawai
    {
        $jabatan = Jabatan::firstOrCreate(['nama' => 'Guru']);
        $user = User::factory()->create();
        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '3273'.str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT),
            'nama_lengkap' => $nama,
            'jenis_kelamin' => 'L',
            'status_kepegawaian' => 'guru_tetap_yayasan',
            'status_aktif' => 'aktif',
            'tmt_mengajar' => '2020-01-01',
            'wajib_kantor' => true,
        ]);
        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    private function jadwal(Pegawai $pegawai): Jadwal
    {
        return Jadwal::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'hari' => 'Senin',
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '15:00:00',
            'jenis_jadwal' => 'piket',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);
    }

    private function masuk(Pegawai $pegawai): void
    {
        $p = Presensi::create([
            'pegawai_id' => $pegawai->id,
            'tipe_presensi' => 'kantor',
            'tanggal' => now()->toDateString(),
            'jam_masuk' => '06:40:00',
            'unit_sekolah_id' => $this->unit->id,
        ]);
        $p->status = 'hadir';
        $p->save();
    }

    private function geo(): array
    {
        return ['latitude' => -6.2, 'longitude' => 106.8, 'accuracy' => 15];
    }

    public function test_sesi_menautkan_petugas_dan_laporan(): void
    {
        Carbon::setTestNow('2026-10-26 07:00:00');

        $a = $this->pegawai('Petugas A');
        $b = $this->pegawai('Petugas B');
        $this->masuk($a);
        $this->masuk($b);
        $jadwalA = $this->jadwal($a);
        $this->jadwal($b);

        PiketPenugasan::create([
            'unit_sekolah_id' => $this->unit->id,
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
            'pegawai_id' => $a->id,
            'area_id' => $this->area->id,
            'peran' => 'area',
            'aktif' => true,
        ]);
        PiketPenugasan::create([
            'unit_sekolah_id' => $this->unit->id,
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
            'pegawai_id' => $b->id,
            'area_id' => $this->area->id,
            'pos_id' => $this->gerbang->id,
            'peran' => 'gerbang',
            'aktif' => true,
        ]);

        $this->actingAs($a->user, 'web_mobile')
            ->get(route('presensi.piket.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Mobile/Piket')
                ->where('piket.v2', true)
                ->where('piket.area.nama', 'Kampus 1')
                ->where('piket.pos.0.nama', 'Gerbang Kampus 1')
                ->has('piket.team', 2)
            );

        $sesi = PiketSesi::where('unit_sekolah_id', $this->unit->id)->firstOrFail();
        $this->assertSame(2, $sesi->petugas()->count());
        $this->assertDatabaseHas('laporan_piket', ['pegawai_id' => $a->id, 'sesi_id' => $sesi->id]);
    }

    public function test_pos_wajib_memblokir_kirim_dan_override_membuka(): void
    {
        Carbon::setTestNow('2026-10-26 16:00:00');

        $a = $this->pegawai('Petugas A');
        $this->masuk($a);
        $jadwal = $this->jadwal($a);

        PiketPenugasan::create([
            'unit_sekolah_id' => $this->unit->id,
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
            'pegawai_id' => $a->id,
            'area_id' => $this->area->id,
            'peran' => 'area',
            'aktif' => true,
        ]);

        $sesi = PiketService::sessionFor($a, $jadwal);
        $petugas = PiketService::petugasFor($sesi, $a->id);
        $laporan = LaporanPiket::find($petugas->laporan_piket_id);
        $laporan->forceFill([
            'items' => [
                'kelas_kosong' => ['done_at' => '15:30', 'nihil' => true],
            ],
        ])->save();

        $this->assertDatabaseHas('laporan_piket', ['pegawai_id' => $a->id, 'tanggal' => '2026-10-26']);
        $this->assertSame(1, LaporanPiket::count());

        $this->actingAs($a->user, 'web_mobile')
            ->post(route('presensi.piket.submit'))
            ->assertSessionHasErrors('laporan');

        $this->assertSame(LaporanPiket::STATUS_DRAFT, $laporan->fresh()->status);

        $this->actingAs($this->admin, 'web_admin')
            ->post(route('piket.sesi.pos.layani', [$sesi->id, $this->gerbang->id]), [
                'petugas_id' => $a->id,
                'alasan' => 'Petugas gerbang berhalangan, digantikan.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('piket_pos_log', [
            'sesi_id' => $sesi->id,
            'pos_id' => $this->gerbang->id,
            'sumber' => 'override',
        ]);

        $this->actingAs($a->user, 'web_mobile')
            ->post(route('presensi.piket.submit'))
            ->assertSessionHasNoErrors();

        $this->assertSame(LaporanPiket::STATUS_SUBMITTED, $laporan->fresh()->status);
        $this->assertSame('complete', $sesi->fresh()->status);
    }

    public function test_petugas_tidak_bisa_mencatat_pos_area_lain(): void
    {
        Carbon::setTestNow('2026-10-26 07:00:00');

        $lain = PiketArea::create([
            'unit_sekolah_id' => $this->unit->id,
            'nama' => 'Kampus 2',
            'urutan' => 2,
        ]);
        $posLain = PiketPos::create([
            'unit_sekolah_id' => $this->unit->id,
            'area_id' => $lain->id,
            'nama' => 'Gerbang Kampus 2',
            'jam_mulai' => '06:30',
            'jam_selesai' => '07:15',
            'is_wajib' => false,
            'aktif' => true,
        ]);

        $a = $this->pegawai('Petugas A');
        $this->masuk($a);
        $this->jadwal($a);
        PiketPenugasan::create([
            'unit_sekolah_id' => $this->unit->id,
            'pegawai_id' => $a->id,
            'area_id' => $this->area->id,
            'peran' => 'area',
            'aktif' => true,
        ]);

        $this->actingAs($a->user, 'web_mobile')
            ->post(route('presensi.piket.pos'), [
                'pos_id' => $posLain->id,
                'nihil' => true,
                ...$this->geo(),
            ])
            ->assertForbidden();
    }

    public function test_ronde_dibuat_dan_memblokir_kirim(): void
    {
        Carbon::setTestNow('2026-10-26 16:00:00');

        $a = $this->pegawai('Petugas A');
        $this->masuk($a);
        $jadwal = $this->jadwal($a);

        PiketPenugasan::create([
            'unit_sekolah_id' => $this->unit->id,
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
            'pegawai_id' => $a->id,
            'area_id' => $this->area->id,
            'peran' => 'area',
            'aktif' => true,
        ]);

        $sesi = PiketService::sessionFor($a, $jadwal);
        PiketService::ensureRounds($sesi, $jadwal);

        $this->assertSame(2, PiketRound::where('sesi_id', $sesi->id)->count());

        $petugas = PiketService::petugasFor($sesi, $a->id);
        $laporan = LaporanPiket::find($petugas->laporan_piket_id);
        $laporan->forceFill(['items' => ['kelas_kosong' => ['done_at' => '15:30', 'nihil' => true]]])->save();

        PiketService::recordPos($sesi, $this->gerbang, $a->id, true, null);

        $this->actingAs($a->user, 'web_mobile')
            ->post(route('presensi.piket.submit'))
            ->assertSessionHasErrors('laporan');

        foreach (PiketRound::where('sesi_id', $sesi->id)->get() as $round) {
            $round->forceFill(['selesai_at' => now(), 'status' => 'terlambat', 'alasan' => 'Menunggu instruksi kepala sekolah.'])->save();
        }

        $this->actingAs($a->user, 'web_mobile')
            ->post(route('presensi.piket.submit'))
            ->assertSessionHasNoErrors();

        $this->assertSame(LaporanPiket::STATUS_SUBMITTED, $laporan->fresh()->status);
    }

    public function test_ronde_terlambat_wajib_alasan(): void
    {
        Carbon::setTestNow('2026-10-26 16:00:00');

        $a = $this->pegawai('Petugas A');
        $this->masuk($a);
        $jadwal = $this->jadwal($a);
        PiketPenugasan::create([
            'unit_sekolah_id' => $this->unit->id,
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
            'pegawai_id' => $a->id,
            'area_id' => $this->area->id,
            'peran' => 'area',
            'aktif' => true,
        ]);

        $sesi = PiketService::sessionFor($a, $jadwal);
        PiketService::ensureRounds($sesi, $jadwal);
        $round = PiketRound::where('sesi_id', $sesi->id)->firstOrFail();

        $this->actingAs($a->user, 'web_mobile')
            ->post(route('presensi.piket.round', $round->id), $this->geo())
            ->assertSessionHasErrors('round');

        $this->actingAs($a->user, 'web_mobile')
            ->post(route('presensi.piket.round', $round->id), [
                'alasan' => 'Mendampingi siswa sakit di UKS.',
                ...$this->geo(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertNotNull($round->fresh()->selesai_at);
        $this->assertSame('terlambat', $round->fresh()->status);
    }

    public function test_bentrok_mengajar_dikecualikan_dan_kirim_setelah_kelas(): void
    {
        Carbon::setTestNow('2026-10-27 15:00:00');

        $a = $this->pegawai('Guru Piket');
        $this->masuk($a);
        $jadwal = $this->jadwal($a);
        $jadwal->forceFill(['hari' => 'Selasa', 'jam_selesai' => '18:00:00'])->save();

        Jadwal::create([
            'pegawai_id' => $a->id,
            'unit_sekolah_id' => $this->unit->id,
            'hari' => 'Selasa',
            'jam_mulai' => '14:00:00',
            'jam_selesai' => '18:00:00',
            'jenis_jadwal' => 'mengajar',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);

        PiketPenugasan::create([
            'unit_sekolah_id' => $this->unit->id,
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
            'pegawai_id' => $a->id,
            'area_id' => $this->area->id,
            'peran' => 'area',
            'aktif' => true,
        ]);

        $this->assertTrue(PiketService::overlapsTeaching($a, 'Selasa', '14:30', '15:30'));
        $this->assertSame('18:00', PiketService::lastTeachingEnd($a, 'Selasa'));

        $sesi = PiketService::sessionFor($a, $jadwal);
        PiketService::ensureRounds($sesi, $jadwal);
        foreach (PiketRound::where('sesi_id', $sesi->id)->get() as $round) {
            $round->forceFill(['selesai_at' => now(), 'status' => 'tepat'])->save();
        }
        $petugas = PiketService::petugasFor($sesi, $a->id);
        $laporan = LaporanPiket::find($petugas->laporan_piket_id);
        $laporan->forceFill(['items' => ['kelas_kosong' => ['done_at' => '13:00', 'nihil' => true]]])->save();
        PiketService::recordPos($sesi, $this->gerbang, $a->id, true, null);

        $this->actingAs($a->user, 'web_mobile')
            ->post(route('presensi.piket.submit'))
            ->assertSessionHasErrors('laporan');

        Carbon::setTestNow('2026-10-27 18:30:00');
        $this->actingAs($a->user, 'web_mobile')
            ->post(route('presensi.piket.submit'))
            ->assertSessionHasNoErrors();

        $this->assertSame(LaporanPiket::STATUS_SUBMITTED, $laporan->fresh()->status);
    }

    public function test_admin_bisa_membuka_ronde(): void
    {
        Carbon::setTestNow('2026-10-26 16:00:00');

        $a = $this->pegawai('Petugas A');
        $this->masuk($a);
        $jadwal = $this->jadwal($a);
        PiketPenugasan::create([
            'unit_sekolah_id' => $this->unit->id,
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
            'pegawai_id' => $a->id,
            'area_id' => $this->area->id,
            'peran' => 'area',
            'aktif' => true,
        ]);

        $sesi = PiketService::sessionFor($a, $jadwal);
        PiketService::ensureRounds($sesi, $jadwal);
        PiketService::recordPos($sesi, $this->gerbang, $a->id, true, null);

        $petugas = PiketService::petugasFor($sesi, $a->id);
        $laporan = LaporanPiket::find($petugas->laporan_piket_id);
        $laporan->forceFill(['status' => LaporanPiket::STATUS_SUBMITTED, 'submitted_at' => now()])->save();

        $round = PiketRound::where('sesi_id', $sesi->id)->firstOrFail();

        $this->actingAs($this->admin, 'web_admin')
            ->post(route('piket.round.buka', $round->id), ['alasan' => 'Laporan sudah dikirim sebelum sesi aktif.'])
            ->assertRedirect();

        $this->assertSame('dikecualikan', $round->fresh()->status);
        $this->assertSame('complete', $sesi->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'waive_piket_round']);
    }

    private function siapkanAbsenArea(Pegawai $pegawai): Jadwal
    {
        $this->masuk($pegawai);
        $jadwal = $this->jadwal($pegawai);

        PiketPenugasan::create([
            'unit_sekolah_id' => $this->unit->id,
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
            'pegawai_id' => $pegawai->id,
            'area_id' => $this->area->id,
            'peran' => 'area',
            'aktif' => true,
        ]);

        DB::table('piket_area_kelas')->insert([
            'unit_sekolah_id' => $this->unit->id,
            'area_id' => $this->area->id,
            'kelas_label' => 'X - A',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $jadwal;
    }

    private function payloadAbsen(array $overrides = []): array
    {
        return array_merge([
            'class_id' => '101',
            'class_label' => 'X A',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'accuracy' => 15,
            'siswa' => [
                ['nis' => '001', 'nama' => 'Siswa Satu', 'status' => 'hadir', 'keterangan' => null],
                ['nis' => '002', 'nama' => 'Siswa Dua', 'status' => 'izin', 'keterangan' => 'Acara keluarga'],
            ],
        ], $overrides);
    }

    public function test_absen_siswa_menyimpan_revisi_dan_alasan_timpa(): void
    {
        Carbon::setTestNow('2026-10-26 09:00:00');
        $a = $this->pegawai('Petugas A');
        $this->siapkanAbsenArea($a);

        $this->actingAs($a->user, 'web_mobile')
            ->post(route('presensi.piket.absen-siswa'), $this->payloadAbsen())
            ->assertSessionHasNoErrors();

        $sesi = AbsenSiswaSesi::where('pegawai_id', $a->id)->firstOrFail();
        $this->assertNotNull($sesi->done_at);
        $this->assertSame(1, AbsenSiswaRevisi::where('absen_siswa_sesi_id', $sesi->id)->count());

        $this->actingAs($a->user, 'web_mobile')
            ->post(route('presensi.piket.absen-siswa'), $this->payloadAbsen())
            ->assertSessionHasErrors('alasan');

        $this->actingAs($a->user, 'web_mobile')
            ->post(route('presensi.piket.absen-siswa'), $this->payloadAbsen(['alasan' => 'Koreksi status izin']))
            ->assertSessionHasNoErrors();

        $revisi = AbsenSiswaRevisi::where('absen_siswa_sesi_id', $sesi->id)->orderBy('id')->get();
        $this->assertCount(2, $revisi);
        $this->assertSame('Koreksi status izin', $revisi->last()->alasan);
    }

    public function test_admin_bisa_koreksi_absen_siswa_setelah_laporan_dikirim(): void
    {
        Carbon::setTestNow('2026-10-26 16:00:00');
        $a = $this->pegawai('Petugas A');
        $jadwal = $this->siapkanAbsenArea($a);

        $sesi = AbsenSiswaSesi::create([
            'unit_sekolah_id' => $this->unit->id,
            'pegawai_id' => $a->id,
            'tanggal' => now()->toDateString(),
            'class_id' => '101',
            'class_label' => 'X A',
        ]);
        $sesi->details()->create(['nis' => '001', 'nama_snapshot' => 'Siswa Satu', 'status' => 'hadir']);

        $laporan = LaporanPiket::create([
            'pegawai_id' => $a->id,
            'unit_sekolah_id' => $this->unit->id,
            'tanggal' => now()->toDateString(),
            'status' => LaporanPiket::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'items' => [],
        ]);

        $this->actingAs($this->admin, 'web_admin')
            ->post(route('absen-siswa.koreksi', $sesi->id), [
                'alasan' => 'Surat dokter masuk setelah laporan dikirim',
                'siswa' => [
                    ['nis' => '001', 'nama' => 'Siswa Satu', 'status' => 'sakit', 'keterangan' => 'Demam'],
                ],
            ])
            ->assertRedirect();

        $this->assertSame('sakit', $sesi->details()->first()->status);
        $this->assertDatabaseHas('absen_siswa_revisi', [
            'absen_siswa_sesi_id' => $sesi->id,
            'peran' => 'admin',
        ]);
    }

    public function test_setujui_amanat_piket_memverifikasi_tugas_sakit_bersurat(): void
    {
        Carbon::setTestNow('2026-10-26 16:00:00');

        $piket = $this->pegawai('Petugas Piket');
        $guru = $this->pegawai('Guru Sakit');

        $izin = PengajuanIzin::create([
            'pegawai_id' => $guru->id,
            'jenis_izin' => 'sakit',
            'tanggal_mulai' => '2026-10-26',
            'tanggal_selesai' => '2026-10-26',
            'alasan' => 'Demam tinggi perlu istirahat.',
            'dengan_surat_dokter' => true,
        ]);
        $izin->forceFill(['status' => 'disetujui'])->save();

        $laporan = LaporanPiket::create([
            'pegawai_id' => $piket->id,
            'unit_sekolah_id' => $this->unit->id,
            'tanggal' => '2026-10-26',
            'status' => LaporanPiket::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'items' => [
                LaporanPiket::ITEM_KELAS_KOSONG => ['entries' => [[
                    'kelas' => 'X A',
                    'guru_pegawai_id' => $guru->id,
                    'guru_nama' => 'Guru Sakit',
                    'ada_amanat' => true,
                    'tersampaikan' => true,
                    'validasi' => 'cocok',
                    'sumber' => 'manual',
                ]]],
            ],
        ]);

        $this->actingAs($this->admin, 'web_admin')
            ->post(route('laporan-piket.validasi', $laporan->id), ['entry_index' => 0, 'aksi' => 'setujui'])
            ->assertRedirect();

        $this->assertTrue((bool) $izin->fresh()->tugas_terverifikasi);
        $this->assertNotNull($izin->fresh()->tugas_diverifikasi_at);
    }

    public function test_setujui_amanat_belum_tersampaikan_tidak_memverifikasi_tugas(): void
    {
        Carbon::setTestNow('2026-10-26 16:00:00');

        $piket = $this->pegawai('Petugas Piket');
        $guru = $this->pegawai('Guru Sakit');

        $izin = PengajuanIzin::create([
            'pegawai_id' => $guru->id,
            'jenis_izin' => 'sakit',
            'tanggal_mulai' => '2026-10-26',
            'tanggal_selesai' => '2026-10-26',
            'alasan' => 'Demam tinggi perlu istirahat.',
            'dengan_surat_dokter' => true,
        ]);
        $izin->forceFill(['status' => 'disetujui'])->save();

        $laporan = LaporanPiket::create([
            'pegawai_id' => $piket->id,
            'unit_sekolah_id' => $this->unit->id,
            'tanggal' => '2026-10-26',
            'status' => LaporanPiket::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'items' => [
                LaporanPiket::ITEM_KELAS_KOSONG => ['entries' => [[
                    'kelas' => 'X A',
                    'guru_pegawai_id' => $guru->id,
                    'guru_nama' => 'Guru Sakit',
                    'ada_amanat' => true,
                    'tersampaikan' => false,
                    'validasi' => 'cocok',
                    'sumber' => 'manual',
                ]]],
            ],
        ]);

        $this->actingAs($this->admin, 'web_admin')
            ->post(route('laporan-piket.validasi', $laporan->id), ['entry_index' => 0, 'aksi' => 'setujui'])
            ->assertRedirect();

        $this->assertFalse((bool) $izin->fresh()->tugas_terverifikasi);
    }

    public function test_kelas_unit_area_tidak_error_dan_terfilter(): void
    {
        Carbon::setTestNow('2026-10-26 07:00:00');
        config(['keuangan.url' => 'https://keuangan.test', 'keuangan.key' => 'test-key']);

        $piket = $this->pegawai('Petugas Piket');
        $jadwal = $this->jadwal($piket);

        PiketPenugasan::create([
            'unit_sekolah_id' => $this->unit->id,
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
            'pegawai_id' => $piket->id,
            'area_id' => $this->area->id,
            'peran' => 'area',
            'aktif' => true,
        ]);

        DB::table('piket_area_kelas')->insert([
            'unit_sekolah_id' => $this->unit->id,
            'area_id' => $this->area->id,
            'kelas_label' => 'X A',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::fake([
            '*' => Http::response([
                'data' => ['classes' => [
                    ['id' => 1, 'grade' => 'X', 'name' => 'A'],
                    ['id' => 2, 'grade' => 'Y', 'name' => 'B'],
                ]],
            ]),
        ]);

        $this->actingAs($piket->user, 'web_mobile')
            ->getJson(route('presensi.jadwal.kelas', ['jadwal_id' => $jadwal->id]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'kelas')
            ->assertJsonPath('kelas.0.name', 'A');
    }

    public function test_cek_absen_menyimpan_guru_pegawai_id(): void
    {
        Carbon::setTestNow('2026-10-26 07:00:00');

        $piket = $this->pegawai('Petugas Piket');
        $guru = $this->pegawai('Guru Sakit');
        $this->masuk($piket);
        $this->jadwal($piket);

        PiketPenugasan::create([
            'unit_sekolah_id' => $this->unit->id,
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
            'pegawai_id' => $piket->id,
            'area_id' => $this->area->id,
            'peran' => 'area',
            'aktif' => true,
        ]);
        PiketItemTemplate::create([
            'unit_sekolah_id' => $this->unit->id,
            'kode' => 'cek_absen',
            'label' => 'Cek guru',
            'seksi' => 'pagi',
            'scope' => 'area',
            'tipe' => 'tambah',
            'wajib' => true,
            'urutan' => 2,
        ]);

        $this->actingAs($piket->user, 'web_mobile')
            ->post(route('presensi.piket.item'), [
                'kode' => 'cek_absen',
                'mode' => 'tambah',
                'guru_pegawai_id' => $guru->id,
                'keterangan_absen' => 'Sakit, tidak masuk kelas.',
                ...$this->geo(),
            ])
            ->assertRedirect();

        $entry = LaporanPiket::where('pegawai_id', $piket->id)->firstOrFail()
            ->items['cek_absen']['entries'][0];

        $this->assertSame($guru->id, $entry['guru_pegawai_id']);
        $this->assertSame('Guru Sakit', $entry['nama_guru']);
        $this->assertSame('daftar', $entry['sumber']);
    }

    public function test_validasi_kelas_kosong_memakai_presensi_slot_bukan_harian(): void
    {
        Carbon::setTestNow('2026-10-26 10:00:00');

        $piket = $this->pegawai('Petugas Piket');
        $guru = $this->pegawai('Guru Kelas');
        $this->masuk($piket);
        $this->jadwal($piket);

        $pagi = Jadwal::create([
            'pegawai_id' => $guru->id,
            'unit_sekolah_id' => $this->unit->id,
            'hari' => 'Senin',
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '07:45:00',
            'jenis_jadwal' => 'mengajar',
            'kelas_label' => 'X A',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);
        $siang = Jadwal::create([
            'pegawai_id' => $guru->id,
            'unit_sekolah_id' => $this->unit->id,
            'hari' => 'Senin',
            'jam_mulai' => '09:00:00',
            'jam_selesai' => '09:45:00',
            'jenis_jadwal' => 'mengajar',
            'kelas_label' => 'X B',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);

        $presensi = Presensi::create([
            'pegawai_id' => $guru->id,
            'jadwal_id' => $pagi->id,
            'unit_sekolah_id' => $this->unit->id,
            'tipe_presensi' => 'mengajar',
            'tanggal' => '2026-10-26',
            'jam_masuk' => '07:00:00',
        ]);
        $presensi->status = 'hadir';
        $presensi->save();

        PiketPenugasan::create([
            'unit_sekolah_id' => $this->unit->id,
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
            'pegawai_id' => $piket->id,
            'area_id' => $this->area->id,
            'peran' => 'area',
            'aktif' => true,
        ]);

        $this->actingAs($piket->user, 'web_mobile')
            ->post(route('presensi.piket.item'), [
                'kode' => 'kelas_kosong',
                'mode' => 'tambah',
                'slot_jadwal_id' => $siang->id,
                'ada_amanat' => false,
                ...$this->geo(),
            ])
            ->assertRedirect();

        $entry = LaporanPiket::where('pegawai_id', $piket->id)->firstOrFail()
            ->items['kelas_kosong']['entries'][0];

        $this->assertSame('cocok', $entry['validasi']);
        $this->assertSame($siang->id, $entry['slot_jadwal_id']);
    }

    public function test_laporan_piket_v2_menampilkan_progress_pos_dan_filter_kualitas(): void
    {
        Carbon::setTestNow('2026-10-26 07:00:00');

        $a = $this->pegawai('Petugas A');
        $this->masuk($a);
        $jadwal = $this->jadwal($a);

        PiketPenugasan::create([
            'unit_sekolah_id' => $this->unit->id,
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
            'pegawai_id' => $a->id,
            'area_id' => $this->area->id,
            'peran' => 'area',
            'aktif' => true,
        ]);

        $sesi = PiketService::sessionFor($a, $jadwal);
        $this->assertNotNull($sesi);

        $this->actingAs($this->admin, 'web_admin')
            ->get(route('laporan.piket', ['start_date' => '2026-10-26', 'end_date' => '2026-10-26']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Laporan/Piket')
                ->where('laporan.0.legacy', false)
                ->where('laporan.0.progress.item_total', 1)
                ->where('laporan.0.progress.pos_total', 1)
                ->where('laporan.0.progress.pos_done', 0)
                ->has('laporan.0.pos', 1)
                ->where('laporan.0.pos.0.nama', 'Gerbang Kampus 1')
                ->where('laporan.0.pos_belum.0', 'Gerbang Kampus 1')
            );

        $this->actingAs($this->admin, 'web_admin')
            ->get(route('laporan.piket', ['start_date' => '2026-10-26', 'end_date' => '2026-10-26', 'kualitas' => 'pos_belum']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('laporan', 1));

        $this->actingAs($this->admin, 'web_admin')
            ->get(route('laporan.piket', ['start_date' => '2026-10-26', 'end_date' => '2026-10-26', 'kualitas' => 'ronde_terlewat']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('laporan', 0));
    }
}
