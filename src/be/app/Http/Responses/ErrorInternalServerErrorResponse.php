<?php

declare(strict_types=1);

namespace App\Http\Responses;

class ErrorInternalServerErrorResponse extends ApiErrorResponse
{
    protected function defaultErrorMessage(): string
    {
        return 'There was an error with your request. Please try again later.';
    }
}
