# Technical Debt — `atl_tech`

> Phase 3 register (docs-only). Items are derived from static audit evidence and Phase 2 dependency inventory; no fixes applied in this phase. Priorities: **P0** critical, **P1** high, **P2** medium, **P3** low.

## 1. Debt register

| ID | Priority | Area | Debt item | Evidence | Impact | Recommended remediation (later phase) |
|---|---|---|---|---|---|---|
| TD-SEC-001 | P0 | Secrets | Populated-looking secrets/credentials committed in env template and present in git history; example tokens in docs flagged by gitleaks (values redacted). Treated as **compromised**. | `.env.example:3,41-42,75,80` (redacted), history commits `3533acc...`,`b592f7f...`; gitleaks findings in `src/be/README.md`, `docs/CALL_EVENT_MODULE_README.md`, `src/be/app/Modules/CallEvent/API_EXAMPLES.md` | Credential leakage/token reuse risk; rotation burden; compliance/trust impact. | Rotate/revoke out-of-band (immediate ask), replace with placeholders, sanitize docs, add secret scanning; any history rewrite only under Gate 1 (Phase 4+). |
| TD-CI-001 | P0 | CI/CD | No repository CI/CD pipeline or enforced quality gates. | No `.github/workflows/*` or other CI configs found; only local `Makefile`/composer scripts. | Regressions/security issues can merge undetected; no reproducible gate. | Introduce CI with lint/static/test/secret/image scans in Docker, pinned actions, time budgets (Phase 10). |
| TD-INT-001 | P0 | Reliability / data integrity | Persist-then-publish flow without transaction/outbox/retry/idempotency; broker publish failure after DB insert yields inconsistent state (HTTP 500 but row may exist). | `src/be/app/Modules/CallEvent/Services/CallEventService.php:21-41`, `.../Http/Controllers/CallEventController.php:76-90`, `app/Shared/Brokers/RabbitMQ/RabbitMQPublisher.php:29-65,76-114` | Duplicate/lost/misaligned events for consumers; hard recovery. | Design compensating strategy (outbox/inbox, retries with backoff, idempotency keys) and explicit failure semantics (Phase 8/9). |
| TD-CFG-001 | P1 | Bootstrap/config correctness | `api` middleware group defined twice with different contents; route-level throttle also hardcoded — effective stack unclear. | `src/be/bootstrap/app.php:81-96`, `src/be/app/Modules/CallEvent/Routes/api.php:9` | Subtle behavior differences (CORS/stateful/throttle/compression) across environments/upgrades. | Consolidate to single explicit middleware pipeline and cover with tests (Phase 7/9/11). |
| TD-DOCS-001 | P1 | API documentation | L5-Swagger annotation scan path uses `base_path('App')` while sources are under `app/`; generation correctness on case-sensitive systems uncertain. | `src/be/config/l5-swagger.php:44`, annotations in `CallEventController.php:16-63` | Broken/missing OpenAPI generation in CI/Linux; doc drift. | Fix scan paths/config, generate spec in CI, validate contract (Phase 6/10/11). |
| TD-TEST-001 | P1 | Testing | Tests run on sqlite in-memory while production target is PostgreSQL; only 8 feature + 1 trivial unit test; no broker/repo/migration/contract coverage. | `src/be/phpunit.xml:24-26`, `tests/Feature/Modules/CallEvent/CallEventApiTest.php`, `tests/Unit/ExampleTest.php` | Dialect/behavior drift (JSON, indexing, timestamps), untested failure modes. | Add production-parity integration tests in Docker and expand behavior/edge-case coverage (Phases 5/11). |
| TD-SEC-002 | P1 | AuthN/AuthZ & abuse control | Single static shared Bearer token with no rotation/scoping/audit; fixed global throttle. | `VerifyApiToken.php:23-36`, `config/call-event.php:16`, `Routes/api.php:9` | Token compromise blast radius; limited tenant/client controls. | Introduce token management/rotation, scoped credentials or stronger auth, per-client limits and audit logging (Phase 9). |
| TD-CFG-002 | P1 | Config drift/duplication | Divergent root vs `src/be` `.env.example`; duplicate identical call-event config files (app/Shared vs config) merged/published from Shared path. | `.env.example`, `src/be/.env.example`, `config/call-event.php`, `app/Shared/Config/call-event.php`, `CallEventServiceProvider.php:20-22,45-47` | Onboarding/runtime misconfiguration and maintenance drift. | Canonicalize env templates/config source of truth and document precedence (Phases 5/7). |
| TD-INT-002 | P1 | Messaging semantics | No consumer, DLQ, retry policy, or idempotency contract; queue declared durable but end-to-end guarantees undefined. | `RabbitMQPublisher.php:33-50`, absence of consumer classes | Operational ambiguity; message loss/dup handling undefined. | Define and implement consumption/retry/DLQ/idempotency as needed (Phases 8/9). |
| TD-SEC-003 | P2 | Defense in depth | Security/CSP middleware classes exist but are not effectively wired; reliance partly on Nginx headers. | `bootstrap/app.php:54,78,94-96`, `app/Http/Middlewares/*Security*`, `server/nginx/default.conf:53-59` | Inconsistent header/CSP enforcement across entrypoints/responses. | Wire and test security middleware coherently (Phase 9). |
| TD-OPS-001 | P2 | Docker/image hygiene | `chmod -R 777` on storage/cache in image; conditional composer install/update logic; bind-mounted `.env`; some floating image tags. | `server/php/Dockerfile:69-82`, `docker-compose.yml:24,35,38,51,72,143,175`, image tags in compose/Dockerfiles | Least-privilege and reproducibility risks; environment leakage risk. | Apply Docker standard (least privilege, deterministic builds, pinned digests/tags policy, secrets mounts) (Phases 5/6). |
| TD-OPS-002 | P2 | Observability | No correlation/request IDs or broker/DB metrics/tracing conventions; basic contextual logs only. | `CallEventController.php:74-98`, `CallEventService.php:23-40`, `RabbitMQPublisher.php:52-62,90-110` | Difficult incident diagnosis and SLO tracking. | Add structured logging/traceability and health/readiness semantics (Phase 9). |
| TD-TOOL-001 | P2 | Tooling portability | PHPUnit `<source><include><directory>App</directory></include>` casing differs from `app/`. | `src/be/phpunit.xml:15-18` | Coverage/source resolution issues on case-sensitive filesystems. | Correct paths and align tool configs (Phase 5/7). |
| TD-DEP-001 | P2 | Dependency currency & supply chain | Major framework/lib/tooling gaps (Laravel 12→13, php-amqplib 2→3, l5-swagger 9→11, PHPUnit 12→13, Vite 6→8, Tailwind 3→4 etc.); npm lockfile missing; vulns/licenses/unused unconfirmed by automated audit. | `projects/info/deps/atl_tech.md:39-77,94-106,120-122`, absence of npm lockfile | Security/compatibility risk, non-reproducible JS builds. | Registry-checked upgrades in order, add lockfile, run audits/scans in Docker (Phase 6/10). |
| TD-CODE-001 | P3 | Scaffold / dead-surface candidates | Default users/cache/jobs/session/web scaffolding and unused installed packages (e.g. Sanctum/JWT vs custom token) may be unnecessary; requires proof before removal. | `database/migrations/0001_01_01_*`, `routes/web.php:9-11`, `composer.json:13,16`, `config/auth.php` | Cognitive/load/attack-surface overhead. | Prove usage and remove/reshape with tests (Phase 7). |
| TD-DOC-002 | P3 | Documentation hygiene | Encoding/mojibake artifacts and example token/header strings in docs/README; operational claims not revalidated. | `README.md`, `docs/*`, gitleaks doc hits (redacted) | Misleading setup/usage guidance. | Refresh docs after verified behavior and sanitize examples (Phases 5/15). |

