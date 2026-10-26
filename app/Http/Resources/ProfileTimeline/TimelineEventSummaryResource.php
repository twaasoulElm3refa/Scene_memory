<?php

namespace App\Http\Resources\ProfileTimeline;

use App\Models\Events;
use App\Services\MediaAccessService;
use App\Support\MediaParents;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class TimelineEventSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof Events) {
            // The cover image's preview URL reads this event; avoid a query per event.
            MediaParents::adopt($this->resource);
        }
        $image = $this->whenLoaded('coverImage');
        $translation = $this->whenLoaded('translation');
        $creationRequest = $this->whenLoaded('requests');

        return [
            'id' => $this->id,
            'title' => $translation?->title ?: $this->title,
            'slug' => $this->slug,
            'image_url' => $image ? app(MediaAccessService::class)->previewUrl($image, true) : $this->storageUrl($this->image),
            'status' => $creationRequest?->status ?: ($this->is_active ? 'active' : 'inactive'),
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
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
