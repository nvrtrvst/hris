<?php

namespace App\Http\Controllers;

use App\Constants\PresensiMessages;
use App\Helpers\ApprovalHelper;
use App\Helpers\NotificationHelper;
use App\Models\AuditLog;
use App\Models\PengajuanIzin;
use App\Models\PengajuanIzinComment;
use App\Models\User;
use App\Notifications\IzinReply;
use App\Services\PengajuanIzinService;
use App\Traits\ResolvesPegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class MobileIzinController extends Controller
{
    use ResolvesPegawai;

    public function index(Request $request)
    {
        $pegawai = $this->getPegawai();
        if (! $pegawai) {
            abort(403, 'Akses ditolak.');
        }

        $request->validate([
            'status' => 'nullable|in:semua,pending,disetujui,ditolak,dibatalkan',
            'jenis' => 'nullable|in:sakit,izin,cuti',
            'bulan' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        // Filter jenis & bulan dipakai untuk daftar DAN hitungan chip status.
        $base = PengajuanIzin::where('pegawai_id', $pegawai->id)
            ->when($request->filled('jenis'), fn ($q) => $q->where('jenis_izin', $request->jenis))
            ->when($request->filled('bulan'), function ($q) use ($request) {
                [$tahun, $bulan] = explode('-', $request->bulan);
                $q->whereYear('tanggal_mulai', (int) $tahun)->whereMonth('tanggal_mulai', (int) $bulan);
            });

        $stats = [
            'total' => (clone $base)->count(),
            'pending' => (clone $base)->where('status', 'pending')->count(),
            'disetujui' => (clone $base)->where('status', 'disetujui')->count(),
            'ditolak' => (clone $base)->where('status', 'ditolak')->count(),
            'dibatalkan' => (clone $base)->where('status', 'dibatalkan')->count(),
        ];

        $pengajuan = (clone $base)
            ->when($request->filled('status') && $request->status !== 'semua', fn ($q) => $q->where('status', $request->status))
            ->with(['approverL1' => fn ($q) => $q->select('id', 'name')])
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        // Pill "sisa cuti" — 1 query eksplisit (invariant MobileCutiSharedTest).
        $pegawai->loadCutiInfo();

        return Inertia::render('Mobile/Izin/Index', [
            'pengajuan' => $pengajuan,
            'filters' => [
                'status' => $request->filled('status') ? $request->status : 'semua',
                'jenis' => $request->filled('jenis') ? $request->jenis : '',
                'bulan' => $request->filled('bulan') ? $request->bulan : '',
            ],
            'stats' => $stats,
            'pegawai' => $pegawai,
        ]);
    }

    public function show(PengajuanIzin $pengajuan)
    {
        $pegawai = $this->getPegawai();
        if (! $pegawai || $pengajuan->pegawai_id !== $pegawai->id) {
            abort(404);
        }

        $pengajuan->load([
            'pegawai',
            'approverL1' => fn ($q) => $q->select('id', 'name'),
            'approverL2' => fn ($q) => $q->select('id', 'name'),
            'rejectedByUser' => fn ($q) => $q->select('id', 'name'),
        ]);

        return Inertia::render('Mobile/Izin/Show', [
            'pengajuan' => $pengajuan,
        ]);
    }

    public function create()
    {
        $pegawai = $this->getPegawai();
        if (! $pegawai) {
            abort(403, 'Akses ditolak.');
        }

        // Create.jsx menampilkan sisa_cuti — eager-load + append eksplisit (P2).
        $pegawai->loadCutiInfo();

        return Inertia::render('Mobile/Izin/Create', [
            'pegawai' => $pegawai,
        ]);
    }

    public function store(Request $request)
    {
        $pegawai = $this->getPegawai();
        if (! $pegawai) {
            abort(403, 'Akses ditolak.');
        }

        $request->validate([
            'jenis_izin' => 'required|in:sakit,izin,cuti',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'alasan' => 'required|string',
            'bukti_foto' => ['required_if:jenis_izin,sakit', 'nullable', 'string', 'max:'.PresensiMessages::MAX_FOTO_BASE64, 'regex:/^data:image\/\w+;base64,/'],
            'dengan_surat_dokter' => ['nullable', 'boolean'],
        ], [
            'bukti_foto.required_if' => 'Bukti wajib diunggah untuk pengajuan sakit.',
        ]);

        $result = PengajuanIzinService::createPending(
            $pegawai,
            [
                'jenis_izin' => $request->jenis_izin,
                'tanggal_mulai' => $request->tanggal_mulai,
                'tanggal_selesai' => $request->tanggal_selesai,
                'alasan' => $request->alasan,
                'dengan_surat_dokter' => $request->boolean('dengan_surat_dokter'),
            ],
            $request->input('bukti_foto'),
            true,
            'pwa',
            $request->user()?->id,
        );

        PengajuanIzinService::notifyCreated($result['pengajuan'], $pegawai, $result['approvers']);

        $pesan = $result['pengajuan']->status === 'disetujui'
            ? 'Pengajuan berhasil dikirim.'
            : 'Pengajuan berhasil dikirim dan menunggu persetujuan.';

        return redirect()->route('presensi.izin.index')->with('message', $pesan);
    }

    public function cancel(PengajuanIzin $pengajuan)
    {
        $pegawai = $this->getPegawai();
        if (! $pegawai || $pengajuan->pegawai_id !== $pegawai->id) {
            abort(404);
        }

        $user = $pegawai->user;
        if (! $user) {
            abort(403, 'Akses ditolak.');
        }

        [$pengajuan, $comment, $stage] = DB::transaction(function () use ($pengajuan, $pegawai, $user) {
            $pengajuan = PengajuanIzin::with('pegawai')->lockForUpdate()->findOrFail($pengajuan->id);

            if ($pengajuan->pegawai_id !== $pegawai->id) {
                abort(404);
            }

            if ($pengajuan->status !== 'pending'
                || ! in_array($pengajuan->approval_stage, ['pending_l1', 'pending_l2', 'pending_superadmin'], true)) {
                throw ValidationException::withMessages([
                    'error' => 'Pengajuan sudah diproses dan tidak bisa dibatalkan.',
                ]);
            }

            $stage = $pengajuan->approval_stage;
            $pengajuan->forceFill([
                'status' => 'dibatalkan',
                'approval_stage' => 'cancelled',
                'cancelled_at' => now(),
            ])->save();

            AuditLog::log($pengajuan, 'batalkan', [
                'status' => 'pending',
                'approval_stage' => $stage,
            ], [
                'status' => 'dibatalkan',
                'approval_stage' => 'cancelled',
                'cancelled_at' => $pengajuan->cancelled_at?->toISOString(),
            ]);

            $comment = PengajuanIzinComment::create([
                'pengajuan_izin_id' => $pengajuan->id,
                'user_id' => $user->id,
                'message' => 'Pengajuan dibatalkan oleh pegawai.',
            ]);

            return [$pengajuan, $comment, $stage];
        });

        $superadminIds = fn () => User::whereHas('roles', fn ($q) => $q->where('name', 'superadmin'))->pluck('id');

        $recipientIds = collect([$pengajuan->approver_l1_id, $pengajuan->approver_l2_id])
            ->filter()
            ->reject(fn ($id) => (int) $id === (int) $user->id);

        if ($stage === 'pending_superadmin') {
            $recipientIds = $recipientIds->merge($superadminIds());
        }

        if ($recipientIds->isEmpty()) {
            $recipientIds = $superadminIds()
                ->reject(fn ($id) => (int) $id === (int) $user->id);
        }

        $recipientIds = $recipientIds->unique()->values();

        if ($recipientIds->isNotEmpty()) {
            User::whereIn('id', $recipientIds)->get()
                ->each(fn (User $recipient) => NotificationHelper::sendSafely($recipient, new IzinReply($pengajuan, $comment, $user)));
        }

        ApprovalHelper::sendTembusanIzin($pengajuan, 'dibatalkan', array_filter([$pengajuan->approver_l1_id, $pengajuan->approver_l2_id]));

        return back()->with('message', 'Pengajuan berhasil dibatalkan.');
    }

    public function comments(PengajuanIzin $pengajuan)
    {
        $pegawai = $this->getPegawai();
        if (! $pegawai || $pengajuan->pegawai_id !== $pegawai->id) {
            abort(404);
        }

        $pengajuan->load([
            'comments' => fn ($q) => $q->with('user:id,name'),
        ]);

        return response()->json([
            'pengajuan' => $pengajuan,
            'comments' => $pengajuan->comments->values(),
        ]);
    }

    public function reply(Request $request, PengajuanIzin $pengajuan)
    {
        $pegawai = $this->getPegawai();
        if (! $pegawai || $pengajuan->pegawai_id !== $pegawai->id) {
            abort(404);
        }

        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $user = $pegawai->user;
        $comment = DB::transaction(function () use ($pengajuan, $user, $request) {
            $pengajuan = PengajuanIzin::lockForUpdate()->find($pengajuan->id);

            if (in_array($pengajuan->approval_stage, ['approved', 'rejected', 'cancelled'])) {
                abort(422, 'Pengajuan sudah final. Thread sudah dikunci.');
            }

            return PengajuanIzinComment::create([
                'pengajuan_izin_id' => $pengajuan->id,
                'user_id' => $user->id,
                'message' => $request->message,
            ]);
        });

        // Notify approvers (skip sender) — batch load
        $recipientIds = collect();
        if ($pengajuan->approver_l1_id && $pengajuan->approver_l1_id !== $user->id) {
            $recipientIds->push($pengajuan->approver_l1_id);
        }
        if ($pengajuan->approver_l2_id && $pengajuan->approver_l2_id !== $user->id) {
            $recipientIds->push($pengajuan->approver_l2_id);
        }

        if ($recipientIds->isNotEmpty()) {
            User::whereIn('id', $recipientIds->unique())->get()
                ->each(fn (User $recipient) => NotificationHelper::sendSafely($recipient, new IzinReply($pengajuan, $comment, $user)));
        }

        return response()->json([
            'comment' => $comment->load('user:id,name'),
            'message' => 'Balasan terkirim.',
        ]);
    }
}
