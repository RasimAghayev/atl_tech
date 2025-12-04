<?php

declare(strict_types=1);

namespace App\Modules\CallEvent\Contracts;

use App\Modules\CallEvent\DTOs\CallEventDTO;
use App\Modules\CallEvent\Models\CallEventLog;

interface CallEventRepositoryInterface
{
    public function create(CallEventDTO $dto): CallEventLog;

    public function findByCallId(string $callId): ?CallEventLog;
}