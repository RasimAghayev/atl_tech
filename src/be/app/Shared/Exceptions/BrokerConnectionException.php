<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use RuntimeException;

class BrokerConnectionException extends RuntimeException
{
    public static function connectionFailed(string $broker, string $reason): self
    {
        return new self("Failed to connect to {$broker}: {$reason}");
    }

    public static function publishFailed(string $queue, string $reason): self
    {
        return new self("Failed to publish message to queue '{$queue}': {$reason}");
    }
}