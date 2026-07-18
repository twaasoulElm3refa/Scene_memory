<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class PhotoQualityService
{
    private const MIN_PROFESSIONAL_WIDTH = 720;
    private const MIN_PROFESSIONAL_HEIGHT = 720;
    private const MIN_PROFESSIONAL_SHARPNESS = 40;
    private const MAX_PROFESSIONAL_BLUR = 60;
    private const MIN_PROFESSIONAL_QUALITY = 45;

    public function validate(UploadedFile $photo, string $photographyType): array
    {
        $photographyType = strtolower(trim($photographyType));
        $errors = [];

        if (! in_array($photographyType, ['normal', 'professional'], true)) {
            $photographyType = 'normal';
        }

        if (! $photo->isValid()) {
            return $this->result(false, 'Photo rejected', [], ['Uploaded photo is not valid']);
        }

        $path = $photo->getRealPath();

        if (! $path || ! is_file($path)) {
            return $this->result(false, 'Photo rejected', [], ['Photo file could not be read']);
        }

        $imageSize = @getimagesize($path);

        if (! $imageSize || empty($imageSize[0]) || empty($imageSize[1])) {
            return $this->result(false, 'Photo rejected', [], ['Photo is unreadable or corrupted']);
        }

        $width = (int) $imageSize[0];
        $height = (int) $imageSize[1];
        $megapixels = round(($width * $height) / 1000000, 2);
        $fileSizeMb = round($photo->getSize() / (1024 * 1024), 2);

        $analysis = $this->analyzeSharpness($path);
        $sharpnessScore = $analysis['sharpness_score'];
        $blurScore = $analysis['blur_score'];

        $resolutionScore = $this->normalize($megapixels, 1, 12) * 100;
        $density = $fileSizeMb / max($megapixels, 0.1);
        $densityScore = $this->normalize($density, 0.12, 2.5) * 100;

        $qualityScore = round(
            ($resolutionScore * 0.35) +
            ($sharpnessScore * 0.45) +
            ($densityScore * 0.20),
            2
        );

        $metrics = [
            'width' => $width,
            'height' => $height,
            'megapixels' => $megapixels,
            'sharpness_score' => round($sharpnessScore, 2),
            'blur_score' => round($blurScore, 2),
            'quality_score' => $qualityScore,
            'file_size_mb' => $fileSizeMb,
        ];

        if ($photographyType === 'professional') {
            if ($width < self::MIN_PROFESSIONAL_WIDTH) {
                $errors[] = 'Minimum width is 720px';
            }

            if ($height < self::MIN_PROFESSIONAL_HEIGHT) {
                $errors[] = 'Minimum height is 720px';
            }

            if (! $analysis['available']) {
                $errors[] = 'Server sharpness analyzer is unavailable';
            } elseif ($sharpnessScore < self::MIN_PROFESSIONAL_SHARPNESS) {
                $errors[] = 'Image does not have acceptable sharpness';
            }

            if ($blurScore > self::MAX_PROFESSIONAL_BLUR) {
                $errors[] = 'Image appears blurry';
            }

            if ($qualityScore < self::MIN_PROFESSIONAL_QUALITY) {
                $errors[] = 'Image quality score is too low';
            }
        }

        return $this->result(
            empty($errors),
            empty($errors) ? 'Photo accepted' : 'Photo rejected',
            $metrics,
            $errors
        );
    }

    private function result(bool $accepted, string $message, array $metrics, array $errors): array
    {
        return [
            'accepted' => $accepted,
            'status' => $accepted ? 'accepted' : 'rejected',
            'message' => $message,
            'metrics' => $metrics,
            'suggested_price' => $accepted ? $this->suggestedPrice($metrics) : null,
            'errors' => $errors,
        ];
    }

    private function suggestedPrice(array $metrics): int
    {
        $score = (float) ($metrics['quality_score'] ?? 0);
        $megapixels = (float) ($metrics['megapixels'] ?? 0);
        $price = 10;

        if ($score >= 90) {
            $price = 80;
        } elseif ($score >= 80) {
            $price = 60;
        } elseif ($score >= 70) {
            $price = 45;
        } elseif ($score >= 60) {
            $price = 30;
        } elseif ($score >= 50) {
            $price = 20;
        }

        if ($megapixels >= 12) {
            $price += 15;
        } elseif ($megapixels >= 8) {
            $price += 10;
        } elseif ($megapixels >= 4) {
            $price += 5;
        }

        return max(10, (int) round($price));
    }

    private function analyzeSharpness(string $path): array
    {
        if (function_exists('imagecreatefromstring')) {
            return $this->analyzeWithGd($path);
        }

        if (class_exists(\Imagick::class)) {
            return $this->analyzeWithImagick($path);
        }

        // The fallback keeps basic image validation available while refusing
        // professional-only sharpness confidence in the caller.
        return [
            'available' => false,
            'sharpness_score' => 0,
            'blur_score' => 100,
        ];
    }

    private function analyzeWithGd(string $path): array
    {
        $contents = @file_get_contents($path);

        if ($contents === false || $contents === '') {
            return $this->unavailableAnalysis();
        }

        $source = @imagecreatefromstring($contents);

        if (! $source) {
            return $this->unavailableAnalysis();
        }

        $sample = $this->resizeGdImage($source, 320, 320);
        imagedestroy($source);

        if (! $sample) {
            return $this->unavailableAnalysis();
        }

        $width = imagesx($sample);
        $height = imagesy($sample);
        $total = 0;
        $count = 0;

        for ($y = 1; $y < $height - 1; $y++) {
            for ($x = 1; $x < $width - 1; $x++) {
                $center = $this->gdGrayAt($sample, $x, $y) * 4;
                $left = $this->gdGrayAt($sample, $x - 1, $y);
                $right = $this->gdGrayAt($sample, $x + 1, $y);
                $top = $this->gdGrayAt($sample, $x, $y - 1);
                $bottom = $this->gdGrayAt($sample, $x, $y + 1);

                $total += abs($center - $left - $right - $top - $bottom);
                $count++;
            }
        }

        imagedestroy($sample);

        return $this->scoresFromLaplacianAverage($count > 0 ? $total / $count : 0);
    }

    private function analyzeWithImagick(string $path): array
    {
        try {
            $image = new \Imagick($path);
            $image->thumbnailImage(320, 320, true);

            $width = $image->getImageWidth();
            $height = $image->getImageHeight();
            $pixels = $image->exportImagePixels(0, 0, $width, $height, 'RGB', \Imagick::PIXEL_CHAR);
            $image->clear();
            $image->destroy();

            if (! is_array($pixels) || $width < 3 || $height < 3) {
                return $this->unavailableAnalysis();
            }

            $gray = [];
            $pixelIndex = 0;

            for ($i = 0; $i < count($pixels); $i += 3) {
                $gray[$pixelIndex] = (0.299 * $pixels[$i]) +
                    (0.587 * $pixels[$i + 1]) +
                    (0.114 * $pixels[$i + 2]);
                $pixelIndex++;
            }

            $total = 0;
            $count = 0;

            for ($y = 1; $y < $height - 1; $y++) {
                for ($x = 1; $x < $width - 1; $x++) {
                    $idx = ($y * $width) + $x;
                    $center = $gray[$idx] * 4;

                    $total += abs(
                        $center -
                        $gray[$idx - 1] -
                        $gray[$idx + 1] -
                        $gray[$idx - $width] -
                        $gray[$idx + $width]
                    );
                    $count++;
                }
            }

            return $this->scoresFromLaplacianAverage($count > 0 ? $total / $count : 0);
        } catch (\Throwable) {
            return $this->unavailableAnalysis();
        }
    }

    private function resizeGdImage($source, int $maxWidth, int $maxHeight)
    {
        $width = imagesx($source);
        $height = imagesy($source);

        if ($width < 1 || $height < 1) {
            return null;
        }

        $ratio = min($maxWidth / $width, $maxHeight / $height, 1);
        $newWidth = max(1, (int) round($width * $ratio));
        $newHeight = max(1, (int) round($height * $ratio));

        $sample = imagecreatetruecolor($newWidth, $newHeight);

        imagecopyresampled(
            $sample,
            $source,
            0,
            0,
            0,
            0,
            $newWidth,
            $newHeight,
            $width,
            $height
        );

        return $sample;
    }

    private function gdGrayAt($image, int $x, int $y): float
    {
        $rgb = imagecolorat($image, $x, $y);

        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;

        return (0.299 * $r) + (0.587 * $g) + (0.114 * $b);
    }

    private function scoresFromLaplacianAverage(float $average): array
    {
        $sharpnessScore = min(100, max(0, ($average / 18) * 100));

        return [
            'available' => true,
            'sharpness_score' => $sharpnessScore,
            'blur_score' => 100 - $sharpnessScore,
        ];
    }

    private function unavailableAnalysis(): array
    {
        return [
            'available' => false,
            'sharpness_score' => 0,
            'blur_score' => 100,
        ];
    }

    private function normalize(float|int $value, float|int $min, float|int $max): float
    {
        if ($max === $min) {
            return 0;
        }

        return max(0, min(1, ($value - $min) / ($max - $min)));
    }
}
