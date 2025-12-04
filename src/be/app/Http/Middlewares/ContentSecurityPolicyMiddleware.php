<?php

declare(strict_types=1);

namespace App\Http\Middlewares;

use Illuminate\Http\Request;

class ContentSecurityPolicyMiddleware
{
    public function handle(Request $request, \Closure $next)
    {
        $response = $next($request);
        $csp = "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data:";
        if ($request->is('api/documentation*')) {
            $csp .= "; font-src 'self' data:; connect-src 'self'";
        } else {
            $csp .= " https:; font-src 'self' data:;";
        }

        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}
