<?php

declare(strict_types=1);

namespace App\Shared\Outbox;

use App\Shared\Contracts\EventPublisherInterface;

/**
 * The ONLY code path that talks to the message broker (ADR-0001). The HTTP
 * request path never calls EventPublisherInterface directly anymore —
 * CallEventService only writes to outbox_events. This service is what a
 * `outbox:relay` worker instance runs, over and over, to drain it.
 */
final class OutboxRelayService
{
    public function __construct(
        private readonly OutboxRepository $repository,
        private readonly EventPublisherInterface $publisher,
    ) {
    }

    /**
     * Claims one batch and attempts to publish each row. Returns the count
     * actually published (for the caller to decide whether to sleep before
     * the next pass).
     */
    public function relayBatch(): int
    {
        $events = $this->repository->claimBatch((int) config('outbox.batch_size'));

        $published = 0;

        foreach ($events as $event) {
            try {
                $this->publisher->publish($event->queue_name, $event->payload, [
                    'idempotency_key' => $event->idempotency_key,
                    'event_type' => $event->event_type,
                ]);

                $this->repository->markPublished($event);
                $published++;
            } catch (\Throwable $e) {
                $this->repository->markFailed(
                    $event,
                    $e->getMessage(),
                    (int) config('outbox.backoff_base_seconds'),
                    (int) config('outbox.backoff_cap_seconds'),
                );
            }
        }

        return $published;
    }
}
