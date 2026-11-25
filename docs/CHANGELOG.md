# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
once it cuts its first tagged release (it has not yet).

## [Unreleased]

### Added
- **Transactional outbox pattern** (ADR-0001, `docs/adr/0001-transactional-outbox.md`):
  call-event creation now writes the business row and an `outbox_events` row
  in a single database transaction. Publishing to RabbitMQ is no longer on
  the request path — an independent `outbox:relay` console worker (new
  `relay` service in `docker-compose.yml`) drains the outbox with
  exponential backoff and dead-lettering, and scales horizontally via
  Postgres `FOR UPDATE SKIP LOCKED` claiming (`OutboxRepository::claimBatch()`).
  This removes the prior failure mode where a broker outage after a
  successful DB write produced an HTTP 500 with an inconsistent row
  (TD-INT-001, TD-INT-002).
- **Idempotency key** on call-event submission: a server-computed SHA-256
  key is stored on both `call_event_logs.idempotency_key` and the
  corresponding outbox row; a retried submission with the same key is a
  no-op (`firstOrCreate()` / `wasRecentlyCreated`), not a duplicate.
- **Call-event history endpoint** for querying previously submitted events.
- **Health endpoints**: `GET /health/live` and `GET /health/ready`
  (`HealthController`). Readiness is deliberately scoped to PostgreSQL only
  — the outbox pattern removed the synchronous RabbitMQ dependency from the
  request path, so readiness no longer needs to probe the broker (see
  ADR-0001). `server/nginx/Dockerfile`'s container `HEALTHCHECK` now probes
  `/health/ready` instead of Laravel's default `/up`.
- Two new migrations: `create_outbox_events_table`,
  `add_idempotency_key_to_call_event_logs_table`.
- `docs/adr/0001-transactional-outbox.md` and `docs/BASELINE.md` entries
  documenting the Docker-verified evidence for all of the above.

### Changed
- `SecurityHeaders` middleware is now actually wired into the HTTP
  middleware stack. It existed as a class before but was never attached —
  the duplicate `api` middleware-group definition in `bootstrap/app.php`
  that caused this (TD-CFG-001) was consolidated to one definition.
- `config/l5-swagger.php`'s annotation scan path corrected from
  `base_path('App')` to `base_path('app')` (TD-DOCS-001) — case-sensitive
  filesystems (every Docker build here) could silently scan nothing.
- `src/be/phpunit.xml`'s coverage `<source><include>` path corrected from
  `App` to `app` for the same reason (TD-TOOL-001).
- `.dockerignore`'s `vendor/` exclusion pattern anchored to `**/vendor`
  (previously only matched a top-level `./vendor`, never the real
  `src/be/vendor/` path), matching the existing `**/node_modules`
  convention. Prevents a stray host-side vendor directory from ever being
  copied into an image build and corrupting a stage's own
  freshly-installed dependencies.
- `server/php/Dockerfile`'s comment above the `runtime` stage corrected: a
  green `runtime` build does **not** imply the test suite passed —
  `runtime`'s only ancestor is `base → deps-prod`, entirely independent of
  `deps`/`test`. `make verify` (`--target test`) must be run and inspected
  as its own explicit step.
- `docker-compose.yml`'s `relay` service: added `healthcheck: disable: true`
  (it runs the `outbox:relay` console worker, not php-fpm, so the image's
  inherited HEALTHCHECK on `:9000` would otherwise report "unhealthy"
  forever).
- `Makefile`'s `up` target now also starts `relay` (previously only
  `nginx`; nothing else `depends_on`s `relay`, so it never started by
  default).
- Dependency lockfile drift within already-declared semver ranges
  (`src/be/composer.lock`, `src/be/package.json` devDependencies) — not a
  full ordered latest-stable upgrade pass (that remains Phase 6's job,
  still open per TD-DEP-001).

### Fixed
- `tests/TestCase.php`: removed a manual `DB::disconnect()` call placed
  after `parent::tearDown()` that tried to resolve the already-flushed `db`
  container binding, which errored 21 of 23 tests with "Target class [db]
  does not exist". Laravel's own `RefreshDatabase` trait already handles
  connection teardown correctly for this suite's `:memory:` sqlite
  configuration; `parent::tearDown()` alone is correct and sufficient.
- `OutboxRepository::claimBatch()`: added a missing `syncOriginal()` call
  after manually mutating `$event->status` in memory. Without it, a later
  partial `update()` call from `markFailed()` that set the status back to
  `Pending` matched the (stale) in-memory `original` value and Eloquent's
  dirty-tracking silently dropped `status` from the generated `UPDATE`
  statement — the row stayed stuck on `processing` in the database forever.
  Reproduced via
  `OutboxRelayServiceTest::test_relay_batch_backs_off_on_publish_failure_instead_of_losing_the_event`.
- Removed `src/be/config/call-event.php`, a duplicate of
  `app/Shared/Config/call-event.php` that was merged via
  `CallEventServiceProvider::register()`'s `mergeConfigFrom()` — dead
  duplication, not a behavior change.
- `.env.example` / `src/be/.env.example`: removed real-looking hardcoded
  values, replaced with placeholders. **Note:** the original values were
  already present in git history (TD-SEC-001) — this change does not
  rotate or remove them from history; that still requires action outside
  this repo's codebase (see `docs/TECHNICAL_DEBT.md` section 6).

### Security
- See TD-SEC-001 in `docs/TECHNICAL_DEBT.md` — historical secret exposure
  in git history is unresolved by this change set and requires manual
  credential rotation plus a separately-gated history rewrite (Gate 1).

[Unreleased]: https://github.com/RasimAghayev/atl_tech/compare/master...HEAD
