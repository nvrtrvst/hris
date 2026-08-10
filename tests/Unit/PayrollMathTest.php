<?php

namespace Tests\Unit;

use App\Http\Controllers\PenggajianController;
use App\Models\Jadwal;
use App\Models\KomponenGaji;
use App\Models\Pegawai;
use App\Models\Presensi;
use App\Models\SkalaMasaBakti;
use App\Models\UnitSekolah;
use App\Services\PresensiAggregator;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Subclass untuk expose method protected PenggajianController
 * agar bisa diuji tanpa DB (murni perhitungan).
 */
class TestablePenggajianController extends PenggajianController
{
    public function pubCountWeekdayInRange(string $hari, Carbon $start, Carbon $end): int
    {
        return $this->countWeekdayInRange($hari, $start, $end);
    }

    public function pubComputeComponentNominal(
        KomponenGaji $komponen,
        Pegawai $pegawai,
        $pegawaiKomponens,
        $globalKomponens,
        array $counts,
        $skalas,
        Carbon $periodeEnd,
        Carbon $periodeStart,
        Carbon $attendanceCutoff,
        $lemburByPegawai = null,
        $sakitProrata = null,
        $presentDays = null
    ): float {
        return $this->computeComponentNominal(
            $komponen, $pegawai, $pegawaiKomponens, $globalKomponens, $counts, $skalas,
            $periodeEnd, $periodeStart, $attendanceCutoff, $lemburByPegawai, $sakitProrata, $presentDays
        );
    }
}

class PayrollMathTest extends TestCase
{
    private function controller(): TestablePenggajianController
    {
        return new TestablePenggajianController(new PresensiAggregator);
    }

    private function makeKomponen(array $attrs = []): KomponenGaji
    {
        $k = new KomponenGaji(array_merge([
            'nama' => 'Komponen',
            'tipe' => 'pendapatan',
            'jenis' => 'fixed',
            'nilai_default' => 0,
            'kode' => null,
            'unit_sekolah_id' => null,
            'is_taxable' => false,
            'is_active' => true,
            'applies_to_status_kepegawaian' => null,
            'syarat_bayar_jam_mengajar' => null,
        ], $attrs));
        $k->id = $attrs['id'] ?? 99;

        return $k;
    }

    private function makePegawai(array $attrs = []): Pegawai
    {
        $p = new Pegawai(array_merge([
            'nama_lengkap' => 'Test Pegawai',
            'status_kepegawaian' => 'honorer',
            'status_aktif' => 'aktif',
            'tanggal_mulai_kerja' => '2020-01-01',
        ], $attrs));
        $p->id = $attrs['id'] ?? 42;
        $p->setRelation('units', new Collection);
        $p->setRelation('jadwals', new Collection);

        return $p;
    }

    public function test_count_weekday_in_range_august_2026(): void
    {
        // 1 Agustus 2026 = Sabtu. Senin: 3, 10, 17, 24, 31 → 5
        $start = Carbon::create(2026, 8, 1);
        $end = Carbon::create(2026, 8, 31);

        $this->assertSame(5, $this->controller()->pubCountWeekdayInRange('Senin', $start, $end));
        $this->assertSame(4, $this->controller()->pubCountWeekdayInRange('Jumat', $start, $end));
        $this->assertSame(5, $this->controller()->pubCountWeekdayInRange('Sabtu', $start, $end));
    }

    public function test_count_weekday_in_range_invalid_day_returns_zero(): void
    {
        $this->assertSame(0, $this->controller()->pubCountWeekdayInRange('BukanHari', Carbon::create(2026, 8, 1), Carbon::create(2026, 8, 31)));
    }

    public function test_count_weekday_in_range_reversed_dates_returns_zero(): void
    {
        $this->assertSame(0, $this->controller()->pubCountWeekdayInRange('Senin', Carbon::create(2026, 8, 31), Carbon::create(2026, 8, 1)));
    }

    public function test_fixed_komponen_uses_default_value(): void
    {
        $komponen = $this->makeKomponen(['jenis' => 'fixed', 'nilai_default' => 500000]);
        $pegawai = $this->makePegawai();
        $periodeEnd = Carbon::create(2026, 8, 31);
        $periodeStart = Carbon::create(2026, 8, 1);

        $nominal = $this->controller()->pubComputeComponentNominal(
            $komponen, $pegawai, new Collection, new Collection, [],
            new Collection, $periodeEnd, $periodeStart, $periodeEnd
        );

        $this->assertSame(500000.0, $nominal);
    }

