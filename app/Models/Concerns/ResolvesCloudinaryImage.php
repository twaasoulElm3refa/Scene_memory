<?php

namespace App\Models\Concerns;

use App\Services\UnifiedMediaStorage;

trait ResolvesCloudinaryImage
{
    public function getImageAttribute(?string $value): ?string
    {
        $storage = app(UnifiedMediaStorage::class);
        return $storage->isCloudinary($value) ? $storage->url($value) : $value;
    }
}
