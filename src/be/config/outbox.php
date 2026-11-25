<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Transactional Outbox (ADR-0001)
    |--------------------------------------------------------------------------
    |
    | Tuning for App\Shared\Outbox — the durable, horizontally-scalable relay
    | between "a business write happened" and "the message broker was told
    | about it". See docs/adr/0001-transactional-outbox.md for the full
    | rationale; this file only holds the knobs an operator may need to turn
    | without a code change.
    |
    */

    // How many pending rows a single relay pass claims at once. Each
    // horizontally-scaled `outbox:relay` worker claims its own disjoint
    // batch via `FOR UPDATE SKIP LOCKED` (Postgres only — see
    // OutboxRepository::claimBatch()).
    'batch_size' => (int) env('OUTBOX_BATCH_SIZE', 50),

    // Attempts (including the first) before a row is moved to dead_letter
    // instead of retried again.
    'max_attempts' => (int) env('OUTBOX_MAX_ATTEMPTS', 5),

    // Exponential backoff: available_at = now() + min(cap, base * 2^(attempts-1)).
    'backoff_base_seconds' => (int) env('OUTBOX_BACKOFF_BASE_SECONDS', 2),
    'backoff_cap_seconds' => (int) env('OUTBOX_BACKOFF_CAP_SECONDS', 300),

    // How long the `outbox:relay` worker sleeps between passes when the last
    // pass published nothing (busy passes loop immediately, no sleep).
    'relay_sleep_seconds' => (int) env('OUTBOX_RELAY_SLEEP_SECONDS', 1),
];
