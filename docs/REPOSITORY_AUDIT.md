# Repository Audit — `atl_tech`

> Phase 3 audit (docs-only). Scope: full discovery from repository contents and Phase 2 dependency inventory; no application/runtime behavior changed or executed in Docker in this phase. Evidence cited as `path:line` (repo-relative). Values for suspected secrets are **redacted**.

## 1. Executive summary
- **Repository:** `atl_tech` (default branch `master`, worktree audited at `phase-3/audit-plan` from `origin/master` @ `a828809`, 2026-11-13 UTC). Canonical clone: `projects/repos/atl_tech`.
- **Purpose:** Laravel 12 / PHP 8.4 REST API that ingests SIP call events (`POST /api/v1/call-events`), validates and persists them to PostgreSQL (`call_event_logs`), and publishes messages to RabbitMQ for downstream processing. (`README.md:1-8,56-97`, `src/be/app/Modules/CallEvent/*`)
- **Overall posture:** functional modular skeleton with DDD-shaped module boundaries and useful local tooling/docs, but **not production-ready**: committed/templated secret material flagged by gitleaks (rotation required), no CI/CD, limited automated tests, DB-vs-queue consistency gaps, several config/Docker hardening issues, and major dependency/version lag (see `projects/info/deps/atl_tech.md`).
- **Audit limits:** No `make verify`/`phpunit`/`phpstan`/`pint` execution in this phase (PHP/Composer not on host PATH; no Docker verification run). Claims are static and evidence-backed.

## 2. Discovery inventory

### 2.1 Top-level structure
| Path | Purpose (observed) |
|---|---|
| `.env.example` | Root env template (contains populated-looking values — **redacted**, treated as compromised). |
| `docker-compose.yml`, `Makefile` | Local container orchestration and developer commands. |
| `server/nginx/`, `server/php/`, `server/postgres/` | Docker build contexts/config (Nginx, PHP-FPM, PostgreSQL). |
| `src/be/` | Laravel application (code, config, DB, tests, manifests). |
| `docs/` | Project documentation (architecture, audit, debt, roadmap, API/module guides, Postman collection). |
| `scripts/` | Helper scripts (`restore.sh`, pgAdmin servers config). |
| `ARCHITECTURE.md`, `README.md`, `LICENSE` | Root documentation/license. |

### 2.2 Application (`src/be/`) structure
- **Modules:** `app/Modules/CallEvent/{Contracts,DTOs,Enums,Http/{Controllers,Middleware,Requests},Models,Providers,Repositories,Routes,Services}` — single business module. (`src/be/app/Modules/CallEvent/`)
- **Shared:** `app/Shared/{Brokers/RabbitMQ,Config,Contracts,Exceptions}` — publisher, contracts, exception, duplicate call-event config. 
- **Support/Traits/HTTP base:** `app/Support/*`, `app/Traits/*`, `app/Http/{Controllers,Middlewares,Requests,Resources,Responses}` — generic API scaffolding/response classes (usage breadth not fully exercised by call-event endpoint).
- **Routes:** module route loaded via provider (`CallEventServiceProvider.php:38-41`, `Modules/CallEvent/Routes/api.php:9-14`); `routes/api.php` is a comment placeholder; `routes/web.php` serves welcome view; `routes/console.php` sample command. Health endpoint configured at `/up` (`bootstrap/app.php:67`).
- **Migrations:** users/password/sessions, cache/locks, jobs/batches/failed, `call_event_logs` (`database/migrations/*`, including `2025_12_04_000000_create_call_event_logs_table.php`).
- **Tests:** `tests/Feature/Modules/CallEvent/CallEventApiTest.php` (8 tests), `tests/Unit/ExampleTest.php` (1 trivial), base `TestCase`/`UnitTestCase`/traits. (`phpunit.xml` suites Unit, Feature)

