<?php

declare(strict_types=1);

namespace App\Modules\CallEvent\Repositories;

use App\Modules\CallEvent\Contracts\CallEventRepositoryInterface;
use App\Modules\CallEvent\DTOs\CallEventDTO;
use App\Modules\CallEvent\Models\CallEventLog;

final class PostgresCallEventRepository implements CallEventRepositoryInterface
{
    public function create(CallEventDTO $dto): CallEventLog
    {
        return CallEventLog::create([
            'call_id' => $dto->callId,
            'event_type' => $dto->eventType,
            'payload' => $dto->toArray(),
        ]);
    }

    public function findByCallId(string $callId): ?CallEventLog
    {
        return CallEventLog::where('call_id', $callId)
            ->latest('created_time')
            ->first();
    }
}