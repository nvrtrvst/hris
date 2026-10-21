<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Jadwal;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaporanDetailHarianTest extends TestCase
{
    use RefreshDatabase;

    private const MONDAY = '2024-06-03';

    private const WEDNESDAY = '2024-06-05';

    private UnitSekolah $unit;

    private User $superadmin;

    private Pegawai $guru;

    private Pegawai $guruKosong;

    protected function setUp(): void
    {
        parent::setUp();
        // Rabu 10:00 — Senin sudah lewat, Rabu hari ini.
        $this->travelTo(Carbon::parse(self::WEDNESDAY.' 10:00:00'));
        $this->seed(RolePermissionSeeder::class);

        $this->unit = UnitSekolah::create(['nama' => 'SMP', 'singkatan' => 'SMP', 'latitude' => -6.2, 'longitude' => 106.8, 'radius_meter' => 100]);
        $jabatan = Jabatan::create(['nama' => 'Guru', 'is_guru' => true]);
        $this->superadmin = User::factory()->create();
        $this->superadmin->syncRoles('superadmin');

        $this->guru = $this->makePegawai('Guru Slid', $jabatan);
        $this->guruKosong = $this->makePegawai('Guru Tanpa Presensi', $jabatan);

        // Slot mengajar Senin 08:00-09:40 + slot piket Senin 07:00-16:00.
        Jadwal::create(['pegawai_id' => $this->guru->id, 'unit_sekolah_id' => $this->unit->id, 'hari' => 'Senin', 'jam_mulai' => '08:00', 'jam_selesai' => '09:40', 'jenis_jadwal' => 'mengajar', 'kelas_label' => '7A', 'tahun_ajaran' => '2023/2024', 'semester' => 1]);
        Jadwal::create(['pegawai_id' => $this->guruKosong->id, 'unit_sekolah_id' => $this->unit->id, 'hari' => 'Senin', 'jam_mulai' => '08:00', 'jam_selesai' => '09:40', 'jenis_jadwal' => 'mengajar', 'kelas_label' => '7B', 'tahun_ajaran' => '2023/2024', 'semester' => 1]);
        Jadwal::create(['pegawai_id' => $this->guruKosong->id, 'unit_sekolah_id' => $this->unit->id, 'hari' => 'Senin', 'jam_mulai' => '07:00', 'jam_selesai' => '16:00', 'jenis_jadwal' => 'piket', 'tahun_ajaran' => '2023/2024', 'semester' => 1]);

        // Guru Slid: Senin telat masuk kantor + slide mengajar (terekam, 08:05 > 08:00 = telat).
        Presensi::create([
            'pegawai_id' => $this->guru->id, 'unit_sekolah_id' => $this->unit->id, 'tanggal' => self::MONDAY,
            'tipe_presensi' => 'kantor', 'jam_masuk' => '07:41:00',
        ])->forceFill(['status' => 'telat'])->save();
        $jadwal = Jadwal::where('pegawai_id', $this->guru->id)->where('jenis_jadwal', 'mengajar')->first();
        Presensi::create([
            'pegawai_id' => $this->guru->id, 'unit_sekolah_id' => $this->unit->id, 'jadwal_id' => $jadwal->id, 'tanggal' => self::MONDAY,
            'tipe_presensi' => 'mengajar', 'jam_masuk' => '08:05:00', 'jam_keluar' => '09:40:00',
        ])->forceFill(['status' => 'telat'])->save();

        // Guru Slid: Rabu izin disetujui.
        PengajuanIzin::create([
            'pegawai_id' => $this->guru->id, 'jenis_izin' => 'izin', 'tanggal_mulai' => self::WEDNESDAY,
            'tanggal_selesai' => self::WEDNESDAY, 'alasan' => 'Acara keluarga panjang',
        ])->forceFill(['status' => 'disetujui'])->save();
    }

    private function makePegawai(string $nama, Jabatan $jabatan): Pegawai
    {
        $user = User::factory()->create();
        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => str_pad((string) random_int(100000000000000, 999999999999999), 16, '0'),
            'nama_lengkap' => $nama,
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'Belum Menikah',
            'alamat' => 'Jl. Test',
            'no_hp' => '081234567890',
            'status_kepegawaian' => 'guru_tetap_yayasan',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
        ]);
        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    private function preview(string $start, string $end)
    {
        return $this->actingAs($this->superadmin, 'web_admin')
            ->getJson('/laporan/preview?'.http_build_query([
                'type' => 'detail_harian',
                'start_date' => $start,
                'end_date' => $end,
                'unit_sekolah_id' => $this->unit->id,
            ]))
            ->assertOk();
    }

    private function rowByNama(array $rows, string $nama, string $tanggal): array
    {
        foreach ($rows as $r) {
            if ($r['nama'] === $nama && $r['tanggal'] === $tanggal) {
                return $r;
            }
        }
        $this->fail("Row $nama ($tanggal) tidak ada di preview.");
    }

    public function test_masuk_telat_slide_terekam_dan_piket_menutup_kantor(): void
    {
        $res = $this->preview(self::MONDAY, self::WEDNESDAY);
        $row = $this->rowByNama($res->json('detail'), 'Guru Slid', self::MONDAY);

        $this->assertSame('telat', $row['masuk_status']);
        $this->assertSame('07:41', $row['masuk_jam']);
        $this->assertSame('belum', $row['pulang_state']); // hari past, masuk terekam tapi belum absen keluar.
        $mengajar = collect($row['aktivitas'])->firstWhere('jenis', 'mengajar');
        $this->assertNotNull($mengajar);
        $this->assertSame('telat', $mengajar['state'], 'Sesi slide telat → chip Telat, bukan Hadir');
        $this->assertSame('08:05', $mengajar['jam']);
    }

    public function test_hari_sudah_lewat_tanpa_presensi_jadi_alpa_dan_chip_miss(): void
    {
        $res = $this->preview(self::MONDAY, self::WEDNESDAY);
        $row = $this->rowByNama($res->json('detail'), 'Guru Tanpa Presensi', self::MONDAY);

        $this->assertSame('alpa', $row['masuk_status']);
        $states = collect($row['aktivitas'])->mapWithKeys(fn ($c) => [$c['jenis'] => $c['state']]);
        $this->assertSame('miss', $states['mengajar']);
        $this->assertSame('miss', $states['piket']);
    }

    public function test_izin_disetujui_menggantikan_alpa_dan_chip_izin(): void
    {
        $res = $this->preview(self::MONDAY, self::WEDNESDAY);
        $row = $this->rowByNama($res->json('detail'), 'Guru Slid', self::WEDNESDAY);

        $this->assertSame('izin', $row['masuk_status']);
        // Rabu tak ada slot jadwal, jadi chip aktivitas kosong — status masuk "Izin" jadi penanda.
        $this->assertSame([], $row['aktivitas']);
        $this->assertSame(['telat' => 1, 'takSlide' => 2, 'belumPulang' => 1, 'izinCount' => 1], $res->json('summary'));
    }

    public function test_excel_map_punya_semua_kolom_dan_gemuk_formula_aman(): void
    {
        $res = $this->preview(self::MONDAY, self::WEDNESDAY);
        $row = $res->json('data')[0] ?? [];
        $this->assertCount(7, $row, 'Header lengkap = 7 kolom');
        $this->assertIsString($row[6], 'Kolom aktivitas harus string');
        $this->assertStringContainsString('Telat', $row[6], 'Sesi telat diberi tag Telat');

        $missRow = collect($res->json('data'))->firstWhere(0, 'Guru Tanpa Presensi');
        $this->assertStringContainsString('Tidak Presensi', $missRow[6]);
        $this->assertStringContainsString("\n", $missRow[6], '1 aktivitas per baris (multiline cell: mengajar + piket)');
    }
}
