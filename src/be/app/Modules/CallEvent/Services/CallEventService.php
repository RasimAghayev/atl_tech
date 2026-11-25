<?php

declare(strict_types=1);

namespace App\Modules\CallEvent\Services;

use App\Modules\CallEvent\Contracts\CallEventRepositoryInterface;
use App\Modules\CallEvent\DTOs\CallEventDTO;
use App\Modules\CallEvent\Models\CallEventLog;
use App\Shared\Outbox\OutboxRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class CallEventService
{
    private const string QUEUE_NAME = 'call-events';

    private const string AGGREGATE_TYPE = 'call_event';

    public function __construct(
        private readonly CallEventRepositoryInterface $repository,
        private readonly OutboxRepository $outbox,
    ) {
    }

    /**
     * Writes the call-event log row and its outbox_events row in a single DB
     * transaction (ADR-0001: transactional outbox). Neither write is ever
     * durable without the other — there is no "logged to DB but the broker
     * never finds out" window, and no direct synchronous call to RabbitMQ on
     * the request path at all; a RelayOutboxEvents worker does that.
     *
     * Idempotent: the idempotency key is derived purely from business fields
     * (call_id + event_type + timestamp), so a retried/duplicated submission
     * of the exact same event is a no-op — no duplicate log row, no duplicate
     * outbox row, no duplicate publish — while still returning 200 to the
     * caller (the SIP server's retry doesn't need to know the difference).
     */
    public function handleCallEvent(CallEventDTO $dto): void
    {
        $idempotencyKey = $this->computeIdempotencyKey($dto);

        DB::transaction(function () use ($dto, $idempotencyKey) {
            $log = $this->repository->create($dto, $idempotencyKey);

            if (! $log->wasRecentlyCreated) {
                Log::info('Duplicate call event ignored (idempotent)', [
                    'call_id' => $dto->callId,
                    'idempotency_key' => $idempotencyKey,
                ]);

                return;
            }

            $this->outbox->enqueue(
                aggregateType: self::AGGREGATE_TYPE,
                aggregateId: $dto->callId,
                eventType: $dto->eventType->value,
                queueName: self::QUEUE_NAME,
                payload: $dto->toArray(),
                idempotencyKey: $idempotencyKey,
                maxAttempts: (int) config('outbox.max_attempts'),
            );

            Log::info('Call event logged and enqueued for relay', [
                'id' => $log->id,
                'call_id' => $dto->callId,
                'event_type' => $dto->eventType->value,
            ]);
        });
    }

    /** @return Collection<int, CallEventLog> newest first */
    public function getHistory(string $callId): Collection
    {
        return $this->repository->getHistory($callId);
    }

    private function computeIdempotencyKey(CallEventDTO $dto): string
    {
        return hash('sha256', implode('|', [
            $dto->callId,
            $dto->eventType->value,
            $dto->timestamp->toIso8601String(),
        ]));
    }
}
