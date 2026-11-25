<?php

declare(strict_types=1);

namespace App\Modules\CallEvent\Contracts;

use App\Modules\CallEvent\DTOs\CallEventDTO;
use App\Modules\CallEvent\Models\CallEventLog;
use Illuminate\Database\Eloquent\Collection;

interface CallEventRepositoryInterface
{
    /**
     * Idempotent on $idempotencyKey: a retried submission of the same event
     * returns the already-existing row (check `wasRecentlyCreated` to tell
     * the two cases apart) instead of creating a duplicate.
     */
    public function create(CallEventDTO $dto, string $idempotencyKey): CallEventLog;

    public function findByCallId(string $callId): ?CallEventLog;

    /** @return Collection<int, CallEventLog> newest first */
    public function getHistory(string $callId): Collection;
}
