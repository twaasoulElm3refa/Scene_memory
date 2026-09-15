<?php

namespace App\Http\Resources\ProfileTimeline;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimelineLikeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at?->toISOString(),
            'event' => $this->event ? new TimelineEventSummaryResource($this->event) : null,
        ];
    }
}
