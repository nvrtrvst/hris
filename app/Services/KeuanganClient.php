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
}
