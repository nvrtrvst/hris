<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Jadwal;
use App\Models\LaporanPiket;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use App\Services\LaporanPengawasanService;
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

        Carbon::setTestNow('2026-11-03 12:00:00');
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
        $this->assertSame('1 hari JP kurang', $row['temuan']);
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
