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

        if (! Storage::disk($disk)->exists($fullPath)) {
            abort(404);
        }

        $user = $request->user();

        if (! $user) {
            abort(401);
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
        if (! str_contains($fullPath, $prefix)) {
            abort(403, 'Akses ditolak.');
        }

        return Storage::disk($disk)->response($fullPath);
    }
}
