<?php

namespace Tests\Feature\Api\Payments;

use Tests\TestCase;

class PaymentApiTest extends TestCase
{
    public function test_pay_endpoint_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/pay', ['amount' => 10]);
        $response->assertStatus(401);
    }

    public function test_deposit_pay_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/deposit/pay', ['amount' => 10]);
        $response->assertStatus(401);
    }
}
