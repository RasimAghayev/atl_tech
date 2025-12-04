<?php

declare(strict_types=1);

namespace App\Http\Responses;

class ErrorTooManyAttemptsResponse extends ApiErrorResponse
{
    protected function defaultResponseCode(): int
    {
        return 429;
    }

    protected function defaultErrorMessage(): string
    {
        return 'Too many requests, please stop.';
    }
}
