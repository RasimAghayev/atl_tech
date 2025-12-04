<?php

declare(strict_types=1);

namespace App\Modules\CallEvent\Providers;

use App\Modules\CallEvent\Contracts\CallEventRepositoryInterface;
use App\Modules\CallEvent\Repositories\PostgresCallEventRepository;
use App\Shared\Brokers\RabbitMQ\RabbitMQPublisher;
use App\Shared\Contracts\EventPublisherInterface;
use Illuminate\Support\ServiceProvider;

class CallEventServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge module config
        $this->mergeConfigFrom(
            __DIR__.'/../../../Shared/Config/call-event.php',
            'call-event'
        );

        // Bind interfaces to implementations
        $this->app->bind(CallEventRepositoryInterface::class, PostgresCallEventRepository::class);

        $this->app->singleton(EventPublisherInterface::class, function ($app) {
            return new RabbitMQPublisher(
                host: config('call-event.rabbitmq.host'),
                port: config('call-event.rabbitmq.port'),
                user: config('call-event.rabbitmq.user'),
                password: config('call-event.rabbitmq.password'),
                vhost: config('call-event.rabbitmq.vhost', '/')
            );
        });
    }

    public function boot(): void
    {
        // Load module routes
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../../../database/migrations');

        // Publish config
        $this->publishes([
            __DIR__.'/../../../Shared/Config/call-event.php' => config_path('call-event.php'),
        ], 'call-event-config');
    }
}