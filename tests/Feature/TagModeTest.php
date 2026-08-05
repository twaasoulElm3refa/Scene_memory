<?php

namespace Tests\Feature;

use App\Jobs\TranslateTagJob;
use App\Models\Tags;
use App\Services\TagResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TagModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_tag_mode_defaults_to_ai_when_it_is_not_provided(): void
    {
        $tag = Tags::create([
            'name' => 'Default source',
            'slug' => 'default-source',
        ]);

        $this->assertSame('ai', $tag->fresh()->mode);
    }

    public function test_same_tag_name_is_unique_per_mode(): void
    {
        Queue::fake([TranslateTagJob::class]);

        $resolver = app(TagResolverService::class);
        $aiTag = $resolver->resolve(' Nature ', 'ai');
        $userTag = $resolver->resolve('Nature', 'user');
        $sameAiTag = $resolver->resolve('Nature', 'ai');
        $sameUserTag = $resolver->resolve(' Nature ', 'user');

        $this->assertNotNull($aiTag);
        $this->assertNotNull($userTag);
        $this->assertNotSame($aiTag->id, $userTag->id);
        $this->assertSame($aiTag->id, $sameAiTag->id);
        $this->assertSame($userTag->id, $sameUserTag->id);
        $this->assertDatabaseCount('tags', 2);
        $this->assertDatabaseHas('tags', [
            'name' => 'Nature',
            'slug' => 'nature',
            'mode' => 'ai',
        ]);
        $this->assertDatabaseHas('tags', [
            'name' => 'Nature',
            'slug' => 'nature',
            'mode' => 'user',
        ]);
    }

    public function test_tag_api_responses_include_the_mode(): void
    {
        Tags::create([
            'name' => 'Visible source',
            'slug' => 'visible-source',
            'mode' => 'user',
        ]);

        $this->getJson('/api/v1/tags')
            ->assertOk()
            ->assertJsonPath('data.0.mode', 'user');

        $this->getJson('/api/v1/tags/search?q=Visible')
            ->assertOk()
            ->assertJsonPath('data.0.mode', 'user');
    }
}
