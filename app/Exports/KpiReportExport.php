<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class KpiReportExport implements FromCollection, WithHeadings, WithMapping
{
    use FormulaEscapable;

    public function __construct(private readonly Collection $rows) {}

    public function collection(): Collection
    {
        return $this->rows->flatMap(function (array $row) {
            $items = $row['evaluation_items'] ?? [];
            if ($items === []) {
                $items = [[
                    'nama' => '-', 'target' => null, 'aktual' => null, 'bobot' => null,
                    'nilai' => null, 'bukti' => null, 'catatan' => null,
                    'tidak_berlaku' => false, 'alasan_tidak_berlaku' => null,
                    'level_rubrik' => null, 'rubrik_snapshot' => [],
                ]];
            }

            return collect($items)->map(fn ($item) => [
                'pegawai' => $row['pegawai'],
                'jabatan' => $row['jabatan'],
                'unit' => $row['unit'],
                'attendance_rate' => $row['attendance_rate'],
                'punctuality_rate' => $row['punctuality_rate'],
                'hadir_hari' => $row['hadir_hari'],
                'hari_kerja' => $row['hari_kerja'],
                'teaching_jp_rate' => $row['teaching_jp_rate'],
                'teaching_jp_hadir' => $row['teaching_jp_hadir'],
                'teaching_jp_terjadwal' => $row['teaching_jp_terjadwal'],
                'score' => $row['score'],
                'status' => $row['evaluation_status'] ?? 'belum dinilai',
                'ringkasan' => $row['ringkasan'],
                'tanggapan_pegawai' => $row['tanggapan_pegawai'],
                ...$item,
            ]);
        })->values();
    }

    public function headings(): array
    {
        return ['Nama Pegawai', 'Jabatan', 'Unit', 'Kehadiran (%)', 'Tepat Waktu (%)', 'Hari Hadir', 'Hari Kerja', 'JP Mengajar (%)', 'JP Hadir', 'JP Terjadwal', 'Nilai KPI (%)', 'Status Evaluasi', 'Ringkasan Atasan', 'Tanggapan Pegawai', 'Indikator', 'Target', 'Aktual', 'Bobot (%)', 'Level Rubrik', 'Nilai Rubrik', 'Nilai Indikator (%)', 'Bukti', 'Catatan', 'Tidak Berlaku', 'Alasan'];
    }

    public function map($row): array
    {
        return array_map([self::class, 'escapeFormula'], [
            $row['pegawai'], $row['jabatan'], $row['unit'], $row['attendance_rate'], $row['punctuality_rate'],
            $row['hadir_hari'], $row['hari_kerja'], $row['teaching_jp_rate'], $row['teaching_jp_hadir'], $row['teaching_jp_terjadwal'], $row['score'],
            $row['status'], $row['ringkasan'], $row['tanggapan_pegawai'], $row['nama'], $row['target'], $row['aktual'], $row['bobot'], $row['level_rubrik'],
            $row['rubrik_snapshot'][$row['level_rubrik']] ?? null,
            $row['nilai'], $row['bukti'], $row['catatan'], $row['tidak_berlaku'] ? 'Ya' : 'Tidak', $row['alasan_tidak_berlaku'],
        ]);
    }
}