## 2. Debt by theme (roll-up)
- **Security/secrets:** TD-SEC-001, TD-SEC-002, TD-SEC-003 — highest urgency is rotation and CI secret scanning.
- **Reliability/integration:** TD-INT-001, TD-INT-002, TD-OPS-002 — delivery guarantees and diagnosability.
- **Quality/verification:** TD-CI-001, TD-TEST-001, TD-DOCS-001, TD-TOOL-001 — make correctness enforceable.
- **Maintainability/config:** TD-CFG-001, TD-CFG-002, TD-CODE-001, TD-DOC-002 — reduce ambiguity/drift.
- **Platform/supply chain:** TD-OPS-001, TD-DEP-001 — reproducibility and currency.

## 3. Proposed remediation sequence (aligned to phases)
1. **Phase 4:** Rotate exposed credentials (out-of-band), placeholder/sanitize tracked examples, baseline secret hygiene; branch hygiene. (Gate 1 for any history rewrite — not planned here.)
2. **Phase 5:** Docker baseline + `docs/BASELINE.md`, canonical env templates, reproducible build/test contract.
3. **Phase 6:** Ordered upgrades to latest stable with verification; add npm lockfile/audits.
4. **Phase 7:** Remove proven-dead code/duplication, fix config/middleware ambiguity, align structure.
5. **Phase 8:** Decide and implement delivery semantics (outbox/retry/idempotency/consumer contract) with ADRs where justified.
6. **Phase 9:** Hardening (auth/rate limiting, headers/CSP, health/standalone behavior, logging/metrics, image/dependency scans).
7. **Phase 10:** CI/CD pipeline with required gates and measured runtime.
8. **Phase 11:** Production-parity and contract/edge-case/regression tests.

