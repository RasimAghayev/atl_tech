<?php

declare(strict_types=1);

namespace App\Http\Middlewares;

use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, \Closure $next)
    {
        $response = $next($request);

        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer-when-downgrade');
        // Content-Security-Policy is owned exclusively by ContentSecurityPolicyMiddleware
        // (route-aware: a stricter policy for the API vs. a Swagger-UI-compatible one for docs).
        // Do not set it here too — a second writer here previously made the effective
        // policy depend on undocumented middleware ordering (TD-SEC-003).
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=()');
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        return $response;
    }
}
