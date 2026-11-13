# Architecture — `atl_tech` (ATL Tech Call Event API)

> Phase 3 audit snapshot (docs-only). Evidence is cited as `path:line` or `path` relative to repo root unless noted. No application code changed in this phase.

## 1. Purpose and system context
- **Service:** HTTP ingestion API for SIP/call-center call lifecycle events; validates requests, persists event logs to PostgreSQL, and publishes events to RabbitMQ for downstream consumers. Returns `{"status":"queued"}` on success. (`README.md:1-8,56-97`, `src/be/app/Modules/CallEvent/Http/Controllers/CallEventController.php:64-81`, `.../Services/CallEventService.php:21-41`)
- **Actors/integrations (declared):** SIP server (producer, HTTP), PostgreSQL (storage), RabbitMQ (broker), Redis (available in compose/config), potential downstream consumers (not implemented in-repo). (`README.md:77-88,141-146`, `docker-compose.yml:94-207`)

```mermaid
flowchart LR
  SIP[SIP server / caller system] -->|POST /api/v1/call-events\nBearer token + JSON| NGINX[Nginx]
  NGINX --> PHP[PHP-FPM / Laravel 12]
  PHP -->|VerifyApiToken + throttle + validation| CTRL[CallEventController]
  CTRL --> SVC[CallEventService]
  SVC --> REPO[PostgresCallEventRepository]
  REPO --> DB[(PostgreSQL call_event_logs)]
  SVC --> PUB[RabbitMQPublisher]
  PUB --> RMQ[(RabbitMQ queue: call-events)]
  RMQ -. consumed by .-> C[Downstream consumer\n(not in repo)]
```

## 2. Repository and runtime layout
| Area | Path | Role |
|---|---|---|
| Root infra/docs | `docker-compose.yml`, `Makefile`, `server/{nginx,php,postgres}/`, `.env.example`, `docs/*`, `README.md` | Local/dev container topology, operational helpers, documentation. |
| Laravel application | `src/be/` | Application code, config, migrations, tests, Composer/npm manifests. |
| Domain module | `src/be/app/Modules/CallEvent/` | Call-event ingestion bounded module (routes, middleware, DTO, enum, controller, service, repository, model, provider). |
| Shared infra | `src/be/app/Shared/` | RabbitMQ publisher, event publisher contract, broker exception, duplicated call-event config copy. |
| Framework/support | `src/be/app/{Http,Providers,Support,Traits}/`, `config/`, `bootstrap/`, `database/` | Base responses/middleware scaffolding, providers/bootstrap, DB migrations/seeders. |

## 3. Technology stack (as implemented)
- **PHP/Laravel:** `php:^8.4`, `laravel/framework:^12.41` (`src/be/composer.json:8-12`); Docker runtime base `php:8.4-fpm` (`server/php/Dockerfile:2,31`).
- **Persistence:** PostgreSQL via `pgsql` connection (`src/be/config/database.php:21,87-100`); compose builds `postgres:18.6-alpine` (`server/postgres/Dockerfile:2`, `docker-compose.yml:94-116`).
- **Messaging:** `php-amqplib/php-amqplib:^2.8` publisher to durable queue (`composer.json:15`, `app/Shared/Brokers/RabbitMQ/RabbitMQPublisher.php:33-50`); RabbitMQ `rabbitmq:4.4-management-alpine` (`docker-compose.yml:174-207`).
- **Web edge:** `nginx:alpine` serving `src/be/public`, FastCGI to `php:9000` (`server/nginx/Dockerfile:2`, `server/nginx/default.conf:3-48`, `docker-compose.yml:7-26`).
- **Cache/aux services:** Redis 7 service available (`docker-compose.yml:142-172`); app cache/session/queue drivers are env-configurable (`config/cache.php`, `config/session.php`, `config/queue.php`).
- **API docs:** `darkaonline/l5-swagger:^9.0` with OpenAPI PHP attributes in controller (`composer.json:11`, `CallEventController.php:16-63`, `config/l5-swagger.php`).
- **Quality tooling (declared):** PHPUnit 12, Larastan/PHPStan 3/2, Pint, Rector, PHPCS (`composer.json:21-32,66-102`); CI config not found in repo.

## 4. API and module design
### 4.1 Endpoint
- `POST /api/v1/call-events` (`name: call-events.store`) behind `[VerifyApiToken, throttle:60,1]` middleware. Defined in module route file loaded by provider, not central `routes/api.php`. (`src/be/app/Modules/CallEvent/Routes/api.php:9-14`, `CallEventServiceProvider.php:38-41`)

### 4.2 Request contract (validated)
- `call_id` (required string, max 255), `caller_number`/`callee_number` (required, regex `^\+?[0-9]{10,15}$`), `event_type` (required, in enum values), `timestamp` (required `Y-m-d H:i:s`), `duration` (required if `call_ended`, nullable integer >= 0). (`src/be/app/Modules/CallEvent/Http/Requests/CallEventRequest.php:22-55`, `Enums/CallEventTypeEnum.php:9-26`)

### 4.3 Authentication/authorization
- Static Bearer token compared with timing-safe `hash_equals` against `config('call-event.api_token')` sourced from `CALL_EVENT_API_TOKEN`. (`src/be/app/Modules/CallEvent/Http/Middleware/VerifyApiToken.php:23-36`, `src/be/config/call-event.php:16`)
- Sanctum/JWT packages and JWT guard exist but are not part of this endpoint flow. (`src/be/composer.json:13,16`, `src/be/config/auth.php:41-44`)

