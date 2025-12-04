<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

interface EventPublisherInterface
{
    public function publish(string $queue, array $payload): void;

    public function isConnected(): bool;

    public function close(): void;
}