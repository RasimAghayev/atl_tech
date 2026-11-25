<?php

declare(strict_types=1);

namespace App\Shared\Outbox;

use App\Shared\Outbox\Models\OutboxEvent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Persistence for the transactional outbox (ADR-0001).
 */
final class OutboxRepository
{
    /**
     * Enqueue an event. Called from inside the SAME DB transaction as the
     * business write it describes (see CallEventService::handleCallEvent).
     * Idempotent on $idempotencyKey: a retried business write that reuses
     * the same key (because the business row itself was also deduplicated)
     * returns the already-existing row instead of creating a second one.
     */
    public function enqueue(
        string $aggregateType,
        string $aggregateId,
        string $eventType,
        string $queueName,
        array $payload,
        string $idempotencyKey,
        int $maxAttempts,
    ): OutboxEvent {
        return OutboxEvent::firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'aggregate_type' => $aggregateType,
                'aggregate_id' => $aggregateId,
                'event_type' => $eventType,
                'queue_name' => $queueName,
                'payload' => $payload,
                'status' => OutboxStatus::Pending,
                'max_attempts' => $maxAttempts,
                'available_at' => now(),
            ]
        );
    }

    /**
     * Atomically claim up to $limit due rows and flip them to `processing`,
     * in one DB transaction. On Postgres the claiming SELECT uses
     * `FOR UPDATE SKIP LOCKED`, which is what lets N horizontally-scaled
     * `outbox:relay` worker instances each claim a disjoint batch without
     * blocking on each other or ever double-claiming the same row — the
     * core reason this pattern scales out instead of needing a single
     * designated relay process.
     */
    public function claimBatch(int $limit): Collection
    {
        return DB::transaction(function () use ($limit) {
            $events = $this->selectClaimable($limit);

            if ($events->isEmpty()) {
                return $events;
            }

            OutboxEvent::whereIn('id', $events->pluck('id'))
                ->update(['status' => OutboxStatus::Processing->value, 'updated_at' => now()]);

            // syncOriginal() after the manual mutation below is required, not
            // cosmetic: without it, this object's `original[status]` is still
            // the pre-claim value ('pending'), and Eloquent's dirty-tracking
            // compares against *that* on any later partial update() call on
            // this same instance. OutboxRelayService::relayBatch() does
            // exactly that via markFailed() — when a non-dead-lettered retry
            // sets status back to Pending, Pending again equals the stale
            // original, so Eloquent silently drops `status` from the UPDATE's
            // SET clause and the row is left stuck on `processing` in the
            // database even though the in-memory object reports `pending`.
            // Reproduced via OutboxRelayServiceTest::test_relay_batch_backs_off_on_publish_failure_instead_of_losing_the_event
            // before this fix.
            return $events->map(static function (OutboxEvent $event): OutboxEvent {
                $event->status = OutboxStatus::Processing;
                $event->syncOriginal();

                return $event;
            });
        });
    }

    public function markPublished(OutboxEvent $event): void
    {
        $event->update([
            'status' => OutboxStatus::Published,
            'published_at' => now(),
        ]);
    }

    /**
     * Records a failed publish attempt. Below $event->max_attempts the row
     * goes back to `pending` with exponential backoff (base * 2^(attempts-1),
     * capped); at or beyond max_attempts it is dead-lettered instead of
     * retried again, so a permanently-broken payload can't retry forever.
     */
    public function markFailed(
        OutboxEvent $event,
        string $error,
        int $backoffBaseSeconds,
        int $backoffCapSeconds,
    ): void {
        $attempts = $event->attempts + 1;
        $deadLettered = $attempts >= $event->max_attempts;

        $event->update([
            'attempts' => $attempts,
            'status' => $deadLettered ? OutboxStatus::DeadLetter : OutboxStatus::Pending,
            'available_at' => $deadLettered
                ? $event->available_at
                : now()->addSeconds(min($backoffCapSeconds, $backoffBaseSeconds * (2 ** ($attempts - 1)))),
            'last_error' => $error,
        ]);
    }

    private function selectClaimable(int $limit): Collection
    {
        $query = OutboxEvent::query()
            ->where('status', OutboxStatus::Pending)
            ->where('available_at', '<=', now())
            ->orderBy('available_at')
            ->limit($limit);

        if (DB::connection()->getDriverName() === 'pgsql') {
            // Laravel's query builder has no fluent "skip locked" — the WHERE/
            // ORDER/LIMIT built above are reused verbatim via toSql()/getBindings()
            // so this raw path can never drift from the portable one below.
            $rows = DB::select($query->toSql().' FOR UPDATE SKIP LOCKED', $query->getBindings());

            return OutboxEvent::hydrate(array_map(static fn (object $row): array => (array) $row, $rows));
        }

        // Non-Postgres (sqlite, used only in the unit/feature test suite):
        // plain row lock, no SKIP LOCKED support. The single-process test
        // environment never exercises concurrent claiming anyway — the real
        // concurrency guarantee is proven separately against real Postgres
        // (see docs/BASELINE.md / the Postgres integration test).
        return $query->lockForUpdate()->get();
    }
}