### 2.3 Languages, frameworks, integrations
| Category | Finding | Evidence |
|---|---|---|
| Language/runtime | PHP 8.4 | `src/be/composer.json:9-10`, `server/php/Dockerfile:2` |
| Framework | Laravel 12.41.x | `src/be/composer.json:12`, `bootstrap/app.php` |
| DB | PostgreSQL (pgsql driver/config, 18.6-alpine image build) | `config/database.php:87-100`, `server/postgres/Dockerfile:2`, `docker-compose.yml:94-141` |
| Broker | RabbitMQ (AMQP publish via php-amqplib v2) | `composer.json:15`, `app/Shared/Brokers/RabbitMQ/RabbitMQPublisher.php`, `docker-compose.yml:174-207` |
| Cache | Redis 7 service; app cache/session drivers env-selectable | `docker-compose.yml:142-172`, `config/cache.php`, `config/session.php` |
| Web server | Nginx Alpine reverse proxy to PHP-FPM | `server/nginx/Dockerfile`, `server/nginx/default.conf` |
| API docs | L5-Swagger / OpenAPI attributes | `composer.json:11,32`, `CallEventController.php:16-63`, `config/l5-swagger.php` |

## 3. Configuration and environment
- **Env templates:** Root `.env.example` and `src/be/.env.example` differ in defaults/drivers and secret presence (root has populated-looking `APP_KEY`, mail creds, `JWT_SECRET`, `CALL_EVENT_API_TOKEN` — **redacted**; `src/be/.env.example` uses placeholders/empty key in places). (`.env.example:3,18-24,41-42,75,80`; `src/be/.env.example:3,24,72`)
- **Call-event config duplication:** identical `api_token`/RabbitMQ/queue config in `src/be/config/call-event.php` and `src/be/app/Shared/Config/call-event.php`; provider merges/publishes from Shared copy (`CallEventServiceProvider.php:20-22,45-47`).
- **Bootstrap/middleware:** `api` middleware group registered twice with different composition (`bootstrap/app.php:81-96`); route also sets `throttle:60,1` (`Modules/CallEvent/Routes/api.php:9`). `SecurityHeaders` imported/commented, `csp` group defined but not globally applied (`bootstrap/app.php:54,78,94-96`).
- **CORS:** explicit origin allowlist + broad headers/exposed headers and credentials enabled (`config/cors.php:20-79`).
- **Swagger scan path:** `base_path('App')` (capital A) configured (`config/l5-swagger.php:44`) while source lives under `app/` — potential case-sensitivity issue.
- **Logging/queue/cache defaults:** stack/single logging, queue/cache defaults lean to database in config fallbacks (`config/logging.php:18,51-56`, `config/queue.php:16`, `config/cache.php:16`); actual runtime depends on env.

## 4. API contracts and integrations
- **Endpoint:** `POST /api/v1/call-events` (Bearer token). Request fields: `call_id`, `caller_number`, `callee_number`, `event_type` (enum), `timestamp` (`Y-m-d H:i:s`), conditional `duration` for `call_ended`. Success `200 {"status":"queued"}`; auth failure `401`; validation `422`; broker/unexpected errors `500`. (`Routes/api.php:9-14`, `CallEventRequest.php:24-55`, `VerifyApiToken.php:25-30`, `CallEventController.php:64-101`)
- **Persistence contract:** stores full DTO payload as JSON with `call_id`, enum `event_type`, Unix `created_at`; indexes on `call_id`, `created_at`, `(call_id,event_type)`. (`CallEventRepository.php:13-21`, `CallEventLog.php:20-41`, migration `2025_12_04...:13-19`)
- **Messaging contract:** publishes JSON payload to durable queue `call-events` (default exchange, persistent delivery). No consumer, DLQ, retry, or idempotency key handling in code. (`CallEventService.php:13,33-40`, `RabbitMQPublisher.php:33-50`)
- **External integrations:** PostgreSQL, RabbitMQ, Redis (services provisioned); SIP/downstream consumers are external and not represented as clients in repo.

