<?php

declare(strict_types=1);

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\SwaggerServiceProvider::class,

    // DDD Modules
    App\Modules\CallEvent\Providers\CallEventServiceProvider::class,
];
