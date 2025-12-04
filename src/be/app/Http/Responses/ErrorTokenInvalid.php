<?php

declare(strict_types=1);

namespace App\Http\Responses;

class ErrorTokenInvalid extends ApiErrorResponse
{
    protected function defaultResponseCode(): int
    {
        return 401;
    }

    protected function defaultErrorMessage(): string
    {
        return 'Token is invalid.';
    }
}
