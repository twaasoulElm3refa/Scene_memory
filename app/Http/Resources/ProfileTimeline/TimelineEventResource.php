<?php

namespace App\Http\Resources\ProfileTimeline;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class TimelineEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $image = $this->whenLoaded('coverImage');
        $translation = $this->whenLoaded('translation');
        $creationRequest = $this->whenLoaded('requests');
        $subCategory = $this->whenLoaded('sub_categorey');
        $category = $subCategory?->relationLoaded('category') ? $subCategory->category : null;
        $city = $this->whenLoaded('city');
        $country = $city?->relationLoaded('countries') ? $city->countries : null;

        return [
            'id' => $this->id,
            'title' => $translation?->title ?: $this->title,
            'slug' => $this->slug,
            'image' => $image ? [
                'id' => $image->id,
                'url' => $this->storageUrl($image->preview_url ?: $image->full_url),
                'type' => $image->type,
            ] : ($this->image ? [
                'id' => null,
                'url' => $this->storageUrl($this->image),
                'type' => 'image',
            ] : null),
            'status' => $creationRequest?->status ?: ($this->is_active ? 'active' : 'inactive'),
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'category' => $category ? [
                'id' => $category->id,
                'name' => $category->translation?->name ?: $category->name,
            ] : null,
            'sub_category' => $subCategory ? [
                'id' => $subCategory->id,
                'name' => $subCategory->translation?->name ?: $subCategory->name,
            ] : null,
            'location' => [
                'city' => $city ? [
                    'id' => $city->id,
                    'name' => $city->translation?->name ?: $city->name,
                ] : null,
                'country' => $country ? [
                    'id' => $country->id,
                    'code' => $country->code,
                    'name' => $country->translation?->name ?: $country->name,
                ] : null,
                'latitude' => $this->lattitude,
                'longitude' => $this->langitude,
            ],
        ];
    }

    private function storageUrl(?string $path): ?string
    {
        if (! $path || str_starts_with($path, '/') || filter_var($path, FILTER_VALIDATE_URL)) {
            return $path ?: null;
        }

        return Storage::disk('public')->url($path);
    }
}