### 4.4 Processing flow
1. Controller maps validated input to `CallEventDTO` (immutable `readonly` class, `Carbon` parse, enum conversion, `toArray` serializes timestamp as ISO8601). (`CallEventController.php:70-72`, `DTOs/CallEventDTO.php:12-44`)
2. Service persists via repository then publishes to queue `call-events`, logging at info on success. (`Services/CallEventService.php:21-41`)
3. Repository creates `CallEventLog` with `call_id`, enum `event_type`, full payload array; also exposes `findByCallId` latest-by-`created_time`. (`Repositories/PostgresCallEventRepository.php:13-30`, `Contracts/CallEventRepositoryInterface.php:12-16`)
4. Publisher ensures connection/channel, declares durable queue, sends persistent AMQP message (`delivery_mode` persistent), closes in destructor. (`RabbitMQPublisher.php:29-115`, `Shared/Contracts/EventPublisherInterface.php:9-15`)
5. Controller catches `BrokerConnectionException` → 500 "Failed to queue event...", generic throwable → 500 "unexpected error", logs details. (`CallEventController.php:76-101`)

## 5. Data model and persistence
### 5.1 `call_event_logs`
| Column | Type / behavior | Evidence |
|---|---|---|
| `id` | big integer PK | migration `2025_12_04_000000_create_call_event_logs_table.php:13` |
| `call_id` | string(100), indexed | `:14` |
| `event_type` | string(50) | `:15` |
| `payload` | json | `:16` |
| `created_at` | unsigned integer Unix timestamp, indexed, model-managed in `creating` hook, `$timestamps=false` | `:17`, `Models/CallEventLog.php:14-41` |
| Composite index | `(call_id, event_type)` | migration `:19` |

- **Model casting:** `event_type` → enum, `payload` → array, `created_at` → integer. (`CallEventLog.php:28-32`)
- **Other schema:** standard Laravel `users`/password reset/sessions, `cache`/`cache_locks`, `jobs`/`job_batches`/`failed_jobs` migrations exist (`database/migrations/0001_01_01_*`). Usage by this feature not evidenced beyond framework defaults.

## 6. Configuration, bootstrap, and cross-cutting concerns
- **Bootstrap:** `bootstrap/app.php` wires web/api/console routes and health `/up`, defines middleware groups/aliases, and custom exception rendering for `api/*` with domain response classes (`bootstrap/app.php:62-140`). **Notable:** `api` middleware group is declared twice (`:81-96`), making effective composition ambiguous.
- **Providers:** `AppServiceProvider`, `SwaggerServiceProvider`, and `CallEventServiceProvider` registered (`bootstrap/providers.php:5-10`).
- **Config highlights:** DB default `sqlite` in config fallback but pgsql defined (`config/database.php:21,87-100`); queue default `database` (`config/queue.php:16`); cache default `database` (`config/cache.php:16`); session driver env-driven (default `database` in config) (`config/session.php:21`); CORS configured for API/docs paths and explicit origins (`config/cors.php:18-79`).
- **Env templates:** root `.env.example` and `src/be/.env.example` differ significantly (connection defaults, populated vs placeholder secrets/keys). (`.env.example`, `src/be/.env.example`)

## 7. Container and deployment view (local/dev oriented)
- **Services (compose):** `nginx`, `php`, `composer`, `artisan`, `phpunit`, `pgsql`, `redis`, `rabbitmq` on network `ATLTechCallCenterAPI`; bind mounts of `src/be` and `.env`; ports parameterized via env (`APP_PORT`, `DB_PORT`, `REDIS_PORT`, RabbitMQ ports). (`docker-compose.yml`)
- **PHP image:** multi-stage build→runtime, installs extensions/composer, copies app, runs `composer install` or `update` conditionally, sets `777` on storage/bootstrap cache, drops to `www-data`, healthcheck `php-fpm -t`. (`server/php/Dockerfile`)
- **Postgres image:** custom `postgresql.conf`, healthcheck `pg_isready`, data volume mount under `./db/postgres`. (`server/postgres/Dockerfile`, `server/postgres/postgresql.conf`, compose `pgsql`)
- **Make targets:** build/up/down/logs/shell/test/quality/migrate/swagger/token/db backup-restore, etc. (`Makefile`)

## 8. Observability and testing
- **Logging:** Monolog/Laravel channel config present (`config/logging.php`); application uses contextual `Log::info/error` in controller/service/publisher (no correlation/request ID standard observed). 
- **Tests:** 8 feature tests for `POST /api/v1/call-events` covering success paths, validation, auth failures (EventPublisher mocked), 1 trivial unit test; sqlite in-memory configured for PHPUnit. (`tests/Feature/Modules/CallEvent/CallEventApiTest.php`, `tests/Unit/ExampleTest.php`, `src/be/phpunit.xml:20-32`)
- **CI:** no repository CI workflow detected.

## 9. Known architectural constraints / risks (summary)
- DB write precedes queue publish with no compensating transaction/outbox; singleton broker connection without retry/backoff/idempotency strategy in code. (`CallEventService.php:21-41`, `RabbitMQPublisher.php`)
- Single static API token, coarse rate limiting, and un-wired security/CSP middleware classes reduce defense-in-depth. (`VerifyApiToken.php`, `Routes/api.php:9`, `bootstrap/app.php:78,94-96`)
- Config and env-template duplication/drift and Swagger scan-path casing risk may affect portability and documentation generation. (`bootstrap/app.php:81-96`, `config/l5-swagger.php:44`, dual call-event config files)
- Docs-only phase: structure and claims reflect static inspection and Phase 2 dependency inventory; runtime behavior not re-verified by execution here.
