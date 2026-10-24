<?php

namespace Tests\Feature;

use App\Exports\LaporanRekapKehadiranExport;
use App\Exports\LaporanRekapKehadiranHarianExport;
use App\Models\HariLibur;
use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaporanRekapKehadiranExportTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private User $superadmin;

    private Pegawai $pegawai;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->unit = UnitSekolah::create(['nama' => 'SMP', 'singkatan' => 'SMP', 'latitude' => -6.2, 'longitude' => 106.8, 'radius_meter' => 100, 'durasi_jp' => 40]);
        $jabatan = Jabatan::create(['nama' => 'Guru', 'is_guru' => true]);
        $this->superadmin = User::factory()->create();
        $this->superadmin->syncRoles('superadmin');

        $user = User::factory()->create();
        $this->pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => str_pad((string) random_int(100000000000000, 999999999999999), 16, '0'),
            'nama_lengkap' => 'Guru Rekap',
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
        $this->pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);
    }

    private function hadirKantor(string $tanggal, string $status = 'hadir'): void
    {
        Presensi::create([
            'pegawai_id' => $this->pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'tanggal' => $tanggal,
            'tipe_presensi' => 'kantor',
            'jam_masuk' => '07:00:00',
        ])->forceFill(['status' => $status])->save();
    }

    private function izinSakit(string $mulai, string $selesai, bool $denganSurat): void
    {
        PengajuanIzin::create([
            'pegawai_id' => $this->pegawai->id,
            'jenis_izin' => 'sakit',
            'tanggal_mulai' => $mulai,
            'tanggal_selesai' => $selesai,
            'alasan' => 'Demam',
            'dengan_surat_dokter' => $denganSurat,
        ])->forceFill(['status' => 'disetujui'])->save();
    }

    public function test_presensi_sabtu_tidak_masuk_rekap_dan_persen_tidak_melesat(): void
    {
        // Minggu 2 Jun s.d. Jumat 21 Jun 2024: 15 hari kerja Sen–Jum.
        $this->hadirKantor('2024-06-03');
        $this->hadirKantor('2024-06-04');
        $this->hadirKantor('2024-06-05');
        // Data lama: sabtu + minggu absen kantor (sebelum blokir absen akhir pekan).
        $this->hadirKantor('2024-06-08', 'telat');
        $this->hadirKantor('2024-06-09');

        $export = new LaporanRekapKehadiranExport('2024-06-02', '2024-06-21', $this->unit->id, null, null, null);

        $this->assertCount(1, $export->collection());
        $row = $export->collection()->first();
        $this->assertSame(3, $row['hadir']);
        $this->assertSame(0, $row['telat'], 'Presensi Sabtu tidak dihitung telat di rekap');
        $this->assertSame(0, $row['sakit']);
        $this->assertSame(0, $row['izin']);
        $this->assertSame(0, $row['cuti']);
        $this->assertSame(12, $row['alpa'], 'Alpa = hari kerja − status Sen–Jum');
        $this->assertSame(15, $row['hariKerja']);

        $map = $export->map($row);
        $this->assertSame('20%', $map[count($map) - 1], 'Persen = (3 hadir + 0 telat) / 15 hari kerja');
    }

    public function test_persen_di_clamp_maksimal_100_persen(): void
    {
        // Jumat 7 Jun libur nasional → hariKerja = 4, tapi data lama punya
        // 5 hadir Sen–Jum → tanpa clamp = 125%.
        $this->travelTo(Carbon::parse('2024-06-05 10:00:00'));
        HariLibur::create(['nama' => 'Libur Nasional', 'tanggal' => '2024-06-07', 'unit_sekolah_id' => null]);
        foreach (['2024-06-03', '2024-06-04', '2024-06-05', '2024-06-06', '2024-06-07'] as $d) {
            $this->hadirKantor($d);
        }

        $export = new LaporanRekapKehadiranExport('2024-06-03', '2024-06-07', $this->unit->id, null, null, null);
        $row = $export->collection()->first();
        $this->assertSame(4, $row['hariKerja']);
        $this->assertSame(5, $row['hadir']);
        $this->assertSame(0, $row['alpa'], 'Alpa di-clamp minimal 0');

        $map = $export->map($row);
        $this->assertSame('100%', $map[count($map) - 1], 'Persen clamp 100%');

        $summary = $export->summaryData();
        $this->assertEquals(100, $summary['avgKehadiran']);
    }

    public function test_rekap_bulanan_split_kolom_sakit_surat_dan_tanpa_surat(): void
    {
        // Sen–Rab 10–12 Jun 2024: sakit 3 hari; 2 hari ada surat (10–11), 1 hari tanpa (12).
        foreach (['2024-06-10', '2024-06-11', '2024-06-12'] as $d) {
            Presensi::create([
                'pegawai_id' => $this->pegawai->id,
                'unit_sekolah_id' => $this->unit->id,
                'tanggal' => $d,
                'tipe_presensi' => 'kantor',
            ])->forceFill(['status' => 'sakit'])->save();
        }
        $this->izinSakit('2024-06-10', '2024-06-11', true);
        $this->izinSakit('2024-06-12', '2024-06-12', false);

        $export = new LaporanRekapKehadiranExport('2024-06-10', '2024-06-12', $this->unit->id, null, null, null);
        $row = $export->collection()->first();
        $this->assertSame(3, $row['sakit']);
        $this->assertSame(2, $row['sakit_surat']);
        $this->assertSame(1, $row['sakit_tanpa_surat']);

        $this->assertContains('Sakit (dgn Surat)', $export->headings());
        $this->assertContains('Sakit (tanpa Surat)', $export->headings());
        $map = $export->map($row);
        $this->assertSame(2, $map[5], 'Kolom 6 = sakit dgn surat');
        $this->assertSame(1, $map[6], 'Kolom 7 = sakit tanpa surat');
    }

    public function test_rekap_harian_sel_s_asterisk_untuk_sakit_ber_surat(): void
    {
        Presensi::create([
            'pegawai_id' => $this->pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'tanggal' => '2024-06-10',
            'tipe_presensi' => 'kantor',
        ])->forceFill(['status' => 'sakit'])->save();
        Presensi::create([
            'pegawai_id' => $this->pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'tanggal' => '2024-06-11',
            'tipe_presensi' => 'kantor',
        ])->forceFill(['status' => 'sakit'])->save();
        $this->izinSakit('2024-06-10', '2024-06-10', true);

        $export = new LaporanRekapKehadiranHarianExport('2024-06-10', '2024-06-11', $this->unit->id, null, null, null);
        $row = $export->collection()->first();
        $this->assertSame('S*', $row['cells']['2024-06-10'], 'Sakit dgn surat = S*');
        $this->assertSame('S', $row['cells']['2024-06-11'], 'Sakit tanpa surat = S');
        $this->assertSame(1, $row['sakit_surat']);
        $this->assertSame(1, $row['sakit_tanpa_surat']);
        $this->assertContains('Sakit (dgn Surat)', $export->headings());
        $this->assertContains('Sakit (tanpa Surat)', $export->headings());
    }

    public function test_rekap_mencakup_pegawai_aktif_tanpa_record_presensi(): void
    {
        $export = new LaporanRekapKehadiranExport('2026-10-12', '2026-10-16', $this->unit->id, null, null, null);

        $this->assertCount(1, $export->collection());
        $row = $export->collection()->first();
        $this->assertSame(0, $row['hadir']);
        $this->assertSame(5, $row['hariKerja']);
        $this->assertSame(5, $row['alpa']);
        $this->assertSame(0, $row['persen']);
    }

    public function test_hari_libur_khusus_unit_dikurangi_dari_denominator(): void
    {
        HariLibur::create(['nama' => 'Libur Unit', 'tanggal' => '2026-10-16', 'unit_sekolah_id' => $this->unit->id]);
        foreach (['2026-10-12', '2026-10-13', '2026-10-14', '2026-10-15'] as $date) {
            $this->hadirKantor($date);
        }

        $export = new LaporanRekapKehadiranExport('2026-10-12', '2026-10-16', $this->unit->id, null, null, null);
        $row = $export->collection()->first();

        $this->assertSame(4, $row['hariKerja']);
        $this->assertSame(0, $row['alpa']);
        $this->assertSame(100, $row['persen']);
    }

    public function test_matriks_harian_menampilkan_pegawai_aktif_tanpa_record_sebagai_alpa(): void
    {
        $export = new LaporanRekapKehadiranHarianExport('2026-10-12', '2026-10-12', $this->unit->id, null, null, null);

        $this->assertCount(1, $export->collection());
        $row = $export->collection()->first();
        $this->assertSame('A', $row['cells']['2026-10-12']);
        $this->assertSame(1, $row['alpa']);
    }

    public function test_matriks_harian_menghormati_libur_khusus_unit(): void
    {
        HariLibur::create(['nama' => 'Libur Unit', 'tanggal' => '2026-10-16', 'unit_sekolah_id' => $this->unit->id]);
        foreach (['2026-10-12', '2026-10-13', '2026-10-14', '2026-10-15'] as $date) {
            $this->hadirKantor($date);
        }

        $export = new LaporanRekapKehadiranHarianExport('2026-10-12', '2026-10-16', $this->unit->id, null, null, null);
        $row = $export->collection()->first();

        $this->assertSame('L', $row['cells']['2026-10-16']);
        $this->assertSame(4, $row['hariKerja']);
        $this->assertSame(0, $row['alpa']);
    }
}
