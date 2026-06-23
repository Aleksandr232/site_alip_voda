<?php

declare(strict_types=1);

header('Content-Type: image/png');
header('Cache-Control: public, max-age=604800');

if (!extension_loaded('gd')) {
    readfile(__DIR__ . '/favicon.svg');
    exit;
}

$size = 180;
$image = imagecreatetruecolor($size, $size);
imagealphablending($image, true);
imagesavealpha($image, true);

$transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
imagefill($image, 0, 0, $transparent);

$white = imagecolorallocate($image, 255, 255, 255);

for ($y = 0; $y < $size; $y++) {
    for ($x = 0; $x < $size; $x++) {
        $ratio = ($x + $y) / ($size * 2);
        $r = (int) (11 + (0 - 11) * $ratio);
        $g = (int) (110 + (180 - 110) * $ratio);
        $b = (int) (188 + (216 - 188) * $ratio);
        $color = imagecolorallocate($image, max(0, min(255, $r)), max(0, min(255, $g)), max(0, min(255, $b)));
        imagesetpixel($image, $x, $y, $color);
    }
}

$triangle = [
    (int) round($size * 0.5), (int) round($size * 0.25),
    (int) round($size * 0.78), (int) round($size * 0.74),
    (int) round($size * 0.22), (int) round($size * 0.74),
];
imagefilledpolygon($image, $triangle, $white);

imagepng($image, null, 9);
imagedestroy($image);