    public function test_fixed_komponen_uses_pivot_override(): void
    {
        $komponen = $this->makeKomponen(['id' => 5, 'jenis' => 'fixed', 'nilai_default' => 500000]);
        $override = $this->makeKomponen(['id' => 5, 'jenis' => 'fixed', 'nilai_default' => 500000]);
        $override->pivot = (object) ['nominal' => 750000];
        $pegawaiKomponens = collect([5 => $override]);

        $pegawai = $this->makePegawai();
        $periodeEnd = Carbon::create(2026, 8, 31);
        $periodeStart = Carbon::create(2026, 8, 1);

        $nominal = $this->controller()->pubComputeComponentNominal(
            $komponen, $pegawai, $pegawaiKomponens, new Collection, [],
            new Collection, $periodeEnd, $periodeStart, $periodeEnd
        );

        $this->assertSame(750000.0, $nominal);
    }

    public function test_persentase_komponen_uses_gaji_pokok_as_base(): void
    {
        $gajiPokok = $this->makeKomponen(['id' => 1, 'kode' => 'gaji_pokok', 'nama' => 'Gaji Pokok', 'jenis' => 'fixed', 'nilai_default' => 1000000]);
        $persentase = $this->makeKomponen(['id' => 2, 'kode' => 'tunjangan_jabatan', 'nama' => 'Tunjangan Jabatan', 'jenis' => 'persentase', 'nilai_default' => 10]);
        $pegawai = $this->makePegawai();
        $periodeEnd = Carbon::create(2026, 8, 31);
        $periodeStart = Carbon::create(2026, 8, 1);

        $nominal = $this->controller()->pubComputeComponentNominal(
            $persentase, $pegawai, new Collection, collect([$gajiPokok, $persentase]), [],
            new Collection, $periodeEnd, $periodeStart, $periodeEnd
        );

        $this->assertSame(100000.0, $nominal);
    }

    public function test_tunjangan_kehadiran_dedup_per_hari_kalender(): void
    {
        // 3 record hadir + 1 record telat, tapi hanya 2 hari kalender berbeda.
        $komponen = $this->makeKomponen(['id' => 3, 'kode' => 'tunjangan_kehadiran', 'nama' => 'Tunjangan Kehadiran', 'jenis' => 'dinamis_kehadiran', 'nilai_default' => 20000]);
        $pegawai = $this->makePegawai();
        $counts = ['hadir' => 3, 'telat' => 1, 'sakit' => 0, 'izin' => 0, 'cuti' => 0, 'alpa' => 0];
        $presentDays = collect([$pegawai->id => 2]); // dedup per tanggal
        $periodeEnd = Carbon::create(2026, 8, 31);
        $periodeStart = Carbon::create(2026, 8, 1);

        $nominal = $this->controller()->pubComputeComponentNominal(
            $komponen, $pegawai, new Collection, new Collection, $counts,
            new Collection, $periodeEnd, $periodeStart, $periodeEnd, null, null, $presentDays
        );

        // 2 hari × 20000 = 40000 (BUKAN 4 record × 20000)
        $this->assertSame(40000.0, $nominal);
    }

    public function test_tunjangan_kehadiran_fallback_to_counts_when_present_days_missing(): void
    {
        $komponen = $this->makeKomponen(['id' => 3, 'kode' => 'tunjangan_kehadiran', 'nama' => 'Tunjangan Kehadiran', 'jenis' => 'dinamis_kehadiran', 'nilai_default' => 20000]);
        $pegawai = $this->makePegawai();
        $counts = ['hadir' => 3, 'telat' => 1, 'sakit' => 0, 'izin' => 0, 'cuti' => 0, 'alpa' => 0];
        $periodeEnd = Carbon::create(2026, 8, 31);
        $periodeStart = Carbon::create(2026, 8, 1);

        $nominal = $this->controller()->pubComputeComponentNominal(
            $komponen, $pegawai, new Collection, new Collection, $counts,
            new Collection, $periodeEnd, $periodeStart, $periodeEnd
        );

        // Fallback: hadir + telat = 4
        $this->assertSame(80000.0, $nominal);
    }

    public function test_dinamis_kehadiran_telat_rate_times_count(): void
    {
        $komponen = $this->makeKomponen(['id' => 4, 'kode' => 'kehadiran_telat', 'nama' => 'Potongan Telat', 'jenis' => 'dinamis_kehadiran', 'tipe' => 'potongan', 'nilai_default' => 25000]);
        $pegawai = $this->makePegawai();
        $counts = ['hadir' => 3, 'telat' => 2, 'sakit' => 0, 'izin' => 0, 'cuti' => 0, 'alpa' => 0];
        $periodeEnd = Carbon::create(2026, 8, 31);
        $periodeStart = Carbon::create(2026, 8, 1);

        $nominal = $this->controller()->pubComputeComponentNominal(
            $komponen, $pegawai, new Collection, new Collection, $counts,
            new Collection, $periodeEnd, $periodeStart, $periodeEnd
        );

        $this->assertSame(50000.0, $nominal);
    }

