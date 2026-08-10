<?php

namespace App\Jobs;

use App\Constants\PresensiMessages;
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

            // EXIF forensics: baca metadata GPS & DateTimeOriginal dari foto asli
            // (sebelum di-overlay oleh ImageUploadService).
            $exifMeta = null;
            if (function_exists('exif_read_data')) {
                try {
                    $exif = @exif_read_data($fullPath, 'IFD0,EXIF,GPS', true);
                    if ($exif) {
                        $exifLat = null;
                        $exifLng = null;
                        if (isset($exif['GPS']['GPSLatitude'], $exif['GPS']['GPSLatitudeRef'], $exif['GPS']['GPSLongitude'], $exif['GPS']['GPSLongitudeRef'])) {
                            $exifLat = self::exifGpsToDecimal($exif['GPS']['GPSLatitude'], $exif['GPS']['GPSLatitudeRef']);
                            $exifLng = self::exifGpsToDecimal($exif['GPS']['GPSLongitude'], $exif['GPS']['GPSLongitudeRef']);
                        }
                        $exifMeta = [
                            'gps_lat' => $exifLat,
                            'gps_lng' => $exifLng,
                            'datetime_original' => $exif['EXIF']['DateTimeOriginal'] ?? null,
                        ];

                        // Bandingkan EXIF GPS dengan koordinat yang dilaporkan
                        $reportedLat = $this->metadata['latitude'] ?? null;
                        $reportedLng = $this->metadata['longitude'] ?? null;
                        if ($exifLat !== null && $reportedLat !== null && $reportedLng !== null) {
                            $distance = self::haversine($exifLat, $exifLng, (float) $reportedLat, (float) $reportedLng);
                            if ($distance > 100) {
                                $exifMeta['mismatch'] = true;
                                $exifMeta['mismatch_distance_m'] = round($distance);
                                // Set flag spoofing
                                $presensi->lokasi_perlu_review = true;
                                $presensi->posisi_mencurigakan = true;
                            } else {
                                $exifMeta['mismatch'] = false;
                                $exifMeta['mismatch_distance_m'] = round($distance);
                            }
                        }
                        $presensi->exif_meta = $exifMeta;
                        $presensi->save();
                    }
                } catch (\Throwable $e) {
                    Log::warning('EXIF read gagal (non-fatal)', ['presensi_id' => $this->presensiId, 'error' => $e->getMessage()]);
                }
            }

            $base64 = 'data:image/jpeg;base64,'.base64_encode(file_get_contents($fullPath));

            $finalPath = $uploader->storeBase64(
                $base64,
                $this->subDir,
                $this->overlayData,
                PresensiMessages::MAX_FOTO_BYTES,
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

    /**
     * Konversi koordinat EXIF GPS (dd/mm/ss ref) ke decimal degrees.
     */
    private static function exifGpsToDecimal(array $coord, string $ref): float
    {
        $degrees = count($coord) > 0 ? self::exifRationalToFloat($coord[0]) : 0;
        $minutes = count($coord) > 1 ? self::exifRationalToFloat($coord[1]) : 0;
        $seconds = count($coord) > 2 ? self::exifRationalToFloat($coord[2]) : 0;
        $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);
        if ($ref === 'S' || $ref === 'W') {
            $decimal *= -1;
        }

        return $decimal;
    }

    /**
     * Konversi EXIF rational (array [numerator, denominator]) ke float.
     */
    private static function exifRationalToFloat($rational): float
    {
        if (is_array($rational) && count($rational) === 2) {
            return $rational[1] != 0 ? $rational[0] / $rational[1] : 0;
        }

        return (float) $rational;
    }

    /**
     * Haversine distance (meter) antara dua koordinat.
     */
    private static function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
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
