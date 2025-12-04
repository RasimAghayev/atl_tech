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
        // Global Middleware
        $middleware->use([
            TrustProxies::class,
            HandleCors::class,
            PreventRequestsDuringMaintenance::class,
            ValidatePostSize::class,
            TrimStrings::class,
            ConvertEmptyStringsToNull::class,
            //            SecurityHeaders::class,
        ]);

        // API Middleware Group
        $middleware->group('api', [
            'throttle:api',
            EnsureFrontendRequestsAreStateful::class,
        ]);

        // API Middleware Group
        $middleware->group('api', [
            ThrottleRequests::class.':60,1',
            EnsureFrontendRequestsAreStateful::class,
            CompressResponse::class,
        ]);
        // API Middleware Group
        $middleware->group('csp', [
            ContentSecurityPolicyMiddleware::class,
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
