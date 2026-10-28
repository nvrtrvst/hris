<?php

namespace App\Services;

use App\Constants\PresensiMessages;
use App\Events\IzinBaruEvent;
use App\Helpers\ApprovalHelper;
use App\Helpers\NotificationHelper;
use App\Helpers\PayrollLockHelper;
use App\Models\ApprovalPolicy;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\User;
use App\Notifications\ApprovalMail;
use App\Notifications\IzinBaru;
use App\Notifications\StatusIzin;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PengajuanIzinService
{
    /**
     * @return array{pengajuan: PengajuanIzin, approvers: array<string, mixed>}
     */
    public static function createPending(
        Pegawai $pegawai,
        array $data,
        ?string $buktiBase64 = null,
        bool $allowAutoApprove = true,
        string $sumber = 'pwa',
        ?int $createdBy = null,
    ): array {
        $jenis = (string) $data['jenis_izin'];
        $mulai = Carbon::parse($data['tanggal_mulai']);
        $selesai = Carbon::parse($data['tanggal_selesai']);

        $existingIzin = PengajuanIzin::where('pegawai_id', $pegawai->id)
            ->whereIn('status', ['pending', 'disetujui'])
            ->where(function ($q) use ($mulai, $selesai) {
                $q->whereBetween('tanggal_mulai', [$mulai->toDateString(), $selesai->toDateString()])
                    ->orWhereBetween('tanggal_selesai', [$mulai->toDateString(), $selesai->toDateString()])
                    ->orWhere(function ($q2) use ($mulai, $selesai) {
                        $q2->where('tanggal_mulai', '<=', $mulai->toDateString())
                            ->where('tanggal_selesai', '>=', $selesai->toDateString());
                    });
            })
            ->first();

        if ($existingIzin) {
            $label = $existingIzin->status === 'disetujui' ? 'sudah disetujui' : 'sedang diproses';
            throw ValidationException::withMessages([
                'tanggal_mulai' => 'Sudah ada pengajuan '.$existingIzin->jenis_izin.' untuk tanggal tersebut ('.$label.').',
            ]);
        }

        if ($jenis === 'cuti') {
            $pegawai->loadCutiInfo();
            $requestedDays = self::countWeekdays($mulai, $selesai);
            if ($requestedDays > $pegawai->sisa_cuti) {
                throw ValidationException::withMessages([
                    'alasan' => 'Sisa cuti tidak mencukupi. Diajukan '.$requestedDays.' hari kerja, sisa: '.$pegawai->sisa_cuti.' hari.',
                ]);
            }
        }

        for ($date = $mulai->copy(); $date->lte($selesai); $date->addDay()) {
            if ($date->isWeekday() && PayrollLockHelper::isPeriodLocked($pegawai->id, $date)) {
                throw ValidationException::withMessages([
                    'tanggal_mulai' => 'Periode penggajian untuk bulan '.$date->format('m-Y').' sudah dikunci.',
                ]);
            }
        }

        $pengajuan = new PengajuanIzin([
            'pegawai_id' => $pegawai->id,
            'jenis_izin' => $jenis,
            'tanggal_mulai' => $mulai,
            'tanggal_selesai' => $selesai,
            'alasan' => $data['alasan'],
            'dengan_surat_dokter' => (bool) ($data['dengan_surat_dokter'] ?? false) && $jenis === 'sakit',
            'sumber' => $sumber,
            'created_by' => $createdBy,
        ]);

        $imageName = null;
        if ($buktiBase64) {
            $imageName = app(ImageUploadService::class)->storeBase64(
                $buktiBase64,
                'izin',
                null,
                PresensiMessages::MAX_FOTO_BYTES,
                ['id' => $pegawai->id, 'nama' => $pegawai->nama_lengkap]
            );
            $pengajuan->bukti_foto = $imageName;
        }

        try {
            $pengajuan->save();

            $policy = ApprovalPolicy::for($jenis === 'cuti' ? 'cuti' : 'izin');
            $hari = (int) $pengajuan->tanggal_mulai->diffInDays($pengajuan->tanggal_selesai) + 1;
            $chain = ApprovalChain::resolve($pegawai, $policy->jenis, now(), $hari);

            $pengajuan->forceFill([
                'approver_l1_id' => $chain['l1'],
                'approver_l2_id' => $chain['l2'],
                'due_at' => ApprovalChain::dueAt(now(), $policy->sla_days, ApprovalChain::unitOf($pegawai)),
            ])->save();

            $approvers = [
                'l1_id' => $chain['l1'],
                'l2_id' => $chain['l2'],
                'auto' => (bool) $chain['auto'] && $allowAutoApprove,
                'has_l1' => $chain['l1'] !== null,
                'has_l2' => $chain['l2'] !== null,
            ];

            if (! $chain['auto'] && $chain['l1'] === null) {
                $pengajuan->forceFill(['approval_stage' => 'pending_superadmin'])->save();
            }

            if ($chain['auto'] && $allowAutoApprove) {
                $pengajuan->forceFill([
                    'approval_stage' => 'approved',
                    'status' => 'disetujui',
                    'approved_at_l1' => now(),
                    'approved_at_l2' => now(),
                    'catatan_approval' => 'Tanpa persetujuan (puncak organisasi).',
                ])->save();

                IzinPresensiService::generate($pengajuan);
            } elseif ($chain['auto']) {
                $pengajuan->forceFill(['approval_stage' => 'pending_superadmin'])->save();
            }
        } catch (\Throwable $e) {
            if ($imageName) {
                Storage::disk(config('filesystems.presensi_disk', 'presensi'))->delete($imageName);
            }
            throw $e;
        }

        return ['pengajuan' => $pengajuan, 'approvers' => $approvers];
    }

    public static function notifyCreated(PengajuanIzin $pengajuan, Pegawai $pegawai, array $approvers): void
    {
        if ($pengajuan->status === 'disetujui') {
            NotificationHelper::sendSafely($pegawai->user, new StatusIzin($pengajuan, 'disetujui'));
            ApprovalHelper::sendTembusanIzin($pengajuan, 'disetujui');

            return;
        }

        self::notifyPengajuanBaru($pengajuan, $pegawai, $approvers);
        ApprovalHelper::sendTembusanIzin($pengajuan, 'diajukan', array_filter([$approvers['l1_id'] ?? null]));
        IzinBaruEvent::dispatch($pengajuan);

        if ($approvers['l1_id']) {
            $label = strtoupper($pengajuan->jenis_izin);
            NotificationHelper::sendSafely(User::find($approvers['l1_id']), new ApprovalMail(
                "Pengajuan {$label} Baru - Perlu Persetujuan",
                'Yth. Bapak/Ibu,',
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

    private static function notifyPengajuanBaru(PengajuanIzin $pengajuan, Pegawai $pegawai, array $approvers): void
    {
        $superadmins = User::role('superadmin')->get();

        if (! empty($approvers['has_l1'])) {
            $recipients = $superadmins->push(User::find($approvers['l1_id']))
                ->filter()
                ->unique('id');

            foreach ($recipients as $target) {
                NotificationHelper::sendSafely($target, new IzinBaru($pengajuan));
            }

            return;
        }

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

    private static function countWeekdays(Carbon $start, Carbon $end): int
    {
        $totalDays = $start->diffInDays($end) + 1;
        $fullWeeks = intdiv($totalDays, 7);
        $remainderDays = $totalDays % 7;
        $weekdays = $fullWeeks * 5;

        for ($i = 0; $i < $remainderDays; $i++) {
            if (! in_array($start->copy()->addDays($i)->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY])) {
                $weekdays++;
            }
        }

        return $weekdays;
    }
}