## 4. Acceptance notes
- No item above is marked fixed in this phase. "Compromised" secret findings require user-side rotation; technical removal from history is a separate, gated action (Gate 1) not taken here.

## 5. 2026-11-25 session update (outbox/reliability work)

Status changes below reflect code landed on the worktree this session (transactional
outbox, health endpoints, idempotency, history endpoint). Per the honesty rule, each
is marked **addressed — Docker verification in progress**, not "done," until
`make verify` (the `--target test` build) is confirmed green and, separately, until
`make test-integration` has actually been run against real Postgres. This section
will be updated again once that evidence exists.

| ID | New status | What changed |
|---|---|---|
| TD-INT-001 | Addressed — Docker verification in progress | Replaced the persist-then-publish flow with a transactional outbox: `CallEventService::create()` now writes the call-event row and an `outbox_events` row in one `DB::transaction()` (see `docs/adr/0001-transactional-outbox.md`). The request path no longer talks to RabbitMQ synchronously, so a broker outage can no longer produce a 500 with an inconsistent row. An independent `outbox:relay` worker (new `relay` service in `docker-compose.yml`) drains the outbox with exponential backoff and dead-lettering (`OutboxRelayService`, `OutboxRepository`). Not yet closed: the Postgres-specific `FOR UPDATE SKIP LOCKED` concurrency guarantee is proven by `OutboxRepositoryPostgresConcurrencyTest`, which only runs under `make test-integration` (real `pgsql` driver) — not yet executed this session. |
| TD-INT-002 | Addressed — Docker verification in progress | Retry policy (`OUTBOX_MAX_ATTEMPTS`, exponential backoff with cap via `config/outbox.php`) and dead-lettering (`OutboxStatus::DeadLetter`) are now implemented and covered by `tests/Feature/Shared/Outbox/OutboxRelayServiceTest.php`. Idempotency contract is now explicit: a server-computed sha256 idempotency key is stored on both `call_event_logs.idempotency_key` and the outbox row; a retried submission is a no-op via `firstOrCreate()`/`wasRecentlyCreated` (see `CallEventApiTest::test_duplicate_submission_is_idempotent`). No consumer/DLQ-draining tooling added yet — out of scope for this pass, left as a follow-up. |
| TD-OPS-002 | Addressed — Docker verification in progress | Added `/health/live` and `/health/ready` (`HealthController`), with readiness deliberately scoped to PostgreSQL only (not RabbitMQ), since the outbox pattern removed the synchronous broker dependency from the request path — see ADR-0001 for the reasoning. `server/nginx/Dockerfile`'s `HEALTHCHECK` now targets `/health/ready` instead of the Laravel default `/up`. Structured correlation-ID/tracing logging is still not done — remains open under this ID for a later pass. |
| TD-CFG-002 | Partially addressed | Root `.env.example` and `src/be/.env.example` still diverge in content (not unified — that remains a deliberate, proportionate deferral, see Problem Solving notes), but `src/be/.env.example` now carries an explicit header comment stating the root-level file is the actual source of truth for the Docker Compose stack, closing the "which one is real" ambiguity even though the files themselves are not yet merged. Both files were updated with the new `OUTBOX_*` variables so neither drifts further on this specific set of keys. |

No item in this section is claimed as **Completed and verified** per CLAUDE.md's
four status categories until the Docker build/test evidence below exists; treat
all four as **Implemented, not verified** until `docs/BASELINE.md` records a
confirmed-green `make verify` run and `make test-integration` has actually
executed `OutboxRepositoryPostgresConcurrencyTest` against real Postgres.

## 6. 2026-11-25 (second session) — verification complete, final statuses

