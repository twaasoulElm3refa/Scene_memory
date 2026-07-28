<?php

namespace Tests\Feature\Api\Admin;

use App\Models\EventRequestCreate;
use App\Models\Events;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventRequestAiFlaggedFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::tags(['requests'])->flush();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_it_filters_event_creation_requests_by_ai_flagged_before_paginating(): void
    {
        $safeEvent = Events::create(['title' => 'Safe event']);
        $sensitiveEvent = Events::create(['title' => 'Sensitive event']);

        $safeRequest = EventRequestCreate::create([
            'event_id' => $safeEvent->id,
            'ai_flagged' => false,
        ]);

        $sensitiveRequest = EventRequestCreate::create([
            'event_id' => $sensitiveEvent->id,
            'ai_flagged' => true,
        ]);

        $allResponse = $this->getJson('/api/v1/requests/all/paginated?page=1');
        $safeResponse = $this->getJson('/api/v1/requests/all/paginated?page=1&ai_flagged=0');
        $sensitiveResponse = $this->getJson('/api/v1/requests/all/paginated?page=1&ai_flagged=1');

        $allResponse->assertOk()
            ->assertJsonPath('data.total', 2);

        $safeResponse->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $safeRequest->id)
            ->assertJsonPath('data.data.0.ai_flagged', false);

        $sensitiveResponse->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $sensitiveRequest->id)
            ->assertJsonPath('data.data.0.ai_flagged', true);
    }
}
