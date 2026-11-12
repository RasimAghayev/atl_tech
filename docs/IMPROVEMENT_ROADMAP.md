# Improvement Roadmap

**Repository**: `atl_tech`  
**Audit Date**: 2026-11-12  
**Auditor**: Phase 3 Agent  
**Planning Horizon**: 12 months (Phases 4-11)

---

## Vision

Transform the Call Event API from a **functional prototype** into a **production-grade, observable, resilient platform** capable of handling telecom-scale call event ingestion with enterprise SLAs.

---

## Phase 4: Foundation Hardening (Weeks 1-2)

**Goal**: Eliminate critical blockers; establish quality gates.

| Task | Description | Owner | Effort | Dependencies |
|------|-------------|-------|--------|--------------|
| 4.1 | **Rotate all exposed secrets** (APP_KEY, MAIL, JWT, API_TOKEN) | DevOps | 30 min | None |
| 4.2 | **Sanitize `.env.example`** files (root + src/be) with placeholders | DevOps | 15 min | 4.1 |
| 4.3 | **Add GitHub Actions CI pipeline** (test, pint, phpstan, composer audit) | DevOps | 3 hrs | None |
| 4.4 | **Fix config drift** between docker-compose.yml and .env.example | DevOps | 30 min | 4.2 |
| 4.5 | **Add `/up` health endpoint** for container orchestration | Backend | 30 min | None |
| 4.6 | **Change default RabbitMQ credentials** (guest/guest → strong) | DevOps | 15 min | None |
| 4.7 | **Add request body size limit** in nginx (1M) | DevOps | 10 min | None |

**Exit Criteria**: CI passing on main; no secrets in repo; health checks green.

---

## Phase 5: Reliability & Resilience (Weeks 3-4)

**Goal**: Make the system resilient to infrastructure failures.

| Task | Description | Owner | Effort | Dependencies |
|------|-------------|-------|--------|--------------|
| 5.1 | **RabbitMQ connection recovery** with retry/backoff/circuit breaker | Backend | 3 hrs | 4.3 |
| 5.2 | **PostgreSQL test parity** (testcontainers or dedicated CI DB) | Backend | 4 hrs | 4.3 |
| 5.3 | **Migration rollback tests** in CI | Backend | 1 hr | 5.2 |
| 5.4 | **Per-token rate limiting** (replace IP-only throttle) | Backend | 2 hrs | 4.3 |
| 5.5 | **CSP header** for API responses | Backend | 30 min | None |
| 5.6 | **Audit logging** for auth failures (401 attempts) | Backend | 30 min | None |

**Exit Criteria**: Broker restart doesn't break publishes; CI uses PostgreSQL; rate limits enforced per token.

---

## Phase 6: Observability Foundation (Weeks 5-6)

**Goal**: Gain visibility into system behavior in production.

| Task | Description | Owner | Effort | Dependencies |
|------|-------------|-------|--------|--------------|
| 6.1 | **Structured JSON logging** with correlation IDs | Backend | 3 hrs | 5.1 |
| 6.2 | **Request ID middleware** (generate/propagate X-Request-ID) | Backend | 1 hr | 6.1 |
| 6.3 | **Prometheus metrics** endpoint (`/metrics`) | Backend | 3 hrs | 4.3 |
| 6.4 | **Grafana dashboards** (latency, error rate, queue depth, DB) | DevOps | 4 hrs | 6.3 |
| 6.5 | **Alerting rules** (error rate >1%, p99 >2s, queue lag >1000) | DevOps | 2 hrs | 6.4 |
| 6.6 | **Distributed tracing** (OpenTelemetry → Jaeger/Zipkin) | Backend | 5 hrs | 6.1 |

**Exit Criteria**: Dashboards live; alerts firing correctly; traces span HTTP → RabbitMQ.

---

## Phase 7: Queue Consumer & Processing (Weeks 7-8)

**Goal**: Actually process the queued call events.

| Task | Description | Owner | Effort | Dependencies |
|------|-------------|-------|--------|--------------|
| 7.1 | **Implement RabbitMQ consumer worker** (Laravel Queue or standalone) | Backend | 4 hrs | 5.1 |
| 7.2 | **Dead Letter Queue (DLQ)** configuration with retry policy | Backend | 3 hrs | 7.1 |
| 7.3 | **Consumer scaling** (multiple workers, prefetch tuning) | DevOps | 2 hrs | 7.1 |
| 7.4 | **Consumer monitoring** (lag, throughput, error rate) | DevOps | 2 hrs | 6.3, 7.1 |
| 7.5 | **Idempotency** for downstream processing (deduplication keys) | Backend | 3 hrs | 7.1 |

