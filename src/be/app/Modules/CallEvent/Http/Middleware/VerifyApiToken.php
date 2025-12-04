<?php

declare(strict_types=1);

namespace App\Modules\CallEvent\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token || ! $this->isValidToken($token)) {
            return response()->json([
                'error' => 'Unauthorized. Invalid or missing API token.',
            ], 401);
        }

        return $next($request);
    }

    private function isValidToken(string $token): bool
    {
        $validToken = config('call-event.api_token');

        return hash_equals($validToken, $token);
    }
}