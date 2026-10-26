<?php

namespace App\Http\Controllers;

use App\Constants\PresensiMessages;
use App\Events\IzinBaruEvent;
use App\Helpers\ApprovalHelper;
use App\Helpers\NotificationHelper;
use App\Helpers\PayrollLockHelper;
use App\Models\ApprovalPolicy;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\PengajuanIzinComment;
use App\Models\User;
use App\Notifications\ApprovalMail;
use App\Notifications\IzinBaru;
use App\Notifications\IzinReply;
use App\Notifications\StatusIzin;
use App\Services\ApprovalChain;
use App\Services\ImageUploadService;
use App\Services\IzinPresensiService;
use App\Traits\ResolvesPegawai;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
            'status' => 'nullable|in:semua,pending,disetujui,ditolak',
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

        // ── Validasi overlap: cegah pengajuan untuk tanggal yg sudah ada pengajuan pending/disetujui ──
        $existingIzin = PengajuanIzin::where('pegawai_id', $pegawai->id)
            ->whereIn('status', ['pending', 'disetujui'])
            ->where(function ($q) use ($request) {
                $q->whereBetween('tanggal_mulai', [$request->tanggal_mulai, $request->tanggal_selesai])
                    ->orWhereBetween('tanggal_selesai', [$request->tanggal_mulai, $request->tanggal_selesai])
                    ->orWhere(function ($q2) use ($request) {
                        $q2->where('tanggal_mulai', '<=', $request->tanggal_mulai)
                            ->where('tanggal_selesai', '>=', $request->tanggal_selesai);
                    });
            })
            ->first();

        if ($existingIzin) {
            $label = $existingIzin->status === 'disetujui' ? 'sudah disetujui' : 'sedang diproses';

            return back()->withErrors(['tanggal_mulai' => 'Anda sudah memiliki pengajuan '.$existingIzin->jenis_izin.' untuk tanggal tersebut ('.$label.').']);
        }

        if ($request->jenis_izin === 'cuti') {
            // sisa_cuti: hitung hanya hari kerja (Senin-Jumat)
            $pegawai->loadCutiInfo();
            $requestedDays = $this->countWeekdays($request->tanggal_mulai, $request->tanggal_selesai);
            if ($requestedDays > $pegawai->sisa_cuti) {
                return back()->withErrors(['alasan' => 'Sisa cuti Anda tidak mencukupi. Anda mengajukan '.$requestedDays.' hari kerja, sedangkan sisa cuti: '.$pegawai->sisa_cuti.' hari.']);
            }
        }

        // Cek apakah ada tanggal dalam range yang periode payroll-nya sudah dikunci
        $checkDate = Carbon::parse($request->tanggal_mulai);
        $endDate = Carbon::parse($request->tanggal_selesai);
        while ($checkDate->lte($endDate)) {
            if ($checkDate->isWeekday() && PayrollLockHelper::isPeriodLocked($pegawai->id, $checkDate)) {
                return back()->withErrors(['tanggal_mulai' => 'Periode penggajian untuk bulan '.$checkDate->format('m-Y').' sudah dikunci. Tidak bisa mengajukan izin untuk tanggal tersebut.']);
            }
            $checkDate->addDay();
        }

        $pengajuan = new PengajuanIzin([
            'pegawai_id' => $pegawai->id,
            'jenis_izin' => $request->jenis_izin,
            'tanggal_mulai' => $request->tanggal_mulai,
            'tanggal_selesai' => $request->tanggal_selesai,
            'alasan' => $request->alasan,
            'dengan_surat_dokter' => $request->boolean('dengan_surat_dokter') && $request->jenis_izin === 'sakit',
            'status' => 'pending',
        ]);

        if ($request->bukti_foto || ($request->jenis_izin === 'sakit' && $request->boolean('dengan_surat_dokter'))) {
            $imageName = app(ImageUploadService::class)->storeBase64(
                $request->bukti_foto,
                'izin',
                null,
                PresensiMessages::MAX_FOTO_BYTES,
                ['id' => $pegawai->id, 'nama' => $pegawai->nama_lengkap]
            );
            $pengajuan->bukti_foto = $imageName;
        }

        try {
            $pengajuan->save();

            $policy = ApprovalPolicy::for($request->jenis_izin === 'cuti' ? 'cuti' : 'izin');
            $hari = (int) $pengajuan->tanggal_mulai->diffInDays($pengajuan->tanggal_selesai) + 1;
            $chain = ApprovalChain::resolve($pegawai, $policy->jenis, now(), $hari);

            // approver_* ada di $guarded; update() akan mengabaikannya tanpa error.
            $pengajuan->forceFill([
                'approver_l1_id' => $chain['l1'],
                'approver_l2_id' => $chain['l2'],
                'due_at' => ApprovalChain::dueAt(now(), $policy->sla_days, ApprovalChain::unitOf($pegawai)),
            ])->save();

            $approvers = [
                'l1_id' => $chain['l1'],
                'l2_id' => $chain['l2'],
                'auto' => $chain['auto'],
                'has_l1' => $chain['l1'] !== null,
                'has_l2' => $chain['l2'] !== null,
            ];

            if ($chain['auto']) {
                $pengajuan->forceFill([
                    'approval_stage' => 'approved',
                    'status' => 'disetujui',
                    'approved_at_l1' => now(),
                    'approved_at_l2' => now(),
                    'catatan_approval' => 'Tanpa persetujuan (puncak organisasi).',
                ])->save();

                IzinPresensiService::generate($pengajuan);
            }
        } catch (\Throwable $e) {
            // Jangan tinggalkan file bukti foto yang menggantung bila save gagal.
            if (! empty($imageName)) {
                Storage::disk(config('filesystems.presensi_disk', 'presensi'))->delete($imageName);
            }
            throw $e;
        }

        if ($pengajuan->status === 'disetujui') {
            NotificationHelper::sendSafely($pegawai->user, new StatusIzin($pengajuan, 'disetujui'));
            ApprovalHelper::sendTembusanIzin($pengajuan, 'disetujui');
        } else {
            $this->notifyPengajuanBaru($pengajuan, $pegawai, $approvers);
            ApprovalHelper::sendTembusanIzin($pengajuan, 'diajukan', array_filter([$approvers['l1_id'] ?? null]));
            IzinBaruEvent::dispatch($pengajuan);

            if ($approvers['l1_id']) {
                $label = strtoupper($pengajuan->jenis_izin);
                NotificationHelper::sendSafely(User::find($approvers['l1_id']), new ApprovalMail(
                    "Pengajuan {$label} Baru - Perlu Persetujuan",
                    'Yth. '.($approvers['l1_id'] ? 'Bapak/Ibu' : 'Approver').',',
                    [
                        "{$pegawai->nama_lengkap} mengajukan {$label}.",
                        "Periode: {$pengajuan->tanggal_mulai?->format('d M Y')} s.d. {$pengajuan->tanggal_selesai?->format('d M Y')}",
                        "Alasan: {$pengajuan->alasan}",
                    ],
                    'Tinjau Pengajuan',
                    route('pengajuan-izin.index'),
                ));
            }
        }

        $pesan = $pengajuan->status === 'disetujui'
            ? 'Pengajuan berhasil dikirim.'
            : 'Pengajuan berhasil dikirim dan menunggu persetujuan.';

        return redirect()->route('presensi.izin.index')->with('message', $pesan);
    }

    /**
     * Kabari approver L1 bahwa ada pengajuan baru. Supadmin selalu ikut
     * diberi tahu (awareness + audit lintas unit). Tanpa L1, fallback ke
     * semua admin unit di unit pegawai — pengajuan tidak boleh luput.
     */
    private function notifyPengajuanBaru(PengajuanIzin $pengajuan, Pegawai $pegawai, array $approvers): void
    {
        $superadmins = User::role('superadmin')->get();

        // Approver L1 terkonfigurasi & ditemukan → kabari L1 + semua superadmin.
        if (! empty($approvers['has_l1'])) {
            $recipients = $superadmins->push(User::find($approvers['l1_id']))
                ->filter()
                ->unique('id');

            foreach ($recipients as $target) {
                NotificationHelper::sendSafely($target, new IzinBaru($pengajuan));
            }

            return;
        }

        // Tanpa L1 (tidak dikonfigurasi / tidak ditemukan) → fallback ke semua
        // admin unit di unit pegawai + superadmin. Pengajuan tidak boleh luput.
        $primaryUnit = $pegawai->units()->wherePivot('is_primary', true)->first()
            ?? $pegawai->units()->first();

        $admins = User::role('admin_unit')
            ->where(function ($q) use ($primaryUnit) {
                $q->whereNull('unit_sekolah_id')->orWhere('unit_sekolah_id', $primaryUnit?->id);
            })
            ->get();

        $recipients = $admins->merge($superadmins)->unique('id');

        foreach ($recipients as $target) {
            NotificationHelper::sendSafely($target, new IzinBaru($pengajuan));
        }
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

            if (in_array($pengajuan->approval_stage, ['approved', 'rejected'])) {
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

    /**
     * Hitung jumlah hari kerja (Senin–Jumat) dalam rentang tanggal.
     * O(1) — tidak iterasi per hari.
     */
    private function countWeekdays(string $start, string $end): int
    {
        $startDate = Carbon::parse($start);
        $endDate = Carbon::parse($end);
        $totalDays = $startDate->diffInDays($endDate) + 1;
        $fullWeeks = intdiv($totalDays, 7);
        $remainderDays = $totalDays % 7;

        // Each full week has 5 weekdays
        $weekdays = $fullWeeks * 5;

        // Check remainder days
        for ($i = 0; $i < $remainderDays; $i++) {
            if (! in_array($startDate->copy()->addDays($i)->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY])) {
                $weekdays++;
            }
        }

        return $weekdays;
    }
}