**Exit Criteria**: Events consumed within 5s; DLQ captures failures; horizontal scaling works.

---

## Phase 8: Security Hardening (Weeks 9-10)

**Goal**: Meet enterprise security requirements.

| Task | Description | Owner | Effort | Dependencies |
|------|-------------|-------|--------|--------------|
| 8.1 | **Token rotation/expiration** (JWT with `exp`, rotation endpoint) | Backend | 4 hrs | None |
| 8.2 | **CORS configuration** for production origins | Backend | 30 min | None |
| 8.3 | **Request size validation** in application layer | Backend | 15 min | None |
| 8.4 | **Security headers audit** (HSTS, CSP, Permissions-Policy) | DevOps | 1 hr | 5.5 |
| 8.5 | **Dependency vulnerability scanning** in CI (`composer audit`, `npm audit`) | DevOps | 1 hr | 4.3 |
| 8.6 | **Container image scanning** (Trivy/Snyk in CI) | DevOps | 2 hrs | 4.3 |

**Exit Criteria**: Tokens rotate automatically; CI blocks vulnerable deps; images clean.

---

## Phase 9: API Evolution & Documentation (Weeks 11-12)

**Goal**: Sustainable API lifecycle management.

| Task | Description | Owner | Effort | Dependencies |
|------|-------------|-------|--------|--------------|
| 9.1 | **API versioning strategy** document (semver, deprecation policy) | Backend | 2 hrs | None |
| 9.2 | **OpenAPI contract testing** (validate responses against spec) | Backend | 3 hrs | 4.3 |
| 9.3 | **Swagger config fix** (ensure `app/Modules` scanned) | Backend | 30 min | None |
| 9.4 | **API deprecation headers** (`Sunset`, `Deprecation`) | Backend | 1 hr | 9.1 |
| 9.5 | **Documentation language standardization** (English only) | Tech Writer | 3 hrs | None |

**Exit Criteria**: Versioning policy published; contract tests in CI; docs consistent.

---

## Phase 10: Quality & Developer Experience (Weeks 13-14)

**Goal**: Reduce friction; catch bugs earlier.

| Task | Description | Owner | Effort | Dependencies |
|------|-------------|-------|--------|--------------|
| 10.1 | **Mutation testing** (Infection) in CI | Backend | 3 hrs | 4.3 |
| 10.2 | **Dependabot/Renovate** config for automated updates | DevOps | 30 min | None |
| 10.3 | **Rector upgrade** to Laravel 120 rule set | Backend | 1 hr | None |
| 10.4 | **PHPStan ignore reduction** (address 5 patterns incrementally) | Backend | 4 hrs | Ongoing |
| 10.5 | **Root `.editorconfig`** for consistent IDE setup | DevOps | 10 min | None |
| 10.6 | **Makefile portability** (remove bash-specific `read -p`) | DevOps | 10 min | None |

**Exit Criteria**: Mutation score >80%; Dependabot PRs auto-merge on green; PHPStan clean.

---

## Phase 11: Performance & Scale (Weeks 15-16)

**Goal**: Prepare for production load.

| Task | Description | Owner | Effort | Dependencies |
|------|-------------|-------|--------|--------------|
| 11.1 | **Load testing** (k6/Locust: 1000 req/s target) | QA | 4 hrs | 7.3 |
| 11.2 | **Database optimization** (partitioning `call_event_logs`, read replicas) | Backend | 4 hrs | 11.1 |
| 11.3 | **Redis caching** for hot lookups (if needed) | Backend | 2 hrs | 11.1 |
| 11.4 | **Nginx tuning** (HTTP/2, keepalive, proxy buffering) | DevOps | 1 hr | None |
| 11.5 | **Capacity planning** document (scaling triggers, limits) | DevOps | 2 hrs | 11.1 |
| 11.6 | **Chaos engineering** (broker kill, DB failover, network latency) | DevOps | 4 hrs | 6.4, 7.3 |

