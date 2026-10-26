<?php

namespace App\Http\Controllers;

use App\Helpers\ApprovalHelper;
use App\Helpers\NotificationHelper;
use App\Models\ApprovalPolicy;
use App\Models\AuditLog;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\PengajuanIzinComment;
use App\Models\User;
use App\Notifications\ApprovalMail;
use App\Notifications\IzinBaru;
use App\Notifications\IzinReply;
use App\Notifications\StatusIzin;
use App\Services\ApprovalChain;
use App\Services\IzinPresensiService;
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
            'pegawai' => fn ($q) => $q->with(['units', 'pengajuanIzins' => fn ($iq) => $iq->select('id', 'pegawai_id', 'jenis_izin', 'status', 'tanggal_mulai', 'tanggal_selesai')]),
            'approverL1' => fn ($q) => $q->select('id', 'name'),
            'approverL2' => fn ($q) => $q->select('id', 'name'),
            'rejectedByUser' => fn ($q) => $q->select('id', 'name'),
        ]);
        $tab = $request->input('tab', 'semua');

        if ($tab === 'l1') {
            $query->where('approval_stage', 'pending_l1')
                ->where('approver_l1_id', $user->id);
        } elseif ($tab === 'l2') {
            $query->where('approval_stage', 'pending_l2')
                ->where('approver_l2_id', $user->id);
        } elseif ($tab === 'superadmin') {
            if (! $user->hasRole('superadmin')) {
                abort(403, 'Akses ditolak.');
            }
            $query->where('approval_stage', 'pending_superadmin');
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

        // URL bersih (tanpa key `tanggal`) = default hari ini; `tanggal` eksplisit
        // (isi / null karena ConvertEmptyStringsToNull) = hormati — kosong = lihat semua.
        $explicitTanggal = array_key_exists('tanggal', $request->query());
        $tanggal = $explicitTanggal ? $request->query('tanggal') : now()->toDateString();
        if (! empty($tanggal)) {
            $query->whereDate('tanggal_mulai', '<=', $tanggal)
                ->whereDate('tanggal_selesai', '>=', $tanggal);
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
            $item->unit_sakit_wajib_tugas = (bool) ($item->pegawai?->unitUtama()?->sakitWajibTugas() ?? false);

            return $item;
        });

        // `tanggal` eksplisit → string ('' utk kosong, isi utk filter); URL bersih → null (FE default hari ini).
        $filters = $request->only(['search', 'status', 'tanggal', 'tab', 'jenis_filter']);
        $filters['tanggal'] = $explicitTanggal ? (string) ($request->query('tanggal') ?? '') : null;

        return Inertia::render('PengajuanIzin/Index', [
            'pengajuans' => $pengajuans,
            'filters' => $filters,
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
            'dihitung_setengah' => 'nullable|boolean',
            'dengan_surat_dokter' => 'nullable|boolean',
            'tugas_terverifikasi' => 'nullable|boolean',
        ]);

        $pengajuan = DB::transaction(function () use ($id, $user, $request) {
            $pengajuan = PengajuanIzin::with('pegawai')->lockForUpdate()->findOrFail($id);

            // Flag ½ hari (izin + tugas): boleh dipasang di tahap mana pun,
            // berlaku bila pengajuan berakhir disetujui. Pakai exists()
            // (bukan filled) agar nilai false ikut tersimpan.
            if ($request->exists('dihitung_setengah')) {
                $pengajuan->dihitung_setengah = $request->boolean('dihitung_setengah');
            }

            // Flag surat dokter (sakit): verifikasi admin menimpa klaim pegawai.
            if ($request->exists('dengan_surat_dokter')) {
                $pengajuan->dengan_surat_dokter = $request->boolean('dengan_surat_dokter');
            }

            $tugasLama = (bool) $pengajuan->tugas_terverifikasi;
            if ($request->exists('tugas_terverifikasi')) {
                $pengajuan->tugas_terverifikasi = $request->boolean('tugas_terverifikasi');
            }

            // Tugas hanya bermakna untuk sakit bersurat.
            if ($pengajuan->jenis_izin !== 'sakit' || ! $pengajuan->dengan_surat_dokter) {
                $pengajuan->tugas_terverifikasi = false;
            }

            if ($pengajuan->tugas_terverifikasi) {
                if (! $pengajuan->tugas_diverifikasi_at) {
                    $pengajuan->tugas_diverifikasi_at = now();
                }
                $pengajuan->tugas_diverifikasi_oleh = $user->id;
            } else {
                $pengajuan->tugas_diverifikasi_at = null;
                $pengajuan->tugas_diverifikasi_oleh = null;
            }

            if ($tugasLama !== (bool) $pengajuan->tugas_terverifikasi) {
                AuditLog::log($pengajuan, 'verifikasi_tugas', ['tugas_terverifikasi' => $tugasLama], ['tugas_terverifikasi' => (bool) $pengajuan->tugas_terverifikasi, 'oleh' => $user->id]);
            }

            if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
                if (! $pengajuan->pegawai->belongsToUnit($user->unit_sekolah_id)) {
                    abort(403, 'Akses ditolak.');
                }
            }

            if (in_array($pengajuan->approval_stage, ['approved', 'rejected'])) {
                throw ValidationException::withMessages(['error' => 'Pengajuan sudah final.']);
            }

            $stage = $this->resolveStage($pengajuan, $user, $request);

            if ($stage === 'takeover') {
                AuditLog::log($pengajuan, 'takeover_approve', [
                    'stage' => $pengajuan->approval_stage,
                    'approver_l1_id' => $pengajuan->approver_l1_id,
                    'approver_l2_id' => $pengajuan->approver_l2_id,
                ], [
                    'oleh' => $user->id,
                    'alasan' => $request->catatan_approval,
                ]);
                $pengajuan->approver_l1_id = $pengajuan->approver_l1_id ?: $user->id;
            }

            if (in_array($stage, ['l1', 'takeover'], true)) {
                if ($stage === 'l1') {
                    $pengajuan->approved_at_l1 = now();
                }

                if ($stage !== 'takeover'
                    && $pengajuan->approver_l2_id
                    && (int) $pengajuan->approver_l2_id !== (int) $pengajuan->approver_l1_id) {
                    $pengajuan->approval_stage = 'pending_l2';
                    $pengajuan->due_at = ApprovalChain::dueAt(
                        now(),
                        ApprovalPolicy::for($pengajuan->jenis_izin)->sla_days,
                        ApprovalChain::unitOf($pengajuan->pegawai),
                    );
                    $pengajuan->reminder_sent_at = null;
                } else {
                    $pengajuan->approval_stage = 'approved';
                    $pengajuan->status = 'disetujui';
                    $pengajuan->approved_at_l2 = now();
                    IzinPresensiService::generate($pengajuan);
                }
            } else {
                $pengajuan->approval_stage = 'approved';
                $pengajuan->status = 'disetujui';
                $pengajuan->approved_at_l2 = now();
                IzinPresensiService::generate($pengajuan);
            }

            if ($request->filled('catatan_approval')) {
                $pengajuan->catatan_approval = $request->catatan_approval;
            }

            $pengajuan->save();

            if ($request->filled('dihitung_hadir_kcd')) {
                $pengajuan->dihitung_hadir_kcd = $request->boolean('dihitung_hadir_kcd');
                $pengajuan->save();
            }

            return $pengajuan;
        });

        if ($pengajuan->status === 'disetujui') {
            NotificationHelper::sendSafely($pengajuan->pegawai?->user, new StatusIzin($pengajuan, 'disetujui'));
            ApprovalHelper::sendTembusanIzin($pengajuan, 'disetujui', array_filter([$pengajuan->approver_l1_id, $pengajuan->approver_l2_id]));
        }

        if ($pengajuan->approval_stage === 'pending_l2' && $pengajuan->approver_l2_id) {
            $l2 = User::find($pengajuan->approver_l2_id);
            NotificationHelper::sendSafely($l2, new IzinBaru($pengajuan));

            $label = strtoupper($pengajuan->jenis_izin);
            NotificationHelper::sendSafely($l2, new ApprovalMail(
                "Pengajuan {$label} Menunggu Persetujuan L2",
                'Yth. '.($l2?->name ?? 'Bapak/Ibu').',',
                [
                    "{$pengajuan->pegawai?->nama_lengkap} mengajukan {$label}.",
                    'Telah disetujui atasan L1 dan diteruskan kepada Anda.',
                ],
                'Tinjau Pengajuan',
                route('pengajuan-izin.index'),
            ));
        }

        if ($pengajuan->status === 'disetujui') {
            $pemohon = $pengajuan->pegawai?->user;
            $label = strtoupper($pengajuan->jenis_izin);
            NotificationHelper::sendSafely($pemohon, new ApprovalMail(
                "Pengajuan {$label} Disetujui",
                'Yth. '.($pemohon?->name ?? 'Pegawai').',',
                [
                    "Pengajuan {$label} Anda ({$pengajuan->tanggal_mulai?->format('d M Y')} s.d. {$pengajuan->tanggal_selesai?->format('d M Y')}) telah disetujui.",
                ],
            ));
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

            $boleh = $user->hasRole('superadmin')
                || ($pengajuan->approval_stage === 'pending_l1' && (int) $pengajuan->approver_l1_id === (int) $user->id)
                || ($pengajuan->approval_stage === 'pending_l2' && (int) $pengajuan->approver_l2_id === (int) $user->id);

            if (! $boleh) {
                abort(403, 'Anda bukan penyetuju pada tahap ini.');
            }

            $pengajuan->status = 'ditolak';
            $pengajuan->approval_stage = 'rejected';
            $pengajuan->alasan_penolakan = $request->alasan_penolakan;
            $pengajuan->rejected_by = $user->id;
            $pengajuan->tugas_terverifikasi = false;
            $pengajuan->tugas_diverifikasi_at = null;
            $pengajuan->tugas_diverifikasi_oleh = null;
            $pengajuan->save();

            return $pengajuan;
        });

        NotificationHelper::sendSafely($pengajuan->pegawai?->user, new StatusIzin($pengajuan, 'ditolak', $request->alasan_penolakan));
        ApprovalHelper::sendTembusanIzin($pengajuan, 'ditolak', array_filter([$pengajuan->approver_l1_id, $pengajuan->approver_l2_id]));

        $pemohon = $pengajuan->pegawai?->user;
        $label = strtoupper($pengajuan->jenis_izin);
        NotificationHelper::sendSafely($pemohon, new ApprovalMail(
            "Pengajuan {$label} Ditolak",
            'Yth. '.($pemohon?->name ?? 'Pegawai').',',
            [
                "Pengajuan {$label} Anda ditolak.",
                "Alasan: {$request->alasan_penolakan}",
            ],
        ));

        return back()->with('message', 'Pengajuan berhasil ditolak.');
    }

    public function comments(Request $request, $id)
    {
        $user = auth()->user();
        $pengajuan = PengajuanIzin::with([
            'pegawai' => fn ($q) => $q->select('id', 'user_id'),
            'comments' => fn ($q) => $q->with('user:id,name'),
        ])->findOrFail($id);

        // Authorization: pegawai can see own, approver/admin can see all
        $isOwner = $pengajuan->pegawai?->user_id === $user->id;
        $isAdmin = $user->can('view_izin') || $user->isApprover();
        if (! $isOwner && ! $isAdmin) {
            abort(403, 'Akses ditolak.');
        }

        return response()->json([
            'pengajuan' => $pengajuan,
            'comments' => $pengajuan->comments->values(),
        ]);
    }

    public function reply(Request $request, $id)
    {
        $user = auth()->user();
        if (! $user) {
            abort(401);
        }

        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $comment = DB::transaction(function () use ($id, $user, $request) {
            $pengajuan = PengajuanIzin::with('pegawai.user:id,id,name')->lockForUpdate()->findOrFail($id);

            // Authorization: pegawai can reply on own, approver/admin can reply on any
            $isOwner = $pengajuan->pegawai?->user_id === $user->id;
            $isAdmin = $user->can('view_izin') || $user->isApprover();
            if (! $isOwner && ! $isAdmin) {
                abort(403, 'Akses ditolak.');
            }

            // Thread locked after final (approve/reject) — inside lock, race-safe
            if (in_array($pengajuan->approval_stage, ['approved', 'rejected'])) {
                abort(422, 'Pengajuan sudah final. Thread sudah dikunci.');
            }

            $comment = PengajuanIzinComment::create([
                'pengajuan_izin_id' => $pengajuan->id,
                'user_id' => $user->id,
                'message' => $request->message,
            ]);

            return [$comment, $pengajuan];
        });

        [$comment, $pengajuan] = $comment;

        // Notify: all approvers + pegawai (skip sender) — batch load
        $recipientIds = collect();
        if ($pengajuan->approver_l1_id && $pengajuan->approver_l1_id !== $user->id) {
            $recipientIds->push($pengajuan->approver_l1_id);
        }
        if ($pengajuan->approver_l2_id && $pengajuan->approver_l2_id !== $user->id) {
            $recipientIds->push($pengajuan->approver_l2_id);
        }
        $pegawaiUserId = $pengajuan->pegawai?->user_id;
        if ($pegawaiUserId && $pegawaiUserId !== $user->id) {
            $recipientIds->push($pegawaiUserId);
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

    private function resolveStage(PengajuanIzin $pengajuan, User $user, Request $request): string
    {
        $assigned = match ($pengajuan->approval_stage) {
            'pending_l1' => (int) $pengajuan->approver_l1_id === (int) $user->id,
            'pending_l2' => (int) $pengajuan->approver_l2_id === (int) $user->id,
            'pending_superadmin' => $user->hasRole('superadmin'),
            default => false,
        };

        if ($assigned) {
            return match ($pengajuan->approval_stage) {
                'pending_l1' => 'l1',
                'pending_l2' => 'l2',
                default => 'superadmin',
            };
        }

        if ($user->hasRole('superadmin')) {
            $bolehTakeover = $pengajuan->due_at === null
                || $pengajuan->due_at->isPast()
                || $pengajuan->approver_l1_id === null;

            if ($bolehTakeover) {
                if (! $request->filled('catatan_approval')) {
                    throw ValidationException::withMessages([
                        'catatan_approval' => 'Alasan take-over wajib diisi.',
                    ]);
                }

                return 'takeover';
            }
        }

        abort(403, 'Anda bukan penyetuju pada tahap ini.');
    }

    private function canActOnItem(PengajuanIzin $item, User $user): bool
    {
        return match ($item->approval_stage) {
            'pending_l1' => (int) $item->approver_l1_id === (int) $user->id || $user->hasRole('superadmin'),
            'pending_l2' => (int) $item->approver_l2_id === (int) $user->id || $user->hasRole('superadmin'),
            'pending_superadmin' => $user->hasRole('superadmin'),
            default => false,
        };
    }
}
