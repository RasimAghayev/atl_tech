<?php

declare(strict_types=1);

namespace App\Http\Responses;

class ErrorMethodNotAllowedResponse extends ApiErrorResponse
{
    protected function defaultResponseCode(): int
    {
        return 405;
    }

    protected function defaultErrorMessage(): string
    {
        return 'Error method is not allowed.';
    }
}
