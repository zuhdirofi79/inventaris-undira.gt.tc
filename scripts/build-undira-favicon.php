<?php

/**
 * Build browser favicons from the UNDIRA symbol-only source.
 *
 * Source (do not delete): public/images/LOGO UNDIRA (1) (1).png
 * Primary output:        public/images/favicon-undira.png
 */

$sourcePath = __DIR__.'/../public/images/LOGO UNDIRA (1) (1).png';
$outDir = __DIR__.'/../public/images';
$publicRoot = __DIR__.'/../public';

if (! is_file($sourcePath)) {
    fwrite(STDERR, "Source not found: {$sourcePath}\n");
    exit(1);
}

$src = @imagecreatefrompng($sourcePath);
if (! $src) {
    fwrite(STDERR, "Cannot open source PNG\n");
    exit(1);
}

$w = imagesx($src);
$h = imagesy($src);

$minX = $w;
$minY = $h;
$maxX = 0;
$maxY = 0;

for ($y = 0; $y < $h; $y++) {
    for ($x = 0; $x < $w; $x++) {
        $rgb = imagecolorat($src, $x, $y);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;
        // Treat near-black as background.
        if (($r + $g + $b) > 45) {
            $minX = min($minX, $x);
            $maxX = max($maxX, $x);
            $minY = min($minY, $y);
            $maxY = max($maxY, $y);
        }
    }
}

if ($maxX < $minX) {
    fwrite(STDERR, "No opaque symbol pixels found\n");
    exit(1);
}

$symW = $maxX - $minX + 1;
$symH = $maxY - $minY + 1;
$side = max($symW, $symH);
$pad = (int) max(8, round($side * 0.10));
$canvas = $side + ($pad * 2);

$square = imagecreatetruecolor($canvas, $canvas);
imagesavealpha($square, true);
imagealphablending($square, false);
$transparent = imagecolorallocatealpha($square, 0, 0, 0, 127);
imagefill($square, 0, 0, $transparent);

$dstX = (int) (($canvas - $symW) / 2);
$dstY = (int) (($canvas - $symH) / 2);

imagealphablending($square, true);
for ($y = 0; $y < $symH; $y++) {
    for ($x = 0; $x < $symW; $x++) {
        $rgb = imagecolorat($src, $minX + $x, $minY + $y);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;
        if (($r + $g + $b) < 40) {
            continue;
        }
        imagesetpixel($square, $dstX + $x, $dstY + $y, imagecolorallocatealpha($square, $r, $g, $b, 0));
    }
}

/**
 * @return GdImage
 */
function scaleFavicon(GdImage $square, int $size, int $canvas): GdImage
{
    $scaled = imagecreatetruecolor($size, $size);
    imagesavealpha($scaled, true);
    imagealphablending($scaled, false);
    $t = imagecolorallocatealpha($scaled, 0, 0, 0, 127);
    imagefill($scaled, 0, 0, $t);
    imagecopyresampled($scaled, $square, 0, 0, 0, 0, $size, $size, $canvas, $canvas);

    for ($yy = 0; $yy < $size; $yy++) {
        for ($xx = 0; $xx < $size; $xx++) {
            $rgba = imagecolorat($scaled, $xx, $yy);
            $a = ($rgba & 0x7F000000) >> 24;
            $r = ($rgba >> 16) & 0xFF;
            $g = ($rgba >> 8) & 0xFF;
            $b = $rgba & 0xFF;
            if ($a > 100 || ($r + $g + $b) < 35) {
                imagesetpixel($scaled, $xx, $yy, $t);
            } else {
                imagesetpixel($scaled, $xx, $yy, imagecolorallocatealpha($scaled, $r, $g, $b, 0));
            }
        }
    }

    imagesavealpha($scaled, true);

    return $scaled;
}

$named = [
    16 => 'favicon-16.png',
    32 => 'favicon-undira-32.png',
    48 => 'favicon-48.png',
    64 => 'favicon-64.png',
    180 => 'favicon-undira-180.png',
    192 => 'favicon-undira-192.png',
    512 => 'favicon-undira-512.png',
];

$generated = [];
foreach ($named as $size => $filename) {
    $scaled = scaleFavicon($square, $size, $canvas);
    $path = $outDir.'/'.$filename;
    imagepng($scaled, $path);
    $generated[$size] = $path;
    imagedestroy($scaled);
    echo "Wrote {$path}\n";
}

// Primary normalized favicon (sharp mid-size).
copy($generated[192], $outDir.'/favicon-undira.png');
echo "Wrote {$outDir}/favicon-undira.png\n";

// Legacy aliases used by older references — keep in sync so nothing old remains.
copy($generated[32], $outDir.'/favicon-32.png');
copy($generated[32], $outDir.'/favicon.png');
copy($generated[180], $outDir.'/favicon-180.png');
copy($generated[192], $outDir.'/favicon-192.png');
echo "Synced legacy favicon.png / favicon-32 / favicon-180 / favicon-192\n";

$png16 = file_get_contents($generated[16]);
$png32 = file_get_contents($generated[32]);
writePngIco($publicRoot.'/favicon.ico', [
    ['w' => 16, 'h' => 16, 'data' => $png16],
    ['w' => 32, 'h' => 32, 'data' => $png32],
]);
echo "Wrote {$publicRoot}/favicon.ico\n";

echo "Symbol bbox: {$minX},{$minY} - {$maxX},{$maxY} on {$w}x{$h}\n";
echo "Canvas: {$canvas}x{$canvas}, pad={$pad}\n";

imagedestroy($square);
imagedestroy($src);

/**
 * @param  list<array{w:int,h:int,data:string}>  $images
 */
function writePngIco(string $path, array $images): void
{
    $count = count($images);
    $header = pack('vvv', 0, 1, $count);
    $offset = 6 + (16 * $count);
    $directory = '';
    $blob = '';

    foreach ($images as $img) {
        $size = strlen($img['data']);
        $wByte = $img['w'] >= 256 ? 0 : $img['w'];
        $hByte = $img['h'] >= 256 ? 0 : $img['h'];
        $directory .= pack('CCCCvvVV', $wByte, $hByte, 0, 0, 1, 32, $size, $offset);
        $offset += $size;
        $blob .= $img['data'];
    }

    file_put_contents($path, $header.$directory.$blob);
}
