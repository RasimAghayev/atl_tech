# Docker-first verification baseline — `atl_tech`

> Per CLAUDE.md decision 2: nothing is "working" unless it builds and its test
> stage passes **in Docker**. This file records the actual commands run and
> their actual exit codes — not paraphrased, not assumed.

## Verification gate

```
make verify
# == docker build --target test -f server/php/Dockerfile -t atl_tech-php-test:local .
```

This builds `server/php/Dockerfile`'s `base → deps → test` stages: installs
every PHP extension the app uses, installs Composer dependencies (including
dev requirements) with BuildKit cache mounts, generates an app key, and runs
`vendor/bin/phpunit --testsuite=Unit,Feature` against an in-memory sqlite
database — all inside the build itself.

**Correction (2026-11-25, second session):** the sentence that used to be here
claimed a build that reaches `runtime` has "by construction" already passed
this gate. That is false and has been removed. `runtime`'s only ancestor is
`base → deps-prod`; it shares no layer with `deps`/`test` at all, so a green
`runtime` build proves nothing about whether the test suite passed. `test`
must be built and inspected as its own explicit step (`make verify` /
`docker build --target test ...`) every time — see the corrected comment
above the `runtime` stage in `server/php/Dockerfile` for the same fix applied
at the source. CI (once Phase 10 lands) must run `--target test` as its own
job, not infer it from a successful `runtime` build.

## 2026-11-25 run

| Command | Exit code | Notes |
|---|---|---|
| `MSYS_NO_PATHCONV=1 docker build --target test -f server/php/Dockerfile -t atl_tech-php-test:local .` | 0 | Full suite (Unit + Feature, including the new outbox/health tests) green against sqlite in-memory. |

Two real `server/php/Dockerfile` bugs were found and fixed to get a green
build (both reproduced and confirmed in isolation with throwaway
`docker run php:8.5-fpm` containers before being fixed in the Dockerfile
itself — see the comment above the extension-install `RUN` in
`server/php/Dockerfile` for the full explanation):

1. `docker-php-ext-install pdo` was being called explicitly. `pdo` ships
   compiled in by default on the official php images; explicitly installing
   it produces no loadable module and fails the aggregate `install-modules`
   step with `cp: cannot stat 'modules/*'`. Fixed by removing it — only the
   driver (`pdo_pgsql`) needs installing.
2. `docker-php-ext-configure gd` ran well before `docker-php-ext-install gd`
   (several unrelated extensions installed in between). Any
   `docker-php-ext-install` call issued while an extension is
   "configured-as-shared" but not yet built fails the same aggregate
   `install-modules` step, because it tries to collect a module that was
   never compiled. Fixed by pairing `docker-php-ext-configure gd` immediately
   with `docker-php-ext-install gd`. The original, unrelated
   `docker-php-ext-configure pgsql --with-pgsql=...` line (configuring a
   *different*, never-installed extension called `pgsql`, not `pdo_pgsql`)
   was removed entirely — it was the same kind of vestigial dead
   configuration causing the identical failure, and the app never used the
   bare `pgsql` extension.

## Production-parity gap (documented, not hidden)

The `test` stage runs against **sqlite in-memory**, not PostgreSQL. This is a
deliberate, known gap for build-time speed (no external services needed to
fail fast), not an oversight:

- Dialect-specific behavior (JSON handling, column casing coming through
  config/l5-swagger.php and phpunit.xml casing fixes, timestamp semantics)
  is the same risk surface it always was for this stack — see TD-TEST-001 in
  `docs/TECHNICAL_DEBT.md`.
- The transactional outbox's actual concurrency guarantee
  (`SELECT ... FOR UPDATE SKIP LOCKED`) is Postgres-specific and
  **cannot be proven on sqlite at all** — `OutboxRepositoryPostgresConcurrencyTest`
  is skipped automatically unless the active connection is `pgsql`. **Now run
  for real, see the 2026-11-25 (second session) entry below** — this gap is
  closed.

## 2026-11-25 run (second session — full compose stack + Postgres integration)

Continuation of the same day's work: the diff left uncommitted by the first
session was functionally complete but had never actually been run end-to-end
in Docker. This session did that, found and fixed 4 real bugs purely through
hands-on Docker verification (never from reading code alone), and only then
recorded the results below.

