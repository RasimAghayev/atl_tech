<?php

declare(strict_types=1);

namespace App\Modules\CallEvent\Repositories;

use App\Modules\CallEvent\Contracts\CallEventRepositoryInterface;
use App\Modules\CallEvent\DTOs\CallEventDTO;
use App\Modules\CallEvent\Models\CallEventLog;
use Illuminate\Database\Eloquent\Collection;

final class PostgresCallEventRepository implements CallEventRepositoryInterface
{
    public function create(CallEventDTO $dto, string $idempotencyKey): CallEventLog
    {
        return CallEventLog::firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'call_id' => $dto->callId,
                'event_type' => $dto->eventType,
                'payload' => $dto->toArray(),
                'idempotency_key' => $idempotencyKey,
            ]
        );
    }

    public function findByCallId(string $callId): ?CallEventLog
    {
        return CallEventLog::where('call_id', $callId)
            ->latest('created_at')
            ->first();
    }

    public function getHistory(string $callId): Collection
    {
        return CallEventLog::where('call_id', $callId)
            ->orderByDesc('created_at')
            ->get();
    }
}
