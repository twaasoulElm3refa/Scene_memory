<?php

namespace Tests\Feature\Api\Routes;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiRoutesRegistrationTest extends TestCase
{
    public function test_api_v1_routes_are_registered_and_named_controllers_exist(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1'));

        $this->assertGreaterThan(120, $routes->count(), 'Expected a large API surface from routes/api.php');

        $expectedUris = [
            'api/v1/users/register',
            'api/v1/events',
            'api/v1/categories',
            'api/v1/pay',
            'api/v1/paypal/webhook',
            'api/v1/wallet/webhook',
            'api/v1/plans/all',
            'api/v1/creator/all',
        ];

        foreach ($expectedUris as $uri) {
            $this->assertTrue($routes->contains(fn ($route) => $route->uri() === $uri), "Missing route: {$uri}");
        }
    }
}
