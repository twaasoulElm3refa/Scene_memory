<?php

namespace App\Http\Resources\ProfileTimeline;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimelineCommentInteractionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type === 'Exhibitions' ? 'exhibitions' : $this->type,
            'created_at' => $this->created_at?->toISOString(),
            'comment' => $this->comment ? [
                'id' => $this->comment->id,
                'text' => $this->comment->comment,
                'created_at' => $this->comment->created_at?->toISOString(),
            ] : null,
            'event' => $this->comment?->event
                ? new TimelineEventSummaryResource($this->comment->event)
                : null,
        ];
    }
}
