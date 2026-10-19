<?php

namespace App\Http\Controllers;

use App\Helpers\ApprovalHelper;
use App\Helpers\NotificationHelper;
use App\Models\PengajuanPulangCepat;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use App\Notifications\StatusPulangCepat;
use App\Services\PulangAwalDetector;
use App\Services\PulangCepatRealisasi;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PengajuanPulangCepatController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        if (! $user || (! $user->can('view_izin') && ! $user->isApprover())) {
            abort(403, 'Akses ditolak.');
        }

        $query = PengajuanPulangCepat::with([
            'pegawai' => fn ($q) => $q->select('id', 'user_id', 'nama_lengkap', 'nuptk'),
            'approver:id,name',
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

        $pengajuans = $query->orderByDesc('created_at')->paginate(10)->withQueryString();

        $realisasi = app(PulangCepatRealisasi::class)->forPengajuans($pengajuans->getCollection());

        $pengajuans->getCollection()->transform(function ($item) use ($user, $realisasi) {
            $item->can_act = $this->canDecide($user, $item);
            $item->realisasi = $realisasi[$item->pegawai_id.'|'.$item->tanggal->toDateString()] ?? null;

            return $item;
        });

        return Inertia::render('PulangCepat/Index', [
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

        $request->validate(['catatan_approval' => 'nullable|string|max:500']);

        $pengajuan = DB::transaction(function () use ($id, $user, $request) {
            $pengajuan = PengajuanPulangCepat::with('pegawai')->lockForUpdate()->findOrFail($id);

            if ($user->unit_sekolah_id && ! $user->can('view_all_units')) {
                if (! $pengajuan->pegawai->belongsToUnit($user->unit_sekolah_id)) {
                    abort(403, 'Akses ditolak.');
                }
            }

            if (! $this->canDecide($user, $pengajuan)) {
                abort(403, 'Anda tidak berhak menyetujui pengajuan ini.');
            }

            if ($pengajuan->status !== 'pending') {
                throw ValidationException::withMessages(['error' => 'Pengajuan sudah final.']);
            }

            $pengajuan->status = 'disetujui';
            $pengajuan->approver_id = $user->id;
            $pengajuan->approved_at = now();
            if ($request->filled('catatan_approval')) {
                $pengajuan->catatan_approval = $request->catatan_approval;
            }
            $pengajuan->save();

            // Tandai presensi hari itu sebagai pulang awal yang diizinkan (bila sudah ada,
            // mis. presensi pagi). saveQuietly: approval bukan pemicu notifikasi baru.
            $detector = app(PulangAwalDetector::class);
            $rows = Presensi::where('pegawai_id', $pengajuan->pegawai_id)
                ->whereDate('tanggal', $pengajuan->tanggal)
                ->whereNotNull('jam_keluar')
                ->get();
            foreach ($rows as $row) {
                $detector->apply($row);
                $row->saveQuietly();
            }

            return $pengajuan;
        });

        NotificationHelper::sendSafely($pengajuan->pegawai?->user, new StatusPulangCepat($pengajuan, 'disetujui'));

        return back()->with('message', 'Izin pulang cepat disetujui — pegawai dapat presensi keluar lebih awal.');
    }

    public function reject(Request $request, $id)
    {
        $user = auth()->user();
        if (! $user || (! $user->can('view_izin') && ! $user->isApprover())) {
            abort(403, 'Akses ditolak.');
        }

        $request->validate(['alasan_penolakan' => 'required|string|max:255']);

        $pengajuan = DB::transaction(function () use ($id, $user, $request) {
            $pengajuan = PengajuanPulangCepat::with('pegawai')->lockForUpdate()->findOrFail($id);

            if ($user->unit_sekolah_id && ! $user->can('view_all_units')) {
                if (! $pengajuan->pegawai->belongsToUnit($user->unit_sekolah_id)) {
                    abort(403, 'Akses ditolak.');
                }
            }

            if (! $this->canDecide($user, $pengajuan)) {
                abort(403, 'Anda tidak berhak menolak pengajuan ini.');
            }

            if ($pengajuan->status !== 'pending') {
                throw ValidationException::withMessages(['error' => 'Pengajuan sudah final.']);
            }

            $pengajuan->status = 'ditolak';
            $pengajuan->rejected_by = $user->id;
            $pengajuan->rejected_at = now();
            $pengajuan->alasan_penolakan = $request->alasan_penolakan;
            $pengajuan->save();

            return $pengajuan;
        });

        NotificationHelper::sendSafely($pengajuan->pegawai?->user,
            new StatusPulangCepat($pengajuan, 'ditolak', $request->alasan_penolakan));

        return back()->with('message', 'Pengajuan izin pulang cepat ditolak.');
    }

    public function print(Request $request, $id)
    {
        $user = auth()->user();
        abort_unless($user, 401);

        $pengajuan = PengajuanPulangCepat::with([
            'pegawai.units',
            'approver:id,name',
        ])->findOrFail($id);

        $unitId = optional($pengajuan->pegawai->units()->wherePivot('is_primary', true)->first()
            ?? $pengajuan->pegawai->units()->first())?->id;

        $isOwner = $pengajuan->pegawai?->user_id === $user->id;
        $isStaff = $user->hasRole('superadmin') || $user->can('view_presensi')
            || ApprovalHelper::isUnitHead($user, $unitId);
        abort_unless($isOwner || $isStaff, 403, 'Akses ditolak.');

        $kopUnit = $pengajuan->pegawai->units->firstWhere('pivot.is_primary', true)
            ?? $pengajuan->pegawai->units->first();
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

        $pdf = Pdf::loadView('exports.pdf-pulang-cepat', [
            'pengajuan' => $pengajuan,
            'kop' => $kop,
            'logoPath' => $logoPath,
            'logoWidth' => $logoWidth,
        ])->setPaper('A4', 'portrait');

        $nama = 'Izin_Pulang_Cepat_'.str_replace('/', '-', $pengajuan->nomor).'.pdf';

        // unduh=1 -> attachment (mobile: file masuk Downloads). Tanpa itu tetap inline untuk cetak.
        return $request->boolean('unduh') ? $pdf->download($nama) : $pdf->stream($nama);
    }

    private function canDecide(User $user, PengajuanPulangCepat $pengajuan): bool
    {
        if ($user->hasRole('superadmin')) {
            return true;
        }

        if ($pengajuan->approver_id === $user->id) {
            return true;
        }

        $unitId = optional($pengajuan->pegawai->units()->wherePivot('is_primary', true)->first()
            ?? $pengajuan->pegawai->units()->first())?->id;

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
