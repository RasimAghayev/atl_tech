<?php

declare(strict_types=1);

namespace Tests\Feature\Shared\Outbox;

use App\Shared\Outbox\Models\OutboxEvent;
use App\Shared\Outbox\OutboxRepository;
use App\Shared\Outbox\OutboxStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutboxRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private OutboxRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(OutboxRepository::class);
    }

    public function test_enqueue_is_idempotent_on_idempotency_key(): void
    {
        $args = [
            'aggregateType' => 'call_event',
            'aggregateId' => 'CALL-1',
            'eventType' => 'call_started',
            'queueName' => 'call-events',
            'payload' => ['call_id' => 'CALL-1'],
            'idempotencyKey' => 'key-1',
            'maxAttempts' => 5,
        ];

        $first = $this->repository->enqueue(...$args);
        $second = $this->repository->enqueue(...$args);

        $this->assertTrue($first->wasRecentlyCreated);
        $this->assertFalse($second->wasRecentlyCreated);
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('outbox_events', 1);
    }

    public function test_claim_batch_only_returns_due_pending_rows_and_marks_them_processing(): void
    {
        $due = $this->makeEvent('due-1', 'CALL-1', OutboxStatus::Pending, now()->subMinute());
        $notYetDue = $this->makeEvent('future-1', 'CALL-2', OutboxStatus::Pending, now()->addHour());
        $alreadyPublished = $this->makeEvent('done-1', 'CALL-3', OutboxStatus::Published, now()->subMinute());

        $claimed = $this->repository->claimBatch(10);

        $this->assertCount(1, $claimed);
        $this->assertSame($due->id, $claimed->first()->id);
        $this->assertSame(OutboxStatus::Processing, $claimed->first()->status);
        $this->assertSame(OutboxStatus::Processing, $due->refresh()->status);
        $this->assertSame(OutboxStatus::Pending, $notYetDue->refresh()->status);
        $this->assertSame(OutboxStatus::Published, $alreadyPublished->refresh()->status);
    }

    public function test_claim_batch_respects_the_limit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->makeEvent("many-{$i}", "CALL-{$i}", OutboxStatus::Pending, now()->subMinute());
        }

        $claimed = $this->repository->claimBatch(2);

        $this->assertCount(2, $claimed);
    }

    public function test_mark_published_sets_status_and_timestamp(): void
    {
        $event = $this->makeEvent('key-1', 'CALL-1', OutboxStatus::Processing, now());

        $this->repository->markPublished($event);
        $event->refresh();

        $this->assertSame(OutboxStatus::Published, $event->status);
        $this->assertNotNull($event->published_at);
    }

    public function test_mark_failed_retries_with_backoff_below_max_attempts(): void
    {
        $event = $this->makeEvent('key-1', 'CALL-1', OutboxStatus::Processing, now(), attempts: 0, maxAttempts: 3);

        $this->repository->markFailed($event, 'boom', backoffBaseSeconds: 10, backoffCapSeconds: 300);
        $event->refresh();

        $this->assertSame(OutboxStatus::Pending, $event->status);
        $this->assertSame(1, $event->attempts);
        $this->assertSame('boom', $event->last_error);
        $this->assertTrue($event->available_at->isAfter(now()));
    }

    public function test_mark_failed_dead_letters_at_max_attempts(): void
    {
        $event = $this->makeEvent('key-1', 'CALL-1', OutboxStatus::Processing, now(), attempts: 2, maxAttempts: 3);

        $this->repository->markFailed($event, 'boom', backoffBaseSeconds: 10, backoffCapSeconds: 300);
        $event->refresh();

        $this->assertSame(OutboxStatus::DeadLetter, $event->status);
        $this->assertSame(3, $event->attempts);
    }

    private function makeEvent(
        string $idempotencyKey,
        string $aggregateId,
        OutboxStatus $status,
        \DateTimeInterface $availableAt,
        int $attempts = 0,
        int $maxAttempts = 5,
    ): OutboxEvent {
        return OutboxEvent::create([
            'idempotency_key' => $idempotencyKey,
            'aggregate_type' => 'call_event',
            'aggregate_id' => $aggregateId,
            'event_type' => 'call_started',
            'queue_name' => 'call-events',
            'payload' => ['call_id' => $aggregateId],
            'status' => $status,
            'attempts' => $attempts,
            'max_attempts' => $maxAttempts,
            'available_at' => $availableAt,
            'published_at' => $status === OutboxStatus::Published ? now() : null,
        ]);
    }
}
