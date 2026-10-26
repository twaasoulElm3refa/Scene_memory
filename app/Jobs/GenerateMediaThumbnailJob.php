<?php

namespace App\Jobs;

use App\Models\EventsImges;
use App\Services\MediaThumbnailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Builds one media item's thumbnail; repeated dispatches for the same item collapse. */
class GenerateMediaThumbnailJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 600;

    public function __construct(public readonly int $mediaId) {}

    public function uniqueId(): string
    {
        return (string) $this->mediaId;
    }

    public function handle(MediaThumbnailService $thumbnails): void
    {
        $media = EventsImges::query()->with('events')->find($this->mediaId);
        if ($media) {
            $thumbnails->generate($media);
        }
    }
}
