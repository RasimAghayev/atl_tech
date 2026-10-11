<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Services\CacheService;
use Illuminate\Support\ServiceProvider;
use L5Swagger\Generator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Shared services
        $this->app->singleton(CacheService::class);

        // Swagger generator (darkaonline/l5-swagger)
        $this->app->singleton(Generator::class, fn($app) => new Generator($app));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // No custom console commands to register.
    }
}