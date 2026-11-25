<?php

declare(strict_types=1);

namespace Tests\Feature\Shared\Outbox;

use App\Shared\Contracts\EventPublisherInterface;
use App\Shared\Outbox\Models\OutboxEvent;
use App\Shared\Outbox\OutboxRelayService;
use App\Shared\Outbox\OutboxStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class OutboxRelayServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_relay_batch_publishes_due_events_and_marks_them_published(): void
    {
        OutboxEvent::create([
            'idempotency_key' => 'key-1',
            'aggregate_type' => 'call_event',
            'aggregate_id' => 'CALL-1',
            'event_type' => 'call_started',
            'queue_name' => 'call-events',
            'payload' => ['call_id' => 'CALL-1'],
            'status' => OutboxStatus::Pending,
            'max_attempts' => 5,
            'available_at' => now()->subMinute(),
        ]);

        $this->mock(EventPublisherInterface::class, function (MockInterface $mock) {
            $mock->expects('publish')
                ->with(
                    'call-events',
                    ['call_id' => 'CALL-1'],
                    ['idempotency_key' => 'key-1', 'event_type' => 'call_started']
                )
                ->andReturnNull();
        });

        $published = app(OutboxRelayService::class)->relayBatch();

        $this->assertSame(1, $published);
        $this->assertDatabaseHas('outbox_events', [
            'idempotency_key' => 'key-1',
            'status' => OutboxStatus::Published->value,
        ]);
    }

    public function test_relay_batch_backs_off_on_publish_failure_instead_of_losing_the_event(): void
    {
        OutboxEvent::create([
            'idempotency_key' => 'key-2',
            'aggregate_type' => 'call_event',
            'aggregate_id' => 'CALL-2',
            'event_type' => 'call_started',
            'queue_name' => 'call-events',
            'payload' => ['call_id' => 'CALL-2'],
            'status' => OutboxStatus::Pending,
            'max_attempts' => 5,
            'available_at' => now()->subMinute(),
        ]);

        $this->mock(EventPublisherInterface::class, function (MockInterface $mock) {
            $mock->expects('publish')->andThrow(new \RuntimeException('broker down'));
        });

        $published = app(OutboxRelayService::class)->relayBatch();

        $this->assertSame(0, $published);

        $event = OutboxEvent::where('idempotency_key', 'key-2')->first();
        $this->assertSame(OutboxStatus::Pending, $event->status);
        $this->assertSame(1, $event->attempts);
        $this->assertSame('broker down', $event->last_error);
        $this->assertTrue($event->available_at->isAfter(now()));
    }

    public function test_relay_batch_dead_letters_after_exhausting_attempts(): void
    {
        OutboxEvent::create([
            'idempotency_key' => 'key-3',
            'aggregate_type' => 'call_event',
            'aggregate_id' => 'CALL-3',
            'event_type' => 'call_started',
            'queue_name' => 'call-events',
            'payload' => ['call_id' => 'CALL-3'],
            'status' => OutboxStatus::Pending,
            'attempts' => 1,
            'max_attempts' => 2,
            'available_at' => now()->subMinute(),
        ]);

        $this->mock(EventPublisherInterface::class, function (MockInterface $mock) {
            $mock->expects('publish')->andThrow(new \RuntimeException('broker down'));
        });

        app(OutboxRelayService::class)->relayBatch();

        $event = OutboxEvent::where('idempotency_key', 'key-3')->first();
        $this->assertSame(OutboxStatus::DeadLetter, $event->status);
        $this->assertSame(2, $event->attempts);
    }
}
