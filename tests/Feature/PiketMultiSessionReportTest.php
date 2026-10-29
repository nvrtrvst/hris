<?php

namespace Tests\Feature;

use App\Models\LaporanPiket;
use App\Models\Pegawai;
use App\Models\PiketRound;
use App\Models\PiketSesi;
use App\Models\UnitSekolah;
use App\Models\User;
use App\Services\PiketService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PiketMultiSessionReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_satu_pegawai_dua_sesi_mendapat_laporan_terpisah(): void
    {
        Carbon::setTestNow('2026-10-29 12:30:00');

        $pegawai = $this->pegawai('Reza');
        $lpq = $this->unit('LPQ', 'area');
        $smp = $this->unit('SMP', 'simple');

        $sesiLpq = $this->sesi($lpq, '07:30', '14:00');
        $sesiSmp = $this->sesi($smp, '07:30', '14:00');

        $petugasLpq = PiketService::linkPetugas($sesiLpq, $pegawai->id, null, null, 'area');
        $petugasSmp = PiketService::linkPetugas($sesiSmp, $pegawai->id, null, null, 'area');

        $this->assertNotSame($petugasLpq->laporan_piket_id, $petugasSmp->laporan_piket_id);
        $this->assertSame($sesiLpq->id, $petugasLpq->laporan->sesi_id);
        $this->assertSame($sesiSmp->id, $petugasSmp->laporan->sesi_id);
        $this->assertSame(2, LaporanPiket::where('pegawai_id', $pegawai->id)->count());
    }

    public function test_fase_sesi_area_tidak_memblokir_sesi_simple(): void
    {
        Carbon::setTestNow('2026-10-29 12:30:00');

        $pegawai = $this->pegawai('Reza');
        $lpq = $this->unit('LPQ', 'area');
        $smp = $this->unit('SMP', 'simple');

        $sesiLpq = $this->sesi($lpq, '07:30', '14:00');
        $sesiSmp = $this->sesi($smp, '07:30', '14:00');

        PiketRound::create([
            'sesi_id' => $sesiLpq->id,
            'petugas_id' => $pegawai->id,
            'putaran' => 1,
            'jendela_mulai' => '10:41',
            'jendela_selesai' => '11:01',
        ]);

        $laporanLpq = LaporanPiket::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $lpq->id,
            'sesi_id' => $sesiLpq->id,
            'tanggal' => now()->toDateString(),
            'status' => LaporanPiket::STATUS_DRAFT,
            'items' => [],
        ]);
        $laporanSmp = LaporanPiket::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $smp->id,
            'sesi_id' => $sesiSmp->id,
            'tanggal' => now()->toDateString(),
            'status' => LaporanPiket::STATUS_DRAFT,
            'items' => [],
        ]);

        $this->assertStringContainsString('Fase 1', (string) PiketService::roundBlockReason($laporanLpq));
        $this->assertNull(PiketService::roundBlockReason($laporanSmp));
        $this->assertNull(PiketService::sessionBlockReason($laporanSmp));
    }

    private function pegawai(string $nama): Pegawai
    {
        $user = User::factory()->create();

        return Pegawai::create([
            'user_id' => $user->id,
            'nik' => '3273'.str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT),
            'nama_lengkap' => $nama,
            'jenis_kelamin' => 'L',
            'status_kepegawaian' => 'guru_tetap_yayasan',
            'status_aktif' => 'aktif',
            'tmt_mengajar' => '2020-01-01',
            'wajib_kantor' => true,
        ]);
    }

    private function unit(string $nama, string $mode): UnitSekolah
    {
        $unit = UnitSekolah::create([
            'nama' => $nama,
            'singkatan' => $nama,
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
        ]);
        $unit->forceFill(['piket_mode' => $mode])->save();

        return $unit;
    }

    private function sesi(UnitSekolah $unit, string $mulai, string $selesai): PiketSesi
    {
        return PiketSesi::create([
            'unit_sekolah_id' => $unit->id,
            'tanggal' => now()->toDateString(),
            'jam_mulai' => $mulai,
            'jam_selesai' => $selesai,
            'status' => 'open',
        ]);
    }
}
