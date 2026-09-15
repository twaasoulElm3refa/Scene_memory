<?php

namespace Tests\Feature\Api;

use App\Models\Categories;
use App\Models\Cities;
use App\Models\CommentImage;
use App\Models\CommentInteractions;
use App\Models\CommentReplies;
use App\Models\Comments;
use App\Models\Countries;
use App\Models\EventRequestCreate;
use App\Models\Events;
use App\Models\EventsImges;
use App\Models\Likes;
use App\Models\SubCategorey;
use App\Models\User;
use App\Models\UserInteractions;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileTimelineApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Events $event;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-15 12:00:00');
        Storage::fake('public');

        $this->user = User::factory()->create();
        $country = Countries::create(['name' => 'Egypt', 'code' => 'EG']);
        $city = Cities::create(['country_id' => $country->id, 'name' => 'Cairo']);
        $category = Categories::create(['name' => 'Culture']);
        $subCategory = SubCategorey::create([
            'category_id' => $category->id,
            'name' => 'Festivals',
            'slug' => 'festivals',
        ]);

        $this->event = Events::create([
            'user_id' => $this->user->id,
            'city_id' => $city->id,
            'sub_categorey_id' => $subCategory->id,
            'title' => 'Cairo Festival',
            'slug' => 'cairo-festival',
            'is_active' => true,
        ]);

        EventsImges::create([
            'event_id' => $this->event->id,
            'preview_url' => 'events/preview/festival.jpg',
            'full_url' => 'events/full/festival.jpg',
            'type' => 'image',
            'is_active' => true,
        ]);
        EventRequestCreate::create(['event_id' => $this->event->id, 'status' => 'approved']);

        $comment = Comments::create([
            'user_id' => $this->user->id,
            'event_id' => $this->event->id,
            'comment' => 'A useful comment',
        ]);
        CommentImage::create([
            'comment_id' => $comment->id,
            'path' => 'comments/photo.jpg',
            'disk' => 'public',
        ]);
        CommentReplies::create([
            'user_id' => $this->user->id,
            'comment_id' => $comment->id,
            'comment' => 'My reply',
        ]);
        CommentInteractions::create([
            'user_id' => $this->user->id,
            'comment_id' => $comment->id,
            'type' => 'support',
        ]);
        Likes::create(['user_id' => $this->user->id, 'event_id' => $this->event->id]);
        Wishlist::create(['user_id' => $this->user->id, 'event_id' => $this->event->id]);
        UserInteractions::create([
            'user_id' => $this->user->id,
            'event_id' => $this->event->id,
            'like' => true,
            'comment' => 'Legacy interaction',
        ]);

        Sanctum::actingAs($this->user);
    }

    public function test_all_activity_returns_separate_sections_with_counts_and_relations(): void
    {
        $response = $this->getJson('/api/v1/users/timeline?section=all&period=all&timezone=UTC');

        $response
            ->assertOk()
            ->assertJsonPath('data.summary.total', 7)
            ->assertJsonPath('data.summary.counts.events', 1)
            ->assertJsonPath('data.events.data.0.title', 'Cairo Festival')
            ->assertJsonPath('data.events.data.0.status', 'approved')
            ->assertJsonPath('data.events.data.0.category.name', 'Culture')
            ->assertJsonPath('data.events.data.0.location.city.name', 'Cairo')
            ->assertJsonPath('data.likes.data.0.event.title', 'Cairo Festival')
            ->assertJsonPath('data.comments.data.0.images.0.id', 1)
            ->assertJsonPath('data.comment_images.data.0.comment.text', 'A useful comment')
            ->assertJsonPath('data.replies.data.0.comment.text', 'A useful comment')
            ->assertJsonPath('data.comment_interactions.data.0.type', 'support')
            ->assertJsonPath('data.wishlists.data.0.event.slug', 'cairo-festival')
            ->assertJsonMissingPath('data.user_interactions');
    }

    public function test_a_single_section_is_paginated_in_the_database(): void
    {
        Comments::create([
            'user_id' => $this->user->id,
            'event_id' => $this->event->id,
            'comment' => 'Newest comment',
        ]);

        $response = $this->getJson('/api/v1/users/timeline?section=comments&period=all&page=1&per_page=1&timezone=UTC');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.comments.data')
            ->assertJsonPath('data.comments.total', 2)
            ->assertJsonPath('data.comments.pagination.current_page', 1)
            ->assertJsonPath('data.comments.pagination.last_page', 2)
            ->assertJsonPath('data.comments.pagination.has_more', true)
            ->assertJsonPath('data.comments.data.0.text', 'Newest comment')
            ->assertJsonMissingPath('data.events');
    }

    public function test_today_filter_is_applied_to_each_section_query(): void
    {
        Events::create([
            'user_id' => $this->user->id,
            'title' => 'Old event',
            'slug' => 'old-event',
            'is_active' => true,
            'created_at' => now()->subMonth(),
            'updated_at' => now()->subMonth(),
        ]);

        $response = $this->getJson('/api/v1/users/timeline?section=all&period=today&timezone=UTC');

        $response
            ->assertOk()
            ->assertJsonPath('data.events.total', 1)
            ->assertJsonPath('data.summary.total', 7);
    }

    public function test_custom_period_requires_a_valid_range(): void
    {
        $this->getJson('/api/v1/users/timeline?section=events&period=custom&timezone=UTC')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['from', 'to']);
    }
}
