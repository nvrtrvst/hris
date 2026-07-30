<?php

namespace App\Jobs;

use App\Models\Presensi;
use App\Services\ImageUploadService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessPresensiFoto implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public array $backoff = [5, 15, 30];

    public function __construct(
        public int $presensiId,
        public string $tipe,
        public string $tempPath,
        public string $subDir,
        public ?array $overlayData = null,
        public array $metadata = [],
    ) {}

    public function handle(ImageUploadService $uploader): void
    {
        $presensi = Presensi::findOrFail($this->presensiId);
        $fieldStatus = $this->tipe === 'masuk' ? 'foto_masuk_status' : 'foto_keluar_status';
        $fieldFoto = $this->tipe === 'masuk' ? 'foto_masuk' : 'foto_keluar';
        $fieldError = $this->tipe === 'masuk' ? 'foto_masuk_error' : 'foto_keluar_error';

        $presensi->update([$fieldStatus => 'processing']);

        $disk = Storage::disk('local');
        $fullPath = $disk->path($this->tempPath);

        try {
            if (! $disk->exists($this->tempPath)) {
                throw new \RuntimeException("Temp file not found: {$this->tempPath}");
            }

            $base64 = 'data:image/jpeg;base64,'.base64_encode(file_get_contents($fullPath));

            $finalPath = $uploader->storeBase64(
                $base64,
                $this->subDir,
                $this->overlayData,
                5 * 1024 * 1024,
                $this->metadata,
            );

            $presensi->update([
                $fieldFoto => $finalPath,
                $fieldStatus => 'success',
                $fieldError => null,
            ]);
        } finally {
            if ($disk->exists($this->tempPath)) {
                $disk->delete($this->tempPath);
            }
        }
    }

    public function failed(\Throwable $e): void
    {
        $disk = Storage::disk('local');
        if ($disk->exists($this->tempPath)) {
            $disk->delete($this->tempPath);
        }

        $presensi = Presensi::find($this->presensiId);
        if (! $presensi) {
            return;
        }

        $fieldStatus = $this->tipe === 'masuk' ? 'foto_masuk_status' : 'foto_keluar_status';
        $fieldError = $this->tipe === 'masuk' ? 'foto_masuk_error' : 'foto_keluar_error';

        $presensi->update([
            $fieldStatus => 'failed',
            $fieldError => $e->getMessage(),
        ]);

        Log::error('Foto presensi gagal diproses setelah retry', [
            'presensi_id' => $this->presensiId,
            'pegawai_id' => $presensi->pegawai_id,
            'error' => $e->getMessage(),
        ]);
    }
}
