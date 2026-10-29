<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Klien API internal aplikasi keuangan (sumber data kelas & siswa).
 * Kunci API hanya disimpan server-side.
 */
class KeuanganClient
{
    public static function request(string $endpoint, array $payload, bool $post = false): ?Response
    {
        $base = rtrim((string) config('keuangan.url'), '/');
        $key = (string) config('keuangan.key');
        if ($base === '' || $key === '' || $key === 'change-me-in-production') {
            Log::error('Integrasi keuangan belum dikonfigurasi.');

            return null;
        }

        $request = Http::acceptJson()
            ->withHeaders(['x-internal-key' => $key])
            ->connectTimeout(2)
            ->timeout($post ? 15 : 8);

        try {
            return $post
                ? $request->post($base.'/api/integration/'.$endpoint, $payload)
                : $request->get($base.'/api/integration/'.$endpoint, $payload);
        } catch (ConnectionException) {
            Log::warning('Aplikasi keuangan tidak dapat dihubungi.', ['endpoint' => $endpoint]);

            return null;
        }
    }

    public static function classLabel(array $class): string
    {
        $base = trim(($class['grade'] ?? '').' '.($class['name'] ?? ''));
        $section = trim((string) ($class['section'] ?? ''));

        if ($section !== '' && ! in_array($section, preg_split('/\s+/', $base) ?: [], true)) {
            $base .= ' '.$section;
        }

        return trim($base);
    }

    /**
     * Daftar kelas aktif satu unit dari aplikasi keuangan.
     * Null = integrasi gagal / belum dikonfigurasi.
     *
     * @return array<int, array{id:mixed,grade?:string,name?:string,section?:string,major_name?:string}>|null
     */
    public static function kelasByUnit(string $unitNama): ?array
    {
        $response = self::request('kelas-by-unit', ['unit' => $unitNama]);

        if (! $response?->successful()) {
            return null;
        }

        return $response->json('data.classes') ?? [];
    }
}