    public function test_dinamis_masa_bakti_lookup_bracket(): void
    {
        $komponen = $this->makeKomponen(['id' => 6, 'kode' => 'masa_bakti', 'nama' => 'Masa Bakti', 'jenis' => 'dinamis_masa_bakti']);
        // Mulai kerja 2020-08-10 → per 2026-08-31 ≈ 6 tahun.
        $pegawai = $this->makePegawai(['tanggal_mulai_kerja' => '2020-08-10']);

        $skala5 = new SkalaMasaBakti(['masa_kerja_tahun' => 5, 'nominal_gaji' => 50000]);
        $skala10 = new SkalaMasaBakti(['masa_kerja_tahun' => 10, 'nominal_gaji' => 100000]);
        $skalas = collect([$skala5, $skala10]); // di-sort desc di produksi; lookup first() <= years

        $periodeEnd = Carbon::create(2026, 8, 31);
        $periodeStart = Carbon::create(2026, 8, 1);

        // Bracket tertinggi dengan masa_kerja_tahun <= 6 → skala 5
        $skalasDesc = $skalas->sortByDesc('masa_kerja_tahun')->values();
        $nominal = $this->controller()->pubComputeComponentNominal(
            $komponen, $pegawai, new Collection, new Collection, [],
            $skalasDesc, $periodeEnd, $periodeStart, $periodeEnd
        );

        $this->assertSame(50000.0, $nominal);
    }

    public function test_dinamis_lembur_rate_times_approved_hours(): void
    {
        $komponen = $this->makeKomponen(['id' => 7, 'kode' => 'dinamis_lembur', 'nama' => 'Lembur', 'jenis' => 'dinamis_lembur', 'nilai_default' => 50000]);
        $pegawai = $this->makePegawai();
        $lembur = new Presensi(['jam_masuk' => '18:00:00', 'jam_keluar' => '20:00:00']); // 2 jam
        $lemburByPegawai = collect([$pegawai->id => collect([$lembur])]);

        $periodeEnd = Carbon::create(2026, 8, 31);
        $periodeStart = Carbon::create(2026, 8, 1);

        $nominal = $this->controller()->pubComputeComponentNominal(
            $komponen, $pegawai, new Collection, new Collection, [],
            new Collection, $periodeEnd, $periodeStart, $periodeEnd, $lemburByPegawai
        );

        $this->assertSame(100000.0, $nominal);
    }

    public function test_komponen_applies_only_to_matching_status(): void
    {
        $komponen = $this->makeKomponen(['id' => 8, 'kode' => 'insentif_tetap', 'nama' => 'Insentif Tetap', 'jenis' => 'fixed', 'nilai_default' => 100000, 'applies_to_status_kepegawaian' => 'tetap']);
        $pegawai = $this->makePegawai(['status_kepegawaian' => 'honorer']);
        $periodeEnd = Carbon::create(2026, 8, 31);
        $periodeStart = Carbon::create(2026, 8, 1);

        $nominal = $this->controller()->pubComputeComponentNominal(
            $komponen, $pegawai, new Collection, new Collection, [],
            new Collection, $periodeEnd, $periodeStart, $periodeEnd
        );

        $this->assertSame(0.0, $nominal);
    }

    public function test_jam_mengajar_jp_duration_calculation(): void
    {
        // Jadwal Senin 08:00-09:30 (durasi JP 45 → 2 JP), syarat 'semua'
        $unit = new UnitSekolah(['nama' => 'Unit A', 'durasi_jp' => 45]);
        $unit->id = 1;
        $jadwal = new Jadwal([
            'hari' => 'Senin',
            'jam_mulai' => '08:00:00',
            'jam_selesai' => '09:30:00',
            'jenis_jadwal' => 'mengajar',
        ]);
        $jadwal->setRelation('unitSekolah', $unit);
        $jadwal->id = 1;

        $pegawai = $this->makePegawai();
        $pegawai->setRelation('jadwals', collect([$jadwal]));

        $komponen = $this->makeKomponen(['id' => 9, 'kode' => 'honor_mengajar', 'nama' => 'Honor Mengajar', 'jenis' => 'dinamis_jam_mengajar', 'nilai_default' => 50000, 'syarat_bayar_jam_mengajar' => 'semua']);

        $periodeStart = Carbon::create(2026, 8, 1);
        $periodeEnd = Carbon::create(2026, 8, 31);

        // 5 Senin × 2 JP = 10 JP × 50000 = 500000
        $nominal = $this->controller()->pubComputeComponentNominal(
            $komponen, $pegawai, new Collection, new Collection, [],
            new Collection, $periodeEnd, $periodeStart, $periodeEnd
        );

        $this->assertSame(500000.0, $nominal);
    }
}
