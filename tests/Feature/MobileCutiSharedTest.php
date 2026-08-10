<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\UnitSekolah;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MobileCutiSharedTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unit = UnitSekolah::create([
            'nama' => 'SMP Test',
            'singkatan' => 'SMP',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'toleransi_menit' => 0,
        ]);
    }

    private function makePegawaiWithCuti(): Pegawai
    {
        $jabatan = Jabatan::create(['nama' => 'Guru']);
        $user = User::factory()->create();
        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '11223344'.str_pad((string) $user->id, 8, '0', STR_PAD_LEFT),
            'nama_lengkap' => 'Guru Berhak Cuti',
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'kawin',
            'jumlah_tanggungan' => 2,
            'alamat_ktp' => 'Jl. Test No. 1',
            'no_hp' => '081234567890',
            'status_kepegawaian' => 'tetap',
            'tanggal_mulai_kerja' => '2020-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
            'jatah_cuti_tahunan' => 12,
        ]);
        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        // 2 hari cuti disetujui di tahun berjalan.
        // Catatan: `status` ada di $guarded — set eksplisit setelah create.
        $izin = new PengajuanIzin([
            'pegawai_id' => $pegawai->id,
            'jenis_izin' => 'cuti',
            'tanggal_mulai' => Carbon::now()->startOfMonth()->format('Y-m-d'),
            'tanggal_selesai' => Carbon::now()->startOfMonth()->addDay()->format('Y-m-d'),
            'alasan' => 'Cuti tahunan test',
        ]);
        $izin->status = 'disetujui';
        $izin->save();

        return $pegawai;
    }

    public function test_profile_mobile_menampilkan_sisa_cuti(): void
    {
        $pegawai = $this->makePegawaiWithCuti();

        $res = $this->actingAs($pegawai->user, 'web_mobile')
            ->get(route('presensi.profile.edit'));

        $res->assertOk();
        // Shared auth.user.pegawai harus membawa sisa_cuti (12 - 2 = 10)
        $pegawaiJson = $res->viewData('page')['props']['auth']['user']['pegawai'] ?? null;
        $this->assertNotNull($pegawaiJson, 'auth.user.pegawai tidak ada di props');
        $this->assertSame(10, (int) $pegawaiJson['sisa_cuti']);
        $this->assertSame(2, (int) $pegawaiJson['cuti_terpakai']);
    }

    public function test_izin_create_menampilkan_sisa_cuti(): void
    {
        $pegawai = $this->makePegawaiWithCuti();

        $res = $this->actingAs($pegawai->user, 'web_mobile')
            ->get(route('presensi.izin.create'));

        $res->assertOk();
        $pegawaiJson = $res->viewData('page')['props']['pegawai'] ?? null;
        $this->assertNotNull($pegawaiJson);
        $this->assertSame(10, (int) $pegawaiJson['sisa_cuti']);
    }

    public function test_serialisasi_pegawai_tanpa_cuti_info_tidak_query_pengajuan_izins(): void
    {
        $pegawai = $this->makePegawaiWithCuti();

        // Reset query log setelah setup, lalu akses pegawai tanpa loadCutiInfo()
        DB::flushQueryLog();
        DB::enableQueryLog();

        $pegawai->toArray();

        $queries = collect(DB::getQueryLog())->pluck('query');
        $cutiQueries = $queries->filter(fn ($q) => str_contains($q, 'pengajuan_izins'))->count();
        $this->assertSame(0, $cutiQueries, 'Serialisasi biasa tidak boleh query pengajuan_izins (P2).');
        $this->assertArrayNotHasKey('sisa_cuti', $pegawai->toArray());
    }

    public function test_load_cuti_info_mengquery_sekali_dan_append(): void
    {
        $pegawai = $this->makePegawaiWithCuti();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $pegawai->loadCutiInfo();

        $queries = collect(DB::getQueryLog())->pluck('query');
        $cutiQueries = $queries->filter(fn ($q) => str_contains($q, 'pengajuan_izins'))->count();
        $this->assertSame(1, $cutiQueries, 'loadCutiInfo() hanya 1 query pengajuan_izins.');
        $this->assertSame(10, (int) $pegawai->sisa_cuti);
    }
}
