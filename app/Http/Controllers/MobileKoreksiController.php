<?php

namespace App\Http\Controllers;

use App\Helpers\ApprovalHelper;
use App\Helpers\NotificationHelper;
use App\Helpers\PayrollLockHelper;
use App\Http\Requests\PengajuanKoreksiStoreRequest;
use App\Models\Pegawai;
use App\Models\PengajuanKoreksi;
use App\Models\Presensi;
use App\Models\User;
use App\Notifications\KoreksiBaru;
use App\Traits\ResolvesPegawai;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MobileKoreksiController extends Controller
{
    use ResolvesPegawai;

    public function index()
    {
        $pegawai = $this->getPegawai();
        if (! $pegawai) {
            abort(403, 'Akses ditolak.');
        }

        $pengajuan = PengajuanKoreksi::with([
            'presensi' => fn ($q) => $q->select('id', 'pegawai_id', 'tanggal', 'jam_masuk', 'jam_keluar', 'unit_sekolah_id'),
            'presensi.unitSekolah' => fn ($q) => $q->select('id', 'nama'),
            'approver:id,name',
        ])
            ->where('pegawai_id', $pegawai->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Mobile/Koreksi/Index', [
            'pengajuan' => $pengajuan,
            'koreksi_count' => PengajuanKoreksi::kuotaTerpakai($pegawai->id),
            'kuota' => PengajuanKoreksi::KUOTA_BULANAN,
        ]);
    }

    public function create(Request $request)
    {
        $pegawai = $this->getPegawai();
        if (! $pegawai) {
            abort(403, 'Akses ditolak.');
        }

        abort_unless($request->filled('presensi_id'), 404);
        $presensi = Presensi::with('unitSekolah:id,nama')->findOrFail($request->query('presensi_id'));

        if ($presensi->pegawai_id !== $pegawai->id) {
            abort(403, 'Akses ditolak.');
        }
        if (! $presensi->jam_masuk) {
            abort(422, 'Presensi belum memiliki jam masuk.');
        }
        $sudahPending = PengajuanKoreksi::where('presensi_id', $presensi->id)
            ->where('status', 'pending')
            ->exists();
        if ($sudahPending) {
            abort(422, 'Sudah ada pengajuan koreksi yang menunggu persetujuan untuk presensi ini.');
        }
        if (PayrollLockHelper::isPeriodLocked($presensi->pegawai_id, $presensi->tanggal)) {
            abort(422, 'Periode payroll sudah terkunci. Hubungi HR.');
        }

        return Inertia::render('Mobile/Koreksi/Create', [
            'presensi' => $presensi,
            'koreksi_count' => PengajuanKoreksi::kuotaTerpakai($pegawai->id),
            'kuota' => PengajuanKoreksi::KUOTA_BULANAN,
        ]);
    }

    public function store(PengajuanKoreksiStoreRequest $request)
    {
        $pegawai = $this->getPegawai();
        if (! $pegawai) {
            abort(403, 'Akses ditolak.');
        }

        $presensi = Presensi::findOrFail($request->validated('presensi_id'));
        $approvers = ApprovalHelper::determineApprovers($pegawai);

        $koreksi = DB::transaction(function () use ($request, $pegawai, $presensi, $approvers) {
            // Race: duplikat pending dicek ulang di dalam transaksi.
            $pending = PengajuanKoreksi::where('presensi_id', $presensi->id)
                ->where('status', 'pending')
                ->exists();
            if ($pending) {
                abort(422, 'Sudah ada pengajuan koreksi yang menunggu persetujuan untuk presensi ini.');
            }

            $seq = PengajuanKoreksi::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->count() + 1;

            // Retry kecil bila tabrakan nomor (unique) pada pengajuan paralel.
            for ($coba = 0; $coba < 3; $coba++) {
                try {
                    $koreksi = PengajuanKoreksi::create([
                        'pegawai_id' => $pegawai->id,
                        'presensi_id' => $presensi->id,
                        'tanggal' => $presensi->tanggal,
                        'nilai_baru' => $request->validated('nilai_baru'),
                        'alasan' => $request->validated('alasan'),
                        'alasan_detail' => $request->validated('alasan_detail'),
                        'penjelasan_khusus' => $request->validated('penjelasan_khusus'),
                        'approver_id' => $approvers['l1_id'],
                        'nomor' => sprintf('KOR/%s/%s/%04d', now()->format('Y'), now()->format('m'), $seq),
                    ]);

                    return $koreksi;
                } catch (UniqueConstraintViolationException) {
                    $seq++;
                }
            }

            abort(500, 'Gagal membuat nomor pengajuan koreksi. Coba lagi.');
        });

        $this->notifyKoreksiBaru($koreksi, $pegawai, $approvers);

        return redirect()->route('presensi.koreksi.index')
            ->with('message', 'Pengajuan koreksi berhasil dikirim dan menunggu persetujuan.');
    }

    /**
     * Kabari approver L1 + superadmin; tanpa L1 → fallback admin unit
     * pegawai + superadmin (pola MobileIzinController::notifyPengajuanBaru).
     */
    private function notifyKoreksiBaru(PengajuanKoreksi $koreksi, Pegawai $pegawai, array $approvers): void
    {
        $superadmins = User::role('superadmin')->get();

        if (! empty($approvers['has_l1'])) {
            $recipients = $superadmins->push(User::find($approvers['l1_id']))
                ->filter()
                ->unique('id');
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
            NotificationHelper::sendSafely($target, new KoreksiBaru($koreksi));
        }
    }
}
