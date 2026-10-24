<?php

namespace App\Services;

use App\Constants\KpiProfiles;
use App\Models\Jabatan;
use App\Models\KpiTemplate;
use Illuminate\Support\Facades\DB;

class KpiTemplateDefaults
{
    public function ensureForJabatan(Jabatan $jabatan): KpiTemplate
    {
        $profile = KpiProfiles::forJabatan($jabatan->nama, (bool) $jabatan->is_guru, (bool) $jabatan->is_supervisor);

        return DB::transaction(function () use ($jabatan, $profile) {
            $template = KpiTemplate::firstOrCreate(
                ['jabatan_id' => $jabatan->id, 'unit_sekolah_id' => null],
                ['nama' => $jabatan->nama.' — KPI '.$profile['label'], 'is_active' => true],
            );

            if (! $template->indicators()->exists()) {
                foreach ($profile['indicators'] as $urutan => [$kode, $nama, $bobot, $jenis, $sourceKey, $target, $arah]) {
                    $template->indicators()->create([
                        'kode' => $kode,
                        'nama' => $nama,
                        'jenis' => $jenis,
                        'sumber' => $sourceKey ? 'system' : 'manual',
                        'source_key' => $sourceKey,
                        'target' => $target,
                        'arah' => $arah,
                        'bobot' => $bobot,
                        'satuan' => $sourceKey || ($jenis === 'numeric' && (float) $target === 100.0) ? '%' : null,
                        'rubrik' => $jenis === 'rubric' ? [
                            1 => 'Belum memenuhi standar',
                            2 => 'Memenuhi sebagian standar',
                            3 => 'Memenuhi target',
                            4 => 'Melampaui target secara konsisten',
                        ] : null,
                        'wajib_bukti' => $sourceKey === null,
                        'urutan' => $urutan,
                        'is_active' => true,
                    ]);
                }
            }

            return $template;
        });
    }
}
