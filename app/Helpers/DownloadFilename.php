<?php

namespace App\Helpers;

use App\Models\Pegawai;
use App\Models\UnitSekolah;
use Illuminate\Support\Str;

class DownloadFilename
{
    public static function person(string $prefix, Pegawai|string $pegawai, string $periode, string $ext = 'pdf'): string
    {
        $nama = $pegawai instanceof Pegawai ? $pegawai->nama_lengkap : $pegawai;

        return self::build($prefix, self::safe($nama, 'Pegawai'), $periode, $ext);
    }

    public static function collection(string $prefix, ?UnitSekolah $unit, string $periode, string $ext = 'xlsx'): string
    {
        $nama = $unit?->singkatan ?: $unit?->nama ?: 'Semua_Unit';

        return self::build($prefix, self::safe($nama, 'Semua_Unit'), $periode, $ext);
    }

    public static function safe(string $value, string $fallback = 'File'): string
    {
        $value = trim((string) Str::ascii($value));
        $value = preg_replace('/[^A-Za-z0-9]+/', '_', $value) ?: '';

        return trim($value, '_') ?: $fallback;
    }

    private static function build(string $prefix, string $subject, string $periode, string $ext): string
    {
        $periode = self::safePeriod($periode);

        return $prefix.'_'.$subject.'_'.$periode.'.'.ltrim($ext, '.');
    }

    private static function safePeriod(string $value): string
    {
        $value = trim((string) Str::ascii($value));
        $value = preg_replace('/[^A-Za-z0-9_-]+/', '_', $value) ?: '';

        return trim($value, '_') ?: 'periode';
    }
}
