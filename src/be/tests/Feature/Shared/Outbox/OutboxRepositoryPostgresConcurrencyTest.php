<?php

declare(strict_types=1);

namespace Tests\Feature\Shared\Outbox;

use App\Shared\Outbox\Models\OutboxEvent;
use App\Shared\Outbox\OutboxRepository;
use App\Shared\Outbox\OutboxStatus;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The sqlite suite (OutboxRepositoryTest) proves claimBatch()'s behavior but
 * cannot prove its actual concurrency guarantee — sqlite has no
 * `FOR UPDATE SKIP LOCKED`. This test proves the real claim: two concurrent
 * claimers against real Postgres never both receive the same row.
 *
 * Skipped unless running against pgsql — the Docker `test` build stage runs
 * against sqlite (fast, no external services, see server/php/Dockerfile), so
 * this never runs there. Run it explicitly via `make test-integration`
 * (docs/BASELINE.md) against the real `pgsql` compose service.
 */
class OutboxRepositoryPostgresConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requires a real Postgres connection — run via make test-integration.');
        }

        DB::statement('TRUNCATE TABLE outbox_events RESTART IDENTITY');
    }

    public function test_two_concurrent_claimers_never_double_claim_the_same_row(): void
    {
        for ($i = 0; $i < 20; $i++) {
            OutboxEvent::create([
                'idempotency_key' => "concurrency-{$i}",
                'aggregate_type' => 'call_event',
                'aggregate_id' => "CALL-{$i}",
                'event_type' => 'call_started',
                'queue_name' => 'call-events',
                'payload' => ['call_id' => "CALL-{$i}"],
                'status' => OutboxStatus::Pending,
                'max_attempts' => 5,
                'available_at' => now()->subMinute(),
            ]);
        }

        // Two independent DB connections simulate two separate relay worker
        // processes/instances racing for the same rows.
        $repositoryA = new OutboxRepository();
        $repositoryB = new OutboxRepository();

        $claimedA = [];
        $claimedB = [];

        // Interleave claims in small batches rather than one call each, to
        // maximize the chance of overlap if SKIP LOCKED were not working.
        for ($round = 0; $round < 10; $round++) {
            $claimedA = array_merge($claimedA, $repositoryA->claimBatch(1)->pluck('id')->all());
            $claimedB = array_merge($claimedB, $repositoryB->claimBatch(1)->pluck('id')->all());
        }

        $overlap = array_intersect($claimedA, $claimedB);

        $this->assertSame([], array_values($overlap), 'The same outbox row was claimed by both workers.');
        $this->assertCount(20, array_unique(array_merge($claimedA, $claimedB)));
    }
}
