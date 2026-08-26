<?php

namespace App\Http\Controllers;

use App\Helpers\ApprovalHelper;
use App\Helpers\NotificationHelper;
use App\Helpers\PayrollLockHelper;
use App\Models\AuditPresensi;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\Presensi;
use App\Models\User;
use App\Notifications\IzinBaru;
use App\Notifications\StatusIzin;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PengajuanIzinController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        if (! $user || (! $user->can('view_izin') && ! $user->isApprover())) {
            abort(403, 'Akses ditolak.');
        }

        $query = PengajuanIzin::with([
            'pegawai' => fn ($q) => $q->with(['pengajuanIzins' => fn ($iq) => $iq->select('id', 'pegawai_id', 'jenis_izin', 'status', 'tanggal_mulai', 'tanggal_selesai')]),
            'approverL1' => fn ($q) => $q->select('id', 'name'),
            'approverL2' => fn ($q) => $q->select('id', 'name'),
            'rejectedByUser' => fn ($q) => $q->select('id', 'name'),
        ]);
        $tab = $request->input('tab', 'semua');

        if ($tab === 'l1') {
            $headUnitIds = ApprovalHelper::headUnitIds($user);
            $query->where(function ($q) use ($user, $headUnitIds) {
                $q->where('approver_l1_id', $user->id);
                if ($user->hasRole('superadmin')) {
                    $q->orWhereNull('approver_l1_id');
                }
                if ($headUnitIds) {
                    $q->orWhereHas('pegawai', function ($pq) use ($headUnitIds) {
                        $pq->whereHas('units', function ($uq) use ($headUnitIds) {
                            $uq->where('is_primary', 1)->whereIn('unit_sekolah.id', $headUnitIds);
                        });
                    });
                }
            })->where('approval_stage', 'pending_l1');
        } elseif ($tab === 'l2') {
            $query->where('approver_l2_id', $user->id)
                ->where('approval_stage', 'pending_l2');
        }

        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $query->whereHas('pegawai', function ($q) use ($user) {
                $q->forUnit($user->unit_sekolah_id);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('pegawai', function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                    // NIK ter-enkripsi — LIKE tidak akan match. Lookup via nik_hash (normalisasi sama dgn model).
                    // ?? '' utk hindari where('nik_hash', null) yang jadi IS NULL di Laravel.
                    ->orWhere('nik_hash', Pegawai::nikHash($search) ?? '');
            });
        }

        if ($request->filled('status') && $request->status !== 'semua') {
            $query->where('status', $request->status);
        }

        if ($request->filled('tanggal')) {
            $query->whereDate('tanggal_mulai', '<=', $request->tanggal)
                ->whereDate('tanggal_selesai', '>=', $request->tanggal);
        }

        // Filter jenis pegawai (Dapodik): pendidik = punya jabatan guru, kependidikan = tidak.
        if ($request->filled('jenis_filter') && in_array($request->jenis_filter, ['pendidik', 'kependidikan'], true)) {
            $query->whereHas('pegawai', fn ($q) => $request->jenis_filter === 'pendidik' ? $q->guru() : $q->nonGuru());
        }

        $stats = [
            'total' => (clone $query)->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'selesai' => (clone $query)->whereIn('status', ['disetujui', 'ditolak'])->count(),
        ];

        $pengajuans = $query->orderBy('created_at', 'desc')->paginate(10)->withQueryString();

        $pengajuans->getCollection()->transform(function ($item) use ($user) {
            $item->can_act = $this->canActOnItem($item, $user);
            if ($item->pegawai) {
                $item->pegawai->append(['sisa_cuti', 'cuti_terpakai']);
            }

            return $item;
        });

        return Inertia::render('PengajuanIzin/Index', [
            'pengajuans' => $pengajuans,
            'filters' => $request->only(['search', 'status', 'tanggal', 'tab', 'jenis_filter']),
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
            'dihitung_hadir_kcd' => 'nullable|boolean',
        ]);

        $pengajuan = DB::transaction(function () use ($id, $user, $request) {
            $pengajuan = PengajuanIzin::with('pegawai')->lockForUpdate()->findOrFail($id);

            if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
                if (! $pengajuan->pegawai->belongsToUnit($user->unit_sekolah_id)) {
                    abort(403, 'Akses ditolak.');
                }
            }

            if (in_array($pengajuan->approval_stage, ['approved', 'rejected'])) {
                throw ValidationException::withMessages(['error' => 'Pengajuan sudah final.']);
            }

            $isSuperadmin = $user->hasRole('superadmin');
            $recordUnit = optional($pengajuan->pegawai->units()->wherePivot('is_primary', true)->first()
                ?? $pengajuan->pegawai->units()->first())?->id;
            $isL1 = $isSuperadmin || $pengajuan->approver_l1_id === $user->id || ApprovalHelper::isUnitHead($user, $recordUnit);
            $isL2 = $isSuperadmin || $pengajuan->approver_l2_id === $user->id || ApprovalHelper::isUnitHead($user, $recordUnit);

            if ($pengajuan->approval_stage === 'pending_l1') {
                if (! $isL1) {
                    abort(403, 'Anda bukan atasan L1 untuk pengajuan ini.');
                }

                // Superadmin approve → overwrite approver_l1 ke superadmin
                // (karena superadmin yang benar-benar approve, bukan atasan asli)
                if ($isSuperadmin) {
                    $pengajuan->approver_l1_id = $user->id;
                }
                $pengajuan->approved_at_l1 = now();
                if ($request->filled('catatan_approval')) {
                    $pengajuan->catatan_approval = $request->catatan_approval;
                }

                if ($pengajuan->approver_l2_id && $pengajuan->approver_l2_id !== $pengajuan->approver_l1_id) {
                    $pengajuan->approval_stage = 'pending_l2';
                } else {
                    $pengajuan->approval_stage = 'approved';
                    $pengajuan->status = 'disetujui';
                    $this->generatePresensi($pengajuan);
                }

                $pengajuan->save();
            } elseif ($pengajuan->approval_stage === 'pending_l2') {
                if (! $isL2) {
                    abort(403, 'Anda bukan atasan L2 untuk pengajuan ini.');
                }

                // Superadmin approve → overwrite approver_l2 ke superadmin
                if ($isSuperadmin) {
                    $pengajuan->approver_l2_id = $user->id;
                }
                $pengajuan->approval_stage = 'approved';
                $pengajuan->status = 'disetujui';
                $pengajuan->approved_at_l2 = now();
                if ($request->filled('catatan_approval')) {
                    $pengajuan->catatan_approval = $request->catatan_approval;
                }
                $this->generatePresensi($pengajuan);
                $pengajuan->save();
            }

            if ($request->filled('dihitung_hadir_kcd')) {
                $pengajuan->dihitung_hadir_kcd = $request->boolean('dihitung_hadir_kcd');
                $pengajuan->save();
            }

            return $pengajuan;
        });

        if ($pengajuan->status === 'disetujui') {
            NotificationHelper::sendSafely($pengajuan->pegawai?->user, new StatusIzin($pengajuan, 'disetujui'));
        }

        if ($pengajuan->approval_stage === 'pending_l2' && $pengajuan->approver_l2_id) {
            NotificationHelper::sendSafely(User::find($pengajuan->approver_l2_id), new IzinBaru($pengajuan));
        }

        $msg = $pengajuan->approval_stage === 'pending_l2'
            ? 'Pengajuan telah disetujui L1 dan diteruskan ke atasan L2.'
            : 'Pengajuan berhasil disetujui dan data absensi telah di-generate.';

        return back()->with('message', $msg);
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

        $pengajuan = DB::transaction(function () use ($id, $user, $request) {
            $pengajuan = PengajuanIzin::with('pegawai')->lockForUpdate()->findOrFail($id);

            if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
                if (! $pengajuan->pegawai->belongsToUnit($user->unit_sekolah_id)) {
                    abort(403, 'Akses ditolak.');
                }
            }

            if (in_array($pengajuan->approval_stage, ['approved', 'rejected'])) {
                throw ValidationException::withMessages(['error' => 'Pengajuan sudah final.']);
            }

            $isSuperadmin = $user->hasRole('superadmin');
            $recordUnit = optional($pengajuan->pegawai->units()->wherePivot('is_primary', true)->first()
                ?? $pengajuan->pegawai->units()->first())?->id;
            $isL1 = $isSuperadmin || $pengajuan->approver_l1_id === $user->id || ApprovalHelper::isUnitHead($user, $recordUnit);
            $isL2 = $isSuperadmin || $pengajuan->approver_l2_id === $user->id || ApprovalHelper::isUnitHead($user, $recordUnit);

            if (! $isL1 && ! $isL2) {
                abort(403, 'Anda tidak berhak menolak pengajuan ini.');
            }

            $pengajuan->status = 'ditolak';
            $pengajuan->approval_stage = 'rejected';
            $pengajuan->alasan_penolakan = $request->alasan_penolakan;
            $pengajuan->rejected_by = $user->id;
            $pengajuan->save();

            return $pengajuan;
        });

        NotificationHelper::sendSafely($pengajuan->pegawai?->user, new StatusIzin($pengajuan, 'ditolak', $request->alasan_penolakan));

        return back()->with('message', 'Pengajuan berhasil ditolak.');
    }

    private function generatePresensi(PengajuanIzin $pengajuan): void
    {
        $pegawai = $pengajuan->pegawai;
        $primaryUnit = $pegawai?->units()->wherePivot('is_primary', true)->first()
            ?? $pegawai?->units()->first();
        $unitId = $primaryUnit?->id;

        $period = CarbonPeriod::create($pengajuan->tanggal_mulai, $pengajuan->tanggal_selesai);
        foreach ($period as $date) {
            if ($date->isWeekend()) {
                continue;
            }
            // ponytail: pegawai tanpa unit tetap bisa diapprove tanpa 500 NOT NULL
            if (! $unitId) {
                continue;
            }
            if (PayrollLockHelper::isPeriodLocked($pengajuan->pegawai_id, $date)) {
                continue;
            }
            $presensi = Presensi::updateOrCreate(
                [
                    'pegawai_id' => $pengajuan->pegawai_id,
                    'tanggal' => $date->format('Y-m-d'),
                ],
                [
                    'unit_sekolah_id' => $unitId,
                    'keterangan' => 'Dari Pengajuan Izin/Cuti',
                ]
            );

            $presensi->status = $pengajuan->jenis_izin;
            $presensi->save();

            AuditPresensi::log($presensi->id, 'generate_izin', 'status', null, $pengajuan->jenis_izin,
                "Izin {$pengajuan->jenis_izin} {$date->format('Y-m-d')} (pengajuan #{$pengajuan->id})"
            );
        }
    }

    private function canActOnItem(PengajuanIzin $item, User $user): bool
    {
        if (! in_array($item->approval_stage, ['pending_l1', 'pending_l2'], true)) {
            return false;
        }

        if ($user->hasRole('superadmin')) {
            return true;
        }

        $unit = optional($item->pegawai->units()->wherePivot('is_primary', true)->first()
            ?? $item->pegawai->units()->first())?->id;

        return $item->approver_l1_id === $user->id
            || $item->approver_l2_id === $user->id
            || ApprovalHelper::isUnitHead($user, $unit);
    }
}
