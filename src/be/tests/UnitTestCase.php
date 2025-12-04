<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Traits\CreatesApplication;

abstract class UnitTestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up testing environment
        $this->artisan('config:clear');
        $this->artisan('cache:clear');

        // Disable Laravel Telescope for testing
        config(['telescope.enabled' => false]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
