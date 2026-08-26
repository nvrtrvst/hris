<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\Presensi;
use App\Models\TugasLuar;
use App\Models\UnitSekolah;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TugasLuarTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->unit = UnitSekolah::create([
            'nama' => 'SMP Test',
            'singkatan' => 'SMP',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'toleransi_menit' => 0,
            'toleransi_tap_menit' => 15,
        ]);

        $this->admin = User::factory()->create(['role' => 'superadmin']);
        $this->admin->assignRole('superadmin');
    }

    private function makePegawaiKontrak(): Pegawai
    {
        $jabatan = Jabatan::create(['nama' => 'Guru']);
        $user = User::factory()->create();
        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '1234567890'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT),
            'nama_lengkap' => 'Guru Kontrak',
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'kawin',
            'jumlah_tanggungan' => 2,
            'alamat_ktp' => 'Jl. Test No. 1',
            'no_hp' => '081234567890',
            'status_kepegawaian' => 'kontrak',
            'tanggal_mulai_kerja' => '2020-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
            'wajib_kantor' => false,
        ]);

        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    private function fotoBase64(): string
    {
        $img = imagecreatetruecolor(320, 240);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);

        return 'data:image/png;base64,'.base64_encode($png);
    }

    public function test_mendadak_di_luar_radius_diterima_dan_pending(): void
    {
        $pegawai = $this->makePegawaiKontrak();

        $res = $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.store'), [
                'tipe' => 'masuk',
                'foto' => $this->fotoBase64(),
                'latitude' => 0,
                'longitude' => 0,
                'accuracy' => 15,
                'is_tugas_luar' => true,
                'tujuan' => 'Rapat dinas',
            ]);

        $res->assertOk()->assertJson(['success' => true]);

        $record = Presensi::where('pegawai_id', $pegawai->id)->first();
        $this->assertNotNull($record);
        $this->assertTrue($record->is_tugas_luar);
        $this->assertSame('hadir', $record->status);
        $this->assertSame('pending', $record->tugas_luar_status);
        $this->assertSame('Rapat dinas', $record->tujuan);
    }

    public function test_terjadwal_di_luar_radius_langsung_disetujui(): void
    {
        $pegawai = $this->makePegawaiKontrak();
        Carbon::setTestNow('2026-08-21 08:00:00');
        $schedule = TugasLuar::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'tanggal' => '2026-08-21',
            'tujuan' => 'Monitoring UN',
            'created_by' => $this->admin->id,
        ]);

        $res = $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.store'), [
                'tipe' => 'masuk',
                'foto' => $this->fotoBase64(),
                'latitude' => 0,
                'longitude' => 0,
                'accuracy' => 15,
                'is_tugas_luar' => true,
                'tujuan' => 'Monitoring UN',
            ]);

        $res->assertOk()->assertJson(['success' => true]);

        $record = Presensi::where('pegawai_id', $pegawai->id)->first();
        $this->assertSame('disetujui', $record->tugas_luar_status);
        $this->assertSame($schedule->id, $record->tugas_luar_id);
        Carbon::setTestNow();
    }

    public function test_non_tugas_luar_di_luar_radius_ditolak(): void
    {
        $pegawai = $this->makePegawaiKontrak();

        $res = $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.store'), [
                'tipe' => 'masuk',
                'foto' => $this->fotoBase64(),
                'latitude' => 0,
                'longitude' => 0,
                'accuracy' => 15,
            ]);

        $res->assertStatus(422);
        $this->assertDatabaseCount('presensi', 0);
    }

    public function test_admin_setuju_dan_tolak_tugas_luar(): void
    {
        $pegawai = $this->makePegawaiKontrak();

        $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.store'), [
                'tipe' => 'masuk',
                'foto' => $this->fotoBase64(),
                'latitude' => 0,
                'longitude' => 0,
                'accuracy' => 15,
                'is_tugas_luar' => true,
                'tujuan' => 'Rapat dinas',
            ]);

        $record = Presensi::where('pegawai_id', $pegawai->id)->first();
        $this->assertSame('pending', $record->tugas_luar_status);

        $this->actingAs($this->admin, 'web_admin')
            ->post(route('presensi.rejectTugasLuar', $record->id))
            ->assertSessionHas('message');
        $this->assertSame('ditolak', $record->fresh()->tugas_luar_status);

        // Pegawai kedua -> absen pending lalu approve
        $pegawai2 = $this->makePegawaiKontrak();
        $this->actingAs($pegawai2->user, 'web_mobile')
            ->postJson(route('presensi.absen.store'), [
                'tipe' => 'masuk',
                'foto' => $this->fotoBase64(),
                'latitude' => 0,
                'longitude' => 0,
                'accuracy' => 15,
                'is_tugas_luar' => true,
                'tujuan' => 'Rapat dinas 2',
            ]);
        $record2 = Presensi::where('pegawai_id', $pegawai2->id)->first();
        $this->actingAs($this->admin, 'web_admin')
            ->post(route('presensi.approveTugasLuar', $record2->id))
            ->assertSessionHas('message');
        $this->assertSame('disetujui', $record2->fresh()->tugas_luar_status);
    }

    public function test_gabungan_lembur_dan_tugas_luar_ditolak(): void
    {
        $pegawai = $this->makePegawaiKontrak();

        $res = $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.store'), [
                'tipe' => 'masuk',
                'foto' => $this->fotoBase64(),
                'latitude' => 0,
                'longitude' => 0,
                'accuracy' => 15,
                'is_lembur' => true,
                'is_tugas_luar' => true,
                'tujuan' => 'Rapat dinas',
            ]);

        $res->assertStatus(422);
    }

    public function test_admin_buat_jadwal_tugas_luar(): void
    {
        $pegawai = $this->makePegawaiKontrak();

        $this->actingAs($this->admin, 'web_admin')
            ->post(route('tugas-luar.store'), [
                'pegawai_id' => $pegawai->id,
                'unit_sekolah_id' => $this->unit->id,
                'tanggal' => '2026-08-22',
                'jam_mulai' => '08:00',
                'jam_selesai' => '12:00',
                'tujuan' => 'Inspeksi',
                'keterangan' => 'Pendampingan',
            ])
            ->assertSessionHas('message');

        $this->assertDatabaseHas('tugas_luar', [
            'pegawai_id' => $pegawai->id,
            'tanggal' => '2026-08-22',
            'tujuan' => 'Inspeksi',
        ]);
    }

    public function test_reject_tugas_luar_menjadi_alpa(): void
    {
        $pegawai = $this->makePegawaiKontrak();

        $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.store'), [
                'tipe' => 'masuk',
                'foto' => $this->fotoBase64(),
                'latitude' => 0,
                'longitude' => 0,
                'accuracy' => 15,
                'is_tugas_luar' => true,
                'tujuan' => 'Rapat dinas',
            ]);

        $record = Presensi::where('pegawai_id', $pegawai->id)->first();
        $this->assertSame('hadir', $record->status);

        $this->actingAs($this->admin, 'web_admin')
            ->post(route('presensi.rejectTugasLuar', $record->id))
            ->assertSessionHas('message');

        $fresh = $record->fresh();
        $this->assertSame('ditolak', $fresh->tugas_luar_status);
        $this->assertSame('alpa', $fresh->status);
    }

    public function test_auto_close_kantor_saat_mulai_tugas_luar(): void
    {
        $pegawai = $this->makePegawaiKontrak();

        // Simulasikan presensi masuk kantor yang sudah ada (jam_masuk terisi, jam_keluar null).
        $kantor = Presensi::create([
            'pegawai_id' => $pegawai->id,
            'tipe_presensi' => 'kantor',
            'tanggal' => Carbon::today()->toDateString(),
            'jam_masuk' => '08:00:00',
            'unit_sekolah_id' => $this->unit->id,
            'status' => 'hadir',
        ]);
        $this->assertNull($kantor->jam_keluar);

        // Mulai dinas luar (di luar radius) -> kantor otomatis pulang.
        $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.store'), [
                'tipe' => 'masuk',
                'foto' => $this->fotoBase64(),
                'latitude' => 0,
                'longitude' => 0,
                'accuracy' => 15,
                'is_tugas_luar' => true,
                'tujuan' => 'Rapat dinas',
            ])
            ->assertOk();

        $this->assertNotNull($kantor->fresh()->jam_keluar, 'Kantor harus auto-close saat mulai tugas luar.');
    }

    public function test_bukti_kegiatan_tersimpan(): void
    {
        $pegawai = $this->makePegawaiKontrak();

        $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.store'), [
                'tipe' => 'masuk',
                'foto' => $this->fotoBase64(),
                'latitude' => 0,
                'longitude' => 0,
                'accuracy' => 15,
                'is_tugas_luar' => true,
                'tujuan' => 'Rapat dinas',
            ]);

        $record = Presensi::where('pegawai_id', $pegawai->id)->first();

        $res = $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.tugas-luar.bukti', $record->id), [
                'foto' => $this->fotoBase64(),
                'keterangan' => 'Foto rapat',
                'latitude' => 0,
                'longitude' => 0,
            ]);

        $res->assertOk()->assertJson(['success' => true]);
        $this->assertCount(1, $res->json('foto_kegiatan_urls'));

        $fresh = $record->fresh();
        $this->assertIsArray($fresh->foto_kegiatan);
        $this->assertCount(1, $fresh->foto_kegiatan);
        $this->assertSame('Foto rapat', $fresh->foto_kegiatan[0]['keterangan']);
        $this->assertArrayHasKey('latitude', $fresh->foto_kegiatan[0]);
        $this->assertArrayHasKey('longitude', $fresh->foto_kegiatan[0]);
        $this->assertArrayHasKey('accuracy', $fresh->foto_kegiatan[0]);
        $this->assertEquals(0.0, $fresh->foto_kegiatan[0]['latitude']);
        $this->assertEquals(0.0, $fresh->foto_kegiatan[0]['longitude']);
    }

    public function test_bukti_kegiatan_bisa_lebih_dari_satu(): void
    {
        $pegawai = $this->makePegawaiKontrak();

        $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.store'), [
                'tipe' => 'masuk',
                'foto' => $this->fotoBase64(),
                'latitude' => 0,
                'longitude' => 0,
                'accuracy' => 15,
                'is_tugas_luar' => true,
                'tujuan' => 'Rapat dinas',
            ]);

        $record = Presensi::where('pegawai_id', $pegawai->id)->first();

        $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.tugas-luar.bukti', $record->id), [
                'foto' => $this->fotoBase64(),
                'keterangan' => 'Foto rapat 1',
            ])
            ->assertOk();

        $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.tugas-luar.bukti', $record->id), [
                'foto' => $this->fotoBase64(),
                'keterangan' => 'Foto rapat 2',
            ])
            ->assertOk();

        $fresh = $record->fresh();
        $this->assertCount(2, $fresh->foto_kegiatan, 'Bukti kegiatan harus menumpuk, bukan menimpa.');
        $this->assertCount(2, $fresh->foto_kegiatan_urls);
    }
}
