# Technical Debt Register

**Repository**: `atl_tech`  
**Audit Date**: 2026-11-12  
**Auditor**: Phase 3 Agent  

---

## Summary

| Category | Count | Critical (P0) | High (P1) | Medium (P2) | Low (P3) |
|----------|-------|---------------|-----------|-------------|----------|
| Security | 11 | 4 | 3 | 4 | 0 |
| Reliability | 7 | 3 | 4 | 0 | 0 |
| Maintainability | 12 | 0 | 3 | 5 | 4 |
| Observability | 6 | 0 | 2 | 2 | 2 |
| Performance | 3 | 0 | 1 | 1 | 1 |
| **Total** | **39** | **7** | **13** | **12** | **7** |

---

## P0 - Critical (Immediate Action Required)

### SEC-01: Exposed APP_KEY in `.env.example`
- **Location**: `/.env.example:3`
- **Risk**: Application key used for encryption/signing; if real, all encrypted data compromised
- **Remediation**: Rotate `APP_KEY`; replace with placeholder `base64:REPLACE_WITH_GENERATED_KEY`
- **Effort**: Low (5 min)

### SEC-02: Exposed MAIL Credentials in `.env.example`
- **Location**: `/.env.example:41-42` (`MAIL_USERNAME`, `MAIL_PASSWORD`)
- **Risk**: Office365 SMTP credentials exposed; email account takeover
- **Remediation**: Rotate mail password; use placeholders
- **Effort**: Low (5 min)

### SEC-03: Exposed JWT_SECRET in `.env.example`
- **Location**: `/.env.example:75`
- **Risk**: JWT signing key exposed; token forgery possible
- **Remediation**: Rotate secret; use placeholder
- **Effort**: Low (5 min)

### SEC-04: Exposed CALL_EVENT_API_TOKEN in `.env.example`
- **Location**: `/.env.example:80`
- **Risk**: API authentication token exposed; unauthorized call event ingestion
- **Remediation**: Rotate token; use placeholder `ce_REPLACE_WITH_GENERATED_TOKEN`
- **Effort**: Low (5 min)

### REL-01: No CI/CD Pipeline
- **Location**: Missing `.github/workflows/`
- **Risk**: No automated testing, linting, analysis; broken code merges to main
- **Remediation**: Add GitHub Actions workflow (test, lint, phpstan, pint, audit)
- **Effort**: Medium (2-4 hrs)

### REL-02: RabbitMQ Singleton Holds Stale Connection
- **Location**: `src/be/app/Shared/Brokers/RabbitMQ/RabbitMQPublisher.php:15-18`, `CallEventServiceProvider.php:24-32`
- **Risk**: Connection not refreshed after broker restart; publishes fail silently until process restart
- **Remediation**: Implement connection recovery with retry/backoff; remove singleton or add health check
- **Effort**: Medium (2-3 hrs)

### REL-03: Config Drift Between docker-compose.yml and .env.example
- **Location**: `docker-compose.yml` vs `/.env.example` (DB, Redis ports, credentials)
- **Risk**: Developers copying `.env.example` cannot connect to services
- **Remediation**: Align values; document which file is source of truth
- **Effort**: Low (30 min)

---

## P1 - High (Address Within Sprint)

### SEC-05: No CSP Header Enforcement in Production
- **Location**: `server/nginx/default.conf` (headers present but no CSP)
- **Risk**: XSS vulnerability surface increased
- **Remediation**: Add `Content-Security-Policy` header appropriate for API
- **Effort**: Low (30 min)

### SEC-06: Rate Limiting Only by IP, Not by Token
- **Location**: `src/be/app/Modules/CallEvent/Routes/api.php:3` (`throttle:60,1`)
- **Risk**: Single compromised token can DoS; no per-client quota
- **Remediation**: Implement per-token rate limiter (e.g., `throttle:1000,1` by token)
- **Effort**: Medium (1-2 hrs)

