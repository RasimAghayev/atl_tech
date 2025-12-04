<?php

declare(strict_types=1);

namespace App\Modules\CallEvent\DTOs;

use App\Modules\CallEvent\Enums\CallEventTypeEnum;
use Carbon\Carbon;

final readonly class CallEventDTO
{
    public function __construct(
        public string $callId,
        public string $callerNumber,
        public string $calleeNumber,
        public CallEventTypeEnum $eventType,
        public Carbon $timestamp,
        public ?int $duration = null
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            callId: $data['call_id'],
            callerNumber: $data['caller_number'],
            calleeNumber: $data['callee_number'],
            eventType: CallEventTypeEnum::from($data['event_type']),
            timestamp: Carbon::parse($data['timestamp']),
            duration: $data['duration'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'call_id' => $this->callId,
            'caller_number' => $this->callerNumber,
            'callee_number' => $this->calleeNumber,
            'event_type' => $this->eventType->value,
            'timestamp' => $this->timestamp->toIso8601String(),
            'duration' => $this->duration,
        ];
    }
}