<?php

namespace App\Console\Commands;

use App\Models\HariLibur;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\Presensi;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FinalizeAlpa extends Command
{
    /**
     * Tandai pegawai aktif yang tidak hadir sebagai alpa (H+1, Senin-Jumat).
     * Wajib hadir berlaku untuk semua pegawai aktif (biarpun tak punya jadwal).
     */
    protected $signature = 'presensi:finalize-alpa
        {--date= : Target date YYYY-MM-DD (default: kemarin/H+1)}
        {--days= : Backfill N hari terakhir (berakhir kemarin)}';

    protected $description = 'Tandai pegawai aktif tanpa kehadiran sebagai alpa';

    public function handle(): int
    {
        $targets = $this->resolveTargets();
        $pegawais = Pegawai::with('jadwals')->where('status_aktif', 'aktif')->get();
        $hariIndo = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];

        $marked = 0;
        $skipped = 0;

        foreach ($pegawais as $pegawai) {
            $unitIds = $pegawai->units->pluck('id')->filter()->unique()->toArray();

            foreach ($targets as $target) {
                if (! $target->isWeekday()) {
                    continue;
                }
                $tanggal = $target->format('Y-m-d');

                // Hari libur (nasional unit-null atau unit terkait) -> lewati.
                if (HariLibur::where('tanggal', $tanggal)
                    ->where(function ($q) use ($unitIds) {
                        $q->whereNull('unit_sekolah_id');
                        if ($unitIds) {
                            $q->orWhereIn('unit_sekolah_id', $unitIds);
                        }
                    })
                    ->exists()
                ) {
                    $skipped++;

                    continue;
                }

                // Izin/cuti/sakit disetujui yang cover tanggal ini -> lewati.
                if (PengajuanIzin::where('pegawai_id', $pegawai->id)
                    ->where('status', 'disetujui')
                    ->where('tanggal_mulai', '<=', $tanggal)
                    ->where('tanggal_selesai', '>=', $tanggal)
                    ->exists()
                ) {
                    $skipped++;

                    continue;
                }

                $hariTarget = $hariIndo[$target->format('l')];

                // Tugas luar/kantor (hadir utuh) -> jangan tandai alpa sama sekali.
                $hasTugas = Presensi::where('pegawai_id', $pegawai->id)
                    ->where('tanggal', $tanggal)
                    ->whereIn('tipe_presensi', ['tugas_luar', 'tugas_kantor'])
                    ->where('status', '!=', 'alpa')
                    ->exists();
                if ($hasTugas) {
                    $skipped++;

                    continue;
                }

                $presentAny = Presensi::where('pegawai_id', $pegawai->id)
                    ->where('tanggal', $tanggal)
                    ->where('status', '!=', 'alpa')
                    ->exists();

                if ($presentAny) {
                    // Sudah hadir -> tandai alpa per jadwal yang tak di-tap.
                    foreach ($pegawai->jadwals->filter(fn ($j) => $j->hari === $hariTarget) as $jadwal) {
                        $jadwalPresent = Presensi::where('pegawai_id', $pegawai->id)
                            ->where('jadwal_id', $jadwal->id)
                            ->where('tanggal', $tanggal)
                            ->where('status', '!=', 'alpa')
                            ->exists();
                        if ($jadwalPresent) {
                            continue;
                        }
                        if (! $jadwal->unit_sekolah_id) {
                            continue;
                        }
                        $row = Presensi::firstOrCreate(
                            ['pegawai_id' => $pegawai->id, 'jadwal_id' => $jadwal->id, 'tanggal' => $tanggal],
                            ['unit_sekolah_id' => $jadwal->unit_sekolah_id, 'tipe_presensi' => 'mengajar', 'keterangan' => 'Auto-mark alpa (mengajar)'],
                        );
                        if ($row->wasRecentlyCreated) {
                            $row->status = 'alpa';
                            $row->save();
                            $marked++;
                        }
                    }

                    continue;
                }

                // Tak ada kehadiran sama sekali -> alpa kehadiran (kantor).
                $unitId = $this->resolveUnitId($pegawai);
                if (! $unitId) {
                    // Benar-benar tanpa unit di mana pun -> lewati.
                    $skipped++;

                    continue;
                }
                $row = Presensi::firstOrCreate(
                    ['pegawai_id' => $pegawai->id, 'jadwal_id' => null, 'tipe_presensi' => 'kantor', 'tanggal' => $tanggal],
                    ['unit_sekolah_id' => $unitId, 'keterangan' => 'Auto-mark alpa (kehadiran)'],
                );
                if ($row->wasRecentlyCreated) {
                    $row->status = 'alpa';
                    $row->save();
                    $marked++;
                } else {
                    $skipped++;
                }
            }
        }

        $this->info("Selesai. {$marked} alpa di-mark, {$skipped} dilewati.");

        return self::SUCCESS;
    }

    /**
     * Cari unit_sekolah_id pegawai secara robust: baca FK langsung dari pivot
     * pegawai_unit (hindari scope soft-delete di relasi units()), lalu fallback
     * ke unit_sekolah_id milik User. Null hanya jika benar-benar tak ada unit.
     */
    private function resolveUnitId(Pegawai $pegawai): ?int
    {
        $fromPivot = DB::table('pegawai_unit')
            ->where('pegawai_id', $pegawai->id)
            ->orderByDesc('is_primary')
            ->value('unit_sekolah_id');

        if ($fromPivot) {
            return (int) $fromPivot;
        }

        return $pegawai->user?->unit_sekolah_id;
    }

    private function resolveTargets(): array
    {
        if ($this->option('date')) {
            return [Carbon::parse($this->option('date'))];
        }
        if ($this->option('days')) {
            $n = max(1, (int) $this->option('days'));
            $targets = [];
            for ($i = $n - 1; $i >= 0; $i--) {
                $targets[] = Carbon::yesterday()->subDays($i);
            }

            return $targets;
        }

        return [Carbon::yesterday()];
    }
}
