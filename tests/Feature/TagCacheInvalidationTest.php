<?php

namespace Tests\Feature;

use App\Models\Event_Tags;
use App\Models\Events;
use App\Models\EventsImges;
use App\Models\ImagesTags;
use App\Models\Tags;
use App\Services\EventTagCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TagCacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tag_lifecycle_and_relation_changes_refresh_scoped_caches(): void
    {
        $cache = app(EventTagCacheService::class);
        $initialVersion = $cache->tagCacheVersion();
        Cache::put('all_tags_v2', ['stale']);

        $tag = Tags::create([
            'name' => 'Cache test',
            'slug' => 'cache-test',
            'mode' => 'ai',
        ]);

        $this->assertFalse(Cache::has('all_tags_v2'));
        $this->assertGreaterThan($initialVersion, $cache->tagCacheVersion());

        $event = Events::create([
            'title' => 'Cached event',
            'description' => 'Cached event description',
            'slug' => 'cached-event',
        ]);
        $media = EventsImges::create([
            'event_id' => $event->id,
            'type' => 'image',
            'full_url' => 'events/full/cache.jpg',
        ]);

        $this->primeEventCaches($event, $media);
        $eventTag = Event_Tags::create([
            'event_id' => $event->id,
            'tag_id' => $tag->id,
        ]);
        $this->assertEventCachesWereForgotten($event, $media, false);

        $this->primeEventCaches($event, $media);
        ImagesTags::create([
            'events_imges_id' => $media->id,
            'tags_id' => $tag->id,
        ]);
        $this->assertEventCachesWereForgotten($event, $media);

        $versionBeforeUpdate = $cache->tagCacheVersion();
        $tag->update(['name' => 'Updated cache test']);
        $this->assertGreaterThan($versionBeforeUpdate, $cache->tagCacheVersion());

        $this->primeEventCaches($event, $media);
        $eventTag->delete();
        $this->assertEventCachesWereForgotten($event, $media, false);

        $versionBeforeDelete = $cache->tagCacheVersion();
        $tag->delete();
        $this->assertGreaterThan($versionBeforeDelete, $cache->tagCacheVersion());
    }

    private function primeEventCaches(Events $event, EventsImges $media): void
    {
        Cache::put("event_image_event_{$event->id}", ['stale']);
        Cache::put("event_media_{$media->id}", ['stale']);
        Cache::put("events_single_{$event->slug}", ['stale']);
        Cache::put("events_single_{$event->slug}_en", ['stale']);
    }

    private function assertEventCachesWereForgotten(
        Events $event,
        EventsImges $media,
        bool $expectMediaCacheInvalidated = true
    ): void {
        $this->assertFalse(Cache::has("event_image_event_{$event->id}"));
        $this->assertSame(
            ! $expectMediaCacheInvalidated,
            Cache::has("event_media_{$media->id}")
        );
        $this->assertFalse(Cache::has("events_single_{$event->slug}"));
        $this->assertFalse(Cache::has("events_single_{$event->slug}_en"));
    }
}
