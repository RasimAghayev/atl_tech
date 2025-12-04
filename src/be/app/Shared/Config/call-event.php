<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | API Token
    |--------------------------------------------------------------------------
    |
    | This token is used to authenticate requests to the call event API.
    | The token should be sent in the Authorization header as a Bearer token.
    |
    */
    'api_token' => env('CALL_EVENT_API_TOKEN', 'your-secret-api-token'),

    /*
    |--------------------------------------------------------------------------
    | RabbitMQ Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for RabbitMQ message broker connection.
    |
    */
    'rabbitmq' => [
        'host' => env('RABBITMQ_HOST', 'localhost'),
        'port' => (int) env('RABBITMQ_PORT', 5672),
        'user' => env('RABBITMQ_USER', 'guest'),
        'password' => env('RABBITMQ_PASSWORD', 'guest'),
        'vhost' => env('RABBITMQ_VHOST', '/'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Name
    |--------------------------------------------------------------------------
    |
    | The name of the RabbitMQ queue where call events will be published.
    |
    */
    'queue_name' => env('CALL_EVENT_QUEUE_NAME', 'call-events'),
];