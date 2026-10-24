<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Jadwal;
use App\Models\LaporanPiket;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\PegawaiMapel;
use App\Models\PengajuanIzin;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LaporanPiketTest extends TestCase
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
            'toleransi_slide_menit' => 15,
        ]);

        $this->admin = User::factory()->create(['role' => 'superadmin']);
        $this->admin->assignRole('superadmin');
    }

    private function makePegawai(string $nama = 'Guru Piket'): Pegawai
    {
        $jabatan = Jabatan::create(['nama' => 'Guru '.$nama]);
        $user = User::factory()->create();
        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '1234567890'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT),
            'nama_lengkap' => $nama,
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'kawin',
            'jumlah_tanggungan' => 2,
            'alamat' => 'Jl. Test No. 1',
            'no_hp' => '081234567890',
            'status_kepegawaian' => 'guru_tidak_tetap',
            'tmt_mengajar' => '2020-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
            'wajib_kantor' => false,
        ]);

        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    private function makePiketJadwal(Pegawai $pegawai): Jadwal
    {
        return Jadwal::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'hari' => 'Senin',
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '15:00:00',
            'jenis_jadwal' => 'piket',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);
    }

    private function geo(): array
    {
        return ['latitude' => -6.2, 'longitude' => 106.8, 'accuracy' => 15];
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

    private function masukkan(Pegawai $pegawai, string $tanggal = '2026-10-12'): void
    {
        // status di-$guarded: set via assignment langsung, bukan create().
        $p = Presensi::create([
            'pegawai_id' => $pegawai->id,
            'tipe_presensi' => 'kantor',
            'tanggal' => $tanggal,
            'jam_masuk' => '07:00:00',
            'unit_sekolah_id' => $this->unit->id,
        ]);
        $p->status = 'hadir';
        $p->save();
    }

    public function test_halaman_piket_kosong_tanpa_jadwal(): void
    {
        $pegawai = $this->makePegawai();

        $this->actingAs($pegawai->user, 'web_mobile')
            ->get(route('presensi.piket.index'))
            ->assertOk();
    }

    public function test_piket_only_bisa_masuk_kantor(): void
    {
        $pegawai = $this->makePegawai();
        $pegawai->wajib_kantor = true;
        $pegawai->save();
        $this->makePiketJadwal($pegawai);
        Carbon::setTestNow('2026-10-12 07:00:00'); // Senin pagi

        $this->actingAs($pegawai->user, 'web_mobile')
            ->get(route('presensi.absen'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('officeAttendance', true));

        $res = $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.store'), array_merge(
                ['tipe' => 'masuk', 'foto' => $this->fotoBase64()],
                $this->geo()
            ));
        $res->assertOk()->assertJson(['success' => true]);
        Carbon::setTestNow();

        $this->assertDatabaseHas('presensi', [
            'pegawai_id' => $pegawai->id,
            'tipe_presensi' => 'kantor',
        ]);
    }

    public function test_slide_piket_ditolak(): void
    {
        $pegawai = $this->makePegawai();
        // Gate slide khusus tetap: status via relasi kode (bukan find PK).
        $pegawai->status_kepegawaian = 'guru_tetap_yayasan';
        $pegawai->save();
        $slot = $this->makePiketJadwal($pegawai);
        Carbon::setTestNow('2026-10-12 08:00:00');

        $res = $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.slide'), array_merge(
                ['jadwal_id' => $slot->id, 'tipe' => 'masuk'],
                $this->geo()
            ));

        $res->assertStatus(422);
        $this->assertStringContainsString('Piket tidak perlu slide', $res->json('message'));
        Carbon::setTestNow();
    }

    public function test_store_absen_piket_jadwal_ditolak(): void
    {
        $pegawai = $this->makePegawai();
        $this->makePiketJadwal($pegawai);
        Carbon::setTestNow('2026-10-12 08:00:00');

        // Wajib kantor agar sampai cabang jadwal (bukan error pilih-jadwal).
        $pegawai->wajib_kantor = true;
        $pegawai->save();
        $slot = Jadwal::where('pegawai_id', $pegawai->id)->where('jenis_jadwal', 'piket')->first();

        $res = $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.store'), array_merge(
                ['tipe' => 'masuk', 'jadwal_id' => $slot->id, 'foto' => $this->fotoBase64()],
                $this->geo()
            ));

        $res->assertStatus(422)->assertJsonValidationErrors('jadwal_id');
        Carbon::setTestNow();
    }

    public function test_finalize_alpa_lewati_slot_piket(): void
    {
        $pegawai = $this->makePegawai();
        $this->makePiketJadwal($pegawai);
        // Bypass hook saving (paksa wajib false apa adanya).
        Pegawai::whereKey($pegawai->id)->update(['wajib_kantor' => false]);

        $this->artisan('presensi:finalize-alpa', ['--date' => '2026-10-12'])
            ->assertSuccessful();

        // Tak ada baris auto untuk slot piket; pegawai non-wajib-kantor tanpa
        // jadwal non-piket juga tak dapat baris kantor.
        $this->assertDatabaseCount('presensi', 0);
    }

    public function test_halaman_terkunci_tanpa_masuk(): void
    {
        $pegawai = $this->makePegawai();
        $this->makePiketJadwal($pegawai);
        Carbon::setTestNow('2026-10-12 10:00:00');

        $this->actingAs($pegawai->user, 'web_mobile')
            ->get(route('presensi.piket.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('butuhMasuk', true)
                ->where('piket', null)
            );
        Carbon::setTestNow();
    }

    public function test_item_ditolak_tanpa_masuk(): void
    {
        $pegawai = $this->makePegawai();
        $this->makePiketJadwal($pegawai);
        Carbon::setTestNow('2026-10-12 08:00:00');

        $res = $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.piket.item'), array_merge(
                ['kode' => 'gerbang', 'mode' => 'centang'],
                $this->geo()
            ));

        $res->assertStatus(422)->assertJsonValidationErrors('presensi');
        Carbon::setTestNow();
        $this->assertDatabaseCount('laporan_piket', 0);
    }

    public function test_item_gerbang_dalam_radius_diterima(): void
    {
        $pegawai = $this->makePegawai();
        $this->makePiketJadwal($pegawai);
        $this->masukkan($pegawai);
        Carbon::setTestNow('2026-10-12 08:00:00'); // Senin, seksi pagi

        $res = $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.piket.item'), array_merge(
                ['kode' => 'gerbang', 'mode' => 'centang', 'terlambat_count' => 3],
                $this->geo()
            ));

        $res->assertRedirect();
        Carbon::setTestNow();

        $laporan = LaporanPiket::where('pegawai_id', $pegawai->id)->first();
        $this->assertNotNull($laporan);
        $this->assertSame('draft', $laporan->status);
        $this->assertTrue($laporan->isItemDone('gerbang'));
        $this->assertSame(3, $laporan->item('gerbang')['terlambat_count']);
    }

    public function test_item_di_luar_radius_ditolak(): void
    {
        $pegawai = $this->makePegawai();
        $this->makePiketJadwal($pegawai);
        $this->masukkan($pegawai);
        Carbon::setTestNow('2026-10-12 08:00:00');

        $res = $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.piket.item'), [
                'kode' => 'gerbang', 'mode' => 'centang',
                'latitude' => 0, 'longitude' => 0, 'accuracy' => 15,
            ]);

        $res->assertStatus(422);
        Carbon::setTestNow();
        $this->assertDatabaseCount('laporan_piket', 0);
    }

    public function test_item_di_luar_jendela_ditolak(): void
    {
        $pegawai = $this->makePegawai();
        $this->makePiketJadwal($pegawai);
        $this->masukkan($pegawai);
        Carbon::setTestNow('2026-10-12 06:00:00'); // sebelum slot

        $res = $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.piket.item'), array_merge(
                ['kode' => 'gerbang', 'mode' => 'centang'],
                $this->geo()
            ));

        $res->assertStatus(422);
        Carbon::setTestNow();
        $this->assertDatabaseCount('laporan_piket', 0);
    }

    public function test_item_pagi_telat_ditolak_tanpa_unlock(): void
    {
        $pegawai = $this->makePegawai();
        $this->makePiketJadwal($pegawai);
        $this->masukkan($pegawai, '2026-10-12');
        // Jendela pagi slot 07:00-15:00 = 07:00-09:40; 10:00 sudah lewat.
        Carbon::setTestNow('2026-10-12 10:00:00');

        $res = $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.piket.item'), array_merge(
                ['kode' => 'gerbang', 'mode' => 'centang'],
                $this->geo()
            ));

        $res->assertStatus(422);
        Carbon::setTestNow();
    }

    public function test_item_pagi_telat_diterima_setelah_unlock_admin(): void
    {
        $pegawai = $this->makePegawai();
        $this->makePiketJadwal($pegawai);
        $this->masukkan($pegawai, '2026-10-12');
        // KBM terbuka pada 10:00, pagi (07:00-09:40) terkunci.
        Carbon::setTestNow('2026-10-12 10:00:00');
        $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.piket.item'), array_merge(
                ['kode' => 'buku', 'mode' => 'centang'],
                $this->geo()
            ))->assertRedirect();
        $laporan = LaporanPiket::where('pegawai_id', $pegawai->id)->whereDate('tanggal', '2026-10-12')->first();

        Carbon::setTestNow('2026-10-12 10:00:00');
        // Pegawai masih terkunci sebelum admin membuka.
        $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.piket.item'), array_merge(
                ['kode' => 'gerbang', 'mode' => 'centang'],
                $this->geo()
            ))->assertStatus(422);

        // Admin membuka jalur telat.
        $this->actingAs($this->admin, 'web_admin')
            ->post(route('laporan.piket.unlock', $laporan->id))
            ->assertRedirect();
        $laporan->refresh();
        $this->assertNotNull($laporan->unlocked_at);
        $this->assertSame($this->admin->id, $laporan->unlocked_by);

        // Item pagi telat kini diterima.
        $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.piket.item'), array_merge(
                ['kode' => 'gerbang', 'mode' => 'centang', 'terlambat_count' => 3],
                $this->geo()
            ))->assertRedirect();

        $laporan->refresh();
        $this->assertTrue($laporan->isItemDone(LaporanPiket::ITEM_GERBANG));
        Carbon::setTestNow();
    }

    public function test_unlock_laporan_submitted_ditolak(): void
    {
        $pegawai = $this->makePegawai();
        $this->makePiketJadwal($pegawai);
        $this->masukkan($pegawai, '2026-10-12');
        $laporan = LaporanPiket::create([
            'pegawai_id' => $pegawai->id,
            'jadwal_id' => Jadwal::where('pegawai_id', $pegawai->id)->first()->id,
            'unit_sekolah_id' => $this->unit->id,
            'tanggal' => '2026-10-12',
            'status' => LaporanPiket::STATUS_SUBMITTED,
            'submitted_at' => '2026-10-12 14:00:00',
        ]);

        $res = $this->actingAs($this->admin, 'web_admin')
            ->post(route('laporan.piket.unlock', $laporan->id));

        $res->assertRedirect();
        $res->assertSessionHasErrors('laporan');
        $this->assertNull($laporan->refresh()->unlocked_at);
    }

    public function test_unlock_tanpa_izin_ditolak(): void
    {
        $pegawai = $this->makePegawai();
        $this->makePiketJadwal($pegawai);
        $pengguna = User::factory()->create(); // tanpa role: tidak punya view_presensi
        $laporan = LaporanPiket::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'tanggal' => '2026-10-12',
        ]);

        $this->actingAs($pengguna, 'web_admin')
            ->post(route('laporan.piket.unlock', $laporan->id))
            ->assertForbidden();
        $this->assertNull($laporan->refresh()->unlocked_at);
    }

    public function test_alur_lengkap_sampai_submit(): void
    {
        $pegawai = $this->makePegawai();
        $this->makePiketJadwal($pegawai);
        $this->masukkan($pegawai);
        $post = fn (string $now, array $payload) => $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.piket.item'), array_merge($payload, $this->geo()));

        Carbon::setTestNow('2026-10-12 08:00:00');
        $post('', ['kode' => 'gerbang', 'mode' => 'centang', 'terlambat_count' => 1])->assertRedirect();
        $post('cek_awal', ['kode' => 'cek_absen', 'mode' => 'centang'])->assertRedirect();

        Carbon::setTestNow('2026-10-12 10:00:00'); // seksi KBM
        $post('kelas', ['kode' => 'kelas_kosong', 'mode' => 'nihil'])->assertRedirect();
        $post('buku', ['kode' => 'buku', 'mode' => 'centang'])->assertRedirect();
        $post('insiden', ['kode' => 'insiden', 'mode' => 'nihil'])->assertRedirect();
        $post('siswa', ['kode' => 'absen_siswa', 'mode' => 'nihil'])->assertRedirect();

        Carbon::setTestNow('2026-10-12 14:00:00'); // seksi sore
        $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.piket.submit'))
            ->assertRedirect();
        Carbon::setTestNow();

        $laporan = LaporanPiket::where('pegawai_id', $pegawai->id)->first();
        $this->assertSame('submitted', $laporan->status);
        $this->assertNotNull($laporan->submitted_at);
        $this->assertTrue($laporan->isLengkap());
    }

    public function test_submit_tak_lengkap_ditolak(): void
    {
        $pegawai = $this->makePegawai();
        $this->makePiketJadwal($pegawai);
        $this->masukkan($pegawai);

        Carbon::setTestNow('2026-10-12 14:00:00');
        $res = $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.piket.submit'));

        $res->assertStatus(422);
        Carbon::setTestNow();
    }

    public function test_keluar_reguler_ditahan_sebelum_laporan_piket_terkirim(): void
    {
        $pegawai = $this->makePegawai();
        $this->makePiketJadwal($pegawai);
        $this->masukkan($pegawai);
        Carbon::setTestNow('2026-10-12 14:00:00'); // Sore bisa pulang, tapi laporan belum ada.

        $res = $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.store'), array_merge(
                ['tipe' => 'keluar', 'foto' => $this->fotoBase64()],
                $this->geo()
            ));

        $res->assertStatus(422);
        $this->assertStringContainsString('Laporan piket belum terkirim', $res->json('errors.piket.0'));
        $this->assertStringContainsString('0/6', $res->json('errors.piket.0'));
        Carbon::setTestNow();
    }

    public function test_keluar_reguler_dibuka_setelah_laporan_piket_terkirim(): void
    {
        $pegawai = $this->makePegawai();
        $this->makePiketJadwal($pegawai);
        $this->masukkan($pegawai);
        $post = fn (string $now, array $payload) => $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.piket.item'), array_merge($payload, $this->geo()));

        Carbon::setTestNow('2026-10-12 08:00:00');
        $post('', ['kode' => 'gerbang', 'mode' => 'centang', 'terlambat_count' => 1])->assertRedirect();
        $post('', ['kode' => 'cek_absen', 'mode' => 'centang'])->assertRedirect();

        Carbon::setTestNow('2026-10-12 10:00:00'); // seksi KBM
        $post('', ['kode' => 'kelas_kosong', 'mode' => 'nihil'])->assertRedirect();
        $post('', ['kode' => 'buku', 'mode' => 'centang'])->assertRedirect();
        $post('', ['kode' => 'insiden', 'mode' => 'nihil'])->assertRedirect();
        $post('', ['kode' => 'absen_siswa', 'mode' => 'nihil'])->assertRedirect();

        Carbon::setTestNow('2026-10-12 14:00:00'); // seksi sore
        $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.piket.submit'))
            ->assertRedirect();

        // Keluar sengaja setelah jam pulang kantor unit (15:00) agar pemeriksaan
        // jam pulang kantor lolos — target test: gate piket sudah tidak menahan.
        Carbon::setTestNow('2026-10-12 15:30:00');
        $res = $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.store'), array_merge(
                ['tipe' => 'keluar', 'foto' => $this->fotoBase64()],
                $this->geo()
            ));
        $res->assertOk()->assertJson(['success' => true]);
        Carbon::setTestNow();

        $this->assertDatabaseHas('presensi', [
            'pegawai_id' => $pegawai->id,
            'tipe_presensi' => 'kantor',
        ]);
    }

    public function test_keluar_lembur_dan_tugas_luar_tak_tertahan_piket(): void
    {
        $pegawai = $this->makePegawai();
        $this->makePiketJadwal($pegawai);
        Carbon::setTestNow('2026-10-12 14:00:00');

        // Lembur: gate dilewati → error berikutnya (belum absen masuk), bukan piket.
        $res = $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.store'), array_merge(
                ['tipe' => 'keluar', 'is_lembur' => true, 'foto' => $this->fotoBase64()],
                $this->geo()
            ));
        $res->assertStatus(422);
        $this->assertStringNotContainsString('piket', (string) json_encode($res->json()));

        // Tugas luar: sama.
        $res = $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.store'), array_merge(
                ['tipe' => 'keluar', 'is_tugas_luar' => true, 'tujuan' => 'Meeting', 'foto' => $this->fotoBase64()],
                $this->geo()
            ));
        $res->assertStatus(422);
        $this->assertStringNotContainsString('piket', (string) json_encode($res->json()));
        Carbon::setTestNow();
    }

    public function test_keluar_tetap_ditahan_pegawai_piket(): void
    {
        $pegawai = $this->makePegawai('Guru Tetap');
        $pegawai->status_kepegawaian = 'guru_tetap_yayasan';
        $pegawai->save();
        $this->makePiketJadwal($pegawai);
        $this->masukkan($pegawai);
        Carbon::setTestNow('2026-10-12 14:00:00');

        $res = $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.absen.tetap'), array_merge(
                ['tipe' => 'keluar', 'foto' => $this->fotoBase64()],
                $this->geo()
            ));

        $res->assertStatus(422);
        $this->assertStringContainsString('Laporan piket belum terkirim', $res->json('errors.piket.0'));
        Carbon::setTestNow();
    }

    public function test_tambah_guru_absen_sore_diterima(): void
    {
        $pegawai = $this->makePegawai();
        $this->makePiketJadwal($pegawai);
        $this->masukkan($pegawai);

        Carbon::setTestNow('2026-10-12 08:00:00');
        $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.piket.item'), array_merge(
                ['kode' => 'cek_absen', 'mode' => 'centang'],
                $this->geo()
            ))->assertRedirect();

        Carbon::setTestNow('2026-10-12 14:30:00'); // konfirmasi sore
        $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.piket.item'), array_merge(
                ['kode' => 'cek_absen', 'mode' => 'tambah', 'nama_guru' => 'Budi', 'keterangan_absen' => 'Sakit, konfirmasi WA sore'],
                $this->geo()
            ))->assertRedirect();
        Carbon::setTestNow();

        $laporan = LaporanPiket::where('pegawai_id', $pegawai->id)->first();
        $entries = $laporan->item('cek_absen')['entries'] ?? [];
        $this->assertCount(1, $entries);
        $this->assertSame('Budi', $entries[0]['nama_guru']);
    }

    public function test_hapus_entry_cek_absen_diterima(): void
    {
        $pegawai = $this->makePegawai();
        $this->makePiketJadwal($pegawai);
        $this->masukkan($pegawai);
        $post = fn (array $payload, ?string $now = null) => $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.piket.item'), array_merge($payload, $this->geo()));

        Carbon::setTestNow('2026-10-12 08:00:00'); // seksi pagi
        $post(['kode' => 'cek_absen', 'mode' => 'centang'])->assertRedirect();
        Carbon::setTestNow('2026-10-12 10:00:00');
        $post(['kode' => 'cek_absen', 'mode' => 'tambah', 'nama_guru' => 'Guru Satu', 'keterangan_absen' => 'Sakit'])->assertRedirect();
        $post(['kode' => 'cek_absen', 'mode' => 'tambah', 'nama_guru' => 'Guru Dua', 'keterangan_absen' => 'Izin'])->assertRedirect();

        Carbon::setTestNow('2026-10-12 10:05:00');
        $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.piket.hapus'), ['kode' => 'cek_absen', 'index' => 0])
            ->assertRedirect();

        $laporan = LaporanPiket::where('pegawai_id', $pegawai->id)->first();
        $entries = $laporan->item('cek_absen')['entries'] ?? [];
        $this->assertCount(1, $entries);
        $this->assertSame('Guru Dua', $entries[0]['nama_guru']);
        $this->assertTrue($laporan->isItemDone('cek_absen'));

        // Hapus entry terakhir → item kembali belum-done agar bisa diproses ulang.
        $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.piket.hapus'), ['kode' => 'cek_absen', 'index' => 0])
            ->assertRedirect();

        $laporan = $laporan->fresh();
        $this->assertNull($laporan->item('cek_absen'));
        $this->assertFalse($laporan->isItemDone('cek_absen'));
        Carbon::setTestNow();
    }

    public function test_hapus_entry_setelah_submitted_ditolak(): void
    {
        $pegawai = $this->makePegawai();
        $this->makePiketJadwal($pegawai);
        $this->masukkan($pegawai);

        Carbon::setTestNow('2026-10-12 08:00:00');
        $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.piket.item'), array_merge(
                ['kode' => 'cek_absen', 'mode' => 'centang'],
                $this->geo()
            ))->assertRedirect();

        $laporan = LaporanPiket::where('pegawai_id', $pegawai->id)->first();
        $laporan->status = LaporanPiket::STATUS_SUBMITTED;
        $laporan->save();

        $res = $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.piket.hapus'), ['kode' => 'cek_absen', 'index' => 0]);

        $res->assertStatus(422);
        $this->assertStringContainsString('sudah dikirim', $res->json('errors.laporan.0'));
        Carbon::setTestNow();
    }

    public function test_hapus_entry_index_invalid_ditolak(): void
    {
        $pegawai = $this->makePegawai();
        $this->makePiketJadwal($pegawai);
        $this->masukkan($pegawai);
        Carbon::setTestNow('2026-10-12 08:00:00');

        $res = $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.piket.hapus'), ['kode' => 'insiden', 'index' => 9]);

        $res->assertStatus(422);
        $this->assertStringContainsString('Belum ada laporan', $res->json('errors.laporan.0'));
        Carbon::setTestNow();
    }

    public function test_validasi_silang_guru_hadir_jadi_perlu_cek(): void
    {
        $pegawai = $this->makePegawai();
        $this->makePiketJadwal($pegawai);
        $this->masukkan($pegawai);
        $guruHadir = $this->makePegawai('Guru Hadir');
        $guruIzin = $this->makePegawai('Guru Izin');

        Carbon::setTestNow('2026-10-12 10:00:00');

        // status di-$guarded: set via assignment langsung, bukan create().
        $presensiHadir = Presensi::create([
            'pegawai_id' => $guruHadir->id,
            'tipe_presensi' => 'kantor',
            'tanggal' => '2026-10-12',
            'jam_masuk' => '07:00:00',
            'unit_sekolah_id' => $this->unit->id,
        ]);
        $presensiHadir->status = 'hadir';
        $presensiHadir->save();
        PengajuanIzin::create([
            'pegawai_id' => $guruIzin->id,
            'jenis_izin' => 'sakit',
            'tanggal_mulai' => '2026-10-12',
            'tanggal_selesai' => '2026-10-12',
            'alasan' => 'Demam dan flu berat',
            'status' => 'disetujui',
        ]);

        $post = fn (array $payload) => $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.piket.item'), array_merge($payload, $this->geo()));

        $post(['kode' => 'kelas_kosong', 'mode' => 'tambah', 'kelas' => 'VII-A', 'guru_pegawai_id' => $guruHadir->id, 'ada_amanat' => false])
            ->assertRedirect();
        $post(['kode' => 'kelas_kosong', 'mode' => 'tambah', 'kelas' => 'VII-B', 'guru_pegawai_id' => $guruIzin->id, 'ada_amanat' => true, 'jenis_amanat' => 'tugas', 'detail_amanat' => 'Kerjakan LKS hal 12', 'tersampaikan' => true])
            ->assertRedirect();
        Carbon::setTestNow();

        $entries = LaporanPiket::where('pegawai_id', $pegawai->id)->first()->item('kelas_kosong')['entries'];
        $this->assertSame('perlu_cek', $entries[0]['validasi']);
        $this->assertSame('cocok', $entries[1]['validasi']);
        $this->assertTrue((bool) $entries[1]['ada_amanat']);
        $this->assertTrue((bool) $entries[1]['tersampaikan']);
    }

    private function makeMengajarSlot(Pegawai $guru, string $kelas = 'VII-A', string $mapel = 'Matematika'): Jadwal
    {
        $mapelRow = MataPelajaran::create(['nama' => $mapel, 'unit_sekolah_id' => $this->unit->id]);
        $pm = PegawaiMapel::create([
            'pegawai_id' => $guru->id,
            'mata_pelajaran_id' => $mapelRow->id,
            'unit_sekolah_id' => $this->unit->id,
        ]);

        return Jadwal::create([
            'pegawai_id' => $guru->id,
            'unit_sekolah_id' => $this->unit->id,
            'pegawai_mapel_id' => $pm->id,
            'kelas_label' => $kelas,
            'hari' => 'Senin',
            'jam_mulai' => '08:00:00',
            'jam_selesai' => '08:45:00',
            'jenis_jadwal' => 'mengajar',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);
    }

    public function test_entry_lewat_slot_isi_dari_data(): void
    {
        $pegawai = $this->makePegawai();
        $this->makePiketJadwal($pegawai);
        $this->masukkan($pegawai);
        $guru = $this->makePegawai('Guru Absen');
        $slot = $this->makeMengajarSlot($guru);

        Carbon::setTestNow('2026-10-12 10:00:00');
        $res = $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.piket.item'), array_merge(
                // Nilai client sengaja salah — server harus pakai data slot.
                ['kode' => 'kelas_kosong', 'mode' => 'tambah', 'slot_jadwal_id' => $slot->id, 'kelas' => 'SALAH', 'ada_amanat' => false],
                $this->geo()
            ));
        $res->assertRedirect();
        Carbon::setTestNow();

        $entry = LaporanPiket::where('pegawai_id', $pegawai->id)->first()->item('kelas_kosong')['entries'][0];
        $this->assertSame('VII-A', $entry['kelas']);
        $this->assertSame('Matematika', $entry['mapel']);
        $this->assertSame('Guru Absen', $entry['guru_nama']);
        $this->assertSame('slot', $entry['sumber']);
        $this->assertSame($slot->id, $entry['slot_jadwal_id']);
    }

    public function test_slot_unit_lain_ditolak(): void
    {
        $pegawai = $this->makePegawai();
        $this->makePiketJadwal($pegawai);
        $this->masukkan($pegawai);
        $unitLain = UnitSekolah::create([
            'nama' => 'Unit Lain', 'singkatan' => 'UL',
            'latitude' => -6.3, 'longitude' => 106.9, 'radius_meter' => 100,
        ]);
        $guruLain = $this->makePegawai('Guru Lain');
        $guruLain->units()->detach();
        $jabatan = Jabatan::create(['nama' => 'Guru UL']);
        $guruLain->units()->attach($unitLain->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);
        $mapelRow = MataPelajaran::create(['nama' => 'IPA', 'unit_sekolah_id' => $unitLain->id]);
        $pm = PegawaiMapel::create(['pegawai_id' => $guruLain->id, 'mata_pelajaran_id' => $mapelRow->id, 'unit_sekolah_id' => $unitLain->id]);
        $slotLain = Jadwal::create([
            'pegawai_id' => $guruLain->id, 'unit_sekolah_id' => $unitLain->id,
            'pegawai_mapel_id' => $pm->id, 'kelas_label' => 'VIII-B',
            'hari' => 'Senin', 'jam_mulai' => '08:00:00', 'jam_selesai' => '08:45:00',
            'jenis_jadwal' => 'mengajar', 'tahun_ajaran' => '2026/2027', 'semester' => 1,
        ]);

        Carbon::setTestNow('2026-10-12 10:00:00');
        $res = $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.piket.item'), array_merge(
                ['kode' => 'kelas_kosong', 'mode' => 'tambah', 'slot_jadwal_id' => $slotLain->id, 'ada_amanat' => false],
                $this->geo()
            ));
        $res->assertStatus(422);
        Carbon::setTestNow();
    }

    public function test_entry_manual_dengan_mapel_unit(): void
    {
        $pegawai = $this->makePegawai();
        $this->makePiketJadwal($pegawai);
        $this->masukkan($pegawai);
        $guru = $this->makePegawai('Guru Absen');
        $mapelRow = MataPelajaran::create(['nama' => 'Bahasa Inggris', 'unit_sekolah_id' => $this->unit->id]);

        Carbon::setTestNow('2026-10-12 10:00:00');
        $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.piket.item'), array_merge(
                ['kode' => 'kelas_kosong', 'mode' => 'tambah', 'kelas' => 'X-2', 'guru_pegawai_id' => $guru->id, 'mapel_id' => $mapelRow->id, 'ada_amanat' => false],
                $this->geo()
            ))->assertRedirect();
        Carbon::setTestNow();

        $entry = LaporanPiket::where('pegawai_id', $pegawai->id)->first()->item('kelas_kosong')['entries'][0];
        $this->assertSame('X-2', $entry['kelas']);
        $this->assertSame('Bahasa Inggris', $entry['mapel']);
        $this->assertSame('manual', $entry['sumber']);
    }

    public function test_slot_termasuk_walau_ada_alpa_otomatis(): void
    {
        $pegawai = $this->makePegawai();
        $this->makePiketJadwal($pegawai);
        $this->masukkan($pegawai);
        $guru = $this->makePegawai('Guru Absen');
        $slot = $this->makeMengajarSlot($guru);
        // Baris alpa-otomatis (tanpa jam_masuk) bukan bukti terisi.
        Presensi::create([
            'pegawai_id' => $guru->id,
            'jadwal_id' => $slot->id,
            'tipe_presensi' => 'mengajar',
            'tanggal' => '2026-10-12',
            'unit_sekolah_id' => $this->unit->id,
        ]);
        Carbon::setTestNow('2026-10-12 10:00:00');

        $this->actingAs($pegawai->user, 'web_mobile')
            ->get(route('presensi.piket.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('slotKosong', 1)
                ->where('slotKosong.0.jadwal_id', $slot->id)
            );
        Carbon::setTestNow();
    }

    public function test_slot_hilang_setelah_masuk_beneran(): void
    {
        $pegawai = $this->makePegawai();
        $this->makePiketJadwal($pegawai);
        $this->masukkan($pegawai);
        $guru = $this->makePegawai('Guru Absen');
        $slot = $this->makeMengajarSlot($guru);
        $p = Presensi::create([
            'pegawai_id' => $guru->id,
            'jadwal_id' => $slot->id,
            'tipe_presensi' => 'mengajar',
            'tanggal' => '2026-10-12',
            'jam_masuk' => '08:00:00',
            'unit_sekolah_id' => $this->unit->id,
        ]);
        $p->status = 'hadir';
        $p->save();
        Carbon::setTestNow('2026-10-12 10:00:00');

        $this->actingAs($pegawai->user, 'web_mobile')
            ->get(route('presensi.piket.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('slotKosong', 0));
        Carbon::setTestNow();
    }

    public function test_mapel_global_termasuk_di_unit(): void
    {
        $pegawai = $this->makePegawai();
        $this->makePiketJadwal($pegawai);
        $this->masukkan($pegawai);
        $global = MataPelajaran::create(['nama' => 'Mapel Global']);
        Carbon::setTestNow('2026-10-12 10:00:00');

        $this->actingAs($pegawai->user, 'web_mobile')
            ->get(route('presensi.piket.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('mapelUnit.0.nama', 'Mapel Global')
                ->where('mapelUnit.0.id', $global->id)
            );
        Carbon::setTestNow();
    }

    public function test_admin_lihat_laporan_dan_belum_melapor(): void
    {
        $pegawai = $this->makePegawai('Guru Lapor');
        $jadwal = $this->makePiketJadwal($pegawai);
        $laporan = LaporanPiket::create([
            'pegawai_id' => $pegawai->id,
            'jadwal_id' => $jadwal->id,
            'unit_sekolah_id' => $this->unit->id,
            'tanggal' => '2026-10-12',
            'items' => ['gerbang' => ['done_at' => '07:05:00', 'terlambat_count' => 2]],
            'status' => 'draft',
        ]);

        // Petugas kedua: ada jadwal, tanpa laporan.
        $pegawai2 = $this->makePegawai('Guru Malas');
        $this->makePiketJadwal($pegawai2);

        $this->actingAs($this->admin, 'web_admin')
            ->get('/laporan/piket?start_date=2026-10-12&end_date=2026-10-12')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Laporan/Piket')
                ->has('laporan', 1)
                ->where('laporan.0.id', $laporan->id)
                ->where('laporan.0.done', 1)
                ->has('belum', 1)
                ->where('belum.0.pegawai.nama_lengkap', 'Guru Malas')
            );
    }

    public function test_admin_tanpa_izin_ditolak_dan_scope_unit(): void
    {
        $pegawai = $this->makePegawai('Guru Lapor');
        $this->makePiketJadwal($pegawai);
        LaporanPiket::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'tanggal' => '2026-10-12',
            'status' => 'draft',
        ]);

        // Pegawai biasa tanpa view_presensi.
        $this->actingAs($pegawai->user, 'web_admin')
            ->get('/laporan/piket')
            ->assertForbidden();

        // Admin unit lain tidak melihat unit ini.
        $unit2 = UnitSekolah::create([
            'nama' => 'SD Lain', 'singkatan' => 'SDL',
            'latitude' => -6.3, 'longitude' => 106.9, 'radius_meter' => 100,
        ]);
        $adminLain = User::factory()->create(['role' => 'admin_unit', 'unit_sekolah_id' => $unit2->id]);
        $adminLain->assignRole('admin_unit');

        $this->actingAs($adminLain, 'web_admin')
            ->get('/laporan/piket?start_date=2026-10-12&end_date=2026-10-12')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('laporan', 0)
                ->has('belum', 0)
            );
    }

    public function test_riwayat_mobile_hanya_milik_sendiri(): void
    {
        $a = $this->makePegawai('Guru A');
        $b = $this->makePegawai('Guru B');
        foreach ([$a, $b] as $pg) {
            LaporanPiket::create([
                'pegawai_id' => $pg->id,
                'unit_sekolah_id' => $this->unit->id,
                'tanggal' => '2026-10-12',
                'status' => 'submitted',
            ]);
        }

        $this->actingAs($a->user, 'web_mobile')
            ->get(route('presensi.piket.riwayat'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Mobile/PiketRiwayat')
                ->has('riwayat', 1)
                ->where('riwayat.0.status', 'submitted')
            );
    }

    public function test_laporan_milik_sendiri_tidak_bocor(): void
    {
        $a = $this->makePegawai('Guru A');
        $b = $this->makePegawai('Guru B');
        $this->makePiketJadwal($a);
        $this->masukkan($a);
        $this->makePiketJadwal($b);

        Carbon::setTestNow('2026-10-12 08:00:00');
        $this->actingAs($a->user, 'web_mobile')
            ->postJson(route('presensi.piket.item'), array_merge(
                ['kode' => 'gerbang', 'mode' => 'centang'],
                $this->geo()
            ))->assertRedirect();
        Carbon::setTestNow();

        $this->assertDatabaseHas('laporan_piket', ['pegawai_id' => $a->id]);
        $this->assertDatabaseMissing('laporan_piket', ['pegawai_id' => $b->id]);
    }

    public function test_admin_validasi_entry_dan_scope_unit(): void
    {
        $pegawai = $this->makePegawai();
        $this->makePiketJadwal($pegawai);
        $this->masukkan($pegawai);
        $guru = $this->makePegawai('Guru Absen');

        Carbon::setTestNow('2026-10-12 10:00:00');
        $this->actingAs($pegawai->user, 'web_mobile')
            ->postJson(route('presensi.piket.item'), array_merge(
                ['kode' => 'kelas_kosong', 'mode' => 'tambah', 'kelas' => 'VII-A', 'guru_pegawai_id' => $guru->id, 'ada_amanat' => false],
                $this->geo()
            ))->assertRedirect();
        Carbon::setTestNow();

        $laporan = LaporanPiket::where('pegawai_id', $pegawai->id)->first();

        $this->actingAs($this->admin, 'web_admin')
            ->post(route('laporan-piket.validasi', $laporan->id), ['entry_index' => 0, 'aksi' => 'setujui'])
            ->assertSessionHas('message');

        $entry = $laporan->fresh()->item('kelas_kosong')['entries'][0];
        $this->assertSame('setujui', $entry['verified']);
        $this->assertSame($this->admin->id, $entry['verified_by']);

        // Admin unit lain ditolak
        $unit2 = UnitSekolah::create([
            'nama' => 'SD Lain', 'singkatan' => 'SDL',
            'latitude' => -6.3, 'longitude' => 106.9, 'radius_meter' => 100,
        ]);
        $adminLain = User::factory()->create(['role' => 'admin_unit', 'unit_sekolah_id' => $unit2->id]);
        $adminLain->assignRole('admin_unit');

        $this->actingAs($adminLain, 'web_admin')
            ->post(route('laporan-piket.validasi', $laporan->id), ['entry_index' => 0, 'aksi' => 'tolak'])
            ->assertForbidden();
    }
}
