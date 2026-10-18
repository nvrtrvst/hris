<?php

namespace App\Http\Controllers;

use App\Helpers\ApprovalHelper;
use App\Helpers\NotificationHelper;
use App\Http\Requests\PengajuanPulangCepatStoreRequest;
use App\Models\Pegawai;
use App\Models\PengajuanPulangCepat;
use App\Models\User;
use App\Notifications\PulangCepatBaru;
use App\Services\PulangCepatRealisasi;
use App\Traits\ResolvesPegawai;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MobilePulangCepatController extends Controller
{
    use ResolvesPegawai;

    public function index()
    {
        $pegawai = $this->getPegawai();
        if (! $pegawai) {
            abort(403, 'Akses ditolak.');
        }

        $pengajuan = PengajuanPulangCepat::with('approver:id,name')
            ->where('pegawai_id', $pegawai->id)
            ->orderByDesc('created_at')
            ->get();

        $realisasi = app(PulangCepatRealisasi::class)->forPengajuans($pengajuan);

        $pengajuan->each(fn ($item) => $item->realisasi = $realisasi[$item->pegawai_id.'|'.$item->tanggal->toDateString()] ?? null);

        return Inertia::render('Mobile/PulangCepat/Index', [
            'pengajuan' => $pengajuan,
        ]);
    }

    public function create()
    {
        $pegawai = $this->getPegawai();
        if (! $pegawai) {
            abort(403, 'Akses ditolak.');
        }

        return Inertia::render('Mobile/PulangCepat/Create');
    }

    public function store(PengajuanPulangCepatStoreRequest $request)
    {
        $pegawai = $this->getPegawai();
        if (! $pegawai) {
            abort(403, 'Akses ditolak.');
        }

        $approvers = ApprovalHelper::determineApprovers($pegawai);

        $pengajuan = DB::transaction(function () use ($request, $pegawai, $approvers) {
            $seq = PengajuanPulangCepat::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->count() + 1;

            for ($coba = 0; $coba < 3; $coba++) {
                try {
                    return PengajuanPulangCepat::create([
                        'pegawai_id' => $pegawai->id,
                        'tanggal' => $request->validated('tanggal'),
                        'jam_diminta' => $request->validated('jam_diminta'),
                        'alasan' => $request->validated('alasan'),
                        'alasan_detail' => $request->validated('alasan_detail'),
                        'approver_id' => $approvers['l1_id'],
                        'nomor' => sprintf('PPC/%s/%s/%04d', now()->format('Y'), now()->format('m'), $seq),
                    ]);
                } catch (UniqueConstraintViolationException) {
                    $seq++;
                }
            }

            abort(500, 'Gagal membuat nomor pengajuan. Coba lagi.');
        });

        $this->notifyPulangCepatBaru($pengajuan, $pegawai, $approvers);

        return redirect()->route('presensi.pulang-cepat.index')
            ->with('message', 'Pengajuan izin pulang cepat berhasil dikirim dan menunggu persetujuan.');
    }

    /** Pola MobileKoreksiController::notifyKoreksiBaru. */
    private function notifyPulangCepatBaru(PengajuanPulangCepat $pengajuan, Pegawai $pegawai, array $approvers): void
    {
        $superadmins = User::role('superadmin')->get();

        if (! empty($approvers['has_l1'])) {
            $recipients = $superadmins->push(User::find($approvers['l1_id']))->filter()->unique('id');
        } else {
            $primaryUnit = $pegawai->units()->wherePivot('is_primary', true)->first()
                ?? $pegawai->units()->first();

            $admins = User::role('admin_unit')
                ->where(function ($q) use ($primaryUnit) {
                    $q->whereNull('unit_sekolah_id')->orWhere('unit_sekolah_id', $primaryUnit?->id);
                })
                ->get();

            $recipients = $admins->merge($superadmins)->unique('id');
        }

        foreach ($recipients as $target) {
            NotificationHelper::sendSafely($target, new PulangCepatBaru($pengajuan));
        }
    }
}
