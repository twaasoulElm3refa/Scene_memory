<?php

namespace Tests\Feature\Cache;

use App\Jobs\TranslateCommentJob;
use App\Models\Categories;
use App\Models\Comments;
use App\Models\Countries;
use App\Models\CountryTranslations;
use App\Models\Events;
use App\Models\User;
use App\Support\CommentCache;
use Illuminate\Cache\NullStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class CacheReliabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_tagged_reads_on_a_store_without_tags_are_computed_instead_of_failing(): void
    {
        config(['cache.stores.tagless_probe' => ['driver' => 'database', 'table' => 'cache'], 'cache.default' => 'tagless_probe']);
        Log::spy();
        $calls = 0;
        $read = function () use (&$calls) {
            return Cache::tags(['events'])->remember('probe', 600, function () use (&$calls) {
                $calls++;

                return 'fresh';
            });
        };

        $this->assertSame('fresh', $read());
        $this->assertSame('fresh', $read());
        // Never stored, so never stale after a write elsewhere.
        $this->assertSame(2, $calls);
        $this->assertTrue(Cache::tags(['events'])->flush());
        // Untagged caching on the same store keeps working.
        Cache::put('plain', 'kept', 60);
        $this->assertSame('kept', Cache::get('plain'));

        Categories::create(['name' => 'Concerts']);
        $this->getJson('/api/v1/categories')->assertOk();
        Log::shouldHaveReceived('warning')->with('cache_tags_unsupported', Mockery::on(fn ($context) => $context['store'] === 'tagless_probe'))->once();
    }

    public function test_up_is_a_readiness_check_for_the_database_and_cache(): void
    {
        $this->get('/up')->assertOk();

        Cache::extend('unreachable', fn () => Cache::repository(new class extends NullStore
        {
            public function put($key, $value, $seconds)
            {
                throw new RuntimeException('connection refused 10.0.0.9:6379');
            }
        }));
        config(['cache.stores.unreachable' => ['driver' => 'unreachable'], 'cache.default' => 'unreachable']);
        $this->get('/up')->assertStatus(500)->assertDontSee('10.0.0.9');
    }

    public function test_up_can_require_tag_support(): void
    {
        config(['cache.require_tags' => true]);
        $this->get('/up')->assertOk();

        config(['cache.default' => 'database']);
        $this->get('/up')->assertStatus(500);
    }

    public function test_a_comment_only_invalidates_its_own_events_comment_pages(): void
    {
        Queue::fake([TranslateCommentJob::class]);
        $user = User::factory()->create();
        $first = $this->event($user, 'first-event');
        $second = $this->event($user, 'second-event');
        $this->getJson('/api/v1/comments/first-event')->assertOk();
        $this->getJson('/api/v1/comments/second-event')->assertOk();
        $firstKey = "comments_page_1_event_{$first->id}_ar";
        $secondKey = "comments_page_1_event_{$second->id}_ar";
        $this->assertTrue(Cache::tags(CommentCache::tags($first->id))->has($firstKey));

        Sanctum::actingAs($user);
        $this->postJson("/api/v1/comments/{$first->id}/create", ['comment' => 'Fresh comment'])->assertOk();

        $this->assertFalse(Cache::tags(CommentCache::tags($first->id))->has($firstKey));
        $this->assertTrue(Cache::tags(CommentCache::tags($second->id))->has($secondKey));
        $this->getJson('/api/v1/comments/first-event')->assertOk()->assertJsonPath('data.data.0.comment', 'Fresh comment');
        // Malformed page numbers share page 1's entry instead of creating new keys.
        $this->getJson('/api/v1/comments/first-event?page=abc')->assertOk();
        $this->assertTrue(Cache::tags(CommentCache::tags($first->id))->has($firstKey));
    }

    public function test_a_reply_clears_the_cached_event_page_in_every_locale(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $event = $this->event($admin, 'reply-event');
        $comment = Comments::create(['event_id' => $event->id, 'user_id' => $admin->id, 'comment' => 'Question']);
        foreach (['ar', 'tr'] as $locale) {
            Cache::tags(['events'])->put("event_reply-event_{$locale}", 'stale', 600);
        }

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/replies/reply/{$comment->id}", ['comment' => 'Answer'])->assertOk();

        $this->assertFalse(Cache::tags(['events'])->has('event_reply-event_ar'));
        $this->assertFalse(Cache::tags(['events'])->has('event_reply-event_tr'));
    }

    public function test_clearing_one_cart_keeps_other_users_cart_cache(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($other);
        $this->getJson('/api/v1/cart/get')->assertOk();
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/cart/get')->assertOk();

        $this->deleteJson('/api/v1/cart/clearCart')->assertOk();

        $this->assertFalse(Cache::tags(['cart', "user_{$user->id}"])->has("cart_user_{$user->id}"));
        $this->assertTrue(Cache::tags(['cart', "user_{$other->id}"])->has("cart_user_{$other->id}"));
    }

    public function test_localized_country_pages_are_cached_per_locale(): void
    {
        $country = Countries::create(['name' => 'Egypt', 'code' => 'EG']);
        CountryTranslations::create(['country_id' => $country->id, 'locale' => 'ar', 'name' => 'مصر']);
        CountryTranslations::create(['country_id' => $country->id, 'locale' => 'en', 'name' => 'Egypt']);

        $this->getJson('/api/v1/countries/paginated/get', ['Accept-Language' => 'ar'])->assertJsonPath('data.data.0.translation.name', 'مصر');
        $this->getJson('/api/v1/countries/paginated/get', ['Accept-Language' => 'en'])->assertJsonPath('data.data.0.translation.name', 'Egypt');
        $this->getJson("/api/v1/countries/{$country->id}", ['Accept-Language' => 'ar'])->assertOk();
        $this->getJson("/api/v1/countries/{$country->id}", ['Accept-Language' => 'en'])->assertOk();
        $this->assertTrue(Cache::tags(['countries'])->has("countries_single_{$country->id}_ar"));
        $this->assertTrue(Cache::tags(['countries'])->has("countries_single_{$country->id}_en"));
    }

    public function test_production_check_is_read_only_and_never_prints_secrets(): void
    {
        config(['paypal.live.client_secret' => 'LIVE-SECRET-VALUE', 'app.key' => 'base64:'.base64_encode(str_repeat('k', 32))]);

        $exit = Artisan::call('scemory:production-check', ['--json' => true]);
        $output = Artisan::output();
        $rows = collect(json_decode($output, true));

        $this->assertNotEmpty($rows);
        $this->assertStringNotContainsString('LIVE-SECRET-VALUE', $output);
        $this->assertStringNotContainsString(base64_encode(str_repeat('k', 32)), $output);
        $this->assertSame('ok', $rows->firstWhere('check', 'db.table.financial_holds')['status']);
        $this->assertSame('ok', $rows->firstWhere('check', 'media.private_root')['status']);
        $this->assertSame($rows->contains('status', 'fail') ? 1 : 0, $exit);
    }

    private function event(User $user, string $slug): Events
    {
        return Events::create(['user_id' => $user->id, 'title' => $slug, 'description' => 'Cache test event', 'slug' => $slug, 'is_active' => true]);
    }
}
