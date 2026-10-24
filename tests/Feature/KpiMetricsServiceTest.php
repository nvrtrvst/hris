<?php

namespace Tests\Feature;

use App\Models\HariLibur;
use App\Models\Jabatan;
use App\Models\Jadwal;
use App\Models\Pegawai;
use App\Models\PegawaiUnit;
use App\Models\PengajuanIzin;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use App\Services\KpiMetricsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KpiMetricsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_metrics_batch_attendance_and_teaching_for_assignment(): void
    {
        Carbon::setTestNow('2026-10-12 12:00:00');
        $unit = UnitSekolah::create([
            'nama' => 'Unit KPI', 'singkatan' => 'KPI',
            'latitude' => -6.2, 'longitude' => 106.8,
            'radius_meter' => 100, 'durasi_jp' => 45, 'toleransi_menit' => 0,
        ]);
        $jabatan = Jabatan::create(['nama' => 'Guru KPI', 'is_guru' => true]);
        $user = User::factory()->create();
        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '1234567890123456',
            'nama_lengkap' => 'Guru KPI',
            'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L', 'agama' => 'Islam',
            'status_pernikahan' => 'kawin', 'jumlah_tanggungan' => 0,
            'alamat' => 'Jl. Uji No. 1', 'no_hp' => '081234567890',
            'status_kepegawaian' => 'guru_tetap_yayasan', 'tmt_mengajar' => '2020-01-01', 'wajib_kantor' => true,
            'status_aktif' => 'aktif', 'pendidikan_terakhir' => 'S1',
        ]);
        $pegawai->units()->attach($unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);
        $assignment = PegawaiUnit::where('pegawai_id', $pegawai->id)->firstOrFail();
        $jadwal = Jadwal::create([
            'pegawai_id' => $pegawai->id, 'unit_sekolah_id' => $unit->id,
            'hari' => 'Senin', 'jam_mulai' => '08:00:00', 'jam_selesai' => '08:45:00',
            'jenis_jadwal' => 'mengajar', 'tahun_ajaran' => '2026/2027', 'semester' => 1,
        ]);
        Presensi::forceCreate([
            'pegawai_id' => $pegawai->id, 'jadwal_id' => $jadwal->id,
            'unit_sekolah_id' => $unit->id, 'tanggal' => '2026-10-12',
            'jam_masuk' => '08:00:00', 'status' => 'hadir', 'is_lembur' => false,
        ]);
        foreach (['2026-10-14' => 'telat', '2026-10-15' => 'hadir'] as $date => $status) {
            Presensi::forceCreate([
                'pegawai_id' => $pegawai->id, 'unit_sekolah_id' => $unit->id,
                'tanggal' => $date, 'jam_masuk' => '08:00:00', 'status' => $status, 'is_lembur' => false,
            ]);
        }
        HariLibur::create(['nama' => 'Libur unit', 'tanggal' => '2026-10-16', 'unit_sekolah_id' => $unit->id]);
        PengajuanIzin::create([
            'pegawai_id' => $pegawai->id, 'jenis_izin' => 'izin',
            'tanggal_mulai' => '2026-10-13', 'tanggal_selesai' => '2026-10-13', 'alasan' => 'Keperluan keluarga',
        ])->forceFill(['status' => 'disetujui'])->save();

        $metrics = app(KpiMetricsService::class)->forAssignments(
            collect([$assignment->load(['pegawai', 'unitSekolah', 'jabatan'])]),
            Carbon::parse('2026-10-12'),
            Carbon::parse('2026-10-16'),
        );

        $this->assertSame(100.0, $metrics[$assignment->id]['attendance_rate']);
        $this->assertSame(66.67, $metrics[$assignment->id]['punctuality_rate']);
        $this->assertSame(100.0, $metrics[$assignment->id]['teaching_jp_rate']);
        $this->assertSame(3, $metrics[$assignment->id]['hari_kerja']);

        Carbon::setTestNow();
    }
}
