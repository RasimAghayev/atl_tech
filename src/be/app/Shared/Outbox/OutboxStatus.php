<?php

declare(strict_types=1);

namespace App\Shared\Outbox;

/**
 * Lifecycle of one outbox_events row (ADR-0001).
 *
 *   pending -> processing -> published
 *                 |
 *                 +-> pending (retry, backoff applied) -> ... -> dead_letter
 *
 * `processing` only ever exists for the lifetime of the claiming relay
 * worker's single pass — a worker that crashes mid-batch leaves rows stuck in
 * `processing` rather than silently losing them; an operator (or a future
 * sweeper) can requeue anything `processing` past a sane age.
 */
enum OutboxStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Published = 'published';
    case DeadLetter = 'dead_letter';
}
