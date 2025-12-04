<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Console\Commands\GenerateSwaggerFromAttributes;
use App\Support\Swagger\Services\SwaggerGenerator;
use Illuminate\Support\ServiceProvider;

class SwaggerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SwaggerGenerator::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateSwaggerFromAttributes::class,
            ]);
        }
    }
}
