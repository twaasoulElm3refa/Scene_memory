<?php

namespace App\Http\Resources\ProfileTimeline;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimelineCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'text' => $this->comment,
            'created_at' => $this->created_at?->toISOString(),
            'images' => TimelineCommentImageResource::collection($this->whenLoaded('images')),
            'event' => $this->event ? new TimelineEventSummaryResource($this->event) : null,
        ];
    }
}
