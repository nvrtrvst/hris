<?php

namespace App\Http\Controllers;

use App\Models\Pegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PresensiPhotoController extends Controller
{
    public function show(Request $request, string $path)
    {
        $disk = config('filesystems.presensi_disk', 'presensi');
        $fullPath = ltrim($path, '/');

        // Defense-in-depth: tolak path traversal sebelum cek kepemilikan mana pun.
        if (str_contains($fullPath, '..')) {
            abort(403, 'Path tidak valid.');
        }

        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        // Otorisasi dulu (403), baru cek eksistensi file — jangan bocorkan
        // keberadaan file lewat 404 (anti-IDOR: unit lain tetap 403 walau file ada/tidak).
        $allowed = false;
        $reason = 'no-match';

        // Foto profil milik user yang sedang login (admin unit & pegawai) → izinkan.
        if ($user->pegawai && $fullPath === $user->pegawai->foto) {
            $allowed = true;
            $reason = 'own-profile';
        } elseif ($user->can('view_presensi')) {
            // Admin unit: scope foto ke pegawai unitnya sendiri (anti-IDOR).
            // Nama file berformat {folder}/{pegawai_id}_{slug}/{uuid}.webp
            if (! $user->unit_sekolah_id || $user->can('view_all_units')) {
                $allowed = true;
                $reason = 'view-all-units';
            } elseif (preg_match('/(\d+)_/', $fullPath, $m)) {
                $pegawai = Pegawai::find((int) $m[1]);
                $allowed = $pegawai && $pegawai->belongsToUnit($user->unit_sekolah_id);
                $reason = $allowed ? 'unit-scope-ok' : sprintf('unit-scope-fail:peg=%d', (int) $m[1]);
            } else {
                $reason = 'no-pegawai-id-in-path';
            }
        } else {
            $pegawai = Pegawai::where('user_id', $user->id)->first();
            if ($pegawai && ($fullPath === $pegawai->foto || str_contains($fullPath, '/'.$pegawai->id.'_'))) {
                $allowed = true;
                $reason = 'own-file';
            } else {
                $reason = 'pegawai-no-match';
            }
        }

        if (! $allowed) {
            // Logging observability: kenapa ditolak — bantu debug unit scope drift tanpa bocor info.
            Log::info('presensi-photo denied', [
                'user_id' => $user->id,
                'unit_sekolah_id' => $user->unit_sekolah_id,
                'reason' => $reason,
                'path' => $fullPath,
            ]);
            abort(403, 'Akses ditolak.');
        }

        if (! Storage::disk($disk)->exists($fullPath)) {
            abort(404);
        }

        // Cache-Control: no-store agar FE tidak cache gambar yang sebelumnya 403/404.
        $response = Storage::disk($disk)->response($fullPath);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
