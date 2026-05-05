<?php

namespace Tests\Feature\Api\Events;

use Tests\TestCase;

class EventApiTest extends TestCase
{
    public function test_user_event_create_requires_auth(): void
    {
        $response = $this->postJson('/api/v1/events/create/user', []);
        $response->assertStatus(401);
    }

    public function test_user_dashboard_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/user-dshboard/my-events');
        $response->assertStatus(401);
    }
}
