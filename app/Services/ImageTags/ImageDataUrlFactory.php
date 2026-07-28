<?php

namespace App\Services\ImageTags;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class ImageDataUrlFactory
{
    public function __construct(
        private readonly ConfigRepository $config,
        private readonly FilesystemFactory $filesystem
    ) {}

    /**
     * @param  array<int, UploadedFile>  $images
     * @return array<int, string>
     */
    public function fromUploadedImages(array $images): array
    {
        return collect($images)
            ->take($this->imagesLimit())
            ->map(fn (UploadedFile $image) => $this->uploadedImageToDataUrl($image))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $storedPaths
     * @return array<int, string>
     */
    public function fromStoredPaths(array $storedPaths): array
    {
        $disk = $this->filesystem->disk('public');
        $imageDataUrls = [];

        foreach (array_slice($storedPaths, 0, $this->imagesLimit()) as $path) {
            $path = ltrim(trim((string) $path), '/');

            if ($path === '' || ! $disk->exists($path)) {
                continue;
            }

            $contents = $disk->get($path);
            $mimeType = (string) $disk->mimeType($path);

            if ($contents === '' || ! str_starts_with($mimeType, 'image/')) {
                continue;
            }

            $imageDataUrls[] = "data:{$mimeType};base64,".base64_encode($contents);
        }

        return $imageDataUrls;
    }

    private function uploadedImageToDataUrl(UploadedFile $image): string
    {
        $mimeType = $image->getMimeType() ?: 'image/jpeg';
        $path = $image->getRealPath();

        if ($path === false || ! is_file($path)) {
            throw new RuntimeException('Uploaded image could not be read.');
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('Uploaded image could not be read.');
        }

        return "data:{$mimeType};base64,".base64_encode($contents);
    }

    private function imagesLimit(): int
    {
        return max(0, (int) $this->config->get('ai_tags.images_limit', 5));
    }
}
