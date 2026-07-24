<?php

namespace App\Http\Controllers;

use App\Models\PengajuanIzin;
use App\Models\Presensi;
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

        $query = PengajuanIzin::with('pegawai');
        $tab = $request->input('tab', 'semua');

        if ($tab === 'l1') {
            $query->where(function ($q) use ($user) {
                $q->where('approver_l1_id', $user->id);
                if ($user->hasRole('superadmin')) {
                    $q->orWhereNull('approver_l1_id');
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
                    ->orWhere('nik', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'semua') {
            $query->where('status', $request->status);
        }

        if ($request->filled('tanggal')) {
            $query->whereDate('tanggal_mulai', '<=', $request->tanggal)
                ->whereDate('tanggal_selesai', '>=', $request->tanggal);
        }

        $pengajuans = $query->orderBy('created_at', 'desc')->paginate(10)->withQueryString();

        return Inertia::render('PengajuanIzin/Index', [
            'pengajuans' => $pengajuans,
            'filters' => $request->only(['search', 'status', 'tanggal', 'tab']),
        ]);
    }

    public function approve($id)
    {
        $user = auth()->user();
        if (! $user || (! $user->can('view_izin') && ! $user->isApprover())) {
            abort(403, 'Akses ditolak.');
        }

        $pengajuan = DB::transaction(function () use ($id, $user) {
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
            $isL1 = $pengajuan->approver_l1_id === $user->id || $isSuperadmin;
            $isL2 = $pengajuan->approver_l2_id === $user->id || $isSuperadmin;

            if ($pengajuan->approval_stage === 'pending_l1') {
                if (! $isL1) {
                    abort(403, 'Anda bukan atasan L1 untuk pengajuan ini.');
                }
                $updates = ['approved_at_l1' => now()];

                if ($pengajuan->approver_l2_id && $pengajuan->approver_l2_id !== $pengajuan->approver_l1_id) {
                    $updates['approval_stage'] = 'pending_l2';
                } else {
                    $updates['approval_stage'] = 'approved';
                    $updates['status'] = 'disetujui';
                    $this->generatePresensi($pengajuan);
                }

                $pengajuan->update($updates);
            } elseif ($pengajuan->approval_stage === 'pending_l2') {
                if (! $isL2) {
                    abort(403, 'Anda bukan atasan L2 untuk pengajuan ini.');
                }
                $pengajuan->update([
                    'approval_stage' => 'approved',
                    'status' => 'disetujui',
                    'approved_at_l2' => now(),
                ]);
                $this->generatePresensi($pengajuan);
            }

            return $pengajuan;
        });

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

        DB::transaction(function () use ($id, $user, $request) {
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
            $isL1 = $pengajuan->approver_l1_id === $user->id || $isSuperadmin;
            $isL2 = $pengajuan->approver_l2_id === $user->id || $isSuperadmin;

            if (! $isL1 && ! $isL2) {
                abort(403, 'Anda tidak berhak menolak pengajuan ini.');
            }

            $pengajuan->update([
                'status' => 'ditolak',
                'approval_stage' => 'rejected',
                'alasan_penolakan' => $request->alasan_penolakan,
                'rejected_by' => $user->id,
            ]);
        });

        return back()->with('message', 'Pengajuan berhasil ditolak.');
    }

    private function generatePresensi(PengajuanIzin $pengajuan): void
    {
        $period = CarbonPeriod::create($pengajuan->tanggal_mulai, $pengajuan->tanggal_selesai);
        foreach ($period as $date) {
            if ($date->isWeekend()) {
                continue;
            }
            Presensi::updateOrCreate(
                [
                    'pegawai_id' => $pengajuan->pegawai_id,
                    'tanggal' => $date->format('Y-m-d'),
                ],
                [
                    'status' => $pengajuan->jenis_izin,
                    'keterangan' => 'Dari Pengajuan Izin/Cuti',
                ]
            );
        }
    }
}
