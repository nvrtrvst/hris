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

class PresensiRingkasJpTerlewatTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private User $superadmin;

    private Pegawai $guru;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        // Kamis, pukul 10:00 — JP yang selesai sebelum 10:00 dianggap terlewat,
        // JP yang belum mulai tidak boleh ditandai terlewat.
        Carbon::setTestNow('2026-08-20 10:00:00');

        $this->unit = UnitSekolah::create([
            'nama' => 'SMP JP Terlewat',
            'singkatan' => 'SMPJP',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'toleransi_menit' => 0,
            'toleransi_slide_menit' => 15,
        ]);

        $this->superadmin = User::factory()->create(['role' => 'superadmin']);
        $this->superadmin->assignRole('superadmin');

        $jabatan = Jabatan::create(['nama' => 'Guru JP Terlewat', 'is_guru' => true]);

        $this->guru = Pegawai::create([
            'user_id' => User::factory()->create()->id,
            'nik' => '3273010101900001',
            'nama_lengkap' => 'Guru JP Terlewat',
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'kawin',
            'jumlah_tanggungan' => 0,
            'alamat' => 'Jl. Uji No. 1',
            'no_hp' => '081200000001',
            'status_kepegawaian' => 'guru_pemula',
            'tmt_mengajar' => '2021-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
        ]);

        $this->guru->units()->attach($this->unit->id, [
            'jabatan_id' => $jabatan->id,
            'is_primary' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function buatJadwal(string $hari, string $mulai, string $selesai): Jadwal
    {
        return Jadwal::create([
            'pegawai_id' => $this->guru->id,
            'unit_sekolah_id' => $this->unit->id,
            'hari' => $hari,
            'jam_mulai' => $mulai,
            'jam_selesai' => $selesai,
            'jenis_jadwal' => 'mengajar',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);
    }

    private function buatPresensi(Jadwal $jadwal, ?string $jamMasuk, ?string $jamKeluar, string $status): Presensi
    {
        $presensi = Presensi::create([
            'pegawai_id' => $this->guru->id,
            'jadwal_id' => $jadwal->id,
            'unit_sekolah_id' => $this->unit->id,
            'tipe_presensi' => 'mengajar',
            'tanggal' => '2026-08-20',
            'jam_masuk' => $jamMasuk,
            'jam_keluar' => $jamKeluar,
        ]);

        // `status` masuk daftar $guarded, jadi harus di-assign langsung.
        $presensi->status = $status;
        $presensi->save();

        return $presensi;
    }

    private function propsRingkas(string $query = ''): array
    {
        $res = $this->actingAs($this->superadmin)->get('/presensi?display_mode=ringkas'.$query);
        $res->assertOk();

        // Inertia mengirim baris sebagai objek di view data; samakan dengan
        // bentuk yang diterima frontend (array hasil JSON) agar assertion mudah.
        return json_decode(json_encode($res->viewData('page')['props']['presensis']['data']), true);
    }

    public function test_jp_terlewat_yang_belum_diabsen_muncul_sebagai_baris_sintetis(): void
    {
        $jp1 = $this->buatJadwal('Kamis', '07:00:00', '07:45:00');
        $jp2 = $this->buatJadwal('Kamis', '08:30:00', '09:15:00'); // sudah lewat, tanpa presensi
        $jp3 = $this->buatJadwal('Kamis', '11:00:00', '11:45:00'); // belum mulai

        $this->buatPresensi($jp1, '07:00:00', '07:45:00', 'hadir');

        $data = collect($this->propsRingkas());

        $this->assertCount(2, $data, 'Hanya JP yang sudah lewat tanpa presensi yang disisipkan.');

        $sintetis = $data->firstWhere('is_sintetis', true);
        $this->assertNotNull($sintetis, 'Baris JP terlewat tidak ditemukan.');
        $this->assertSame('belum_presensi', $sintetis['status']);
        $this->assertSame($jp2->id, $sintetis['jadwal']['id']);
        $this->assertNull($sintetis['jam_masuk']);
        $this->assertNull($sintetis['jam_keluar']);

        $this->assertFalse(
            $data->contains(fn ($row) => ($row['jadwal']['id'] ?? null) === $jp3->id),
            'JP yang belum mulai tidak boleh ditandai terlewat.'
        );
    }

    public function test_jp_yang_sudah_punya_presensi_tidak_digandakan(): void
    {
        $jp1 = $this->buatJadwal('Kamis', '07:00:00', '07:45:00');
        $this->buatPresensi($jp1, '07:00:00', '07:45:00', 'hadir');

        $data = collect($this->propsRingkas());

        $this->assertCount(1, $data);
        $this->assertNull($data->first()['is_sintetis'] ?? null);
    }

    public function test_baris_alpa_yang_sudah_ada_tidak_digandakan(): void
    {
        $jp1 = $this->buatJadwal('Kamis', '07:00:00', '07:45:00');
        $this->buatPresensi($jp1, null, null, 'alpa');

        $data = collect($this->propsRingkas());

        $this->assertCount(1, $data);
        $this->assertSame('alpa', $data->first()['status']);
        $this->assertNull($data->first()['is_sintetis'] ?? null);
    }

    public function test_izin_disetujui_tidak_menghasilkan_baris_sintetis(): void
    {
        $jp1 = $this->buatJadwal('Kamis', '07:00:00', '07:45:00');
        $this->buatJadwal('Kamis', '08:30:00', '09:15:00');
        $this->buatPresensi($jp1, '07:00:00', '07:45:00', 'hadir');

        $izin = PengajuanIzin::create([
            'pegawai_id' => $this->guru->id,
            'jenis_izin' => 'izin',
            'tanggal_mulai' => '2026-08-20',
            'tanggal_selesai' => '2026-08-20',
            'alasan' => 'Keperluan keluarga yang mendesak',
        ]);
        $izin->status = 'disetujui';
        $izin->save();

        $data = collect($this->propsRingkas());

        $this->assertCount(1, $data);
        $this->assertNull($data->first()['is_sintetis'] ?? null);
    }

    public function test_filter_status_tidak_disuntik_baris_sintetis(): void
    {
        $jp1 = $this->buatJadwal('Kamis', '07:00:00', '07:45:00');
        $this->buatJadwal('Kamis', '08:30:00', '09:15:00');
        $this->buatPresensi($jp1, '07:00:00', '07:45:00', 'hadir');

        $data = collect($this->propsRingkas('&status_filter=hadir'));

        $this->assertCount(1, $data);
        $this->assertNull($data->first()['is_sintetis'] ?? null);
    }

    public function test_cron_finalize_alpa_terdaftar_pada_schedule(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('presensi:finalize-alpa')
            ->assertSuccessful();
    }
}