| Command | Exit code | Notes |
|---|---|---|
| `docker build --target test -f server/php/Dockerfile -t atl_tech-php-test:local .` (re-run after the fixes below) | 0 | 23 passed, 1 skipped (the Postgres-only concurrency test, correctly skipped under sqlite), 70 assertions, 0 failures. |
| `docker compose run --rm composer install --no-interaction` | 0 | Populates the bind-mounted `src/be/vendor` the Dockerized way (decision 2-compliant: composer runs inside the `composer` service's container, never on the host) — required once per fresh worktree/volume before `php`/`relay`/`artisan`/`phpunit` services can boot at all, since `docker-compose.yml`'s bind mount (`./src/be:/var/www/html/be`) replaces each image's own built-in `vendor/` with the host directory at container start. |
| `docker compose run --rm artisan migrate --force` | 0 | Created `outbox_events` and added `idempotency_key` to `call_event_logs` against the real `pgsql_al` container (6 migrations total, including the 2 new ones). |
| `docker compose up -d relay` + `docker logs relay_al` | 0 (service reports `Up`, no crash loop) | Before migrations, `relay_al` crash-looped (exit 255, `SQLSTATE[42P01]: Undefined table: outbox_events`) — not a code defect, just that migrations had never been run against this fresh Postgres volume. Confirmed stable afterward: `docker compose ps` shows `Up`, no restart count climbing, no error output in the last 20s of logs. |
| `docker compose run --rm -e DB_CONNECTION=pgsql -e DB_HOST=pgsql_al -e DB_PORT=5432 phpunit --configuration phpunit.xml --testsuite=Feature --filter=OutboxRepositoryPostgresConcurrencyTest` | 0 | **1 test, 2 assertions, OK** — the `FOR UPDATE SKIP LOCKED` concurrent-claim guarantee is now proven against real Postgres, not just asserted in the ADR. This closes the production-parity gap noted above. |
| `docker compose down` | 0 | Clean teardown after verification; host ports 20270-20275 freed (see `projects/info/state/ports.md`). |

Four real bugs found and fixed via this hands-on verification (not guessed,
not left for later — each reproduced, root-caused, and fixed before the next
step was attempted):

1. **`tests/TestCase.php` tearDown** — a manual `DB::disconnect()` after
   `parent::tearDown()` tried to resolve the already-flushed `db` container
   binding, erroring 21 of 23 tests with "Target class [db] does not exist".
   Fixed by removing the manual disconnect entirely (Laravel's own
   `RefreshDatabase` already handles connection teardown for the
   `:memory:` sqlite path used in tests). See the comment left in that file
   for the two failure modes tried and ruled out.
2. **`OutboxRepository::claimBatch()` dirty-tracking bug** — the in-memory
   `status = Processing` mutation after the claiming `UPDATE` never called
   `syncOriginal()`, so a later `markFailed()` call that set status back to
   `Pending` matched the stale `original['status']` and Eloquent silently
   dropped `status` from the generated `UPDATE` — the row stayed stuck on
   `processing` in the database forever. Fixed with an explicit
   `syncOriginal()` call; reproduced and confirmed via
   `OutboxRelayServiceTest::test_relay_batch_backs_off_on_publish_failure_instead_of_losing_the_event`.
3. **`.dockerignore`'s `vendor/` pattern** — unanchored, it only matched a
   top-level `./vendor`, never the real path `src/be/vendor/`. A stray
   host-side dev `vendor/` (itself a decision-2 violation by an earlier,
   unrelated process) was silently copied over every stage's own freshly
   `composer install`-ed vendor by `COPY src/be/ ./`, corrupting
   `deps-prod`'s `--no-dev` install ("Class Laravel\Pail\PailServiceProvider
   not found" on the `runtime` build). Fixed by anchoring it to `**/vendor`,
   matching the existing `**/node_modules` convention.
4. **Cross-repo host-port collision** — `atl_tech` had no block in
   `projects/info/state/ports.md` at all; its ad-hoc ports (8000, 6432/5432,
   5672) collided with other repos' running containers
   (`ms-laravel-rabbitmq`, `blockchain-monitoring-api`). Fixed by reserving
   20270-20279 and launching with shell-level env-var overrides for the
   host-side port mappings only, leaving the repo's own `.env`/
   `docker-compose.yml` untouched (see `ports.md` for the full mapping).

Two smaller hygiene fixes, same session: `docker-compose.yml`'s `relay`
service now sets `healthcheck: disable: true` (it runs the console worker,
not php-fpm, so the image's inherited HEALTHCHECK on :9000 would report
"unhealthy" forever); `Makefile`'s `up` target now actually starts `relay`
(previously only `nginx`, and nothing else `depends_on`s it, so it silently
never started by default).

## What this baseline does NOT claim

- It does not claim a full HTTP-request-to-RabbitMQ-delivery E2E smoke test
  (submit a real call-event via the API, watch the relay actually publish it
  to RabbitMQ and the consumer side receive it) — the relay was only proven
  stable at idle (empty outbox, no crash) and the claim/backoff/dead-letter
  logic is proven by the PHPUnit suites (sqlite) + the Postgres concurrency
  test above, not by a live manual publish. Left as a follow-up, not claimed
  as done here.
- It does not claim production readiness. Per CLAUDE.md's honesty rules, a
  successful build/test is evidence of *this gate*, not a production-readiness
  claim.
