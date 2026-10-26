<?php

namespace Tests\Feature\Performance;

use App\Models\Events;
use App\Models\EventsImges;
use App\Models\Likes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Query counts must not grow with the number of media items or timeline rows. */
class MediaQueryCountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Serialize like Redis, so cache hits do not keep lazily loaded relations.
        config()->set('cache.stores.array.serialize', true);
        app('cache')->forgetDriver('array');
    }

    public function test_an_event_page_costs_the_same_queries_for_few_or_many_images(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $few = $this->event($owner, 3);
        $many = $this->event($owner, 40);

        foreach (['miss', 'hit'] as $phase) {
            $fewCount = $this->queries(fn () => $this->getJson("/api/v1/events/{$few->slug}/single/get")->assertOk());
            $manyCount = $this->queries(fn () => $this->getJson("/api/v1/events/{$many->slug}/single/get")->assertOk());
            $this->assertSame($fewCount, $manyCount, "cache {$phase}");
        }
    }

    public function test_listing_loads_one_first_image_per_event(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $events = collect(range(1, 3))->map(fn () => $this->event($owner, 10));
        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->getJson('/api/v1/events')->assertOk();
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $rows = 0;
        foreach ($log as $entry) {
            if (str_contains($entry['query'], 'from "events_images"') && str_contains($entry['query'], 'MIN(')) {
                $rows += count(DB::select($entry['query'], $entry['bindings']));
            }
        }
        $this->assertSame(3, $rows);
        foreach ($events as $event) {
            $lowest = EventsImges::where('event_id', $event->id)->min('id');
            $item = collect($response->json('data.data'))->firstWhere('id', $event->id);
            $this->assertSame($lowest, $item['first_image']['id'] ?? $item['firstImage']['id'] ?? null);
        }
    }

    public function test_the_profile_timeline_does_not_query_once_per_event(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $count = function (int $likes) use ($user) {
            Likes::query()->delete();
            foreach (range(1, $likes) as $i) {
                Likes::create(['user_id' => $user->id, 'event_id' => $this->event($user, 1)->id]);
            }

            return $this->queries(fn () => $this->getJson('/api/v1/users/timeline')->assertOk());
        };
        Sanctum::actingAs($user);

        $this->assertSame($count(1), $count(4));
    }

    private function event(User $owner, int $images): Events
    {
        $event = Events::create(['user_id' => $owner->id, 'title' => 'Count', 'slug' => 'count-'.Str::random(10), 'is_active' => 1, 'start_date' => now()->toDateString()]);
        for ($i = 0; $i < $images; $i++) {
            EventsImges::create(['event_id' => $event->id, 'type' => 'image', 'is_active' => '1', 'price' => '5.00',
                'full_url' => "events/full/{$event->id}-{$i}.jpg", 'preview_url' => "events/preview/{$event->id}-{$i}.jpg",
                'original_disk' => 'media', 'preview_disk' => 'media']);
        }

        return $event;
    }

    private function queries(callable $request): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $request();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
