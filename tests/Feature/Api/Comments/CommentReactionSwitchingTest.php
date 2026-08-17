<?php

namespace Tests\Feature\Api\Comments;

use App\Models\CommentInteractions;
use App\Models\Comments;
use App\Models\Events;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommentReactionSwitchingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_switch_reactions_and_counts_remain_authoritative(): void
    {
        [$comment, $userA, $userB] = $this->createCommentFixture();

        Sanctum::actingAs($userA);

        $this->assertReaction($comment, 'support', 'support', 1, 0, 0);
        $this->assertReaction($comment, 'neutral', 'neutral', 0, 1, 0);
        $this->assertReaction($comment, 'Exhibitions', 'exhibitions', 0, 0, 1);
        $this->assertReaction($comment, 'support', 'support', 1, 0, 0);

        // Clicking the selected reaction is idempotent and does not create a duplicate.
        $this->assertReaction($comment, 'support', 'support', 1, 0, 0);
        $this->assertSame(1, CommentInteractions::where([
            'comment_id' => $comment->id,
            'user_id' => $userA->id,
        ])->count());

        Sanctum::actingAs($userB);
        $this->assertReaction($comment, 'neutral', 'neutral', 1, 1, 0);

        Sanctum::actingAs($userA);
        $this->assertReaction($comment, 'Exhibitions', 'exhibitions', 0, 1, 1);

        $this->assertDatabaseHas('comment_interactions', [
            'comment_id' => $comment->id,
            'user_id' => $userA->id,
            'type' => 'Exhibitions',
        ]);
        $this->assertDatabaseHas('comment_interactions', [
            'comment_id' => $comment->id,
            'user_id' => $userB->id,
            'type' => 'neutral',
        ]);
        $this->assertSame(2, CommentInteractions::where('comment_id', $comment->id)->count());

        $allComments = $this->getJson("/api/v1/comments/{$comment->event->slug}");
        $allComments->assertOk()
            ->assertJsonPath('data.data.0.current_user_reaction', 'exhibitions')
            ->assertJsonPath('data.data.0.support_count', 0)
            ->assertJsonPath('data.data.0.neutral_count', 1)
            ->assertJsonPath('data.data.0.exhibitions_count', 1);

        $singleEvent = $this->getJson("/api/v1/events/{$comment->event->slug}/single/get");
        $singleEvent->assertOk()
            ->assertJsonPath('data.comments.0.current_user_reaction', 'exhibitions')
            ->assertJsonPath('data.comments.0.support_count', 0)
            ->assertJsonPath('data.comments.0.neutral_count', 1)
            ->assertJsonPath('data.comments.0.exhibitions_count', 1);

        Sanctum::actingAs($userB);
        $this->getJson("/api/v1/comments/{$comment->event->slug}")
            ->assertOk()
            ->assertJsonPath('data.data.0.current_user_reaction', 'neutral');
    }

    public function test_reacting_requires_authentication(): void
    {
        [$comment] = $this->createCommentFixture();

        $this->postJson("/api/v1/comments/{$comment->id}/support")
            ->assertUnauthorized();

        $this->assertDatabaseCount('comment_interactions', 0);
    }

    private function assertReaction(
        Comments $comment,
        string $endpoint,
        string $expectedReaction,
        int $supportCount,
        int $neutralCount,
        int $exhibitionsCount
    ): void {
        $this->postJson("/api/v1/comments/{$comment->id}/{$endpoint}")
            ->assertOk()
            ->assertJsonPath('data.current_user_reaction', $expectedReaction)
            ->assertJsonPath('data.support_count', $supportCount)
            ->assertJsonPath('data.neutral_count', $neutralCount)
            ->assertJsonPath('data.exhibitions_count', $exhibitionsCount);
    }

    private function createCommentFixture(): array
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $now = now();
        $countryId = DB::table('countries')->insertGetId([
            'name' => 'Reaction Country',
            'code' => 'RC',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $cityId = DB::table('cities')->insertGetId([
            'country_id' => $countryId,
            'name' => 'Reaction City',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Reaction Category',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $subCategoryId = DB::table('sub_categoreys')->insertGetId([
            'category_id' => $categoryId,
            'name' => 'Reaction Subcategory',
            'slug' => 'reaction-subcategory',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $event = Events::create([
            'user_id' => $userA->id,
            'city_id' => $cityId,
            'sub_categorey_id' => $subCategoryId,
            'title' => 'Reaction Event',
            'description' => 'Reaction testing event',
            'slug' => 'reaction-event',
            'start_date' => $now->toDateString(),
            'is_active' => 1,
        ]);
        $comment = Comments::create([
            'event_id' => $event->id,
            'user_id' => $userA->id,
            'comment' => 'A comment with switchable reactions.',
        ]);

        return [$comment, $userA, $userB];
    }
}
