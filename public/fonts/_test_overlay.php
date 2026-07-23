<?php

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\PhotoOverlayService;

// Create dummy image
$img = imagecreatetruecolor(640, 853);
imagefill($img, 0, 0, imagecolorallocate($img, 100, 120, 140));
imagestring($img, 5, 250, 400, 'TEST IMAGE', imagecolorallocate($img, 255, 255, 255));
ob_start();
imagejpeg($img, null, 70);
$original = ob_get_clean();
imagedestroy($img);

echo 'Original size: '.strlen($original)."\n";

// Apply overlay
$overlay = new PhotoOverlayService;
$data = [
    'label' => 'BUKTI PRESENSI',
    'is_lembur' => false,
    'pegawai' => 'Test Pegawai',
    'unit' => 'SMP Testing',
    'time' => '08:45:30 WIB',
    'date' => 'Senin, 22 Juni 2026',
    'coordinates' => '-6.912345, 107.609876',
    'distance' => '12 meter',
    'accuracy' => '8 meter',
];

try {
    $result = $overlay->applyToImage($original, $data);
    echo 'Result size: '.strlen($result)."\n";
    file_put_contents(__DIR__.'/_test_overlay.jpg', $result);
    echo "OK - written to public/fonts/_test_overlay.jpg\n";
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
    echo 'Trace: '.$e->getTraceAsString()."\n";
}
