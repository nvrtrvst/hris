<?php

namespace App\Services;

use App\Http\Controllers\Concerns\ScopesPimpinan;
use App\Models\User;
use Illuminate\Support\Collection;

class LaporanTindakLanjutAccess
{
    use ScopesPimpinan;

    public function canManage(User $user): bool
    {
        return $user->can('view_presensi') && ($user->can('manage_tindak_lanjut_laporan') || ($user->hasRole('pimpinan') && $this->isKepsek($user)));
    }

    public function inUnit(User $user, int $unitId): bool
    {
        if ($user->can('view_all_units')) {
            return true;
        }
        if ($user->unit_sekolah_id) {
            return (int) $user->unit_sekolah_id === $unitId;
        }

        return (bool) $user->pegawai?->units->contains('id', $unitId);
    }

    public function candidates(int $unitId): Collection
    {
        return User::where(function ($q) use ($unitId) {
            $q->where('unit_sekolah_id', $unitId)->orWhereHas('pegawai.units', fn ($q) => $q->where('unit_sekolah.id', $unitId));
        })->with(['roles.permissions', 'permissions', 'pegawai.units', 'pegawai.jabatans'])->get()
            ->filter(fn ($u) => $this->canManage($u) && $this->inUnit($u, $unitId))
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values();
    }
}
