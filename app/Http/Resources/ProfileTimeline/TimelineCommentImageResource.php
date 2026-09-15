<?php

namespace App\Http\Resources\ProfileTimeline;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimelineCommentImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $comment = $this->relationLoaded('comment') ? $this->comment : null;

        return [
            'id' => $this->id,
            'url' => $this->url,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'created_at' => $this->created_at?->toISOString(),
            'comment' => $comment ? [
                'id' => $comment->id,
                'text' => $comment->comment,
                'created_at' => $comment->created_at?->toISOString(),
            ] : null,
            'event' => $comment?->event ? new TimelineEventSummaryResource($comment->event) : null,
        ];
    }
}
