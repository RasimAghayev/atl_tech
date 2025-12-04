<?php

declare(strict_types=1);

namespace App\Shared\Brokers\RabbitMQ;

use App\Shared\Contracts\EventPublisherInterface;
use App\Shared\Exceptions\BrokerConnectionException;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

final class RabbitMQPublisher implements EventPublisherInterface
{
    private ?AMQPStreamConnection $connection = null;

    private ?AMQPChannel $channel = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $user,
        private readonly string $password,
        private readonly string $vhost = '/'
    ) {
    }

    public function publish(string $queue, array $payload): void
    {
        try {
            $this->ensureConnection();

            $this->channel->queue_declare(
                queue: $queue,
                passive: false,
                durable: true,
                exclusive: false,
                auto_delete: false
            );

            $message = new AMQPMessage(
                json_encode($payload, JSON_THROW_ON_ERROR),
                ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
            );

            $this->channel->basic_publish(
                msg: $message,
                exchange: '',
                routing_key: $queue
            );

            Log::info('Message published to RabbitMQ', [
                'queue' => $queue,
                'payload' => $payload,
            ]);
        } catch (\Throwable $e) {
            Log::error('RabbitMQ publish failed', [
                'queue' => $queue,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw BrokerConnectionException::publishFailed($queue, $e->getMessage());
        }
    }

    public function isConnected(): bool
    {
        return $this->connection !== null && $this->connection->isConnected();
    }

    public function close(): void
    {
        if ($this->channel !== null) {
            $this->channel->close();
            $this->channel = null;
        }

        if ($this->connection !== null) {
            $this->connection->close();
            $this->connection = null;
        }
    }

    private function ensureConnection(): void
    {
        if ($this->isConnected()) {
            return;
        }

        try {
            $this->connection = new AMQPStreamConnection(
                host: $this->host,
                port: $this->port,
                user: $this->user,
                password: $this->password,
                vhost: $this->vhost
            );

            $this->channel = $this->connection->channel();

            Log::info('RabbitMQ connection established', [
                'host' => $this->host,
                'port' => $this->port,
            ]);
        } catch (\Throwable $e) {
            Log::error('RabbitMQ connection failed', [
                'host' => $this->host,
                'port' => $this->port,
                'error' => $e->getMessage(),
            ]);

            throw BrokerConnectionException::connectionFailed('RabbitMQ', $e->getMessage());
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}