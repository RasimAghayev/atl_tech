<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Console\Commands\GenerateSwaggerFromAttributes;
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

        // Swagger generator
        $this->app->singleton(Generator::class, fn($app) => new Generator($app));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register custom console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateSwaggerFromAttributes::class,
            ]);
        }
    }
}
