# ADR-0001: Transactional outbox for call-event publishing

- Status: Accepted
- Date: 2026-11-25

## Context

`CallEventService::handleCallEvent()` used to do two separate writes for every
incoming call event:

1. `INSERT` a `call_event_logs` row (PostgreSQL).
2. Synchronously call `RabbitMQPublisher::publish()` over AMQP.

These two writes were not atomic. If the process crashed, the database
connection dropped, or RabbitMQ was briefly unreachable between steps 1 and 2,
the event was logged but never queued — a silent data-consistency gap with no
way to detect or repair it after the fact. The inverse failure (publish
succeeds, the surrounding request dies before returning 200, SIP server
retries) could also double-publish, since nothing deduplicated retries.

There was also no isolation between "accept this event" and "tell RabbitMQ
about it": a RabbitMQ outage turned directly into 500s on the ingestion
endpoint, even though the only thing actually wrong was the downstream
broker, not this service's ability to record the event.

## Decision

Adopt the **transactional outbox** pattern:

- `CallEventService::handleCallEvent()` writes the `call_event_logs` row and a
  new `outbox_events` row in one `DB::transaction()`. Either both are durable
  or neither is — there is no window where one exists without the other.
- The HTTP request path **never calls `EventPublisherInterface` directly**
  anymore. A separate `outbox:relay` worker (`RelayOutboxEvents` console
  command, backed by `OutboxRelayService`) is the only code that talks to
  RabbitMQ, draining `outbox_events` on its own schedule.
- Claiming a batch to relay (`OutboxRepository::claimBatch()`) uses
  `SELECT ... FOR UPDATE SKIP LOCKED` on Postgres, so the relay worker can be
  run as **N horizontally-scaled instances** without any of them blocking on
  or double-claiming the other's in-flight rows — a prerequisite for treating
  this service as horizontally scalable rather than a single-instance
  bottleneck.
- Every event carries a server-computed idempotency key
  (`sha256(call_id|event_type|timestamp)`), unique on both `call_event_logs`
  and `outbox_events`. A retried submission of the same event is a no-op at
  the database layer (`firstOrCreate`) — still returns `200 {"status":
  "queued"}` to the caller, but creates no duplicate row and enqueues no
  duplicate publish. The same key travels to the consumer as an AMQP
  `idempotency_key` application header, so a downstream consumer can dedupe
  on redelivery too.
- Failed publishes retry with exponential backoff
  (`base * 2^(attempts-1)`, capped) and dead-letter after `max_attempts`
  instead of retrying forever or being silently dropped.

## Consequences

- **Readiness changed.** `/health/ready` (see `HealthController`) checks only
  PostgreSQL. This is not an oversight — it is the direct, intended
  consequence of this ADR: the request path no longer has a synchronous
  RabbitMQ dependency, so a RabbitMQ outage must not flip this instance to
  "not ready". It instead grows the `outbox_events` backlog, which is
  observable (count of `pending`/`dead_letter` rows) and recoverable once the
  broker returns, rather than an outage of the ingestion API itself.
- **New operational unit.** The relay worker is a new deployable thing (a
  process running `php artisan outbox:relay`), not just a library change. It
  needs its own container/supervision and its own scaling policy, independent
  of the web/php-fpm process count.
- **New table, new index-maintenance cost.** `outbox_events` is written once
  per accepted event and updated at least twice (claim, then
  published/failed). For this service's traffic profile that's a negligible
  cost next to the consistency guarantee bought.
- **Dead rows need an operational answer eventually.** `dead_letter` rows are
  not auto-purged by anything in this change — an operator has to look at
  them (query by `status = 'dead_letter'`) and decide to replay or discard.
  No automatic replay tooling exists yet; tracked as a follow-up, not blocking
  this ADR, since building it speculatively before any real dead-letter
  volume exists would be exactly the kind of premature feature this program's
  YAGNI standing decision warns against.
- **`findByCallId()`'s pre-existing `created_time` column-name bug** (it
  referenced a column that doesn't exist; the real column is `created_at`)
  is fixed as part of this change, since the new `getHistory()` read path
  finally exercises code adjacent to it and the bug would otherwise ship
  silently alongside working code again.

## Alternatives considered

- **Two-phase commit / XA transactions** across Postgres and RabbitMQ:
  rejected — operationally heavy, poor driver support, and still does not
  solve the "process died before either commit" case any better than the
  outbox does for far less complexity.
- **At-least-once direct publish with a retry queue bolted onto the HTTP
  path**: rejected — still requires the DB write and the publish to succeed
  together for the request to complete, which keeps the ingestion endpoint
  coupled to RabbitMQ's availability; the outbox removes that coupling
  entirely by deferring the publish to an independent process.
- **Change Data Capture (e.g. Debezium reading the Postgres WAL)**: a more
  "automatic" way to get the same guarantee, but adds an entire CDC
  pipeline/infra dependency for one queue in one service — not justified by
  this repo's actual scale (KISS/YAGNI, standing decision 7).
