<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\Traits\CreatesApplication;
use Tests\Traits\DatabaseHelpers;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use DatabaseHelpers;
    use RefreshDatabase;
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up testing environment
        $this->artisan('config:clear');
        $this->artisan('cache:clear');

        // Disable Laravel Telescope for testing
        config(['telescope.enabled' => false]);

        // Set up faker locale
        $this->faker = $this->faker();
        $this->faker->locale = 'en_US';
    }

    protected function tearDown(): void
    {
        // No manual DB::disconnect() here — on this suite's DB_DATABASE=:memory:
        // (sqlite) connection, RefreshDatabase takes the usingInMemoryDatabase()
        // path (refreshInMemoryDatabase(): migrate once, no per-test transaction
        // wrap/rollback), so nothing in the framework's own tearDown touches the
        // connection either. Both orderings of a manual disconnect were tried and
        // both broke the suite, for two different reasons:
        //   - disconnect() BEFORE parent::tearDown(): destroys the :memory:
        //     database's only connection before the NEXT test's setUp() can see
        //     it, and since RefreshDatabaseState::$migrated is a static flag that
        //     is already true by then, the next test's migrate step is skipped —
        //     every subsequent test then fails on missing tables.
        //   - disconnect() AFTER parent::tearDown(): parent::tearDown() has
        //     already flushed/torn down the container by that point, so the DB
        //     facade can't resolve the `db` binding at all — fails immediately,
        //     even on the very first test, with "Target class [db] does not
        //     exist" (reproduced: 21/23 tests errored this way).
        // Plain parent::tearDown() is correct and sufficient.
        parent::tearDown();
    }

    /**
     * Create authenticated user for testing
     */
    protected function actingAsUser($user = null): static
    {
        $user = $user ?? $this->createTestUser();

        return $this->actingAs($user);
    }

    /**
     * Create test user
     */
    protected function createTestUser(array $attributes = []): \App\Models\User
    {
        return \App\Models\User::factory()->create($attributes);
    }

    /**
     * Assert JSON response structure
     */
    protected function assertJsonResponseStructure(array $structure, $response = null): void
    {
        $response = $response ?? $this->response;
        $response->assertJsonStructure($structure);
    }

    /**
     * Assert API response format
     */
    protected function assertApiResponse(int $status = 200, bool $hasData = true): void
    {
        $this->response->assertStatus($status);

        $expectedStructure = [
            'timestamp',
            'path',
            'method',
            'error',
        ];

        if ($hasData) {
            $expectedStructure[] = 'result';
        }

        $this->assertJsonResponseStructure($expectedStructure);
    }

    /**
     * Assert validation error response
     */
    protected function assertValidationError(array $fields = []): void
    {
        $this->response->assertStatus(422);
        $this->response->assertJsonStructure([
            'timestamp',
            'path',
            'method',
            'error',
            'result',
        ]);

        if (!empty($fields)) {
            $this->response->assertJsonValidationErrors($fields);
        }
    }
}
