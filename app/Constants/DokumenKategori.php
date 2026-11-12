<?php

namespace App\Constants;

class DokumenKategori
{
    public const LABELS = [
        'legal' => 'Legal',
        'kelembagaan' => 'Kelembagaan',
        'akreditasi' => 'Akreditasi',
        'keuangan' => 'Keuangan',
        'sdm' => 'SDM',
        'aset' => 'Aset',
        'kerja_sama' => 'Kerja Sama',
        'sop' => 'SOP',
        'lainnya' => 'Lainnya',
    ];

    public static function label(string $key): string
    {
        return self::LABELS[$key] ?? 'Lainnya';
    }

    public static function options(): array
    {
        return collect(self::LABELS)
            ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }
}
