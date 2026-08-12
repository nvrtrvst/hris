<?php

namespace App\Http\Controllers;

use App\Models\Pegawai;
use App\Models\PegawaiDokumen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PegawaiDokumenController extends Controller
{
    /**
     * Validasi akses ke dokumen pegawai:
     * - superadmin (view_all_units): boleh semua
     * - admin unit: hanya pegawai di unit-nya
     * - lainnya (staff tanpa scope unit): ditolak
     */
    private function authorizeAccess(Pegawai $pegawai): void
    {
        $user = auth()->user();

        if (! $user) {
            abort(401);
        }

        if ($user->can('view_all_units')) {
            return;
        }

        if ($user->unit_sekolah_id) {
            if (! $pegawai->units->pluck('id')->contains($user->unit_sekolah_id)) {
                abort(403, 'Akses ditolak.');
            }

            return;
        }

        abort(403, 'Akses ditolak.');
    }

    /**
     * Upload dokumen baru (PDF/gambar, maks 5MB). Disimpan private di
     * storage/app/dokumen/... — URL asli tidak pernah diekspos; unduh hanya
     * lewat route terproteksi.
     */
    public function store(Request $request, Pegawai $pegawai)
    {
        $this->authorizeAccess($pegawai);

        $validated = $request->validate([
            'nama_dokumen' => ['required', 'string', 'max:255'],
            'jenis' => ['required', 'string', 'max:100'],
            'keterangan' => ['nullable', 'string', 'max:500'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'mimetypes:application/pdf,image/jpeg,image/png,image/webp', 'max:5120'],
        ]);

        $file = $request->file('file');

        // Nama file UUID + ekstensi dari MIME (bukan dari input user) —
        // cegah path traversal & eksekusi file berbahaya.
        $path = $file->storeAs(
            'dokumen/pegawai_'.$pegawai->id,
            Str::uuid().'.'.$file->guessExtension(),
            'local'
        );

        PegawaiDokumen::create([
            'pegawai_id' => $pegawai->id,
            'nama_dokumen' => $validated['nama_dokumen'],
            'jenis' => $validated['jenis'],
            'file_path' => $path,
            'keterangan' => $validated['keterangan'] ?? null,
        ]);

        return redirect()->back()->with('message', 'Dokumen berhasil diunggah.');
    }

    /**
     * Unduh dokumen (terproteksi auth) dengan nama asli dokumen.
     */
    public function download(Request $request, Pegawai $pegawai, PegawaiDokumen $dokumen)
    {
        $this->authorizeAccess($pegawai);

        abort_unless($dokumen->pegawai_id === $pegawai->id, 404, 'Dokumen tidak ditemukan.');

        $disk = Storage::disk('local');

        abort_unless($disk->exists($dokumen->file_path), 404, 'File dokumen tidak ditemukan.');

        $extension = pathinfo($dokumen->file_path, PATHINFO_EXTENSION);

        return $disk->download($dokumen->file_path, $dokumen->nama_dokumen.'.'.$extension);
    }

    /**
     * Hapus dokumen beserta file fisiknya.
     */
    public function destroy(Request $request, Pegawai $pegawai, PegawaiDokumen $dokumen)
    {
        $this->authorizeAccess($pegawai);

        abort_unless($dokumen->pegawai_id === $pegawai->id, 404, 'Dokumen tidak ditemukan.');

        Storage::disk('local')->delete($dokumen->file_path);

        $dokumen->delete();

        return redirect()->back()->with('message', 'Dokumen berhasil dihapus.');
    }
}
