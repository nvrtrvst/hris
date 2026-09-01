<?php

namespace App\Console\Commands;

use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\PegawaiMapel;
use App\Models\UnitSekolah;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportJadwalMap extends Command
{
    protected $signature = 'import:jadwal-map {file : Path to Excel file} {--unit= : Unit sekolah (e.g. SMK, SD, SMP)}';

    protected $description = 'Import jadwal mapping (guru-mapel) into pegawai_mapel';

    public function handle(): int
    {
        $path = $this->argument('file');
        $unitName = $this->option('unit');

        if (! file_exists($path)) {
            $this->error("File not found: {$path}");

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

        $excel = IOFactory::load($path);
        $sheet = $excel->getActiveSheet();

        // ── Phase 1: Read ALL rows + collect unique mapel names ──
        $rows = [];
        $lastTeacherName = '';
        $uniqueMapelNames = [];

        for ($row = 2; $row <= $sheet->getHighestRow(); $row++) {
            $teacherName = trim((string) $sheet->getCellByColumnAndRow(2, $row)->getValue());
            $mapelName = trim((string) $sheet->getCellByColumnAndRow(4, $row)->getValue());

            if (empty($teacherName)) {
                $teacherName = $lastTeacherName;
            } else {
                $lastTeacherName = $teacherName;
            }

            if ($teacherName !== '' && $mapelName !== '') {
                $rows[] = [
                    'row' => $row,
                    'teacher' => $teacherName,
                    'mapel' => $mapelName,
                ];
                $uniqueMapelNames[$mapelName] = true;
            }
        }

        // ── Phase 2: Create ALL MataPelajaran for this unit ──
        $created = 0;
        $alreadyExists = 0;
        $autoCreatedNames = [];

        DB::transaction(function () use (&$created, &$alreadyExists, &$autoCreatedNames, $uniqueMapelNames, $unitId) {
            foreach (array_keys($uniqueMapelNames) as $nama) {
                $exists = MataPelajaran::where('nama', $nama)
                    ->when($unitId, fn ($q) => $q->where('unit_sekolah_id', $unitId))
                    ->lockForUpdate()
                    ->exists();

                if ($exists) {
                    $alreadyExists++;
                } else {
                    MataPelajaran::create([
                        'nama' => $nama,
                        'unit_sekolah_id' => $unitId,
                    ]);
                    $autoCreatedNames[] = $nama;
                    $created++;
                }
            }
        });

        // ── Phase 3: Process pegawai_mapel inserts ──
        $pegawaiByName = Pegawai::select('id', 'nama_lengkap')->get()->keyBy('nama_lengkap');
        $mapelByName = MataPelajaran::select('id', 'nama')
            ->when($unitId, fn ($q) => $q->where('unit_sekolah_id', $unitId)->orWhereNull('unit_sekolah_id'))
            ->get()
            ->keyBy('nama');
        $existingPairs = PegawaiMapel::select('pegawai_id', 'mata_pelajaran_id')
            ->when($unitId, fn ($q) => $q->where('unit_sekolah_id', $unitId))
            ->get()
            ->map(fn ($r) => "{$r->pegawai_id}_{$r->mata_pelajaran_id}")
            ->flip();

        $pending = [];
        $failures = [];
        $skipped = 0;

        foreach ($rows as $item) {
            $pegawaiId = $this->resolvePegawaiId($item['teacher'], $pegawaiByName);
            if (! $pegawaiId) {
                $failures[] = "Row {$item['row']}: Pegawai not found for '{$item['teacher']}'";

                continue;
            }

            $mapelId = $mapelByName[$item['mapel']]->id ?? null;
            if (! $mapelId) {
                $failures[] = "Row {$item['row']}: Mapel not found for '{$item['mapel']}'";

                continue;
            }

            $pairKey = "{$pegawaiId}_{$mapelId}";
            if (isset($existingPairs[$pairKey]) || isset($pending[$pairKey])) {
                $skipped++;

                continue;
            }

            $pending[$pairKey] = [
                'pegawai_id' => $pegawaiId,
                'mata_pelajaran_id' => $mapelId,
            ];
        }

        $pegCreated = 0;

        if (! empty($pending)) {
            DB::transaction(function () use (&$pegCreated, $pending, $unitId) {
                $toInsert = [];
                foreach ($pending as $item) {
                    $duplicate = PegawaiMapel::where('pegawai_id', $item['pegawai_id'])
                        ->where('mata_pelajaran_id', $item['mata_pelajaran_id'])
                        ->when($unitId, fn ($q) => $q->where('unit_sekolah_id', $unitId))
                        ->exists();

                    if (! $duplicate) {
                        $toInsert[] = [
                            'pegawai_id' => $item['pegawai_id'],
                            'mata_pelajaran_id' => $item['mata_pelajaran_id'],
                            'unit_sekolah_id' => $unitId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                foreach (array_chunk($toInsert, 100) as $chunk) {
                    PegawaiMapel::insert($chunk);
                    $pegCreated += count($chunk);
                }
            });
        }

        // ── Report ──
        $this->newLine();
        $this->info('=== Import Selesai ===');
        $this->info("MataPelajaran:  {$created} created, {$alreadyExists} already exist");
        $this->info("PegawaiMapel:   {$pegCreated} created");
        $this->info("Skipped:        {$skipped} (duplikat)");
        $this->info('Failed:         '.count($failures).' (pegawai tidak ditemukan)');

        if (! empty($autoCreatedNames)) {
            $this->newLine();
            $this->info('MataPelajaran baru:');
            foreach ($autoCreatedNames as $name) {
                $this->line("  - {$name}");
            }
        }

        if (! empty($failures)) {
            $this->newLine();
            $this->error('Failed pegawai:');
            foreach ($failures as $f) {
                $this->error("  {$f}");
            }
        }

        return 0;
    }

    private function resolvePegawaiId(string $name, $pegawaiByName): ?int
    {
        if (isset($pegawaiByName[$name])) {
            return $pegawaiByName[$name]->id;
        }

        foreach ($pegawaiByName as $p) {
            if (str_contains($p->nama_lengkap, $name) || str_contains($name, $p->nama_lengkap)) {
                return $p->id;
            }
        }

        $clean = preg_replace('/,?\s*(S\.Pd\.?|S\.Kom\.?|S\.Ag\.?|S\.Si\.?|SE\.?|ST\.?|M\.Pd\.?|M\.Kom\.?|M\.MPd\.?|A\.Md\.Kom\.?|S\.S\.?|S\.A\.?.*)$/iu', '', trim($name));
        $clean = trim($clean);
        if ($clean !== '' && $clean !== $name) {
            foreach ($pegawaiByName as $p) {
                if (str_contains($p->nama_lengkap, $clean)) {
                    return $p->id;
                }
            }
        }

        return null;
    }
}
