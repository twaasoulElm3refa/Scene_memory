<?php

namespace App\Http\Resources\ProfileTimeline;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimelineReplyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'text' => $this->comment,
            'created_at' => $this->created_at?->toISOString(),
            'comment' => $this->commentRelation ? [
                'id' => $this->commentRelation->id,
                'text' => $this->commentRelation->comment,
                'created_at' => $this->commentRelation->created_at?->toISOString(),
            ] : null,
            'event' => $this->commentRelation?->event
                ? new TimelineEventSummaryResource($this->commentRelation->event)
                : null,
        ];
    }
}
