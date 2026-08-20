<?php

namespace App\Services;

use App\Models\Event_Tags;
use App\Models\Events;
use App\Models\EventsImges;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EventAiTagsPersistenceService
{
    public function __construct(
        private readonly TagResolverService $tagResolver,
        private readonly EventTagCacheService $cache
    ) {}

    /**
     * @param  array{event_tags?: array<mixed>, images?: array<mixed>}  $result
     * @param  array<int, EventsImges>  $imagesByIndex
     */
    public function persist(Events $event, array $result, array $imagesByIndex): void
    {
        DB::transaction(function () use ($event, $result, $imagesByIndex): void {
            $eventTagIds = $this->tagResolver->resolveIds(
                $result['event_tags'] ?? [],
                'ai'
            );

            foreach ($eventTagIds as $tagId) {
                $pivot = Event_Tags::withTrashed()->firstOrCreate([
                    'event_id' => $event->getKey(),
                    'tag_id' => $tagId,
                ]);

                if ($pivot->trashed()) {
                    $pivot->restore();
                }
            }

            foreach ($result['images'] ?? [] as $imageResult) {
                if (! is_array($imageResult)) {
                    continue;
                }

                $imageIndex = filter_var(
                    $imageResult['image_index'] ?? null,
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]]
                );

                if ($imageIndex === false || ! isset($imagesByIndex[$imageIndex])) {
                    Log::warning('event_ai_tags_unknown_image_index', [
                        'event_id' => $event->getKey(),
                        'image_index' => $imageResult['image_index'] ?? null,
                    ]);

                    continue;
                }

                $tagIds = $this->tagResolver->resolveIds(
                    $imageResult['tags'] ?? [],
                    'ai'
                );

                if ($tagIds !== []) {
                    $imagesByIndex[$imageIndex]
                        ->tags()
                        ->syncWithoutDetaching($tagIds);
                }
            }
        });

        $mediaIds = collect($imagesByIndex)
            ->filter(fn ($media) => $media instanceof EventsImges)
            ->map(fn (EventsImges $media) => (int) $media->getKey())
            ->unique()
            ->values()
            ->all();

        $this->cache->invalidate((int) $event->getKey(), $mediaIds);
    }
}