### SEC-07: No Request Body Size Limit
- **Location**: `server/nginx/default.conf` (missing `client_max_body_size`)
- **Risk**: Large payload DoS; memory exhaustion
- **Remediation**: Add `client_max_body_size 1M;` to nginx config
- **Effort**: Low (10 min)

### SEC-08: Default RabbitMQ Credentials (guest/guest)
- **Location**: `docker-compose.yml:149-150`, `config/call-event.php:14-15`
- **Risk**: Well-known defaults; management UI exposed on port 15672
- **Remediation**: Generate strong credentials; restrict management UI to internal network
- **Effort**: Low (15 min)

### REL-04: No Health Check Endpoint (`/health` or `/up`)
- **Location**: Nginx healthcheck expects `/up` (`docker-compose.yml:16`); no route defined
- **Risk**: Orchestration cannot detect unhealthy containers; false positives
- **Remediation**: Add `/up` route returning 200; or change healthcheck to existing endpoint
- **Effort**: Low (30 min)

### REL-05: Test DB (SQLite) ≠ Production DB (PostgreSQL)
- **Location**: `phpunit.xml:25-26` (`DB_CONNECTION=sqlite`)
- **Risk**: Dialect mismatches (JSON, indexes, enum casting); false confidence
- **Remediation**: Use PostgreSQL in CI via testcontainers or dedicated test DB
- **Effort**: Medium (2-4 hrs)

### REL-06: No Database Migration Rollback Testing
- **Location**: Migration `down()` methods exist but never executed in pipeline
- **Risk**: Rollback failures in production; schema drift
- **Remediation**: Add migration rollback test to CI
- **Effort**: Low (1 hr)

### MAINT-01: PHPStan Level 5 with Broad Ignore Patterns
- **Location**: `phpstan.neon:15-21` (5 ignore patterns)
- **Risk**: Real type errors masked; false sense of correctness
- **Remediation**: Address ignored errors incrementally; reduce ignore list
- **Effort**: Medium (ongoing)

### MAINT-02: Rector Configured for Laravel 110, Project Uses Laravel 12
- **Location**: `rector.php:6` (`LaravelSetList::LARAVEL_110`)
- **Risk**: Missed Laravel 12 codemods; automated upgrades incomplete
- **Remediation**: Update to `LaravelSetList::LARAVEL_120` when available; verify rules
- **Effort**: Low (30 min)

### MAINT-03: No Mutation Testing
- **Location**: Only PHPUnit configured; no Infection/PhpMutator
- **Risk**: Test suite may not catch logic bugs; false coverage confidence
- **Remediation**: Add Infection mutation testing to CI
- **Effort**: Medium (2-3 hrs)

### MAINT-04: Postgres Dockerfile Uses Custom Init But No Migration Verification
- **Location**: `docker-compose.yml:83` (`./server/postgres/init:/docker-entrypoint-initdb.d`)
- **Risk**: Schema initialization order vs migrations; potential conflicts
- **Remediation**: Verify init scripts align with migration strategy; document
- **Effort**: Low (1 hr)

### OBS-01: No Structured Logging / Correlation IDs
- **Location**: `CallEventService.php:18-25` (arbitrary array logging)
- **Risk**: Cannot trace requests across services; debugging difficult
- **Remediation**: Implement request ID middleware; structured JSON logging
- **Effort**: Medium (2-3 hrs)

### OBS-02: No API Request/Response Compression
- **Location**: `server/nginx/default.conf` (no gzip for API)
- **Risk**: Larger payloads; slower responses for mobile/clients
- **Remediation**: Enable gzip for `application/json` in nginx
- **Effort**: Low (15 min)

### OBS-03: Prometheus Metrics Not Integrated
- **Location**: No metrics endpoint; Laravel Prometheus not installed
- **Risk**: No observability for latency, error rates, throughput
- **Remediation**: Add `prometheus/client_php` + exporter; expose `/metrics`
- **Effort**: Medium (2-3 hrs)

