<?php

declare(strict_types=1);

namespace App\Modules\CallEvent\Services;

use App\Modules\CallEvent\Contracts\CallEventRepositoryInterface;
use App\Modules\CallEvent\DTOs\CallEventDTO;
use App\Shared\Contracts\EventPublisherInterface;
use Illuminate\Support\Facades\Log;

final class CallEventService
{
    private const string QUEUE_NAME = 'call-events';

    public function __construct(
        private readonly CallEventRepositoryInterface $repository,
        private readonly EventPublisherInterface $publisher
    ) {
    }

    public function handleCallEvent(CallEventDTO $dto): void
    {
        // Log to database
        $log = $this->repository->create($dto);

        Log::info('Call event logged to database', [
            'id' => $log->id,
            'call_id' => $dto->callId,
            'event_type' => $dto->eventType->value,
        ]);

        // Publish to RabbitMQ
        $this->publisher->publish(self::QUEUE_NAME, $dto->toArray());

        Log::info('Call event queued successfully', [
            'call_id' => $dto->callId,
            'queue' => self::QUEUE_NAME,
        ]);
    }
}