## 5. Database layer review
| Aspect | Finding | Evidence |
|---|---|---|
| Schema | Single domain table `call_event_logs` plus default Laravel tables (users/sessions/cache/jobs). | `database/migrations/*` |
| Relations | No foreign keys/relations defined for `call_event_logs`; payload stored as opaque JSON. | migration/model files |
| Indexes | `call_id`, `created_at`, composite `(call_id,event_type)` present — reasonable for lookup/time queries. | migration `:14,17,19` |
| Constraints | Basic column types/lengths; enum enforced in app validation/cast, not DB enum/check constraint. | request/enum/model |
| Transactions | No explicit DB transaction wrapping persist+publish; DB write occurs before publish, so failures can diverge state. | `CallEventService.php:23-41`, `CallEventController.php:76-90` |
| N+1 / query patterns | Simple create and point lookup (`findByCallId` latest) — N+1 not observed. | repository `:23-28` |
| Migrations hygiene | Down methods present; rollback not CI-tested. | migrations, no CI |
| Backup/recovery | Makefile `backup-db`/`restore-db` helpers and `scripts/restore.sh` exist; no automated backup policy verified. | `Makefile:162-172`, `scripts/restore.sh` |

## 6. Tests (coverage/quality snapshot — static)
- **Framework/config:** PHPUnit 12, bootstrap via Composer autoload, sqlite in-memory, array cache/mail/session/queue sync in test env. (`src/be/phpunit.xml`)
- **Coverage:** 8 feature tests for API auth/validation/success paths with publisher mocked; 1 trivial unit test (`assertTrue(true)`); no repository/service/broker/migration/contract tests; no production-parity DB integration. (`tests/Feature/.../CallEventApiTest.php`, `tests/Unit/ExampleTest.php`)
- **Notable quality signal:** base `tests/TestCase.php` includes helpers referencing `App\Models\User` factory and custom API response assertions, but user model/factory presence and helper usage were not validated in this audit.

## 7. CI/CD, Docker, and operational tooling
- **CI/CD:** None detected (no `.github/workflows`, GitLab/Circle/Azure/Bitbucket/Jenkins configs found). 
- **Docker:** Compose defines nginx/php/composer/artisan/phpunit/pgsql/redis/rabbitmq with bind mounts and env wiring; PHP Dockerfile is multi-stage but performs conditional composer install/update and sets `chmod -R 777` on storage/cache; healthchecks defined for php-fpm/pgsql/redis/rabbitmq/nginx images. (`docker-compose.yml`, `server/php/Dockerfile:69-94`, `server/postgres/Dockerfile:36-38`, `server/nginx/Dockerfile:37-39`)
- **Makefile:** developer workflow targets (build/up/down/logs/shell/composer/test/quality/migrate/swagger/token/backup) exist; no `make verify` gate composing build+test contract per repo standard. (`Makefile`)
- **Dependencies automation:** Renovate branches/PR activity visible in git history/remote refs (e.g. `renovate/*`), but merge/triage is a later-phase concern.

## 8. Dependency and supply-chain posture (from Phase 2 + manifests)
- **Major gaps (queried 2026-11-10):** `laravel/framework` 12→13, `php-amqplib` 2→3, `darkaonline/l5-swagger` 9→11, `phpunit` 12→13, `vite` 6→8, `tailwindcss` 3→4 (declared) among others; several minors unconfirmed. (`projects/info/deps/atl_tech.md:39-77,120-122`)
- **Lockfiles:** Composer lock present; npm lockfile missing (`src/be/package.json` exists, no `package-lock.json`/`yarn.lock`/`pnpm-lock.yaml` found). 
- **Vulnerabilities/licenses/unused:** Unconfirmed by automated audit in this pass (no `composer audit`/`npm audit`/image scan run); `roave/security-advisories` included as dev conflict meta-package. (`projects/info/deps/atl_tech.md:94-106`)
- **Cross-repo coupling:** No source-level cross-repo dependencies/submodules detected; RabbitMQ queue is an app integration boundary. (`projects/info/deps/atl_tech.md:108-114`)

