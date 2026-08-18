<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Pegawai;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Scope & otorisasi role "pimpinan" (pengawas read-only: Kepala Sekolah,
 * Kepala TU, Ketua Yayasan, dll). Role ini hanya boleh melihat data
 * BAWAHAN LANGSUNG (atasan_langsung_id = user), tanpa kekuasaan mutasi.
 *
 * Dipakai bersama: PegawaiController, PresensiController, JadwalController,
 * PenggajianController (dan sejenisnya).
 */
trait ScopesPimpinan
{
    /**
     * True jika user ber-role pimpinan murni (tanpa admin_unit / view_all_units).
     */
    private function isPimpinanReadOnly(?User $user): bool
    {
        return $user
            && $user->hasRole('pimpinan')
            && ! $user->hasRole('admin_unit')
            && ! $user->can('view_all_units');
    }

    /**
     * Scope query ke bawahan LANGSUNG user pimpinan (via atasan_langsung_id).
     * Dipanggil hanya saat isPimpinanReadOnly() true.
     */
    private function scopePimpinanBawahan(Builder $query, ?User $user): void
    {
        $userPegawaiId = $user?->pegawai?->id;

        if (! $userPegawaiId) {
            $query->whereRaw('1 = 0');
        } else {
            $query->whereHas('pegawai', function ($q) use ($userPegawaiId) {
                $q->where('atasan_langsung_id', $userPegawaiId);
            });
        }
    }

    /**
     * Kandidat atasan untuk dropdown form (superadmin bebas, admin_unit/pimpinan
     * dibatasi ke pegawai unitnya).
     */
    private function atasanCandidates(?User $user)
    {
        $query = Pegawai::query()
            ->with(['units:id,nama,singkatan', 'jabatans:id,nama'])
            ->where('status_aktif', 'aktif')
            ->orderBy('nama_lengkap');

        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $query->whereHas('units', fn ($q) => $q->where('unit_sekolah.id', $user->unit_sekolah_id));
        }

        return $query->get(['id', 'nama_lengkap']);
    }
}
