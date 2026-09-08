<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Jadwal;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\UnitSekolah;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class JadwalImportExcelTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private User $admin;

    private Pegawai $guru;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->unit = UnitSekolah::create(['nama' => 'SMP Test', 'singkatan' => 'SMP', 'latitude' => -6.2, 'longitude' => 106.8, 'radius_meter' => 100]);

        $this->admin = User::factory()->create();
        $this->admin->syncRoles('superadmin');

        $jabatan = Jabatan::create(['nama' => 'Guru', 'is_guru' => true]);
        $user = User::factory()->create();
        $this->guru = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '1234567890000101',
            'nama_lengkap' => 'Guru Import',
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'Belum Menikah',
            'alamat_ktp' => 'Jl. Test',
            'no_hp' => '081234567890',
            'status_kepegawaian' => 'tetap',
            'tanggal_mulai_kerja' => '2020-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
        ]);
        $this->guru->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        MataPelajaran::create(['nama' => 'Matematika']);
    }

    private function makeXlsx(array $rows): UploadedFile
    {
        $all = array_merge([
            ['Hari', 'Kelas', 'Nama Guru', 'Mata Pelajaran', 'Jam Mulai', 'Jumlah JP', 'Jam Selesai', 'Jenis Jadwal', 'Tahun Ajaran', 'Semester'],
        ], $rows);
        Excel::store(new class($all) implements FromArray
        {
            public function __construct(private array $rows) {}

            public function array(): array
            {
                return $this->rows;
            }
        }, 'test-import.xlsx', 'local');
        $path = config('filesystems.disks.local.root').'/test-import.xlsx';

        return new UploadedFile($path, 'test-import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_import_excel_sukses_jam_selesai_auto_dari_jumlah_jp(): void
    {
        // durasi_jp unit = 40 menit → 07:00 + 2 JP = 08:20 (JP lanjut 08:20).
        $this->unit->forceFill(['durasi_jp' => 40])->save();

        $file = $this->makeXlsx([
            ['Senin', '7 - A', 'Guru Import', 'Matematika', '07:00', 2, '', 'mengajar', '2026/2027', 1],
            ['Senin', '7 - A', 'Guru Import', 'Matematika', '08:20', 2, '', 'mengajar', '2026/2027', 1],
        ]);

        $res = $this->actingAs($this->admin, 'web_admin')
            ->post(route('jadwal.import'), [
                'file' => $file,
                'unit_sekolah_id' => $this->unit->id,
                'tahun_ajaran' => '2026/2027',
                'semester' => 1,
            ]);

        $res->assertRedirect();
        $this->assertSame(2, Jadwal::where('pegawai_id', $this->guru->id)->count());
        $this->assertDatabaseHas('jadwal', ['pegawai_id' => $this->guru->id, 'jam_mulai' => '07:00:00', 'jam_selesai' => '08:20:00']);
        $this->assertDatabaseHas('jadwal', ['pegawai_id' => $this->guru->id, 'jam_mulai' => '08:20:00', 'jam_selesai' => '09:40:00']);
    }

    public function test_import_excel_jam_selesai_manual_non_mengajar(): void
    {
        // Piket: Jumlah JP kosong, Jam Selesai manual di kolom G.
        $file = $this->makeXlsx([
            ['Selasa', '', 'Guru Import', '', '07:00', '', '15:00', 'piket', '2026/2027', 1],
        ]);

        $this->actingAs($this->admin, 'web_admin')
            ->post(route('jadwal.import'), [
                'file' => $file,
                'unit_sekolah_id' => $this->unit->id,
            ]);

        $this->assertDatabaseHas('jadwal', [
            'pegawai_id' => $this->guru->id,
            'jenis_jadwal' => 'piket',
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '15:00:00',
        ]);
    }

    public function test_import_excel_template_lama_tetap_didukung(): void
    {
        // Template lama: tanpa Jumlah JP — jenis jadwal di kolom G (index 6).
        $file = $this->makeXlsx([
            ['Senin', '7 - A', 'Guru Import', 'Matematika', '08:00', '08:45', 'mengajar', '2026/2027', 1],
        ]);

        $this->actingAs($this->admin, 'web_admin')
            ->post(route('jadwal.import'), [
                'file' => $file,
                'unit_sekolah_id' => $this->unit->id,
            ]);

        $this->assertDatabaseHas('jadwal', [
            'pegawai_id' => $this->guru->id,
            'jam_mulai' => '08:00:00',
            'jam_selesai' => '08:45:00',
        ]);
    }

    public function test_import_excel_bentrok_ditolak_semua(): void
    {
        // Two-pass all-or-nothing: baris 2 bentrok dengan baris 1 →
        // SELURUH file ditolak dengan laporan baris bermasalah.
        $file = $this->makeXlsx([
            ['Senin', '7 - A', 'Guru Import', 'Matematika', '08:00', '', '09:00', 'mengajar', '2026/2027', 1],
            ['Senin', '7 - B', 'Guru Import', 'Matematika', '08:30', '', '09:30', 'mengajar', '2026/2027', 1],
        ]);

        $res = $this->actingAs($this->admin, 'web_admin')
            ->post(route('jadwal.import'), [
                'file' => $file,
                'unit_sekolah_id' => $this->unit->id,
                'tahun_ajaran' => '2026/2027',
                'semester' => 1,
            ]);

        $res->assertRedirect();
        $this->assertSame(0, Jadwal::where('pegawai_id', $this->guru->id)->count());
        $this->assertStringContainsString('Bentrok', (string) session('message'));
    }

    public function test_import_excel_guru_tidak_ditemukan_error_per_baris(): void
    {
        $file = $this->makeXlsx([
            ['Senin', '7 - A', 'Guru Tak Ada', 'Matematika', '08:00', '', '08:45', 'mengajar', '2026/2027', 1],
        ]);

        $res = $this->actingAs($this->admin, 'web_admin')
            ->post(route('jadwal.import'), [
                'file' => $file,
                'unit_sekolah_id' => $this->unit->id,
            ]);

        $res->assertRedirect();
        $this->assertSame(0, Jadwal::count());
        $msg = session('message');
        $this->assertStringContainsString("Guru 'Guru Tak Ada' tidak ditemukan", (string) $msg);
    }

    public function test_import_excel_auto_create_pegawai_mapel(): void
    {
        $file = $this->makeXlsx([
            ['Selasa', '8 - A', 'Guru Import', 'Matematika', '09:00', 1, '', 'mengajar', '2026/2027', 1],
        ]);

        $this->actingAs($this->admin, 'web_admin')
            ->post(route('jadwal.import'), [
                'file' => $file,
                'unit_sekolah_id' => $this->unit->id,
            ]);

        $this->assertDatabaseHas('pegawai_mapel', [
            'pegawai_id' => $this->guru->id,
            'unit_sekolah_id' => $this->unit->id,
        ]);
    }

    public function test_template_download(): void
    {
        $this->actingAs($this->admin, 'web_admin')
            ->get(route('jadwal.template'))
            ->assertOk()
            ->assertDownload('template_import_jadwal.xlsx');
    }
}
