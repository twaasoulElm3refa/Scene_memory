<?php

/*
 * Builds AVIF and WebP copies of the homepage tab photos at several widths, next to
 * the untouched PNG originals, and reports size and PSNR against the original.
 * Re-runnable; requires PHP GD with WebP and AVIF support.
 *
 *   php scripts/optimize-home-images.php
 */

const WIDTHS = [960, 1600, 2624];
const WEBP_QUALITY = 86;
const AVIF_QUALITY = 68;
const AVIF_SPEED = 4;

$root = dirname(__DIR__).'/public/images';
$out = $root.'/optimized';
if (! function_exists('imagewebp') || ! function_exists('imageavif')) {
    fwrite(STDERR, "GD needs WebP and AVIF support.\n");
    exit(1);
}
if (! is_dir($out)) {
    mkdir($out, 0755, true);
}

function psnr(GdImage $a, GdImage $b): float
{
    $width = imagesx($a);
    $height = imagesy($a);
    $sum = 0.0;
    $samples = 0;
    // Every 3rd pixel in both directions: enough for a stable estimate.
    for ($y = 0; $y < $height; $y += 3) {
        for ($x = 0; $x < $width; $x += 3) {
            $p = imagecolorat($a, $x, $y);
            $q = imagecolorat($b, $x, $y);
            foreach ([16, 8, 0] as $shift) {
                $d = (($p >> $shift) & 0xFF) - (($q >> $shift) & 0xFF);
                $sum += $d * $d;
            }
            $samples += 3;
        }
    }
    $mse = $sum / max(1, $samples);

    return $mse == 0 ? 99.0 : 10 * log10(255 * 255 / $mse);
}

foreach (['photo1', 'photo2', 'photo3'] as $name) {
    $source = imagecreatefrompng("{$root}/{$name}.png");
    imagepalettetotruecolor($source);
    imagealphablending($source, true);
    $sourceWidth = imagesx($source);
    $sourceHeight = imagesy($source);
    printf("%s.png %dx%d %.0f KB\n", $name, $sourceWidth, $sourceHeight, filesize("{$root}/{$name}.png") / 1024);

    foreach (WIDTHS as $width) {
        $width = min($width, $sourceWidth);
        $height = (int) round($sourceHeight * $width / $sourceWidth);
        $scaled = imagecreatetruecolor($width, $height);
        imagecopyresampled($scaled, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);

        $webp = "{$out}/{$name}-{$width}.webp";
        $avif = "{$out}/{$name}-{$width}.avif";
        imagewebp($scaled, $webp, WEBP_QUALITY);
        imageavif($scaled, $avif, AVIF_QUALITY, AVIF_SPEED);

        $webpPsnr = psnr($scaled, imagecreatefromwebp($webp));
        $avifPsnr = psnr($scaled, imagecreatefromavif($avif));
        printf("  %4dw  webp %4.0f KB (PSNR %.1f dB)  avif %4.0f KB (PSNR %.1f dB)\n",
            $width, filesize($webp) / 1024, $webpPsnr, filesize($avif) / 1024, $avifPsnr);
    }
}
