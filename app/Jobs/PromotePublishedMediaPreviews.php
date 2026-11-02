<?php

namespace App\Jobs;

use App\Models\Events;
use App\Models\EventsImges;
use App\Services\UnifiedMediaStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PromotePublishedMediaPreviews implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public readonly int $eventId) {}

    public function handle(UnifiedMediaStorage $storage): void
    {
        if (! Events::whereKey($this->eventId)->where('is_active', true)->exists()) return;
        EventsImges::where('event_id', $this->eventId)->where('is_active', true)
            ->where('preview_disk', 'media')->whereNotNull('preview_url')->each(function (EventsImges $media) use ($storage) {
                $type = $media->type === 'video' ? 'video' : 'image';
                if (! $storage->enabled($type)) return;
                $old = $media->getRawOriginal('preview_url');
                if (! Storage::disk('media')->exists($old) || $old === $media->getRawOriginal('full_url')) return;
                try {
                    $path = $storage->upload(Storage::disk('media')->path($old), $type, 'events-preview');
                    $changed = EventsImges::whereKey($media->id)->where('preview_url', $old)
                        ->where('preview_disk', 'media')->update(['preview_url' => $path, 'preview_disk' => 'cloudinary', 'fallback_preview_url' => $old]);
                    if (! $changed) $storage->delete($path);
                } catch (\Throwable $e) {
                    Log::warning('cloudinary_promotion_failed', ['media_id' => $media->id, 'reason' => $e->getMessage()]);
                }
            });
    }
}
