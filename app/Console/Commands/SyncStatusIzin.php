<?php

namespace App\Console\Commands;

use App\Models\PengajuanIzin;
use App\Models\Penggajian;
use App\Models\Presensi;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;

class SyncStatusIzin extends Command
{
    protected $signature = 'presensi:sync-status-from-izin
                            {--dry-run : Jangan tulis perubahan, hanya laporan}
                            {--date-from= : Batas tanggal mulai (Y-m-d)}
                            {--date-to= : Batas tanggal akhir (Y-m-d)}';

    protected $description = 'Sinkronisasi status presensi dari pengajuan izin yang sudah disetujui';

    private array $autoFix = [];

    private array $manualReview = [];

    private int $totalChecked = 0;

    private int $totalMismatch = 0;

    public function handle(): int
    {
        $approved = PengajuanIzin::with('pegawai')
            ->where('approval_stage', 'approved')
            ->where('status', 'disetujui')
            ->get()
            ->filter(function ($p) {
                if ($from = $this->option('date-from')) {
                    if ($p->tanggal_selesai < $from) {
                        return false;
                    }
                }
                if ($to = $this->option('date-to')) {
                    if ($p->tanggal_mulai > $to) {
                        return false;
                    }
                }

                return true;
            });

        if ($approved->isEmpty()) {
            $this->info('Tidak ada pengajuan izin yang sudah disetujui.');
            $this->line('Tidak perlu sinkronisasi.');

            return Command::SUCCESS;
        }

        $this->newLine();
        $this->warn('Mode: '.($this->option('dry-run') ? 'DRY-RUN (tidak ada perubahan)' : 'EKSEKUSI (akan menulis perubahan)'));
        $this->newLine();

        foreach ($approved as $p) {
            $pegawaiNama = $p->pegawai?->nama_lengkap ?? "Pegawai #{$p->pegawai_id}";
            $period = CarbonPeriod::create($p->tanggal_mulai, $p->tanggal_selesai);

            foreach ($period as $date) {
                if ($date->isWeekend()) {
                    continue;
                }

                $this->totalChecked++;
                $presensi = Presensi::where('pegawai_id', $p->pegawai_id)
                    ->where('tanggal', $date->format('Y-m-d'))
                    ->first();

                $statusSkrg = $presensi?->status;
                $statusHrs = $p->jenis_izin;

                if (! $presensi) {
                    continue;
                }
                if ($statusSkrg === $statusHrs) {
                    continue;
                }

                $this->totalMismatch++;
                $periode = $date->format('m-Y');
                $payroll = Penggajian::where('pegawai_id', $p->pegawai_id)
                    ->where('periode_bulan', $periode)
                    ->first();
                $payrollStatus = $payroll?->status ?? 'none';

                if (! $payroll || $payrollStatus === 'draft') {
                    $this->autoFix[] = [
                        'pegawai' => $pegawaiNama,
                        'tanggal' => $date->format('Y-m-d'),
                        'status_skrg' => $statusSkrg,
                        'status_hrs' => $statusHrs,
                        'payroll_status' => $payrollStatus,
                    ];
                    if (! $this->option('dry-run')) {
                        $presensi->status = $statusHrs;
                        $presensi->save();
                    }
                } else {
                    $this->manualReview[] = [
                        'pegawai' => $pegawaiNama,
                        'tanggal' => $date->format('Y-m-d'),
                        'status_skrg' => $statusSkrg,
                        'status_hrs' => $statusHrs,
                        'payroll_status' => $payrollStatus,
                    ];
                }
            }
        }

        $this->showReport();

        return Command::SUCCESS;
    }

    private function showReport(): void
    {
        $this->line(str_repeat('-', 70));
        $this->info('LAPORAN SINKRONISASI STATUS IZIN');
        $this->line(str_repeat('-', 70));
        $this->line("Total diperiksa:  {$this->totalChecked}");
        $this->line("Total mismatch:  {$this->totalMismatch}");
        $this->line('Auto-fix (draft/no payroll): '.count($this->autoFix));
        $this->line('Manual review (finalized/paid): '.count($this->manualReview));
        $this->newLine();

        if ($this->autoFix) {
            $this->warn('--- AUTO-FIX ('.count($this->autoFix).' record) ---');
            $this->line(sprintf('  %-25s %-12s %-10s %-10s %s', 'Pegawai', 'Tanggal', 'Sekarang', 'Seharusnya', 'Payroll'));
            foreach ($this->autoFix as $r) {
                $this->line(sprintf('  %-25s %-12s %-10s %-10s %s',
                    $r['pegawai'], $r['tanggal'], $r['status_skrg'] ?? 'NULL', $r['status_hrs'], $r['payroll_status']));
            }
        }

        if ($this->manualReview) {
            $this->newLine();
            $this->error('--- REVIEW MANUAL ('.count($this->manualReview).' record) ---');
            $this->line('Record berikut TIDAK diubah karena payroll sudah finalized/paid:');
            $this->line(sprintf('  %-25s %-12s %-10s %-10s %s', 'Pegawai', 'Tanggal', 'Sekarang', 'Seharusnya', 'Payroll'));
            foreach ($this->manualReview as $r) {
                $this->line(sprintf('  %-25s %-12s %-10s %-10s %s',
                    $r['pegawai'], $r['tanggal'], $r['status_skrg'] ?? 'NULL', $r['status_hrs'], $r['payroll_status']));
            }
        }

        if ($this->totalMismatch === 0) {
            $this->info('Semua record presensi sudah sesuai dengan izin yang disetujui.');
        }

        $this->newLine();
        if ($this->option('dry-run')) {
            $this->warn('DRY-RUN: Tidak ada perubahan yang ditulis ke database.');
            $this->line('Jalankan tanpa --dry-run untuk eksekusi perubahan.');
        } else {
            $this->info('Perubahan telah ditulis ke database.');
        }

        if ($this->manualReview) {
            $this->newLine();
            $this->line('CLEANUP (jika ingin hapus data test):');
            $this->line('  DELETE FROM presensi WHERE keterangan = "Dari Pengajuan Izin/Cuti";');
            $this->line('  -- Tapi pastikan hanya data test yang kena, bukan data real.');
        }
    }
}
