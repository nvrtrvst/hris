<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\UnitSekolah;
use App\Models\User;
use Database\Seeders\JabatanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnnouncementReadTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private Pegawai $pegawai;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(JabatanSeeder::class);

        $this->unit = UnitSekolah::create([
            'nama' => 'SMP Baca',
            'singkatan' => 'SMPB',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'toleransi_menit' => 0,
            'toleransi_tap_menit' => 15,
        ]);

        $this->user = User::factory()->create(['role' => 'pegawai']);
        $this->user->assignRole('pegawai');

        $this->pegawai = Pegawai::create([
            'user_id' => $this->user->id,
            'nik' => '1234567890123456',
            'nama_lengkap' => 'Pegawai Baca',
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'Menikah',
            'no_hp' => '081234567890',
            'alamat_ktp' => 'Jl. Contoh No. 1',
            'status_kepegawaian' => 'tetap',
            'tanggal_mulai_kerja' => '2020-01-01',
            'pendidikan_terakhir' => 'S1',
            'status_aktif' => 'aktif',
        ]);
        $this->pegawai->units()->attach($this->unit->id, ['jabatan_id' => Jabatan::first()->id, 'is_primary' => true]);
    }

    public function test_badge_hitung_pengumuman_belum_dibaca_saja(): void
    {
        $this->makeAnnouncement('Umum 1');
        $this->makeAnnouncement('Umum 2');
        // Satu sudah dibaca → badge harus 1, bukan 2
        Announcement::first()->markReadBy($this->pegawai);

        $response = $this->actingAs($this->user, 'web_mobile')->get(route('presensi.dashboard'));

        $response->assertInertia(fn ($page) => $page->component('Mobile/Dashboard')
            ->where('auth.announcement_count', 1));
    }

    public function test_buka_halaman_pengumuman_menandai_semua_terbaca(): void
    {
        $this->makeAnnouncement('Umum 1');
        $this->makeAnnouncement('Umum 2');

        // Buka halaman pengumuman → semua yang tampil ditandai terbaca
        $this->actingAs($this->user, 'web_mobile')->get(route('presensi.pengumuman'))
            ->assertOk();

        $this->assertSame(2, DB::table('announcement_pegawai')->where('pegawai_id', $this->pegawai->id)->count(), 'Semua pengumuman harus terbaca');

        // Badge berikutnya = 0
        $response = $this->actingAs($this->user, 'web_mobile')->get(route('presensi.dashboard'));
        $response->assertInertia(fn ($page) => $page->where('auth.announcement_count', 0));
    }

    public function test_pengumuman_unit_lain_tidak_dihitung_dan_tidak_ditandai(): void
    {
        $otherUnit = UnitSekolah::create([
            'nama' => 'SMA Lain',
            'singkatan' => 'SMAL',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'toleransi_menit' => 0,
            'toleransi_tap_menit' => 15,
        ]);
        $this->makeAnnouncement('Umum 1'); // null = semua unit
        $this->makeAnnouncement('Khusus SMP', $this->unit->id);
        $this->makeAnnouncement('Khusus SMA', $otherUnit->id); // bukan unit pegawai

        $response = $this->actingAs($this->user, 'web_mobile')->get(route('presensi.dashboard'));

        $response->assertInertia(fn ($page) => $page->where('auth.announcement_count', 2));
    }

    private function makeAnnouncement(string $title, ?int $unitId = null): Announcement
    {
        return Announcement::create([
            'title' => $title,
            'body' => 'Isi pengumuman',
            'unit_sekolah_id' => $unitId,
            'published_at' => now(),
            'created_by' => $this->user->id,
        ]);
    }
}
