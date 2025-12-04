<?php

declare(strict_types=1);

namespace App\Http\Responses;

class SuccessApiResponse extends ApiBaseResponse
{
    protected function defaultResponseCode(): int
    {
        return 200;
    }
}
