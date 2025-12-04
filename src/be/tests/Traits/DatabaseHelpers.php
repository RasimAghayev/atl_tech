<?php

declare(strict_types=1);

namespace Tests\Traits;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

trait DatabaseHelpers
{
    /**
     * Assert that a model record exists in database
     */
    protected function assertModelRecordExists(string $model, array $attributes = []): void
    {
        $this->assertDatabaseHas((new $model())->getTable(), $attributes);
    }

    /**
     * Assert that a model record doesn't exist in database
     */
    protected function assertModelRecordNotExists(string $model, array $attributes = []): void
    {
        $this->assertDatabaseMissing((new $model())->getTable(), $attributes);
    }

    /**
     * Create model using factory
     */
    protected function createModel(string $model, array $attributes = [], int $count = 1): Model|Collection
    {
        $factory = $model::factory();

        if ($count === 1) {
            return $factory->create($attributes);
        }

        return $factory->count($count)->create($attributes);
    }

    /**
     * Make model using factory (not persisted)
     */
    protected function makeModel(string $model, array $attributes = [], int $count = 1): Model|Collection
    {
        $factory = $model::factory();

        if ($count === 1) {
            return $factory->make($attributes);
        }

        return $factory->count($count)->make($attributes);
    }

    /**
     * Get fresh database connection for testing
     */
    protected function getFreshDatabase(): void
    {
        DB::purge();
        DB::reconnect();
    }

    /**
     * Assert table record count
     */
    protected function assertTableRecordCount(string $table, int $count): void
    {
        $actual = DB::table($table)->count();
        $this->assertEquals($count, $actual, "Expected {$count} records in {$table}, but found {$actual}");
    }

    /**
     * Truncate table
     */
    protected function truncateTable(string $table): void
    {
        DB::table($table)->truncate();
    }

    /**
     * Disable foreign key checks for testing
     */
    protected function disableForeignKeyChecks(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
    }

    /**
     * Enable foreign key checks for testing
     */
    protected function enableForeignKeyChecks(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
