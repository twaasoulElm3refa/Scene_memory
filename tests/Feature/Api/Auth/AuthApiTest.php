<?php

namespace Tests\Feature\Api\Auth;

use Tests\TestCase;

class AuthApiTest extends TestCase
{
    public function test_register_requires_required_fields(): void
    {
        $response = $this->postJson('/api/v1/users/register', []);
        $response->assertStatus(422);
    }

    public function test_forgot_password_requires_email_validation(): void
    {
        $response = $this->postJson('/api/v1/users/forgot-password', []);
        $response->assertStatus(422);
    }

    public function test_profile_requires_authentication(): void
    {
        $this->getJson('/api/v1/users/profile')->assertStatus(401);
    }
}
