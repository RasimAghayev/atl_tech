<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_liveness_endpoint_is_ok_without_authentication(): void
    {
        $this->getJson('/health/live')
            ->assertStatus(200)
            ->assertJson(['status' => 'ok']);
    }

    public function test_readiness_endpoint_is_ok_when_database_is_reachable(): void
    {
        $this->getJson('/health/ready')
            ->assertStatus(200)
            ->assertJson(['status' => 'ok']);
    }
}