## 9. Secret scan findings (redacted) — **compromised, rotation required**
**Commands (exit 0 with reports):**
- `gitleaks detect --source . --no-git --report-format json --report-path %TEMP%\atl_tech_gitleaks_nogit.json --exit-code 0` → leaks reported: 15
- `gitleaks detect --source . --report-format json --report-path %TEMP%\atl_tech_gitleaks_git.json --exit-code 0` → leaks reported: 16 (history scanned)

| Location | Line | Rule | Commit (if history) | Note |
|---|---:|---|---|---|
| `.env.example` | 3 | populated secret-like value observed | n/a | `APP_KEY` present — `<REDACTED>` |
| `.env.example` | 41,42 | populated credentials observed | n/a | mail user/password — `<REDACTED>` |
| `.env.example` | 75 | `generic-api-key` | `3533acc23f595aa6ce727ac4d31f6623d087b161`, `b592f7f087e0bafba25b4d9101caca74511ee142` | `JWT_SECRET` — `<REDACTED>` |
| `.env.example` | 80 | `generic-api-key` | `3533acc23f595aa6ce727ac4d31f6623d087b161`, `b592f7f087e0bafba25b4d9101caca74511ee142` | `CALL_EVENT_API_TOKEN` — `<REDACTED>` |
| `src/be/README.md` | 31,64 | `curl-auth-header` | `3533acc...` | example token/header — `<REDACTED>` |
| `docs/CALL_EVENT_MODULE_README.md` | 279 | `curl-auth-header` | `3533acc...` | example token/header — `<REDACTED>` |
| `src/be/app/Modules/CallEvent/API_EXAMPLES.md` | 102,124,147,162,177,192,211,273,421,460 | `curl-auth-header` / `generic-api-key` | `3533acc...` | example tokens/headers — `<REDACTED>` |

**Required handling:** Treat exposed values as compromised and request rotation/revocation. Do **not** rewrite git history in this phase (Approval Gate 1). Replace with placeholders and sanitize docs in later phases via reviewed commits.

## 10. Duplication and maintainability observations
- Duplicated call-event config files (`src/be/config/call-event.php` and `src/be/app/Shared/Config/call-event.php`, identical). 
- Two `api` middleware group definitions in bootstrap (`bootstrap/app.php:81-96`).
- Parallel env templates with divergent defaults (`.env.example` vs `src/be/.env.example`).
- Broad generic API response/support scaffolding present; actual usage coverage by this endpoint not fully evidenced (potential dead/unused surface to validate in Phase 7).

## 11. Prioritized findings (summary)
| Priority | Theme | Key refs |
|---|---|---|
| P0 | Secret exposure in env/examples/docs/history; no CI; non-resilient DB-then-publish flow | Sec. 9; `CallEventService.php:21-41`; no workflows |
| P1 | Middleware/config duplication/ambiguity; Swagger scan path risk; sqlite-vs-pgsql tests and low coverage; static token/rate limiting; env/config drift | `bootstrap/app.php:81-96`, `config/l5-swagger.php:44`, `phpunit.xml:24-26`, auth/middleware files |
| P2 | Unwired security headers/CSP classes, Docker permission/tagging issues, limited observability, tooling path/casing nits, dependency lag/lockfile gaps | middleware/bootstrap/Dockerfiles/configs, deps inventory |
| P3 | Scaffold remnants, doc encoding/example hygiene, README/Make claims to revalidate | migrations/routes/views/tests/docs |

## 12. Out-of-scope (Phase 3)
- No application code, dependency, CI, or Docker configuration changes. No history rewrite. No test/build execution. Remediation items are planned for Phases 4–11 subject to Gate 10 approval.
