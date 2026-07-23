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
