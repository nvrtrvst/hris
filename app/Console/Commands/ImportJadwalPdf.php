<?php

namespace App\Console\Commands;

use App\Models\Jadwal;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\PegawaiMapel;
use App\Models\UnitSekolah;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportJadwalPdf extends Command
{
    protected $signature = 'import:jadwal-pdf {file : Path to JSON file from extract_jadwal.py} {--unit= : Unit sekolah} {--delete : Delete existing jadwal for this unit before import}';

    protected $description = 'Import jadwal pelajaran from extracted PDF JSON';

    public function handle(): int
    {
        $path = $this->argument('file');
        $unitName = $this->option('unit');
        $deleteExisting = $this->option('delete');

        if (! file_exists($path)) {
            $this->error("File not found: {$path}");

            return 1;
        }

        $data = json_decode(file_get_contents($path), true);
        if (! $data || ! isset($data['jadwal'])) {
            $this->error('Invalid JSON format');

            return 1;
        }

        $unitId = null;
        if ($unitName) {
            $unit = UnitSekolah::where('nama', $unitName)->orWhere('singkatan', $unitName)->first();
            if (! $unit) {
                $this->error("Unit '{$unitName}' not found.");

                return 1;
            }
            $unitId = $unit->id;
            $this->info("Unit: {$unit->nama} (id={$unitId})");
        }

        $tahunAjaran = $data['tahun_ajaran'] ?? '2026/2027';
        $semester = $data['semester'] ?? 1;
        $entries = $data['jadwal'];

        $this->info('Entries: '.count($entries));
        $this->info("Tahun Ajaran: {$tahunAjaran}, Semester: {$semester}");

        // ── Prefetch lookup data ──
        $pegawaiByName = Pegawai::select('id', 'nama_lengkap')->get()->keyBy('nama_lengkap');
        $mapelByNama = MataPelajaran::select('id', 'nama')
            ->when($unitId, fn ($q) => $q->where('unit_sekolah_id', $unitId)->orWhereNull('unit_sekolah_id'))
            ->get()
            ->keyBy('nama');
        $pmlPairs = PegawaiMapel::select('id', 'pegawai_id', 'mata_pelajaran_id')
            ->when($unitId, fn ($q) => $q->where('unit_sekolah_id', $unitId))
            ->get()
            ->keyBy(fn ($r) => "{$r->pegawai_id}_{$r->mata_pelajaran_id}");

        // ── Delete existing if requested ──
        if ($deleteExisting && $unitId) {
            $deleted = Jadwal::where('unit_sekolah_id', $unitId)
                ->where('tahun_ajaran', $tahunAjaran)
                ->where('semester', $semester)
                ->delete();
            $this->info("Deleted {$deleted} existing jadwal for this unit/periode");
        }

        // ── Process entries ──
        $created = 0;
        $skipped = 0;
        $failures = [];

        DB::transaction(function () use ($entries, $unitId, $tahunAjaran, $semester, $pegawaiByName, $mapelByNama, $pmlPairs, &$created, &$skipped, &$failures) {
            foreach ($entries as $idx => $entry) {
                $row = $idx + 2;

                // Resolve Pegawai
                $pegawaiId = $this->resolvePegawaiId($entry['guru'], $pegawaiByName);
                if (! $pegawaiId) {
                    $failures[] = "Row {$row}: Pegawai not found for '{$entry['guru']}'";

                    continue;
                }

                // Resolve MataPelajaran
                $mapel = $mapelByNama[$entry['mapel']] ?? null;
                if (! $mapel) {
                    $failures[] = "Row {$row}: Mapel not found for '{$entry['mapel']}'";

                    continue;
                }

                // Resolve PegawaiMapel
                $pmlKey = "{$pegawaiId}_{$mapel->id}";
                $pml = $pmlPairs[$pmlKey] ?? null;
                if (! $pml) {
                    // Auto-create pegawai_mapel
                    $pml = PegawaiMapel::create([
                        'pegawai_id' => $pegawaiId,
                        'mata_pelajaran_id' => $mapel->id,
                        'unit_sekolah_id' => $unitId,
                    ]);
                    $pmlPairs[$pmlKey] = $pml;
                }

                // Check duplicate
                $exists = Jadwal::where('pegawai_id', $pegawaiId)
                    ->where('kelas_label', $entry['kelas'])
                    ->where('hari', $entry['hari'])
                    ->where('jam_mulai', $entry['jam_mulai'])
                    ->where('tahun_ajaran', $tahunAjaran)
                    ->where('semester', $semester)
                    ->exists();

                if ($exists) {
                    $skipped++;

                    continue;
                }

                Jadwal::create([
                    'pegawai_id' => $pegawaiId,
                    'unit_sekolah_id' => $unitId,
                    'kelas_label' => $entry['kelas'],
                    'pegawai_mapel_id' => $pml->id,
                    'hari' => $entry['hari'],
                    'jam_mulai' => $this->normalizeTime($entry['jam_mulai']),
                    'jam_selesai' => $this->normalizeTime($entry['jam_selesai']),
                    'jenis_jadwal' => 'mengajar',
                    'tahun_ajaran' => $tahunAjaran,
                    'semester' => $semester,
                ]);
                $created++;
            }
        });

        // ── Report ──
        $this->newLine();
        $this->info('=== Import Selesai ===');
        $this->info("Jadwal created:  {$created}");
        $this->info("Skipped:         {$skipped} (duplikat)");
        $this->info('Failed:          '.count($failures));

        if (! empty($failures)) {
            $this->newLine();
            $this->error('Failed entries:');
            foreach (array_slice($failures, 0, 30) as $f) {
                $this->error("  {$f}");
            }
            if (count($failures) > 30) {
                $this->error('  ... and '.(count($failures) - 30).' more');
            }
        }

        return 0;
    }

    private function normalizeTime(string $time): string
    {
        // "09:30" -> "09:30:00"
        $parts = explode(':', $time);
        if (count($parts) === 2) {
            return $time.':00';
        }

        return $time;
    }

    private function resolvePegawaiId(string $name, $pegawaiByName): ?int
    {
        if (isset($pegawaiByName[$name])) {
            return $pegawaiByName[$name]->id;
        }

        foreach ($pegawaiByName as $p) {
            if (stripos($p->nama_lengkap, $name) !== false || stripos($name, $p->nama_lengkap) !== false) {
                return $p->id;
            }
        }

        $clean = preg_replace('/,?\s*(S\.Pd\.?|S\.Kom\.?|S\.Ag\.?|S\.Si\.?|SE\.?|ST\.?|M\.Pd\.?|M\.Kom\.?|M\.MPd\.?|A\.Md\.Kom\.?|S\.S\.?|S\.A\.?.*)$/iu', '', trim($name));
        $clean = trim($clean);
        if ($clean !== '' && $clean !== $name) {
            foreach ($pegawaiByName as $p) {
                if (stripos($p->nama_lengkap, $clean) !== false) {
                    return $p->id;
                }
            }
        }

        return null;
    }
}