**Exit Criteria**: 1000 req/s sustained; p99 <500ms; automated failover verified.

---

## Cross-Cutting Concerns (Ongoing)

| Area | Activities | Cadence |
|------|------------|---------|
| **Dependency Management** | `composer outdated`, `npm outdated`, security advisories | Weekly |
| **Secret Rotation** | Rotate API keys, DB passwords, JWT secrets | Quarterly |
| **Documentation Sync** | Update docs with code changes | Per PR |
| **Architecture Decision Records (ADRs)** | Record significant decisions | Per decision |
| **Post-Incident Reviews** | Blameless postmortems for SEV-1/2 | Per incident |

---

## Resource Estimates

| Phase | Backend (hrs) | DevOps (hrs) | QA (hrs) | Tech Writer (hrs) | Total (hrs) |
|-------|---------------|--------------|----------|-------------------|-------------|
| 4 | 1 | 4.5 | 0 | 0 | 5.5 |
| 5 | 10.5 | 0 | 0 | 0 | 10.5 |
| 6 | 12 | 6 | 0 | 0 | 18 |
| 7 | 12 | 4 | 0 | 0 | 16 |
| 8 | 4.75 | 3 | 0 | 0 | 7.75 |
| 9 | 6.5 | 0 | 0 | 3 | 9.5 |
| 10 | 8.5 | 1 | 0 | 0 | 9.5 |
| 11 | 10 | 7 | 4 | 0 | 21 |
| **Total** | **65.25** | **25.5** | **4** | **3** | **97.75** |

---

## Risk Register

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| RabbitMQ 2.x → 3.x migration breaks publisher | Medium | High | Test in staging; feature flag; Phase 5.1 addresses |
| Laravel 12 → 13 upgrade scope creep | Medium | High | Pin to 12.x until Phase 11; separate epic |
| Testcontainers flakiness in CI | Medium | Medium | Use dedicated PostgreSQL service; retry logic |
| Consumer implementation reveals schema gaps | Low | High | Contract testing (Phase 9.2) before consumer |
| Team capacity insufficient for parallel phases | High | Medium | Prioritize P0/P1; defer P2/P3; seek stakeholder alignment |

---

## Gate 10: Pre-Approval Status

**This roadmap requires stakeholder approval before Phase 4 execution.**

| Gate | Status | Notes |
|------|--------|-------|
| Gate 10 (Roadmap Approval) | **NEEDS-APPROVAL** | Not pre-approved; record in `projects/info/state/handoff/3/atl_tech.md` |

**Approval Required From**: Product Owner, Engineering Lead, DevOps Lead

**Approval Criteria**:
- [ ] Resource allocation confirmed (2 backend, 1 DevOps, 0.5 QA)
- [ ] Timeline acceptable (16 weeks to Phase 11)
- [ ] Scope aligned with business priorities
- [ ] Risk register acknowledged

---

## Success Metrics (KPIs)

| Metric | Baseline (Audit) | Target (Phase 11) | Measurement |
|--------|------------------|-------------------|-------------|
| CI Pass Rate | N/A (no CI) | 100% on main | GitHub Actions |
| Secret Exposure | 4 critical | 0 | TruffleHog/GitLeaks in CI |
| Test DB Parity | SQLite only | PostgreSQL in CI | CI config |
| RabbitMQ Resilience | Fails on restart | Auto-recover <5s | Chaos test |
| Health Endpoint | Missing | `/up` returns 200 | Orchestration |
| Observability | Logs only | Metrics + Traces + Logs | Grafana/Jaeger |
| Queue Processing | Publisher only | Consumer + DLQ | Lag <5s |
| Mutation Score | N/A | >80% | Infection |
| Load Capacity | Unknown | 1000 req/s, p99<500ms | k6 |
| Deployment Frequency | Manual | On merge to main | CI/CD |

---

## Handoff Notes for Phase 4 Team

1. **Start with Phase 4.1-4.7 immediately** — these unblock everything else
2. **CI pipeline (4.3) is the force multiplier** — invest in quality here
3. **RabbitMQ resilience (5.1) is the highest technical risk** — allocate senior engineer
4. **Testcontainers (5.2) may need CI runner resources** — provision early
5. **Document decisions in ADRs** — start from Phase 4

---

*Generated by Phase 3 Audit — `atl_tech` repository*