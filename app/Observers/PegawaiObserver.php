<?php

namespace App\Observers;

use App\Models\Pegawai;

class PegawaiObserver
{
    /**
     * Handle the Pegawai "created" event.
     */
    public function created(Pegawai $pegawai): void
    {
        //
    }

    /**
     * Handle the Pegawai "updated" event.
     */
    public function updated(Pegawai $pegawai): void
    {
        $trackedFields = [
            'status_kepegawaian',
            'status_aktif',
            'atasan_langsung_id',
            'tanggal_akhir_kontrak',
        ];

        foreach ($trackedFields as $field) {
            if ($pegawai->isDirty($field)) {
                $pegawai->riwayat()->create([
                    'jenis_perubahan' => 'perubahan_'.$field,
                    'nilai_lama' => $pegawai->getOriginal($field),
                    'nilai_baru' => $pegawai->getAttribute($field),
                    'changed_by' => auth()->id(),
                ]);
            }
        }
    }

    /**
     * Handle the Pegawai "deleted" event.
     */
    public function deleted(Pegawai $pegawai): void
    {
        $pegawai->riwayat()->create([
            'jenis_perubahan' => 'penghapusan_data',
            'nilai_lama' => 'aktif',
            'nilai_baru' => 'terhapus',
            'changed_by' => auth()->id(),
        ]);
    }

    /**
     * Handle the Pegawai "restored" event.
     */
    public function restored(Pegawai $pegawai): void
    {
        $pegawai->riwayat()->create([
            'jenis_perubahan' => 'pemulihan_data',
            'nilai_lama' => 'terhapus',
            'nilai_baru' => 'aktif',
            'changed_by' => auth()->id(),
        ]);
    }

    /**
     * Handle the Pegawai "force deleted" event.
     */
    public function forceDeleted(Pegawai $pegawai): void
    {
        //
    }
}