---

## P2 - Medium (Backlog / Next Quarter)

### SEC-09: No Audit Logging for Auth Failures
- **Location**: `VerifyApiToken.php` (no logging on 401)
- **Risk**: Cannot detect brute force or credential stuffing
- **Remediation**: Log failed auth attempts with IP, token prefix, timestamp
- **Effort**: Low (30 min)

### SEC-10: No Token Expiration/Rotation Policy
- **Location**: `CALL_EVENT_API_TOKEN` static in `.env`
- **Risk**: Long-lived tokens; no revocation mechanism
- **Remediation**: Implement token TTL; rotation endpoint; JWT with exp claim
- **Effort**: Medium (3-4 hrs)

### SEC-11: CORS Not Configured for Production
- **Location**: `config/cors.php` (default allows all)
- **Risk**: Unintended cross-origin access in production
- **Remediation**: Restrict `allowed_origins` to known domains
- **Effort**: Low (15 min)

### MAINT-05: Duplicate .gitignore Entries
- **Location**: `/.gitignore:1,6` (`/.vscode` and `.vscode/*`; `/.idea` and `/.idea/`)
- **Risk**: Noise; potential confusion
- **Remediation**: Deduplicate entries
- **Effort**: Low (5 min)

### MAINT-06: .dockerignore Excludes README.md
- **Location**: `/.dockerignore:28`
- **Risk**: `COPY README.md` in Dockerfile would fail
- **Remediation**: Remove or verify no Dockerfile copies README
- **Effort**: Low (5 min)

### MAINT-07: No Automated Dependency Update Bot
- **Location**: Missing `.github/dependabot.yml` or `renovate.json`
- **Risk**: Dependencies drift; security patches missed
- **Remediation**: Add Dependabot or Renovate config
- **Effort**: Low (30 min)

### MAINT-08: Makefile Uses Bash-Specific `read -p` in `restore-db`
- **Location**: `Makefile:47` (`read -p`)
- **Risk**: Not portable to non-bash shells (though Docker uses bash)
- **Remediation**: Use `printf` + `read` or make interactive optional
- **Effort**: Low (10 min)

### MAINT-09: No `.editorconfig` in Repo Root
- **Location**: Only in `src/be/.editorconfig`
- **Risk**: IDE inconsistency across repo (docs, scripts, docker configs)
- **Remediation**: Add root `.editorconfig`
- **Effort**: Low (5 min)

### OBS-04: No Distributed Tracing
- **Location**: No OpenTelemetry / Zipkin / Jaeger integration
- **Risk**: Cannot trace requests across RabbitMQ boundary
- **Remediation**: Add OpenTelemetry SDK; propagate trace context in message headers
- **Effort**: Medium (3-5 hrs)

### PERF-01: No Consumer/Worker for RabbitMQ Queue
- **Location**: Only publisher exists; no `queue:work` or consumer service
- **Risk**: Messages accumulate; no downstream processing
- **Remediation**: Implement consumer worker (Laravel Queue or standalone)
- **Effort**: Medium (2-4 hrs)

### PERF-02: No Dead Letter Queue (DLQ) Configuration
- **Location**: RabbitMQ queue declaration in `RabbitMQPublisher.php:27-33`
- **Risk**: Failed messages lost or retry indefinitely; no visibility
- **Remediation**: Declare DLQ; configure retry policy; monitor DLQ
- **Effort**: Medium (2-3 hrs)

---

## P3 - Low (Nice to Have / Polish)

### MAINT-10: Documentation in Mixed Languages (Azerbaijani/English)
- **Location**: `ARCHITECTURE.md` (AZ), `README.md` (mixed), nginx config comments (AZ)
- **Risk**: Onboarding friction for non-AZ speakers; inconsistency
- **Remediation**: Standardize on English for all documentation
- **Effort**: Low (2-3 hrs)

