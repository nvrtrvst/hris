<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Jadwal;
use App\Models\Pegawai;
use App\Models\UnitSekolah;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JadwalPdfExportTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $sd;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->sd = UnitSekolah::create(['nama' => 'SD Uji', 'singkatan' => 'SD']);
    }

    private function makePegawai(string $nama, string $jabatanNama = 'Guru Mata Pelajaran', bool $isGuru = true): Pegawai
    {
        $jabatan = Jabatan::firstOrCreate(['nama' => $jabatanNama], ['is_guru' => $isGuru]);

        $pegawai = Pegawai::create([
            'nik' => '3273'.str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT),
            'nama_lengkap' => $nama,
            'jenis_kelamin' => 'L',
            'status_kepegawaian' => 'guru_pemula',
            'status_aktif' => 'aktif',
            'tmt_mengajar' => '2020-01-01',
            'tanggal_akhir_kontrak' => now()->addDays(15),
        ]);
        $pegawai->units()->attach($this->sd->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        return $pegawai;
    }

    private function makePimpinan(Pegawai $pegawai): User
    {
        $user = User::factory()->create(['unit_sekolah_id' => $this->sd->id]);
        $user->assignRole('pimpinan');
        $pegawai->update(['user_id' => $user->id]);

        return $user;
    }

    public function test_export_pdf_kepsek_dan_pimpinan_lain_tetap_menghasilkan_pdf(): void
    {
        $kepsek = $this->makePegawai('Kepala Sekolah SD', 'Kepala Sekolah', false);
        $this->makePegawai('Guru Satu');
        $this->makePegawai('Staf TU', 'Staf Tata Usaha', false);

        Jadwal::create([
            'pegawai_id' => Pegawai::where('nama_lengkap', 'Guru Satu')->value('id'),
            'unit_sekolah_id' => $this->sd->id,
            'kelas_label' => '7 - A',
            'hari' => 'Senin',
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '08:00:00',
            'jenis_jadwal' => 'mengajar',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);

        $this->actingAs($this->makePimpinan($kepsek))
            ->get(route('jadwal.export-pdf'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $kepalaTu = $this->makePegawai('Kepala TU SD', 'Kepala Tata Usaha', false);

        $this->actingAs($this->makePimpinan($kepalaTu))
            ->get(route('jadwal.export-pdf'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_view_menampilkan_kolom_jam_dinamis_dan_tugas_non_mengajar(): void
    {
        $html = view('exports.pdf-jadwal', [
            'matrix' => [
                '7 - A' => [
                    'Senin' => [
                        '07:00' => [
                            ['guru' => 'Budi Santoso', 'mapel' => 'Matematika', 'jam' => '07:00-08:00'],
                        ],
                    ],
                ],
            ],
            'perGuru' => [
                1 => [
                    'nama' => 'Budi Santoso',
                    'jadwals' => [[
                        'hari' => 'Senin',
                        'jam_mulai' => '07:00',
                        'jam_selesai' => '08:00',
                        'kelas' => '7 - A',
                        'mapel' => 'Matematika',
                        'jenis' => 'mengajar',
                    ]],
                ],
            ],
            'tugasNonMengajar' => [
                ['nama' => 'Siti Aminah', 'tugas' => 'Piket', 'hari' => 'Senin', 'jam' => '08:00-09:00'],
            ],
            'kelasList' => ['7 - A'],
            'DAYS' => ['Senin'],
            'slotTimes' => ['07:00'],
            'logoPath' => null,
            'logoWidth' => null,
            'unitName' => 'SD Uji',
            'tahunAjaran' => '2026/2027',
            'semester' => 1,
            'kepalaSekolah' => null,
        ])->render();

        $this->assertStringContainsString('Budi Santoso', $html);
        $this->assertStringContainsString('Matematika', $html);
        $this->assertStringContainsString('7 - A', $html);
        $this->assertStringContainsString('07:00-08:00', $html);
        $this->assertStringContainsString('Tugas Non-Mengajar', $html);
        $this->assertStringContainsString('Siti Aminah', $html);
        $this->assertStringContainsString('Piket', $html);
    }
}
