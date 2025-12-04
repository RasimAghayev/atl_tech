<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'docs/*', 'docs', 'docs/api-docs.json'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => [
        'https://8000-idx-fs-al-invoice-new-1741685745064.cluster-p6qcyjpiljdwusmrjxdspyb5m2.cloudworkstations.dev',
        'http://localhost',
        'http://localhost:3000',
        'http://localhost:3001',
        'http://localhost:5173',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Accept-Encoding',
        'Content-Encoding',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'Origin',
        'X-CSRF-TOKEN',
        'X-XSS-Protection',
        'X-Frame-Options',
        'X-Content-Type-Options',
        'Referrer-Policy',
        'Content-Security-Policy',
        'Permissions-Policy',
        'Strict-Transport-Security',
        'Cache-Control',
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'X-Powered-By',
    ],

    'exposed_headers' => [
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'X-XSS-Protection',
        'X-Frame-Options',
        'X-Content-Type-Options',
        'Referrer-Policy',
        'Content-Security-Policy',
        'Permissions-Policy',
        'Strict-Transport-Security',
    ],

    'max_age' => 86400,

    'supports_credentials' => true,

];
