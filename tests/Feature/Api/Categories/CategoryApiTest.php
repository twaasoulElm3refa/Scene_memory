<?php

namespace Tests\Feature\Api\Categories;

use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    public function test_category_create_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/categories/create', ['name' => 'Test']);
        $response->assertStatus(401);
    }

    public function test_sub_category_create_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/sub_categories/create', ['name' => 'Sub']);
        $response->assertStatus(401);
    }
}
