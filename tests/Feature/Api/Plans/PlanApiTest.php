<?php

namespace Tests\Feature\Api\Plans;

use Tests\TestCase;

class PlanApiTest extends TestCase
{
    public function test_admin_plans_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/plans/all/admin');
        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_subscribe_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/subscribe/1');
        $response->assertStatus(401);
    }
}
