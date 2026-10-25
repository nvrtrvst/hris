<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Dummy tile 256x256 (light gray + roads)
$tile = imagecreatetruecolor(256, 256);
$tileBg = imagecolorallocate($tile, 243, 244, 246);
imagefilledrectangle($tile, 0, 0, 255, 255, $tileBg);
$road = imagecolorallocate($tile, 255, 255, 255);
imagefilledrectangle($tile, 0, 118, 255, 138, $road);
imagefilledrectangle($tile, 118, 0, 138, 255, $road);
$label = imagecolorallocate($tile, 156, 163, 175);
imagefilledrectangle($tile, 20, 20, 90, 40, $label);
imagefilledrectangle($tile, 160, 180, 230, 200, $label);
ob_start(); imagepng($tile); $tileData = ob_get_clean(); imagedestroy($tile);

// Dummy photo portrait 720x1280
$photo = imagecreatetruecolor(720, 1280);
$bg = imagecolorallocate($photo, 70, 110, 190);
imagefilledrectangle($photo, 0, 0, 719, 1279, $bg);
$skin = imagecolorallocate($photo, 200, 180, 160);
imagefilledellipse($photo, 360, 400, 200, 200, $skin);
imagefilledrectangle($photo, 260, 500, 460, 900, $skin);
ob_start(); imagejpeg($photo, null, 80); $jpeg = ob_get_clean(); imagedestroy($photo);

Illuminate\Support\Facades\Http::fake([
    '*.basemaps.cartocdn.com/*' => Illuminate\Support\Facades\Http::response($tileData, 200),
    '*tile.openstreetmap.org/*' => Illuminate\Support\Facades\Http::response($tileData, 200),
]);

$svc = new App\Services\PhotoOverlayService();
$out = $svc->applyToImage($jpeg, [
    'latitude' => -7.197421, 'longitude' => 107.895458,
    'accuracy' => '12m', 'radius_status' => true,
    'label' => 'BUKTI PRESENSI', 'time' => '07:32:15 WIB',
    'date' => 'Sabtu, 12 Sep 2026',
    'pegawai' => 'Budi Santoso', 'unit' => 'SMK Nurul Muttaqiin',
    'coordinates' => '-7.197421, 107.895458',
    'kecamatan' => 'Cidahu', 'kelurahan' => 'Pasawahan', 'kabupaten' => 'Sukabumi',
]);

$dst = 'D:\yayasan-apps\hris\storage\card-v4.png';
$img = imagecreatefromstring($out);
imagepng($img, $dst);
imagedestroy($img);
echo "saved: $dst (" . round(filesize($dst)/1024, 1) . "kb)\n";
