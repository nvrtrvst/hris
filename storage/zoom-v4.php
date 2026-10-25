<?php
$src = 'D:\yayasan-apps\hris\storage\card-v4.png';
$img = imagecreatefrompng($src);
$w = imagesx($img); $h = imagesy($img);

// Find any green-ish pixel (emerald text 5,150,105 or marker 16,185,129)
$found = [];
for ($y = 0; $y < $h; $y++) {
    for ($x = 0; $x < $w; $x++) {
        $rgb = imagecolorat($img, $x, $y);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;
        // greenish: g > 100, g >> r, g > b
        if ($g > 100 && $g > $r * 2 && $g > $b) {
            $found[] = [$x, $y, $r, $g, $b];
        }
    }
}

echo "green-ish pixels found: " . count($found) . "\n";
if ($found) {
    // Show first 10 and last 10
    $first = array_slice($found, 0, 5);
    $last = array_slice($found, -5);
    echo "first:\n";
    foreach ($first as [$x,$y,$r,$g,$b]) echo "  ($x,$y) rgb=($r,$g,$b)\n";
    echo "last:\n";
    foreach ($last as [$x,$y,$r,$g,$b]) echo "  ($x,$y) rgb=($r,$g,$b)\n";

    $xs = array_column($found, 0);
    $ys = array_column($found, 1);
    echo "bounds: x=[".min($xs)."-".max($xs)."] y=[".min($ys)."-".max($ys)."]\n";
}

imagedestroy($img);
