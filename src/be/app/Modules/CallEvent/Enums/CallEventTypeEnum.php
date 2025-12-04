<?php

declare(strict_types=1);

namespace App\Modules\CallEvent\Enums;

enum CallEventTypeEnum: string
{
    case CALL_STARTED = 'call_started';
    case CALL_ENDED = 'call_ended';
    case CALL_HELD = 'call_held';
    case CALL_TRANSFERRED = 'call_transferred';
    case CALL_MISSED = 'call_missed';
    case CALL_ANSWERED = 'call_answered';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isDurationRequired(): bool
    {
        return $this === self::CALL_ENDED;
    }
}