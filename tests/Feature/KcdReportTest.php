<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\LaporanKcdCetak;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class KcdReportTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(UnitSekolah $unit): User
    {
        $user = User::factory()->create(['unit_sekolah_id' => $unit->id]);
        $perm = Permission::firstOrCreate(['name' => 'view_laporan_kcd', 'guard_name' => 'web']);
        $user->givePermissionTo($perm);

        return $user;
    }

    private function makePegawai(UnitSekolah $unit, Jabatan $jabatan, array $attrs = []): Pegawai
    {
        $pegawai = Pegawai::create(array_merge([
            'nik' => 'KCD'.fake()->unique()->numerify('######'),
            'nama_lengkap' => fake()->name(),
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'kawin',
            'alamat_ktp' => 'Jl. Test',
            'no_hp' => '08123456789',
            'status_kepegawaian' => 'tetap',
            'tanggal_mulai_kerja' => '2020-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
        ], $attrs));
        $pegawai->units()->attach($unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    public function test_kcd_pdf_generates_and_increments_nomor_cetak(): void
    {
        $unit = UnitSekolah::create([
            'nama' => 'SMP Test',
            'singkatan' => 'SMP',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 50,
            'durasi_jp' => 45,
            'max_jam_minggu' => 30,
            'jam_masuk_kantor' => '07:30',
            'jam_pulang_kantor' => '15:00',
        ]);
        $jabatan = Jabatan::create(['nama' => 'Guru', 'is_guru' => true]);
        $user = $this->makeUser($unit);
        $pegawai = $this->makePegawai($unit, $jabatan);

        $tanggal = now()->startOfMonth()->addWeekday(0)->toDateString();
        Presensi::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $unit->id,
            'tanggal' => $tanggal,
            'jam_masuk' => '07:45:00',
            'jam_keluar' => '15:10:00',
            'is_lembur' => false,
            'status' => 'hadir',
        ]);

        $this->actingAs($user, 'web_admin');

        $first = $this->get(route('laporan.kcd.pdf', ['unit_sekolah_id' => $unit->id, 'periode' => now()->format('Y-m')]));
        $first->assertOk();
        $first->assertHeader('content-disposition');

        $this->assertDatabaseHas('laporan_kcd_cetak', [
            'unit_sekolah_id' => $unit->id,
            'minggu' => null,
            'nomor_cetak' => 1,
        ]);

        $this->get(route('laporan.kcd.pdf', ['unit_sekolah_id' => $unit->id, 'periode' => now()->format('Y-m')]))
            ->assertOk();

        $this->assertSame(2, LaporanKcdCetak::where('unit_sekolah_id', $unit->id)->whereNull('minggu')->count());
    }

    public function test_koordinasi_izin_ditampilkan_sebagai_hadir(): void
    {
        $unit = UnitSekolah::create([
            'nama' => 'SMP Test',
            'singkatan' => 'SMP',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 50,
            'durasi_jp' => 45,
            'max_jam_minggu' => 30,
            'jam_masuk_kantor' => '07:30',
            'jam_pulang_kantor' => '15:00',
        ]);
        $jabatan = Jabatan::create(['nama' => 'Guru', 'is_guru' => true]);
        $user = $this->makeUser($unit);
        $pegawai = $this->makePegawai($unit, $jabatan);

        $mulai = now()->startOfMonth()->addWeekday(1)->toDateString();
        $selesai = now()->startOfMonth()->addWeekday(2)->toDateString();
        $izin = PengajuanIzin::create([
            'pegawai_id' => $pegawai->id,
            'jenis_izin' => 'sakit',
            'tanggal_mulai' => $mulai,
            'tanggal_selesai' => $selesai,
            'alasan' => 'Sakit demam',
            'dihitung_hadir_kcd' => true,
        ]);
        $izin->status = 'disetujui';
        $izin->save();

        $this->actingAs($user, 'web_admin');

        $res = $this->getJson(route('laporan.kcd.preview', ['unit_sekolah_id' => $unit->id, 'periode' => now()->format('Y-m')]));
        $res->assertOk();

        $peg = collect($res->json('pegawai'))->firstWhere('nama', $pegawai->nama_lengkap);
        $this->assertNotNull($peg);
        $this->assertTrue($peg['days'][$mulai]['koordinasi']);
        $this->assertSame('07.30', $peg['days'][$mulai]['masuk']);
    }

    public function test_hanya_guru_tetap_yang_muncul(): void
    {
        $unit = UnitSekolah::create([
            'nama' => 'SMP Test',
            'singkatan' => 'SMP',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 50,
            'durasi_jp' => 45,
            'max_jam_minggu' => 30,
            'jam_masuk_kantor' => '07:30',
            'jam_pulang_kantor' => '15:00',
        ]);
        $guruTetap = Jabatan::create(['nama' => 'Guru', 'is_guru' => true]);
        $tendik = Jabatan::create(['nama' => 'TU', 'is_guru' => false]);
        $user = $this->makeUser($unit);

        $this->makePegawai($unit, $guruTetap, ['nama_lengkap' => 'Guru Tetap']);
        $this->makePegawai($unit, $tendik, ['nama_lengkap' => 'Tendik']);
        $this->makePegawai($unit, $guruTetap, ['nama_lengkap' => 'Guru Honor', 'status_kepegawaian' => 'honorer']);

        $this->actingAs($user, 'web_admin');

        $res = $this->getJson(route('laporan.kcd.preview', ['unit_sekolah_id' => $unit->id, 'periode' => now()->format('Y-m')]));
        $res->assertOk();

        $names = collect($res->json('pegawai'))->pluck('nama')->all();
        $this->assertContains('Guru Tetap', $names);
        $this->assertNotContains('Tendik', $names);
        $this->assertNotContains('Guru Honor', $names);
    }

    public function test_minggu_filter_hanya_satu_blok(): void
    {
        $unit = UnitSekolah::create([
            'nama' => 'SMP Test',
            'singkatan' => 'SMP',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 50,
            'durasi_jp' => 45,
            'max_jam_minggu' => 30,
            'jam_masuk_kantor' => '07:30',
            'jam_pulang_kantor' => '15:00',
        ]);
        $jabatan = Jabatan::create(['nama' => 'Guru', 'is_guru' => true]);
        $user = $this->makeUser($unit);
        $this->makePegawai($unit, $jabatan);

        $this->actingAs($user, 'web_admin');

        $res = $this->getJson(route('laporan.kcd.preview', ['unit_sekolah_id' => $unit->id, 'periode' => now()->format('Y-m'), 'minggu' => 1]));
        $res->assertOk();

        $this->assertCount(1, $res->json('weeks'));

        $resFull = $this->getJson(route('laporan.kcd.preview', ['unit_sekolah_id' => $unit->id, 'periode' => now()->format('Y-m')]));
        $this->assertGreaterThan(1, count($resFull->json('weeks')));
    }
}
