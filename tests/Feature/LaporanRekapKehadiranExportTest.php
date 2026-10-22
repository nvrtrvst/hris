<?php

namespace Tests\Feature;

use App\Exports\LaporanRekapKehadiranExport;
use App\Models\HariLibur;
use App\Models\Jabatan;
use App\Models\Pegawai;
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
}
