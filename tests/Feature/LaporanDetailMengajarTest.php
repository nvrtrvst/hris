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

class LaporanDetailMengajarTest extends TestCase
{
    use RefreshDatabase;

    private const MONDAY = '2024-06-03';

    private const WEDNESDAY = '2024-06-05';

    private UnitSekolah $unit;

    private User $superadmin;

    private Pegawai $guruTelat;

    private Pegawai $guruSkip;

    protected function setUp(): void
    {
        parent::setUp();
        // Rabu 10:00 — Senin sudah lewat, Rabu hari ini.
        $this->travelTo(Carbon::parse(self::WEDNESDAY.' 10:00:00'));
        $this->seed(RolePermissionSeeder::class);

        // durasi_jp 40 menit → JP math tidak pecah.
        $this->unit = UnitSekolah::create(['nama' => 'SMP', 'singkatan' => 'SMP', 'latitude' => -6.2, 'longitude' => 106.8, 'radius_meter' => 100, 'durasi_jp' => 40]);
        $jabatan = Jabatan::create(['nama' => 'Guru', 'is_guru' => true]);
        $this->superadmin = User::factory()->create();
        $this->superadmin->syncRoles('superadmin');

        $this->guruTelat = $this->makePegawai('Guru Telat', $jabatan);
        $this->guruSkip = $this->makePegawai('Guru Skip Presensi', $jabatan);

        foreach ([$this->guruTelat, $this->guruSkip] as $pegawai) {
            Jadwal::create(['pegawai_id' => $pegawai->id, 'unit_sekolah_id' => $this->unit->id, 'hari' => 'Senin', 'jam_mulai' => '08:00', 'jam_selesai' => '09:20', 'jenis_jadwal' => 'mengajar', 'kelas_label' => '7A', 'tahun_ajaran' => '2023/2024', 'semester' => 1]);
            Jadwal::create(['pegawai_id' => $pegawai->id, 'unit_sekolah_id' => $this->unit->id, 'hari' => 'Senin', 'jam_mulai' => '07:20', 'jam_selesai' => '08:00', 'jenis_jadwal' => 'piket', 'tahun_ajaran' => '2023/2024', 'semester' => 1]);
        }
        // Guru Telat: Rabu slot mengajar + izin disetujui.
        Jadwal::create(['pegawai_id' => $this->guruTelat->id, 'unit_sekolah_id' => $this->unit->id, 'hari' => 'Rabu', 'jam_mulai' => '08:00', 'jam_selesai' => '08:40', 'jenis_jadwal' => 'mengajar', 'kelas_label' => '7B', 'tahun_ajaran' => '2023/2024', 'semester' => 1]);
        PengajuanIzin::create([
            'pegawai_id' => $this->guruTelat->id, 'jenis_izin' => 'izin', 'tanggal_mulai' => self::WEDNESDAY,
            'tanggal_selesai' => self::WEDNESDAY, 'alasan' => 'Acara keluarga panjang',
        ])->forceFill(['status' => 'disetujui'])->save();

        // Guru Telat Senin: kantor telat 07:25 + sesi mengajar telat 08:05.
        Presensi::create([
            'pegawai_id' => $this->guruTelat->id, 'unit_sekolah_id' => $this->unit->id, 'tanggal' => self::MONDAY,
            'tipe_presensi' => 'kantor', 'jam_masuk' => '07:25:00',
        ])->forceFill(['status' => 'telat'])->save();
        $jadwalMengajar = Jadwal::where('pegawai_id', $this->guruTelat->id)->where('jenis_jadwal', 'mengajar')->where('hari', 'Senin')->first();
        Presensi::create([
            'pegawai_id' => $this->guruTelat->id, 'unit_sekolah_id' => $this->unit->id, 'jadwal_id' => $jadwalMengajar->id, 'tanggal' => self::MONDAY,
            'tipe_presensi' => 'mengajar', 'jam_masuk' => '08:05:00', 'jam_keluar' => '09:20:00',
        ])->forceFill(['status' => 'telat'])->save();
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
                'type' => 'mengajar_harian',
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

    public function test_telat_memotong_jam_dan_piket_ditutup_kantor(): void
    {
        $row = $this->rowByNama($this->preview(self::MONDAY, self::MONDAY)->json('detail'), 'Guru Telat', self::MONDAY);

        // Seharusnya 3 JP (2j), masuk 2.75 JP (1j 50m: telat memotong 5m mengajar + 5m piket).
        $this->assertSame('3 JP (2j)', $row['planned_text']);
        $this->assertSame('2.75 JP (1j 50m)', $row['masuk_text']);
        $this->assertSame('Kurang 10m', $row['kurang_text']);

        $sesi = collect($row['sesi']);
        $mengajar = $sesi->firstWhere('jenis', 'mengajar');
        $this->assertSame('telat', $mengajar['state']);
        $this->assertSame('08:05', $mengajar['jam']);
        $this->assertSame('Kurang 5m', $mengajar['kurang_text']);

        // Piket ditutup absen kantor 07:25 (telat 5m dari jam jadwal 07:20).
        $piket = $sesi->firstWhere('jenis', 'piket');
        $this->assertSame('telat', $piket['state']);
        $this->assertSame('07:25', $piket['jam']);
        $this->assertSame('Kurang 5m', $piket['kurang_text']);
    }

    public function test_hari_selesai_tanpa_presensi_semua_sesi_miss_full_kurang(): void
    {
        $row = $this->rowByNama($this->preview(self::MONDAY, self::MONDAY)->json('detail'), 'Guru Skip Presensi', self::MONDAY);

        $this->assertSame(['telat' => 0, 'sesi_miss' => 2], ['telat' => $row['sesi_telat'], 'sesi_miss' => $row['sesi_miss']]);
        $this->assertSame('3 JP (2j)', $row['planned_text']);
        $this->assertSame('Kurang 2j', $row['kurang_text']);
        $this->assertSame('-', $row['masuk_text']);

        // Statistik hitungan sesi tepat.
        $states = collect($row['sesi'])->mapWithKeys(fn ($s) => [$s['jenis'] => $s['state']]);
        $this->assertSame('miss', $states['mengajar']);
        $this->assertSame('miss', $states['piket']);
    }

    public function test_izin_dihari_jadwal_tidak_dihitung_kurang(): void
    {
        $row = $this->rowByNama($this->preview(self::WEDNESDAY, self::WEDNESDAY)->json('detail'), 'Guru Telat', self::WEDNESDAY);

        $this->assertSame('izin', $row['izin']);
        $this->assertSame('1 JP (40m)', $row['planned_text']);
        $this->assertSame('-', $row['masuk_text']);
        $this->assertSame('-', $row['kurang_text'], 'Izin tidak dihitung "tidak presensi"');

        $sesi = $row['sesi'][0];
        $this->assertSame('izin', $sesi['state']);

        // Pegawai tanpa slot Rabu tidak muncul.
        $names = collect($this->preview(self::WEDNESDAY, self::WEDNESDAY)->json('detail'))->pluck('nama')->unique()->values();
        $this->assertNotContains('Guru Skip Presensi', $names);
    }

    public function test_summary_dan_excel_kolom_pakai_durasi(): void
    {
        $res = $this->preview(self::MONDAY, self::WEDNESDAY);

        $summary = $res->json('summary');
        $this->assertEquals(7.0, $summary['plannedJp']);
        $this->assertEquals(2.75, $summary['masukJp']);
        $this->assertEquals(3.25, $summary['kurangJp']);
        $this->assertSame(2, $summary['sesiTelat']);
        $this->assertSame(2, $summary['sesiMiss']);
        $this->assertSame(1, $summary['izinCount']);

        // Excel: 9 kolom, detail sesi multiline dengan durasi kurang.
        $telatRow = collect($res->json('data'))->firstWhere(0, 'Guru Telat');
        $this->assertCount(9, $telatRow);
        $this->assertSame('3 JP (2j)', $telatRow[5], 'Kolom Seharusnya');
        $this->assertStringContainsString('Telat (kurang 5m)', $telatRow[8]);
        $this->assertStringContainsString("\n", $telatRow[8], '1 sesi per baris');
    }

    public function test_maksimal_31_hari_per_generate(): void
    {
        $this->actingAs($this->superadmin, 'web_admin')
            ->getJson('/laporan/preview?'.http_build_query([
                'type' => 'mengajar_harian',
                'start_date' => '2024-01-01',
                'end_date' => '2024-02-15',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('end_date');
    }
}
