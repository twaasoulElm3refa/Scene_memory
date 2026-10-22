<?php

namespace App\Services;

use App\Models\EventsImges;
use App\Models\MediaTier;
use InvalidArgumentException;

class MediaTierService
{
    /**
     * Handle static calls dynamically by delegating to container instance.
     */
    public static function __callStatic(string $method, array $parameters)
    {
        return app(self::class)->$method(...$parameters);
    }

    /**
     * Classify media into a MediaTier based on type and price.
     * Returns null if price exceeds the highest configured eligible ceiling.
     */
    public function classify(string $type, float $price): ?MediaTier
    {
        if ($price < 0) {
            throw new InvalidArgumentException('Price cannot be negative.');
        }

        $isVideo = in_array(strtolower($type), ['video', 'videos', 'mp4'], true);
        $minCol = $isVideo ? 'min_price_video' : 'min_price_photo';
        $maxCol = $isVideo ? 'max_price_video' : 'max_price_photo';

        // Tiers ordered by rank
        $tiers = MediaTier::query()
            ->where('is_active', true)
            ->orderBy('rank', 'asc')
            ->get();

        foreach ($tiers as $tier) {
            $min = (float) $tier->{$minCol};
            $max = (float) $tier->{$maxCol};

            if ($price >= $min && $price <= $max) {
                return $tier;
            }
        }

        return null;
    }

    /**
     * Assign or update media tier classification on an EventsImges model.
     */
    public function assignTierToMedia(EventsImges $media, ?float $newPrice = null): EventsImges
    {
        $price = $newPrice !== null ? $newPrice : (float) $media->price;
        $tier = $this->classify($media->type ?: 'image', $price);

        $media->price = $price;
        $media->media_tier_id = $tier?->id;
        $media->is_plan_redeemable = ($tier !== null && (bool) $tier->is_plan_eligible);
        $media->save();

        return $media;
    }

    /**
     * Validate that tier price ranges are ordered, positive, and non-overlapping.
     */
    public function validateTiersIntegrity(): bool
    {
        $tiers = MediaTier::orderBy('rank', 'asc')->get();
        $prevMaxPhoto = -0.01;
        $prevMaxVideo = -0.01;

        foreach ($tiers as $tier) {
            if ($tier->min_price_photo > $tier->max_price_photo) {
                return false;
            }
            if ($tier->min_price_video > $tier->max_price_video) {
                return false;
            }
            if ($tier->min_price_photo <= $prevMaxPhoto) {
                return false;
            }
            if ($tier->min_price_video <= $prevMaxVideo) {
                return false;
            }

            $prevMaxPhoto = (float) $tier->max_price_photo;
            $prevMaxVideo = (float) $tier->max_price_video;
        }

        return true;
    }
}
