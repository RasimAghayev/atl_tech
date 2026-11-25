<?php

declare(strict_types=1);

use App\Http\Middlewares\CompressResponse;
use App\Http\Middlewares\ContentSecurityPolicyMiddleware;
use App\Http\Responses\
{
    ErrorTokenBlacklisted,
    ErrorTokenInvalid,
    ErrorTokenExpired,
    //    ErrorMethodNotAllowedResponse,
    ErrorNotFoundResponse,
    ErrorTooManyAttemptsResponse,
    ErrorUnauthenticatedResponse,
    ErrorValidationResponse,
    ErrorInternalServerErrorResponse
};
use Illuminate\Foundation\Configuration\
{
    Exceptions,
    Middleware
};
use Illuminate\Foundation\Http\Middleware\
{
    ConvertEmptyStringsToNull,
    PreventRequestsDuringMaintenance,
    TrimStrings,
    ValidatePostSize,
};
use Illuminate\Http\Exceptions\
{
    ThrottleRequestsException,
    HttpResponseException
};
use phpOpenSourceSaver\JWTAuth\Exceptions\
{
    TokenExpiredException,
    TokenInvalidException,
    TokenBlacklistedException
};
use Illuminate\Http\
{
    Middleware\HandleCors,
    Middleware\TrustProxies,
    Request,
    Response
};
use Illuminate\Routing\Middleware\
{
    ThrottleRequests,
    ValidateSignature
};
use App\Http\Middlewares\SecurityHeaders;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Illuminate\Foundation\Application;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up'
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Global middleware — runs for every request (web, api, docs).
        // SecurityHeaders sets the generic hardening headers; ContentSecurityPolicyMiddleware
        // is the single owner of Content-Security-Policy (it varies the policy for the
        // Swagger UI route vs. the API) — keeping both on the global stack but each owning
        // disjoint headers removes the ordering-dependent conflict recorded in TD-SEC-003.
        $middleware->use([
            TrustProxies::class,
            HandleCors::class,
            PreventRequestsDuringMaintenance::class,
            ValidatePostSize::class,
            TrimStrings::class,
            ConvertEmptyStringsToNull::class,
            SecurityHeaders::class,
            ContentSecurityPolicyMiddleware::class,
        ]);

        // API middleware group — the one and only definition (TD-CFG-001: this used to be
        // declared twice with different contents; the second silently won and the first,
        // which referenced an undefined "api" rate limiter, was dead either way).
        // NOTE: routes/api.php (the file withRouting() wraps in this group) is currently
        // empty — every real route is registered by a module's own ServiceProvider via
        // loadRoutesFrom(), which does NOT go through this group. This definition is kept
        // for any future module that registers directly into routes/api.php; it applies
        // to nothing today. Each module's own route file must declare its own throttle/
        // auth middleware explicitly (see app/Modules/CallEvent/Routes/api.php) until/
        // unless modules are changed to route through this group instead.
        $middleware->group('api', [
            ThrottleRequests::class.':60,1',
            EnsureFrontendRequestsAreStateful::class,
            CompressResponse::class,
        ]);

        // Route Specific Middleware Aliases
        $middleware->alias([
            'signed' => ValidateSignature::class,
            'throttle' => ThrottleRequests::class,
            'auth.throttle' => ThrottleRequests::class.':30,1',
        ]);
    })
    ->withExceptions(using: function (Exceptions $exceptions) {
        $exceptions->renderable(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                if ($e instanceof HttpResponseException) {
                    return null;
                }
                $response = match (true) {
                    $e instanceof RouteNotFoundException => ErrorNotFoundResponse::make(),
                    $e instanceof AuthenticationException => ErrorUnauthenticatedResponse::make(),
                    //                    $e instanceof BadMethodCallException => ErrorMethodNotAllowedResponse::make(),
                    $e instanceof ValidationException => ErrorValidationResponse::make($e->errors()),
                    $e instanceof ThrottleRequestsException => ErrorTooManyAttemptsResponse::make(),
                    $e instanceof TokenExpiredException => ErrorTokenExpired::make(),
                    $e instanceof TokenInvalidException => ErrorTokenInvalid::make(),
                    $e instanceof TokenBlacklistedException => ErrorTokenBlacklisted::make(),
                    default => null,
                };
                if (!$response) {
                    $errorMessage = config('app.debug') ? $e->getMessage() : '';
                    $response = ErrorInternalServerErrorResponse::make($errorMessage);
                }
                if ($response instanceof Response && $e instanceof HttpException) {
                    $headers = $e->getHeaders();
                    $response->headers->add($headers);
                }

                return $response;
            }

            return null;
        });

        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });
    })->create();