### MAINT-11: COMMAND.md References Wrong Network Name
- **Location**: `docs/COMMAND.md` references `esds` network
- **Actual**: docker-compose uses `ATLTechCallCenterAPI`
- **Risk**: Developer confusion; commands fail
- **Remediation**: Update COMMAND.md to match actual network name
- **Effort**: Low (10 min)

### MAINT-12: JWT Packages Installed But Unused
- **Location**: `composer.json` (`laravel/sanctum`, `php-open-source-saver/jwt-auth`)
- **Risk**: Unused dependencies increase attack surface; confusion
- **Remediation**: Remove if not planned; or implement JWT auth
- **Effort**: Low (30 min)

### MAINT-13: No API Versioning Strategy Documented
- **Location**: Routes use `/api/v1` prefix but no policy
- **Risk**: Breaking changes deployed without migration path
- **Remediation**: Document versioning policy (semver, deprecation timeline)
- **Effort**: Low (1 hr)

### OBS-05: No Request Size Limit Enforcement in Application
- **Location**: No validation on payload size in `CallEventRequest`
- **Risk**: Large JSON DoS before nginx limit
- **Remediation**: Add `max:100kb` or similar to request validation
- **Effort**: Low (15 min)

### OBS-06: Swagger Scan Paths Config May Miss Module Controllers
- **Location**: `config/l5-swagger.php` (not inspected; default `app/Http`)
- **Risk**: Module annotations not picked up; incomplete API docs
- **Remediation**: Verify `paths` config includes `app/Modules`
- **Effort**: Low (15 min)

### PERF-03: Nginx Not Configured for HTTP/2 or Keep-Alive Tuning
- **Location**: `server/nginx/default.conf`, `nginx.conf`
- **Risk**: Suboptimal connection reuse; higher latency
- **Remediation**: Enable HTTP/2; tune keepalive; add `proxy_buffering`
- **Effort**: Low (30 min)

---

## Debt Paydown Strategy

### Phase 1 (Week 1): Critical Security & Reliability
1. Rotate all exposed secrets (SEC-01 to SEC-04)
2. Sanitize `.env.example` files
3. Add GitHub Actions CI pipeline (REL-01)
4. Fix config drift (REL-03)
5. Add `/up` health endpoint (REL-04)

### Phase 2 (Week 2): Connection Resilience & Test Parity
1. RabbitMQ connection recovery (REL-02)
2. PostgreSQL in CI / testcontainers (REL-05)
3. Migration rollback tests (REL-06)
4. Default RabbitMQ credentials (SEC-08)

### Phase 3 (Week 3): Observability & Quality Gates
1. Structured logging + correlation IDs (OBS-01)
2. Mutation testing (MAINT-03)
3. Prometheus metrics (OBS-03)
4. PHPStan ignore reduction (MAINT-01)

### Phase 4 (Week 4): Consumer Implementation & Hardening
1. RabbitMQ consumer worker (PERF-01)
2. Dead letter queue (PERF-02)
4. Token rotation/expiration (SEC-10)
5. Dependabot config (MAINT-07)

### Ongoing
- Address P2/P3 items during feature work
- Quarterly dependency audit
- Annual architecture review

---

## Tracking

| Item | Status | Owner | Target Date | Notes |
|------|--------|-------|-------------|-------|
| SEC-01 to SEC-04 | Open | - | Week 1 | Blocking for production |
| REL-01 (CI) | Open | - | Week 1 | Prerequisite for other gates |
| REL-02 (RabbitMQ) | Open | - | Week 2 | High impact on reliability |
| REL-05 (Test DB) | Open | - | Week 2 | Reduces false confidence |
| OBS-01 (Logging) | Open | - | Week 3 | Enables debugging |
| PERF-01 (Consumer) | Open | - | Week 4 | Required for queue processing |

---

*Generated by Phase 3 Audit — `atl_tech` repository*