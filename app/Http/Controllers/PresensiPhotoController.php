<?php

namespace App\Http\Controllers;

use App\Models\Pegawai;
use Illuminate\Http\Request;
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

        if (! Storage::disk($disk)->exists($fullPath)) {
            abort(404);
        }

        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        // Foto profil milik user yang sedang login (admin unit & pegawai) →
        // izinkan langsung tanpa regex path (foto pegawai disimpan tanpa prefix {id}_).
        if ($user->pegawai && $fullPath === $user->pegawai->foto) {
            return Storage::disk($disk)->response($fullPath);
        }

        if ($user->can('view_presensi')) {
            // Admin unit: scope foto ke pegawai unitnya sendiri (anti-IDOR).
            // Nama file berformat {folder}/{pegawai_id}_{slug}/{uuid}.webp
            if ($user->unit_sekolah_id && ! $user->can('view_all_units')) {
                if (! preg_match('/(\d+)_/', $fullPath, $m)) {
                    abort(403, 'Akses ditolak.');
                }

                $pegawai = Pegawai::find((int) $m[1]);
                if (! $pegawai || ! $pegawai->belongsToUnit($user->unit_sekolah_id)) {
                    abort(403, 'Akses ditolak.');
                }
            }

            return Storage::disk($disk)->response($fullPath);
        }

        $pegawai = Pegawai::where('user_id', $user->id)->first();
        if (! $pegawai) {
            abort(403, 'Akses ditolak.');
        }

        $prefix = '/'.$pegawai->id.'_';
        if ($fullPath !== $pegawai->foto && ! str_contains($fullPath, $prefix)) {
            abort(403, 'Akses ditolak.');
        }

        return Storage::disk($disk)->response($fullPath);
    }
}
