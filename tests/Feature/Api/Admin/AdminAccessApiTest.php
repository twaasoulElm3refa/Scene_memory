<?php

namespace Tests\Feature\Api\Admin;

use Tests\TestCase;

class AdminAccessApiTest extends TestCase
{
    public function test_admin_protected_routes_require_authentication(): void
    {
        $routes = [
            '/api/v1/purchases',
            '/api/v1/withdraw',
            '/api/v1/users',
            '/api/v1/requests/all/paginated',
            '/api/v1/event-images/1',
        ];

        foreach ($routes as $route) {
            $response = $this->getJson($route);
            $this->assertContains($response->status(), [401, 403]);
        }
    }
}
