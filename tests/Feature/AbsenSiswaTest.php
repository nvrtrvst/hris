<?php

namespace Tests\Feature;

use App\Models\AbsenSiswaDetail;
use App\Models\AbsenSiswaSesi;
use App\Models\Jabatan;
use App\Models\Jadwal;
use App\Models\LaporanPiket;
use App\Models\Pegawai;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AbsenSiswaTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->unit = UnitSekolah::create([
            'nama' => 'SMP Test', 'singkatan' => 'SMP',
            'latitude' => -6.2, 'longitude' => 106.8,
            'radius_meter' => 100, 'durasi_jp' => 45, 'toleransi_menit' => 0,
        ]);
    }

    private function makePegawai(): Pegawai
    {
        $jabatan = Jabatan::create(['nama' => 'Guru Piket']);
        $user = User::factory()->create();
        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '1234567890'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT),
            'nama_lengkap' => 'Guru Piket',
            'tempat_lahir' => 'Jakarta', 'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L', 'agama' => 'Islam',
            'status_pernikahan' => 'kawin', 'jumlah_tanggungan' => 2,
            'alamat' => 'Jl. Test No. 1', 'no_hp' => '081234567890',
            'status_kepegawaian' => 'guru_tidak_tetap', 'tmt_mengajar' => '2020-01-01',
            'status_aktif' => 'aktif', 'pendidikan_terakhir' => 'S1',
        ]);
        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        Jadwal::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'hari' => 'Senin',
            'jam_mulai' => '07:00:00', 'jam_selesai' => '15:00:00',
            'jenis_jadwal' => 'piket',
            'tahun_ajaran' => '2026/2027', 'semester' => 1,
        ]);

        // Presensi masuk hari ini (gate assertSudahMasuk).
        $p = Presensi::create([
            'pegawai_id' => $pegawai->id,
            'tipe_presensi' => 'kantor',
            'tanggal' => '2026-10-12',
            'jam_masuk' => '07:00:00',
            'unit_sekolah_id' => $this->unit->id,
        ]);
        $p->status = 'hadir';
        $p->save();

        Carbon::setTestNow('2026-10-12 08:00:00'); // Senin, jendela pagi

        return $pegawai;
    }

    private function payload(array $siswa = []): array
    {
        return [
            'class_id' => '77',
            'class_label' => 'VII A',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'accuracy' => 15,
            'siswa' => $siswa ?: [
                ['nis' => '001', 'nama' => 'Ani', 'status' => 'hadir'],
                ['nis' => '002', 'nama' => 'Budi', 'status' => 'izin', 'keterangan' => 'Acara keluarga'],
            ],
        ];
    }

    public function test_simpan_absen_siswa_membuat_sesi_dan_detail(): void
    {
        $pegawai = $this->makePegawai();

        $this->actingAs($pegawai->user, 'web_mobile')
            ->post(route('presensi.piket.absen-siswa'), $this->payload())
            ->assertRedirect();

        $this->assertDatabaseHas('absen_siswa_sesi', [
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'tanggal' => '2026-10-12',
            'class_id' => '77',
            'class_label' => 'VII A',
        ]);
        $this->assertSame(2, AbsenSiswaDetail::count());
        $this->assertDatabaseHas('absen_siswa_detail', [
            'nis' => '002', 'status' => 'izin', 'keterangan' => 'Acara keluarga',
        ]);

        Carbon::setTestNow();
    }

    public function test_kirim_ulang_mengganti_detail_bukan_menambah_sesi(): void
    {
        $pegawai = $this->makePegawai();

        $this->actingAs($pegawai->user, 'web_mobile')
            ->post(route('presensi.piket.absen-siswa'), $this->payload());
        $this->actingAs($pegawai->user, 'web_mobile')
            ->post(route('presensi.piket.absen-siswa'), $this->payload([
                ['nis' => '001', 'nama' => 'Ani', 'status' => 'sakit', 'keterangan' => 'Demam'],
            ]) + ['alasan' => 'Koreksi status setelah surat dokter']);

        $this->assertSame(1, AbsenSiswaSesi::count());
        $this->assertSame(1, AbsenSiswaDetail::count());
        $this->assertSame('sakit', AbsenSiswaDetail::first()->status);

        Carbon::setTestNow();
    }

    public function test_status_tidak_valid_ditolak(): void
    {
        $pegawai = $this->makePegawai();

        $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.piket.absen-siswa'), $this->payload([
                ['nis' => '001', 'nama' => 'Ani', 'status' => 'bolos'],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('siswa.0.status');

        Carbon::setTestNow();
    }

    public function test_keterangan_wajib_untuk_izin_dan_sakit(): void
    {
        $pegawai = $this->makePegawai();

        $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.piket.absen-siswa'), $this->payload([
                ['nis' => '001', 'nama' => 'Ani', 'status' => 'sakit'],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('siswa.0.keterangan');

        Carbon::setTestNow();
    }

    public function test_centang_item_butuh_sesi_tersimpan(): void
    {
        $pegawai = $this->makePegawai();

        $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.piket.item'), [
                'kode' => 'absen_siswa', 'mode' => 'centang',
                'latitude' => -6.2, 'longitude' => 106.8, 'accuracy' => 15,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('absen_siswa');

        $this->actingAs($pegawai->user, 'web_mobile')
            ->post(route('presensi.piket.absen-siswa'), $this->payload());

        $this->actingAs($pegawai->user, 'web_mobile')
            ->post(route('presensi.piket.item'), [
                'kode' => 'absen_siswa', 'mode' => 'centang',
                'latitude' => -6.2, 'longitude' => 106.8, 'accuracy' => 15,
            ])
            ->assertRedirect();

        $items = LaporanPiket::first()->items;
        $this->assertSame(1, $items['absen_siswa']['sesi_count']);

        Carbon::setTestNow();
    }

    public function test_nihil_ditolak_bila_sudah_ada_sesi(): void
    {
        $pegawai = $this->makePegawai();

        $this->actingAs($pegawai->user, 'web_mobile')
            ->post(route('presensi.piket.absen-siswa'), $this->payload());

        $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.piket.item'), [
                'kode' => 'absen_siswa', 'mode' => 'nihil',
                'latitude' => -6.2, 'longitude' => 106.8, 'accuracy' => 15,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('absen_siswa');

        Carbon::setTestNow();
    }

    public function test_nihil_diterima_bila_belum_ada_sesi(): void
    {
        $pegawai = $this->makePegawai();

        $this->actingAs($pegawai->user, 'web_mobile')
            ->post(route('presensi.piket.item'), [
                'kode' => 'absen_siswa', 'mode' => 'nihil',
                'latitude' => -6.2, 'longitude' => 106.8, 'accuracy' => 15,
            ])
            ->assertRedirect();

        $this->assertTrue(LaporanPiket::first()->items['absen_siswa']['nihil']);

        Carbon::setTestNow();
    }

    public function test_rekap_dibatasi_unit_admin(): void
    {
        $unitB = UnitSekolah::create([
            'nama' => 'Unit B', 'singkatan' => 'UB',
            'latitude' => -6.21, 'longitude' => 106.81,
            'radius_meter' => 100, 'durasi_jp' => 45, 'toleransi_menit' => 0,
        ]);
        $pegawai = $this->makePegawai();
        Carbon::setTestNow();

        $sesiA = AbsenSiswaSesi::create([
            'unit_sekolah_id' => $this->unit->id, 'pegawai_id' => $pegawai->id,
            'tanggal' => '2026-10-12', 'class_id' => '77', 'class_label' => 'VII A',
        ]);
        $sesiA->details()->create(['nis' => '001', 'nama_snapshot' => 'Ani', 'status' => 'hadir']);
        $sesiB = AbsenSiswaSesi::create([
            'unit_sekolah_id' => $unitB->id, 'pegawai_id' => $pegawai->id,
            'tanggal' => '2026-10-12', 'class_id' => '88', 'class_label' => 'VIII B',
        ]);
        $sesiB->details()->create(['nis' => '009', 'nama_snapshot' => 'Cici', 'status' => 'alpa']);

        $adminUnit = User::factory()->create();
        $adminUnit->assignRole('admin_unit');
        $adminUnit->forceFill(['unit_sekolah_id' => $this->unit->id])->save();

        $this->actingAs($adminUnit, 'web_admin')
            ->get(route('absen-siswa.index', ['tanggal' => '2026-10-12']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('AbsenSiswa/Index')
                ->has('sesi', 1)
                ->where('sesi.0.class_label', 'VII A')
                ->where('sesi.0.counts.hadir', 1));

        $super = User::factory()->create();
        $super->assignRole('superadmin');

        $this->actingAs($super, 'web_admin')
            ->get(route('absen-siswa.index', ['tanggal' => '2026-10-12']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('sesi', 2)->has('units', 2));
    }

    public function test_rekap_butuh_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole('pegawai');

        $this->actingAs($user, 'web_admin')
            ->get(route('absen-siswa.index'))
            ->assertForbidden();
    }

    public function test_rekap_mendeteksi_kelas_belum_diabsen(): void
    {
        config(['keuangan.url' => 'http://keuangan.test', 'keuangan.key' => 'test-key']);

        $unitB = UnitSekolah::create([
            'nama' => 'Unit B', 'singkatan' => 'UB',
            'latitude' => -6.21, 'longitude' => 106.81,
            'radius_meter' => 100, 'durasi_jp' => 45, 'toleransi_menit' => 0,
        ]);
        $pegawai = $this->makePegawai();
        Carbon::setTestNow();

        $sesiA = AbsenSiswaSesi::create([
            'unit_sekolah_id' => $this->unit->id, 'pegawai_id' => $pegawai->id,
            'tanggal' => '2026-10-12', 'class_id' => '200', 'class_label' => 'VII A',
        ]);
        $sesiA->details()->create(['nis' => '001', 'nama_snapshot' => 'Ani', 'status' => 'hadir']);

        // Keuangan: Unit A (SMP Test) punya kelas 200+201, Unit B punya kelas 300.
        Http::fake(function ($request) {
            $classes = $request['unit'] === 'Unit B'
                ? [['id' => 300, 'grade' => 'VIII', 'name' => 'B', 'section' => '']]
                : [['id' => 200, 'grade' => 'VII', 'name' => 'A', 'section' => ''], ['id' => 201, 'grade' => 'VII', 'name' => 'B', 'section' => '']];

            return Http::response(['data' => ['classes' => $classes]]);
        });

        $super = User::factory()->create();
        $super->assignRole('superadmin');

        $this->actingAs($super, 'web_admin')
            ->get(route('absen-siswa.index', ['tanggal' => '2026-10-12']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('AbsenSiswa/Index')
                ->where('integrasi_ok', true)
                ->has('sesi', 1)
                ->has('belum_diabsen', 2)
                ->where('belum_diabsen.0.class_id', '201')
                ->where('belum_diabsen.0.unit', 'SMP Test')
                ->where('belum_diabsen.1.class_id', '300'));
    }

    public function test_rekap_integrasi_gagal_tetap_tampil(): void
    {
        $pegawai = $this->makePegawai();
        Carbon::setTestNow();

        $sesi = AbsenSiswaSesi::create([
            'unit_sekolah_id' => $this->unit->id, 'pegawai_id' => $pegawai->id,
            'tanggal' => '2026-10-12', 'class_id' => '200', 'class_label' => 'VII A',
        ]);
        $sesi->details()->create(['nis' => '001', 'nama_snapshot' => 'Ani', 'status' => 'hadir']);

        // Keuangan tidak dikonfigurasi di lingkungan test -> klien null.
        $super = User::factory()->create();
        $super->assignRole('superadmin');

        $this->actingAs($super, 'web_admin')
            ->get(route('absen-siswa.index', ['tanggal' => '2026-10-12']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('AbsenSiswa/Index')
                ->where('integrasi_ok', false)
                ->has('sesi', 1)
                ->has('belum_diabsen', 0));
    }
}
