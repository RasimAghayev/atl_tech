<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

interface EventPublisherInterface
{
    /**
     * @param array<string, scalar> $headers AMQP application headers (e.g. an
     *     idempotency_key so a downstream consumer can dedupe on redelivery).
     */
    public function publish(string $queue, array $payload, array $headers = []): void;

    public function isConnected(): bool;

    public function close(): void;
}