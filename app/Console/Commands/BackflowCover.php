<?php

namespace App\Console\Commands;

use App\Models\Jadwal;
use App\Models\Pegawai;
use App\Models\Presensi;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;

class BackflowCover extends Command
{
    /**
     * Backfill auto-cover JP lanjutan untuk honorer flow A pada tanggal tertentu.
     *
     * Hanya insert JP setelah anchor yang HADIR/TELAT dalam sesi mengajar
     * (pegawai_mapel_id + kelas_label + unit_sekolah_id + hari sama, gap <= 60 menit).
     * Forward-only: JP sebelum anchor TIDAK di-cover (tetap alpa di histori).
     *
     * --execute menulis ke DB; tanpa flag = dry-run (default aman).
     */
    protected $signature = 'presensi:backflow-cover
        {--date= : Tanggal target (Y-m-d). Default: hari ini.}
        {--execute : Tulis ke DB (tanpa flag = dry-run).}';

    protected $description = 'Backfill cover JP lanjutan honorer flow A pada tanggal tertentu (default dry-run).';

    private const HONORER_KODE = 'GTTY';

    private const GAP_SESI_MENIT = 60;

    public function handle(): int
    {
        $tanggal = $this->option('date') ?: Carbon::now()->toDateString();
        $execute = (bool) $this->option('execute');
        $hari = $this->hariIndonesia(Carbon::parse($tanggal));

        $this->info("Backflow cover: tanggal={$tanggal} hari={$hari} mode=".($execute ? 'EXECUTE' : 'DRY-RUN'));

        $jadwals = Jadwal::query()
            ->where('hari', $hari)
            ->where('jenis_jadwal', 'mengajar')
            ->whereHas('pegawai', fn ($q) => $q->where('status_kepegawaian', self::HONORER_KODE))
            ->with('pegawai')
            ->orderBy('pegawai_id')
            ->orderBy('jam_mulai')
            ->get();

        if ($jadwals->isEmpty()) {
            $this->info('Tidak ada jadwal mengajar honorer pada tanggal tsb.');

            return self::SUCCESS;
        }

        $byPegawai = $jadwals->groupBy('pegawai_id');
        $totalCovered = 0;
        $totalSkipped = 0;

        foreach ($byPegawai as $pegawaiId => $listJadwal) {
            /** @var Pegawai $pegawai */
            $pegawai = $listJadwal->first()->pegawai;
            $this->line("Pegawai #{$pegawai->id} {$pegawai->nama} ({$listJadwal->count()} JP)");

            // JP yang sudah punya row (anchor ATAU cover) — skip iterasi berikutnya.
            $processedJadwalIds = [];
            foreach ($listJadwal as $anchor) {
                if (isset($processedJadwalIds[$anchor->id])) {
                    continue;
                }

                $anchorRow = Presensi::where('pegawai_id', $pegawai->id)
                    ->where('tanggal', $tanggal)
                    ->where('jadwal_id', $anchor->id)
                    ->whereNotNull('jam_masuk')
                    ->first();

                if (! $anchorRow) {
                    continue; // JP ini bukan anchor (no jam_masuk = alpa/izin)
                }

                $chain = $this->buildForwardChain($listJadwal, $anchor);
                foreach ($chain as $jp) {
                    $processedJadwalIds[$jp->id] = true;
                }

                $anchorCapt = substr((string) $anchorRow->jam_masuk, 0, 8);

                foreach ($chain as $jp) {
                    if ($jp->id === $anchor->id) {
                        continue;
                    }
                    $existing = Presensi::where('pegawai_id', $pegawai->id)
                        ->where('tanggal', $tanggal)
                        ->where('jadwal_id', $jp->id)
                        ->exists();
                    if ($existing) {
                        $totalSkipped++;

                        continue;
                    }

                    $keterangan = sprintf('auto-cover dari slide JP #%d pukul %s', $anchor->id, $anchorCapt);
                    $this->line("  + JP #{$jp->id} {$jp->jam_mulai}-{$jp->jam_selesai} (anchor={$anchor->id}) -> hadir");

                    if ($execute) {
                        try {
                            $row = new Presensi;
                            $row->pegawai_id = $pegawai->id;
                            $row->jadwal_id = $jp->id;
                            $row->unit_sekolah_id = $jp->unit_sekolah_id;
                            $row->tanggal = $tanggal;
                            $row->tipe_presensi = 'mengajar';
                            $row->jam_masuk = $jp->jam_mulai;
                            $row->jam_keluar = $jp->jam_selesai;
                            $row->status = 'hadir';
                            $row->is_lembur = false;
                            $row->keterangan = $keterangan;
                            $row->save();
                            $totalCovered++;
                        } catch (UniqueConstraintViolationException) {
                            $totalSkipped++;
                        }
                    } else {
                        $totalCovered++;
                    }
                }
            }
        }

        $this->info('Selesai. '.($execute ? 'Inserted' : 'Planned').": {$totalCovered} rows, skipped (existing): {$totalSkipped}.");

        return self::SUCCESS;
    }

    /**
     * Bangun chain forward dari anchor JP (gap <= GAP_SESI_MENIT,
     * unit+kelas+mapel sama). Anchor included di posisi pertama.
     */
    private function buildForwardChain($jadwals, Jadwal $anchor)
    {
        $sesi = $jadwals->filter(fn ($j) => $j->unit_sekolah_id === $anchor->unit_sekolah_id
            && $j->kelas_label === $anchor->kelas_label
            && $j->pegawai_mapel_id === $anchor->pegawai_mapel_id)
            ->sortBy('jam_mulai')
            ->values();

        $anchorIdx = $sesi->search(fn ($j) => $j->id === $anchor->id);
        if ($anchorIdx === false) {
            return collect([$anchor]);
        }

        $chain = collect([$sesi[$anchorIdx]]);
        for ($i = $anchorIdx + 1; $i < $sesi->count(); $i++) {
            $prev = $sesi[$i - 1];
            $cur = $sesi[$i];
            if ($this->toMinutes($cur->jam_mulai) - $this->toMinutes($prev->jam_selesai) <= self::GAP_SESI_MENIT) {
                $chain->push($cur);
            } else {
                break;
            }
        }

        return $chain;
    }

    private function toMinutes(?string $hms): int
    {
        if (! $hms) {
            return 0;
        }
        $parts = explode(':', $hms);

        return ((int) ($parts[0] ?? 0)) * 60 + ((int) ($parts[1] ?? 0));
    }

    private function hariIndonesia(Carbon $date): string
    {
        return ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'][$date->format('l')] ?? 'Senin';
    }
}
