<?php

namespace App\Http\Controllers;

use App\Helpers\ApprovalHelper;
use App\Helpers\NotificationHelper;
use App\Models\AuditPresensi;
use App\Models\PengajuanKoreksi;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use App\Notifications\StatusKoreksi;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PengajuanKoreksiController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        if (! $user || (! $user->can('view_izin') && ! $user->isApprover())) {
            abort(403, 'Akses ditolak.');
        }

        $query = PengajuanKoreksi::with([
            'pegawai' => fn ($q) => $q->select('id', 'user_id', 'nama_lengkap', 'nip'),
            'presensi' => fn ($q) => $q->select('id', 'pegawai_id', 'tanggal', 'jam_masuk', 'jam_keluar', 'unit_sekolah_id'),
            'presensi.unitSekolah' => fn ($q) => $q->select('id', 'nama'),
            'approver:id,name',
            'rejectedByUser:id,name',
        ]);

        if ($user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $query->whereHas('pegawai', fn ($q) => $q->forUnit($user->unit_sekolah_id));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('pegawai', fn ($q) => $q->where('nama_lengkap', 'like', "%{$search}%"));
        }

        if ($request->filled('status') && $request->status !== 'semua') {
            $query->where('status', $request->status);
        }

        $stats = [
            'total' => (clone $query)->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'selesai' => (clone $query)->whereIn('status', ['disetujui', 'ditolak'])->count(),
        ];

        $pengajuans = $query->orderBy('created_at', 'desc')->paginate(10)->withQueryString();

        $pengajuans->getCollection()->transform(function ($item) use ($user) {
            $item->can_act = $this->canDecide($user, $item);

            return $item;
        });

        return Inertia::render('Koreksi/Index', [
            'pengajuans' => $pengajuans,
            'filters' => $request->only(['search', 'status']),
            'stats' => $stats,
        ]);
    }

    public function approve(Request $request, $id)
    {
        $user = auth()->user();
        if (! $user || (! $user->can('view_izin') && ! $user->isApprover())) {
            abort(403, 'Akses ditolak.');
        }

        $request->validate([
            'catatan_approval' => 'nullable|string|max:500',
        ]);

        $koreksi = DB::transaction(function () use ($id, $user, $request) {
            $koreksi = PengajuanKoreksi::with('pegawai')->lockForUpdate()->findOrFail($id);

            if ($user->unit_sekolah_id && ! $user->can('view_all_units')) {
                if (! $koreksi->pegawai->belongsToUnit($user->unit_sekolah_id)) {
                    abort(403, 'Akses ditolak.');
                }
            }

            if (! $this->canDecide($user, $koreksi)) {
                abort(403, 'Anda tidak berhak menyetujui pengajuan ini.');
            }

            if ($koreksi->status !== 'pending') {
                throw ValidationException::withMessages(['error' => 'Pengajuan sudah final.']);
            }

            $presensi = Presensi::lockForUpdate()->findOrFail($koreksi->presensi_id);
            $nilaiLama = $presensi->jam_keluar;
            // NilaiBaru format H:i (input); samakan format kolom time HH:MM:SS.
            $presensi->jam_keluar = date('H:i:s', strtotime($koreksi->nilai_baru));
            $presensi->save();

            $koreksi->status = 'disetujui';
            $koreksi->approver_id = $user->id;
            $koreksi->approved_at = now();
            if ($request->filled('catatan_approval')) {
                $koreksi->catatan_approval = $request->catatan_approval;
            }
            $koreksi->save();

            AuditPresensi::log($presensi->id, 'koreksi_jam_keluar', 'jam_keluar', $nilaiLama, $koreksi->nilai_baru,
                "Koreksi {$koreksi->nomor} — {$koreksi->alasan}"
            );

            return $koreksi;
        });

        NotificationHelper::sendSafely($koreksi->pegawai?->user, new StatusKoreksi($koreksi, 'disetujui'));

        return back()->with('message', 'Koreksi disetujui — jam keluar presensi telah diperbarui.');
    }

    public function reject(Request $request, $id)
    {
        $user = auth()->user();
        if (! $user || (! $user->can('view_izin') && ! $user->isApprover())) {
            abort(403, 'Akses ditolak.');
        }

        $request->validate([
            'alasan_penolakan' => 'required|string|max:255',
        ]);

        $koreksi = DB::transaction(function () use ($id, $user, $request) {
            $koreksi = PengajuanKoreksi::with('pegawai')->lockForUpdate()->findOrFail($id);

            if ($user->unit_sekolah_id && ! $user->can('view_all_units')) {
                if (! $koreksi->pegawai->belongsToUnit($user->unit_sekolah_id)) {
                    abort(403, 'Akses ditolak.');
                }
            }

            if (! $this->canDecide($user, $koreksi)) {
                abort(403, 'Anda tidak berhak menolak pengajuan ini.');
            }

            if ($koreksi->status !== 'pending') {
                throw ValidationException::withMessages(['error' => 'Pengajuan sudah final.']);
            }

            $koreksi->status = 'ditolak';
            $koreksi->rejected_by = $user->id;
            $koreksi->rejected_at = now();
            $koreksi->alasan_penolakan = $request->alasan_penolakan;
            $koreksi->save();

            return $koreksi;
        });

        NotificationHelper::sendSafely($koreksi->pegawai?->user,
            new StatusKoreksi($koreksi, 'ditolak', $request->alasan_penolakan));

        return back()->with('message', 'Pengajuan koreksi ditolak.');
    }

    /**
     * Cetak formulir (1 pengajuan = 1 lembar A4). Dipakai route desktop DAN
     * mobile (guard-agnostic): authorization cek user terhadap pengajuan.
     */
    public function print($id)
    {
        $user = auth()->user();
        abort_unless($user, 401);

        $koreksi = PengajuanKoreksi::with([
            'pegawai.units',
            'presensi.unitSekolah',
            'approver:id,name',
            'rejectedByUser:id,name',
        ])->findOrFail($id);

        $unitId = optional($koreksi->pegawai->units()->wherePivot('is_primary', true)->first()
            ?? $koreksi->pegawai->units()->first())?->id;

        $isOwner = $koreksi->pegawai?->user_id === $user->id;
        $isStaff = $user->hasRole('superadmin') || $user->can('view_presensi')
            || ApprovalHelper::isUnitHead($user, $unitId);
        abort_unless($isOwner || $isStaff, 403, 'Akses ditolak.');

        $kopUnit = $koreksi->presensi?->unitSekolah;
        $logoPath = $this->resolveLogoPath($kopUnit) ?? $this->resolveYayasanLogoPath();
        $logoWidth = null;
        if ($logoPath && file_exists($logoPath)) {
            $sz = @getimagesize($logoPath);
            if ($sz) {
                $logoWidth = (int) round(64 * $sz[0] / $sz[1]);
            }
        }

        $kop = [
            'name' => $kopUnit?->nama ? strtoupper($kopUnit->nama) : config('yayasan.name'),
            'tagline' => config('yayasan.tagline'),
            'address' => $kopUnit?->alamat ?: config('yayasan.address'),
            'phone' => $kopUnit?->telepon ?: config('yayasan.phone'),
            'email' => config('yayasan.email'),
            'website' => $kopUnit?->web ?: config('yayasan.website'),
        ];

        $pdf = Pdf::loadView('exports.pdf-koreksi-presensi', [
            'koreksi' => $koreksi,
            'kop' => $kop,
            'logoPath' => $logoPath,
            'logoWidth' => $logoWidth,
        ])->setPaper('A4', 'portrait');

        // Nomor berisi '/' — tidak sah sebagai nama file HTTP disposition.
        return $pdf->stream('Koreksi_Presensi_'.str_replace('/', '-', $koreksi->nomor).'.pdf');
    }

    private function canDecide(User $user, PengajuanKoreksi $koreksi): bool
    {
        if ($user->hasRole('superadmin')) {
            return true;
        }

        if ($koreksi->approver_id === $user->id) {
            return true;
        }

        $unitId = optional($koreksi->pegawai->units()->wherePivot('is_primary', true)->first()
            ?? $koreksi->pegawai->units()->first())?->id;

        return ApprovalHelper::isUnitHead($user, $unitId);
    }

    private function resolveLogoPath(?UnitSekolah $unit): ?string
    {
        if (! $unit?->logo) {
            return null;
        }

        $disk = config('filesystems.image_disk', 'public');
        $root = config("filesystems.disks.$disk.root");
        $path = rtrim($root, '/').'/'.ltrim($unit->logo, '/');

        return file_exists($path) ? $path : null;
    }

    private function resolveYayasanLogoPath(): ?string
    {
        $rel = config('kcd.yayasan_logo');
        if (! $rel) {
            return null;
        }

        $public = public_path($rel);
        if (file_exists($public)) {
            return $public;
        }

        $disk = config('filesystems.image_disk', 'public');
        $root = config("filesystems.disks.$disk.root");
        $path = rtrim($root, '/').'/'.ltrim($rel, '/');

        return file_exists($path) ? $path : null;
    }
}
