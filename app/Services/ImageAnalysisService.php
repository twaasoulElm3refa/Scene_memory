<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Intervention\Image\ImageManager;
use Intervention\Image\EncodedImage;

class ImageAnalysisService
{
    public function process(UploadedFile $file, ImageManager $manager): array
    {
        if (!$file->isValid()) {
            throw new \RuntimeException('Uploaded file is not valid.');
        }

        $realPath = $file->getRealPath();

        if (!$realPath || !is_string($realPath) || !file_exists($realPath)) {
            throw new \RuntimeException('Image temp path is invalid.');
        }

        try {
            $image = $manager->read($realPath);
        } catch (\Throwable $e) {
            \Log::error('Image decode failed', [
                'original_name' => $file->getClientOriginalName(),
                'mime'          => $file->getMimeType(),
                'extension'     => $file->getClientOriginalExtension(),
                'real_path'     => $realPath,
                'error'         => $e->getMessage(),
            ]);

            throw new \RuntimeException(
                'Unsupported or corrupted image: ' . $file->getClientOriginalName()
            );
        }

        $width = $image->width();
        $height = $image->height();

        $resolution = $this->getResolutionLabel($width, $height);
        $resolutionScore = $this->getResolutionScore($resolution);
        $qualityScore = $this->analyze($file);

        [$price, $plan] = $this->pricing($resolutionScore, $qualityScore);

        $previewEncoded = $this->makePreview(clone $image);

        return [
            'image'            => $image,
            'preview_encoded'  => $previewEncoded,
            'width'            => $width,
            'height'           => $height,
            'resolution'       => $resolution,
            'resolution_score' => $resolutionScore,
            'quality_score'    => $qualityScore,
            'price'            => $price,
            'plan'             => $plan,
        ];
    }

    private function pricing($resolutionScore, $qualityScore): array
    {
        $price = (
            ($qualityScore * 0.12) +
            ($resolutionScore * 2.5)
        );

        $price = max(2, min($price, 50));
        $price = round($price, 2);

        $plan = match (true) {
            $price < 5 => 'free',
            $price < 10 => 'basic',
            $price < 20 => 'pro',
            default => 'premium',
        };

        return [$price, $plan];
    }

    private function getResolutionScore($resolution): int
    {
        return match ($resolution) {
            '720p' => 1,
            '1080p' => 2,
            '2K' => 3,
            '4K' => 4,
            default => 1,
        };
    }

    private function getResolutionLabel($width, $height): string
    {
        $pixels = $width * $height;

        return match (true) {
            $pixels <= 921600 => '720p',
            $pixels <= 2073600 => '1080p',
            $pixels <= 3686400 => '2K',
            default => '4K',
        };
    }

    private function analyze(UploadedFile $file): float|int
    {
        $realPath = $file->getRealPath();

        if (!$realPath || !file_exists($realPath)) {
            return 50;
        }

        $content = @file_get_contents($realPath);
        if ($content === false) {
            return 50;
        }

        $img = @imagecreatefromstring($content);

        if (!$img) {
            return 50;
        }

        $sharpness = $this->sharpness($img);
        $noise = $this->noise($img);
        $upscale = $this->upscale($img);

        imagedestroy($img);

        return round(
            ($sharpness * 0.4) +
            ((100 - $noise) * 0.3) +
            ((100 - $upscale) * 0.3),
            2
        );
    }

    private function sharpness($img): float|int
    {
        $sample = $this->resizeForAnalysis($img, 200, 200);

        $width = imagesx($sample);
        $height = imagesy($sample);

        $total = 0;
        $count = 0;

        for ($y = 1; $y < $height - 1; $y++) {
            for ($x = 1; $x < $width - 1; $x++) {
                $c = $this->grayAt($sample, $x, $y);
                $l = $this->grayAt($sample, $x - 1, $y);
                $r = $this->grayAt($sample, $x + 1, $y);
                $u = $this->grayAt($sample, $x, $y - 1);
                $d = $this->grayAt($sample, $x, $y + 1);

                $laplacian = abs((4 * $c) - $l - $r - $u - $d);

                $total += $laplacian;
                $count++;
            }
        }

        imagedestroy($sample);

        if ($count === 0) {
            return 0;
        }

        $avg = $total / $count;

        return min(100, round(($avg / 40) * 100, 2));
    }

    private function noise($img): float|int
    {
        $sample = $this->resizeForAnalysis($img, 200, 200);

        $width = imagesx($sample);
        $height = imagesy($sample);

        $totalDiff = 0;
        $count = 0;

        for ($y = 1; $y < $height - 1; $y++) {
            for ($x = 1; $x < $width - 1; $x++) {
                $center = $this->grayAt($sample, $x, $y);

                $neighbors = [
                    $this->grayAt($sample, $x - 1, $y),
                    $this->grayAt($sample, $x + 1, $y),
                    $this->grayAt($sample, $x, $y - 1),
                    $this->grayAt($sample, $x, $y + 1),
                ];

                $localMean = array_sum($neighbors) / 4;
                $totalDiff += abs($center - $localMean);
                $count++;
            }
        }

        imagedestroy($sample);

        if ($count === 0) {
            return 0;
        }

        $avgNoise = $totalDiff / $count;

        return min(100, round(($avgNoise / 30) * 100, 2));
    }

    private function upscale($img): float|int
    {
        $sample = $this->resizeForAnalysis($img, 256, 256);

        $width = imagesx($sample);
        $height = imagesy($sample);

        $blockiness = 0;
        $count = 0;

        for ($y = 8; $y < $height; $y += 8) {
            for ($x = 0; $x < $width; $x++) {
                $a = $this->grayAt($sample, $x, $y - 1);
                $b = $this->grayAt($sample, $x, $y);
                $blockiness += abs($a - $b);
                $count++;
            }
        }

        for ($x = 8; $x < $width; $x += 8) {
            for ($y = 0; $y < $height; $y++) {
                $a = $this->grayAt($sample, $x - 1, $y);
                $b = $this->grayAt($sample, $x, $y);
                $blockiness += abs($a - $b);
                $count++;
            }
        }

        imagedestroy($sample);

        if ($count === 0) {
            return 0;
        }

        $avgBlockiness = $blockiness / $count;

        return min(100, round(($avgBlockiness / 25) * 100, 2));
    }

    private function resizeForAnalysis($img, $maxWidth, $maxHeight)
    {
        $width = imagesx($img);
        $height = imagesy($img);

        $ratio = min($maxWidth / $width, $maxHeight / $height, 1);

        $newWidth = max(1, (int) round($width * $ratio));
        $newHeight = max(1, (int) round($height * $ratio));

        $resized = imagecreatetruecolor($newWidth, $newHeight);

        imagecopyresampled(
            $resized,
            $img,
            0,
            0,
            0,
            0,
            $newWidth,
            $newHeight,
            $width,
            $height
        );

        return $resized;
    }

    private function grayAt($img, $x, $y): float
    {
        $rgb = imagecolorat($img, $x, $y);

        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;

        return (0.299 * $r) + (0.587 * $g) + (0.114 * $b);
    }

    private function makePreview($image): EncodedImage
    {
        $image->blur(12);

        $watermark = ImageManager::gd()->read(public_path('images/watermark.png'));

        $watermark->scale(
            width: $image->width() * 0.75,
            height: $image->height() * 0.55
        );

        $image->place($watermark, 'center', 0, 0, 50);

        return $image->toJpeg(75);
    }
}
