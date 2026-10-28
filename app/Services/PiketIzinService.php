<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\LaporanPiket;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\Presensi;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PiketIzinService
{
    /**
     * @return array{pengajuan: PengajuanIzin, approvers: array<string, mixed>}
     */
    public static function convert(LaporanPiket $laporan, int $entryIndex, array $data, User $user): array
    {
        $created = null;
        try {
            $result = DB::transaction(function () use ($laporan, $entryIndex, $data, $user, &$created) {
                $laporan = LaporanPiket::lockForUpdate()->findOrFail($laporan->id);

                if ($laporan->status !== LaporanPiket::STATUS_SUBMITTED) {
                    throw ValidationException::withMessages(['laporan' => 'Laporan piket belum dikirim.']);
                }

                $items = $laporan->items ?? [];
                $entries = $items[LaporanPiket::ITEM_CEK_ABSEN]['entries'] ?? [];

                if (! isset($entries[$entryIndex])) {
                    throw ValidationException::withMessages(['entry_index' => 'Entry guru tidak ditemukan.']);
                }

                $entry = $entries[$entryIndex];
                if (empty($entry['guru_pegawai_id'])) {
                    throw ValidationException::withMessages(['guru_pegawai_id' => 'Entry manual belum terhubung ke data pegawai.']);
                }

                if (! empty($entry['pengajuan_izin_id'])) {
                    throw ValidationException::withMessages(['entry_index' => 'Entry sudah dibuatkan pengajuan izin.']);
                }

                $guru = Pegawai::find((int) $entry['guru_pegawai_id']);
                if (! $guru || $guru->status_aktif !== 'aktif') {
                    throw ValidationException::withMessages(['guru_pegawai_id' => 'Guru tidak aktif atau tidak ditemukan.']);
                }

                if (! $guru->belongsToUnit($laporan->unit_sekolah_id)) {
                    throw ValidationException::withMessages(['guru_pegawai_id' => 'Guru bukan bagian dari unit laporan.']);
                }

                if ($user->unit_sekolah_id && ! $user->can('view_all_units')) {
                    if ((int) $laporan->unit_sekolah_id !== (int) $user->unit_sekolah_id
                        || ! $guru->belongsToUnit($user->unit_sekolah_id)) {
                        abort(403, 'Akses ditolak.');
                    }
                }

                $mulai = Carbon::parse($data['tanggal_mulai']);
                $selesai = Carbon::parse($data['tanggal_selesai']);

                for ($tanggal = $mulai->copy(); $tanggal->lte($selesai); $tanggal->addDay()) {
                    $presensiHadir = Presensi::where('pegawai_id', $guru->id)
                        ->whereDate('tanggal', $tanggal->toDateString())
                        ->whereIn('status', ['hadir', 'telat'])
                        ->exists();

                    if ($presensiHadir) {
                        throw ValidationException::withMessages(['guru_pegawai_id' => 'Guru sudah tercatat hadir pada tanggal '.$tanggal->format('Y-m-d').'.']);
                    }
                }

                if ($data['jenis_izin'] === 'sakit' && blank($data['bukti_foto'] ?? null)) {
                    throw ValidationException::withMessages(['bukti_foto' => 'Bukti surat dokter wajib diunggah untuk sakit.']);
                }

                $created = PengajuanIzinService::createPending(
                    $guru,
                    [
                        'jenis_izin' => $data['jenis_izin'],
                        'tanggal_mulai' => $data['tanggal_mulai'],
                        'tanggal_selesai' => $data['tanggal_selesai'],
                        'alasan' => $data['alasan'],
                        'dengan_surat_dokter' => (bool) ($data['dengan_surat_dokter'] ?? false),
                    ],
                    $data['bukti_foto'] ?? null,
                    false,
                    'piket',
                    $user->id,
                );

                $entry['pengajuan_izin_id'] = $created['pengajuan']->id;
                $entries[$entryIndex] = $entry;
                $items[LaporanPiket::ITEM_CEK_ABSEN]['entries'] = $entries;
                $laporan->items = $items;
                $laporan->save();

                AuditLog::log($laporan, 'buat_izin_dari_piket', [], [
                    'pengajuan_izin_id' => $created['pengajuan']->id,
                    'guru_pegawai_id' => $guru->id,
                    'entry_index' => $entryIndex,
                    'jenis_izin' => $data['jenis_izin'],
                ]);

                return $created;
            });
        } catch (\Throwable $e) {
            if ($created && ! empty($created['pengajuan']->bukti_foto)) {
                Storage::disk(config('filesystems.presensi_disk', 'presensi'))->delete($created['pengajuan']->bukti_foto);
            }

            throw $e;
        }

        PengajuanIzinService::notifyCreated($result['pengajuan'], $result['pengajuan']->pegawai, $result['approvers']);

        return $result;
    }
}
