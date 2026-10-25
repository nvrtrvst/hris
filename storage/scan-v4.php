<?php
$src = 'D:\yayasan-apps\hris\storage\card-v4.png';
$img = imagecreatefrompng($src);
$w = imagesx($img); $h = imagesy($img);
echo "image: {$w}x{$h}\n";

// Scan for emerald/green marker pixel (brand color: 16, 185, 129)
$found = [];
for ($y = 0; $y < $h; $y += 1) {
    for ($x = (int)($w * 0.5); $x < $w; $x += 1) {
        $rgb = imagecolorat($img, $x, $y);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;
        // emerald: g high, r low-medium, b low
        if ($g > 140 && $r < 60 && $b < 170 && $g > $r) {
            $found[] = [$x, $y];
        }
    }
}
if ($found) {
    $xs = array_column($found, 0);
    $ys = array_column($found, 1);
    echo "emerald marker: x=[".min($xs)."-".max($xs)."] y=[".min($ys)."-".max($ys)."]\n";
    echo "marker center: " . (int)round((min($xs)+max($xs))/2) . "," . (int)round((min($ys)+max($ys))/2) . "\n";
}

// Scan for "LOKASI" green text (emerald-600: 5, 150, 105)
$found2 = [];
for ($y = (int)($h * 0.6); $y < $h; $y += 1) {
    for ($x = (int)($w * 0.5); $x < $w; $x += 1) {
        $rgb = imagecolorat($img, $x, $y);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;
        if ($g > 100 && $r < 30 && $b < 150 && $g > $r * 3) {
            $found2[] = [$x, $y];
        }
    }
}
if ($found2) {
    $xs = array_column($found2, 0);
    $ys = array_column($found2, 1);
    echo "emerald text: x=[".min($xs)."-".max($xs)."] y=[".min($ys)."-".max($ys)."]\n";
}

// Scan white rect boundary in bottom-right
$whiteRows = [];
for ($y = (int)($h * 0.5); $y < $h; $y += 1) {
    $whiteCount = 0;
    for ($x = (int)($w * 0.6); $x < $w; $x += 1) {
        $rgb = imagecolorat($img, $x, $y);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;
        if ($r > 240 && $g > 240 && $b > 240) $whiteCount++;
    }
    if ($whiteCount > 10) $whiteRows[] = $y;
}
if ($whiteRows) {
    echo "white rows: y=[".min($whiteRows)."-".max($whiteRows)."] (".count($whiteRows)." rows)\n";
}

imagedestroy($img);
