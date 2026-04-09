<?php

namespace App\Services;

use Intervention\Image\Typography\FontFactory;

class ImageAnalysisService
{
    public function process($file, $manager)
    {
        $image = $manager->read($file);

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

    /**
     * 🔥 نظام التسعير الجديد (Dynamic)
     */
    private function pricing($resolutionScore, $qualityScore)
    {
        // 🧠 المعادلة الأساسية
        $price = (
            ($qualityScore * 0.12) +   // جودة الصورة
            ($resolutionScore * 2.5)   // الدقة
        );

        // 🛡️ حد أدنى وحد أقصى
        $price = max(2, min($price, 50));

        $price = round($price, 2);

        // 📦 تحديد الباقة (للعرض فقط)
        $plan = match (true) {
            $price < 5 => 'free',
            $price < 10 => 'basic',
            $price < 20 => 'pro',
            default => 'premium',
        };

        return [$price, $plan];
    }

    /**
     * 🎯 تحويل الدقة إلى score
     */
    private function getResolutionScore($resolution)
    {
        return match ($resolution) {
            '720p' => 1,
            '1080p' => 2,
            '2K' => 3,
            '4K' => 4,
            default => 1,
        };
    }

    private function getResolutionLabel($width, $height)
    {
        $pixels = $width * $height;

        return match (true) {
            $pixels <= 921600 => '720p',
            $pixels <= 2073600 => '1080p',
            $pixels <= 3686400 => '2K',
            default => '4K',
        };
    }

    /**
     * 🧠 تحليل الجودة
     */
    private function analyze($file)
    {
        $realPath = $file->getRealPath();
        $content = file_get_contents($realPath);

        $img = imagecreatefromstring($content);

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

    private function sharpness($img)
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

        if ($count === 0) return 0;

        $avg = $total / $count;

        return min(100, round(($avg / 40) * 100, 2));
    }

    private function noise($img)
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

        if ($count === 0) return 0;

        $avgNoise = $totalDiff / $count;

        return min(100, round(($avgNoise / 30) * 100, 2));
    }

    private function upscale($img)
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

        if ($count === 0) return 0;

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

    private function grayAt($img, $x, $y)
    {
        $rgb = imagecolorat($img, $x, $y);

        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;

        return (0.299 * $r) + (0.587 * $g) + (0.114 * $b);
    }
    private function makePreview($image): \Intervention\Image\EncodedImage
    {
        // Blur خفيف
        $image->blur(6);

        // Watermark نص في المنتصف
        $image->text('© Protected', $image->width() / 2, $image->height() / 2, function (FontFactory $font) {
            $font->size(42);
            $font->color([255, 255, 255, 100]); // أبيض شبه شفاف
            $font->align('center');
            $font->valign('middle');
            $font->angle(30);
        });

        return $image->toJpeg(75);
    }
}
