<?php

namespace Tests\Feature;

use App\Exports\LaporanLemburanExport;
use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaporanLemburanExportTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->unit = UnitSekolah::create([
            'nama' => 'SMP Laporan Lembur',
            'singkatan' => 'SMPLL',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
        ]);

        $this->superadmin = User::factory()->create();
        $this->superadmin->assignRole('superadmin');
    }

    private function makePegawai(string $nama): Pegawai
    {
        $jabatan = Jabatan::create(['nama' => 'Guru '.$nama]);
        $user = User::factory()->create();
        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => str_pad((string) random_int(100000000000000, 999999999999999), 16, '0'),
            'nama_lengkap' => $nama,
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '1992-03-03',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'kawin',
            'alamat' => 'Jl. Lembur Test',
            'no_hp' => '081234567890',
            'status_kepegawaian' => 'guru_tetap_yayasan',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
        ]);
        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    private function makeLembur(Pegawai $pegawai, string $tanggal, string $lemburStatus, ?string $jamKeluar, array $geo = []): Presensi
    {
        $row = Presensi::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'tipe_presensi' => 'kantor',
            'tanggal' => $tanggal,
            'jam_masuk' => '18:00:00',
            'jarak_masuk_meter' => $geo['jarak_masuk'] ?? 12,
            'latitude_masuk' => $geo['lat_masuk'] ?? -6.200001,
            'longitude_masuk' => $geo['lng_masuk'] ?? 106.800001,
            'foto_masuk' => $geo['foto_masuk'] ?? null,
            'jam_keluar' => $jamKeluar,
            'jarak_keluar_meter' => $geo['jarak_keluar'] ?? null,
            'latitude_keluar' => $geo['lat_keluar'] ?? null,
            'longitude_keluar' => $geo['lng_keluar'] ?? null,
            'foto_keluar' => $geo['foto_keluar'] ?? null,
        ]);

        $row->forceFill([
            'is_lembur' => true,
            'lembur_status' => $lemburStatus,
        ])->save();

        return $row->refresh();
    }

    public function test_kolom_audit_lengkap_dan_status_dinamis(): void
    {
        $pegawai = $this->makePegawai('Guru Lembur');
        $disetujui = $this->makeLembur($pegawai, '2026-10-19', 'disetujui', '20:30:00', [
            'jarak_keluar' => 9, 'lat_keluar' => -6.2, 'lng_keluar' => 106.8,
            'foto_masuk' => 'lembur/a.jpg', 'foto_keluar' => 'lembur/b.jpg',
        ]);
        $pending = $this->makeLembur($pegawai, '2026-10-20', 'pending', null);

        $export = new LaporanLemburanExport('2026-10-19', '2026-10-20');
        $rows = $export->collection();

        $this->assertCount(2, $rows, 'Pending TANPA jam keluar ikut muncul');

        $headings = $export->headings();
        foreach (['Status', 'Lokasi Masuk', 'Jarak Masuk (m)', 'Koordinat Masuk', 'Lokasi Keluar', 'Jarak Keluar (m)', 'Koordinat Keluar', 'Foto Masuk', 'Foto Keluar'] as $label) {
            $this->assertContains($label, $headings);
        }

        $mapBaru = $export->map($disetujui);
        $this->assertSame('Disetujui', $mapBaru[5]);
        $this->assertSame('20:30:00', $mapBaru[7]);
        $this->assertSame(2.5, $mapBaru[8], '18:00–20:30 = 2.5 jam');
        $this->assertSame("'-6.200001, 106.800001", $mapBaru[11], 'Koordinat masuk (escapeFormula prefiks `-`)');
        $this->assertSame("'-6.200000, 106.800000", $mapBaru[14], 'Koordinat keluar');
        $this->assertNotSame('', $mapBaru[15], 'URL foto masuk terisi');
        $this->assertNotSame('', $mapBaru[16], 'URL foto keluar terisi');

        $mapPending = $export->map($pending);
        $this->assertSame('Menunggu', $mapPending[5]);
        $this->assertSame('—', $mapPending[8], 'Pending tanpa keluar: total jam em-dash');
        $this->assertSame('', $mapPending[16], 'Tanpa foto keluar = kosong');
    }

    public function test_filter_status_lembur_menyaring_baris(): void
    {
        $pegawai = $this->makePegawai('Guru Filter');
        $this->makeLembur($pegawai, '2026-10-19', 'disetujui', '19:00:00');
        $this->makeLembur($pegawai, '2026-10-20', 'ditolak', null);
        $this->makeLembur($pegawai, '2026-10-21', 'pending', null);

        $semua = (new LaporanLemburanExport('2026-10-19', '2026-10-21', null, null, null, 'semua'))->collection();
        $this->assertCount(3, $semua);

        $disetujui = (new LaporanLemburanExport('2026-10-19', '2026-10-21', null, null, null, 'disetujui'))->collection();
        $this->assertCount(1, $disetujui);
        $this->assertSame('disetujui', $disetujui->first()->lembur_status);

        $pending = (new LaporanLemburanExport('2026-10-19', '2026-10-21', null, null, null, 'pending'))->collection();
        $this->assertCount(1, $pending);
    }

    public function test_preview_route_lolos_dengan_filter_lembur_status(): void
    {
        $this->actingAs($this->superadmin)
            ->get('/laporan/preview?'.http_build_query([
                'type' => 'lemburan',
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-31',
                'lembur_status' => 'disetujui',
            ]))
            ->assertOk()
            ->assertJson(fn ($json) => $json
                ->where('headings.5', 'Status')
                ->etc());

        $this->actingAs($this->superadmin)
            ->getJson('/laporan/preview?'.http_build_query([
                'type' => 'lemburan',
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-31',
                'lembur_status' => 'haram',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('lembur_status');
    }
}