The evidence section 5 was waiting on now exists in full — see
`docs/BASELINE.md`'s "2026-11-25 run (second session...)" entry for every
command and exit code. Statuses below supersede section 5 for the same IDs;
section 5 is left as-is for the historical record of what was claimed before
verification.

| ID | Final status | Evidence |
|---|---|---|
| TD-INT-001 | **Completed and verified** | `make verify` green (23 passed, 1 skipped, 0 failures) AND `make test-integration` green for real against Postgres (`OutboxRepositoryPostgresConcurrencyTest`: 1 test, 2 assertions, OK) — the `FOR UPDATE SKIP LOCKED` claim-batch guarantee is proven, not just asserted. The relay worker was also confirmed to start and stay stable (no crash loop) once migrations ran against a real `pgsql` instance. |
| TD-INT-002 | **Completed and verified** | Same evidence as TD-INT-001 for the retry/backoff/dead-letter path (`OutboxRelayServiceTest`, green under `make verify`). Idempotency path (`CallEventApiTest::test_duplicate_submission_is_idempotent`) also green. Consumer/DLQ-draining tooling remains explicitly out of scope — still open as a named follow-up, not silently dropped. |
| TD-OPS-002 | **Completed and verified** (health endpoints only) | `/health/live` and `/health/ready` ship and are covered by `tests/Feature/HealthTest.php`, green under `make verify`. Structured correlation-ID/tracing logging is **not** addressed — remains open under this ID. |
| TD-CFG-001 | **Completed and verified** | The duplicate `api` middleware group in `bootstrap/app.php` was consolidated to one definition; `SecurityHeaders` is now actually wired into the stack (it previously existed but was never attached). Confirmed via the green `make verify` run (no middleware-resolution errors) and direct reading of the consolidated `bootstrap/app.php`. |
| TD-SEC-003 | **Completed and verified** | Direct consequence of the TD-CFG-001 fix above — `SecurityHeaders` middleware is now wired, not just present as an unused class. Verified by reading the final `bootstrap/app.php`; no dedicated header-assertion test was added this pass (left as a Phase 9/11 follow-up — this entry does not claim test coverage, only that the middleware is wired). |
| TD-DOCS-001 | **Completed and verified** | `config/l5-swagger.php`'s scan path fixed from `base_path('App')` to `base_path('app')`. Verified by the case-sensitive Linux container build succeeding (the bug would have silently produced an empty/broken scan on a case-sensitive filesystem; the Docker build environment is case-sensitive and completed without error). |
| TD-TOOL-001 | **Completed and verified** | `src/be/phpunit.xml`'s `<source><include><directory>` fixed from `App` to `app`, matching the real path. Verified by directory listing and the green `make verify` build (same case-sensitive-filesystem argument as TD-DOCS-001). |
| TD-CFG-002 | **Partially addressed (unchanged from section 5)** | Still not fully unified — see section 5's note. Not revisited this session. |
| TD-CODE-001 | **Not addressed — out-of-scope finding for Phase 7** | During this session's reading, one additional candidate was found: `findByCallId()` is declared and implemented in the repository/contract layer but has no caller anywhere in the codebase (grepped for all call sites). Per R1 (one agent, one phase), this is **not deleted here** — it is logged for Phase 7 (`cleaner`), which already owns proving dead code via tooling + coverage before deletion. |
| TD-SEC-001 | **Still open — needs Rasim's action, not closable by any agent** | This session's diff replaces the *current* hardcoded-looking values in `.env.example`/`src/be/.env.example` with placeholders (good hygiene going forward), but the original values were already present in git history per the Phase 3 audit. Per CLAUDE.md's Security section, a secret found in history is **compromised** — this does not become "fixed" until Rasim rotates the real credentials out-of-band; removing it from history afterward is separately Gate 1. Flagged again here so it is not lost — see the final report to the orchestrator for the explicit callout. |
| TD-DEP-001 | **Partially addressed, not closed** | `src/be/composer.lock` moved within already-declared semver ranges (no `composer.json` constraint changes) and `package.json` picked up minor devDependency bumps. This is normal lockfile drift from `composer update`/`npm` resolution, **not** the ordered, registry-checked, one-library-at-a-time major-version upgrade pass decision 1 requires — that is still Phase 6's full job, not done by this session. Left open. |

All seven newly-**Completed and verified** rows above meet CLAUDE.md's bar
because they have actual Docker-build/test evidence recorded in
`docs/BASELINE.md`, not because the code merely exists.
