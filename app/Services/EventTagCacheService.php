<?php

namespace App\Services;

use App\Models\Events;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class EventTagCacheService
{
    public const TAG_CACHE_VERSION_KEY = 'tags:cache_version';

    public function tagCacheVersion(): int
    {
        try {
            $version = Cache::get(self::TAG_CACHE_VERSION_KEY);

            if ($version === null) {
                $version = 1;
                Cache::forever(self::TAG_CACHE_VERSION_KEY, $version);
            }

            return (int) $version;
        } catch (Throwable $exception) {
            $this->logFailure('read_tag_version', $exception);

            return 1;
        }
    }

    public function invalidateTagLists(): void
    {
        try {
            Cache::forget('all_tags_v2');
            Cache::forever(
                self::TAG_CACHE_VERSION_KEY,
                $this->tagCacheVersion() + 1
            );
        } catch (Throwable $exception) {
            $this->logFailure('invalidate_tag_lists', $exception);
        }
    }

    /**
     * Invalidate tag lists plus cached representations affected by tag relations.
     *
     * @param  array<int, int>  $mediaIds
     */
    public function invalidate(?int $eventId = null, array $mediaIds = []): void
    {
        $this->invalidateTagLists();
        $this->invalidateEvent($eventId, $mediaIds);
    }

    /**
     * @param  array<int, int>  $mediaIds
     */
    public function invalidateEvent(?int $eventId = null, array $mediaIds = []): void
    {
        $this->flushTaggedCache('events');

        if ($eventId === null) {
            return;
        }

        $this->forget("event_image_event_{$eventId}");

        foreach (array_unique(array_map('intval', $mediaIds)) as $mediaId) {
            $this->forget("event_media_{$mediaId}");
        }

        $event = Events::query()
            ->select(['id', 'slug', 'user_id'])
            ->find($eventId);

        if ($event === null) {
            return;
        }

        if ($event->user_id) {
            $this->forget('my_events_user_id_'.$event->user_id);
        }

        $slug = trim((string) $event->slug);

        if ($slug === '') {
            return;
        }

        $this->forget("events_single_{$slug}");

        foreach ($this->locales() as $locale) {
            $this->forget("events_single_{$slug}_{$locale}");
            $this->forget('event_'.mb_strtolower($slug)."_{$locale}");
        }
    }

    public function invalidateRequests(): void
    {
        $this->flushTaggedCache('requests');
    }

    public function invalidateModerationState(?int $eventId = null): void
    {
        $this->invalidateEvent($eventId);
        $this->invalidateRequests();

        foreach ($this->locales() as $locale) {
            $this->forget('daily_events_'.$locale);
        }
    }

    private function flushTaggedCache(string $tag): void
    {
        try {
            Cache::tags([$tag])->flush();
        } catch (Throwable $exception) {
            // File/array stores do not support tags. Exact known keys are
            // invalidated by invalidateEvent(), while production Redis does.
            if (! str_contains($exception->getMessage(), 'does not support tagging')) {
                $this->logFailure("flush_tag:{$tag}", $exception);
            }
        }
    }

    private function forget(string $key): void
    {
        try {
            Cache::forget($key);
        } catch (Throwable $exception) {
            $this->logFailure("forget:{$key}", $exception);
        }
    }

    private function logFailure(string $operation, Throwable $exception): void
    {
        Log::warning('event_tag_cache_invalidation_failed', [
            'operation' => $operation,
            'message' => $exception->getMessage(),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function locales(): array
    {
        return ['ar', 'en', 'fr', 'es', 'zh', 'de', 'ru', 'it', 'ja', 'fa', 'ur', 'hi', 'tr'];
    }
}
