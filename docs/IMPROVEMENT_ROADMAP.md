# Improvement Roadmap — `atl_tech`

> Phase 3 plan (docs-only). This roadmap defines how Phases 4–11 apply to `atl_tech`, risks, `n/a` items, and where standing decisions land. **Approval Gate 10 required** before execution of Phase 4+.

## 1. Objectives and success criteria
- Establish a secure, reproducible, Docker-first baseline with evidence (`make verify` / tests in container).
- Eliminate/contain P0 risks first (secret rotation, CI gate, delivery consistency strategy).
- Upgrade runtimes, dependencies, images, and tooling to latest stable (registry-verified at execution time) without unapproved behavior regressions.
- Reach enforceable quality: automated tests, static/security checks, and CI within budget.
- Keep scope controlled (YAGNI/KISS): one logical change per commit, docs/behavior aligned with evidence.

## 2. Phase 4–11 plan (repo-specific)

### Phase 4 — Git hygiene (`git-steward`)
- **Applies:** Yes.
- **Plan:**
  - Treat all gitleaks findings as **compromised**: request rotation/revocation for `APP_KEY`, `JWT_SECRET`, `CALL_EVENT_API_TOKEN`, mail credentials referenced in `.env.example` (values redacted in audit). Sanitize tracked examples/docs to placeholders in normal commits.
  - Review/triage open remote branches/PRs (notably `renovate/*` visible on origin) per policy: merge ready safe changes via PR flow as appropriate, or defer dependency bumps to Phase 6 ordering; delete merged/stale branches; keep only `main` and `test` where required.
  - Verify/add `.gitignore` coverage for env/keys/artifacts and pre-commit/CI secret scanning hooks once pipeline exists.
  - Record branch/PR decisions with evidence in handoff.
- **Risks:** Secret rotation coordination outside repo; merge conflicts with dependency-upgrade work.
- **Gates/decisions:** **No history rewrite/force-push** without Gate 1. Gate 11 if abandoning unmergeable work. Default-branch change (`master`→`main` if pursued) requires Gate 4 — **not assumed** in this plan.
- **n/a:** None.

### Phase 5 — Container baseline (`container-engineer`)
- **Applies:** Yes.
- **Plan:**
  - Define and implement Docker contract: multi-stage images, `make build/test/verify/up` semantics (verify gates runtime), healthchecks, non-root runtime, pinned base images/digests where appropriate, BuildKit secrets (no secrets in layers), `.dockerignore` hardening.
  - Canonicalize env handling (`.env.example` placeholders-only, single source of truth) and compose project/port alignment (reserve/check `projects/info/state/ports.md` before binding changes).
  - Produce `docs/BASELINE.md` with real command→exit evidence (build/test/smoke) run in Docker; add characterization tests only for critical untested paths if needed to lock behavior.
  - Address PHP image issues evidenced in audit (e.g. `777` perms, conditional composer install/update determinism) without changing business logic.
- **Risks:** Existing environment assumptions (bind mounts, host paths) break; baseline may expose pre-existing failures (must be recorded honestly).
- **Standing decisions:** Docker-first verification (decision 2), secrets standard, Conventional Commits.
- **n/a:** None.

### Phase 6 — Upgrade to latest (`upgrader`)
- **Applies:** Yes (high impact).
- **Plan (ordered, registry-checked at execution time):**
  1. **Runtime/base images:** align PHP 8.4.x patch and container base digests/tags; PostgreSQL/Redis/RabbitMQ/Nginx image currency policy.
  2. **Backend framework/ecosystem:** Laravel 12→13 and compatible first-party packages (`sanctum`, `tinker`, `l5-swagger`, JWT package decisions), migrate breaking changes.
  3. **Messaging client:** `php-amqplib` 2→3 API migration with verification.
  4. **Dev/test tooling:** PHPUnit 12→13 and aligned PHPStan/Larastan/Rector/Pint/PHPCS/plugin ecosystem.
  5. **Frontend toolchain (scoped):** introduce npm lockfile; evaluate/upgrade Vite 6→8, Tailwind 3→4, `laravel-vite-plugin` compatibility (or remove if proven unused in Phase 7 with evidence).
  6. **CI actions/images (when CI added):** latest stable, SHA-pinned in workflow definitions (Phase 10).
  - Triage `renovate/*` branches/PRs against chosen target versions (merge if aligned/green, close superseded).
  - Run `make verify`/tests in Docker after each logical upgrade group; document blockers and migration notes in `CHANGELOG.md`.
- **Risks:** Breaking changes in Laravel 13, AMQP v3, Swagger major, PHPUnit major, Tailwind/Vite majors; potential contract changes.
- **Gates/decisions:** Latest stable always (decision 1) with runtime registry lookup; **Gate 5** if this repo’s own public API/behavioral contract must break; no new dependency without Phase 2-backed `docs/DEPENDENCY_DECISIONS.md` entry (decision 3).
- **n/a:** Specific package targets not hardcoded here (must be resolved at run time).

### Phase 7 — Dead code & restructure (`cleaner`)
- **Applies:** Yes (targeted).
- **Plan:**
  - Prove and remove unused code/deps/scaffold (e.g. duplicate call-event config source, redundant middleware group, unused packages/routes/views/migrations only if evidenced unused), with separate commits and verification per batch.
  - Normalize structure/casing/config consistency (Swagger scan path, PHPUnit source dir casing, env template consolidation) and align with upgraded framework conventions.
  - Preserve externally observable behavior unless explicitly approved/documented.
- **Risks:** False positives (container bindings, dynamic resolution, route/provider registration).
- **Standing decisions:** Prove-by-tooling+tests, no guessed deletion (decision 6); KISS/YAGNI.
- **n/a:** None.

### Phase 8 — Architecture (`architect`)
- **Applies:** Yes (focused, justified changes only).
- **Plan:**
  - Resolve delivery consistency (DB vs queue) with an explicit, tested strategy (e.g. outbox/transactional boundary/compensation/idempotency) proportionate to current single-endpoint scope.
  - Clarify module boundaries/contracts (publisher/repository interfaces), config ownership, and consumer/DLQ/idempotency expectations (document/implement minimal necessary parts).
  - Record ADRs in `docs/adr/` for non-trivial structural decisions (why applied / why not).
- **Risks:** Over-engineering; behavior/operational semantics changes requiring coordination.
- **Standing decisions:** Apply SOLID/DDD/EDA/Clean patterns only with real present need and ADR (decision 7); Gate 5 if public contract changes.
- **n/a:** Full EDA/platform decomposition likely n/a unless justified by real requirements.

### Phase 9 — Hardening (`hardener`)
- **Applies:** Yes.
- **Plan:**
  - Security: credential/token handling model (rotation, least privilege), validation/error hygiene, secure headers/CSP wiring, CORS tightening as appropriate, rate limiting/auth hardening, dependency/image scanning, log redaction.
  - Reliability/operability: broker/DB timeouts and failure handling, health endpoints `/health/live` and `/health/ready` semantics, graceful behavior in `MODE=standalone` for external deps where feasible, correlation IDs/structured logging.
  - Config/12-factor alignment and `openapi.yaml` (and AsyncAPI if messaging contract formalized) kept in sync with implementation.
- **Risks:** Stricter auth/rate/CORS rules may affect existing clients; requires coordinated rollout notes.
- **Standing decisions:** Security/secrets rules in `CLAUDE.md`; no secrets in logs/images; P0→P3 fix/verify discipline.
- **n/a:** Payment/PCI-specific controls n/a unless payment data handling is introduced (not evidenced).

### Phase 10 — CI/CD (`ci-engineer`)
- **Applies:** Yes.
- **Plan:**
  - Add GitHub Actions (or existing platform if introduced later) pipeline: fast parallel checks (format/lint, secret scan) → tests/build in Docker → image/dependency scans; cache deps/layers; build once/reuse; job timeouts and concurrency controls.
  - Pin actions by commit SHA, least-privilege `permissions`, no secrets to fork PRs; publish only on protected/tag flows as relevant.
  - Measure real PR runtime against ≤10 min target and document results in `docs/TEST_REPORT.md`/handoffs.
- **Risks:** Environment/service dependencies slow or flake CI; initial baseline may be red until Phases 5/6/11 stabilize.
- **Standing decisions:** CI runner-time standard (`PROMPT.md` §5).
- **n/a:** Deployment to real environments (Gate 9) out of scope unless separately requested.

### Phase 11 — Tests (`tester`)
- **Applies:** Yes.
- **Plan:**
  - Expand unit/feature/integration tests: validation/auth edge cases, persistence semantics, broker failure/consistency behavior, migration checks, OpenAPI/contract alignment; use production-parity PostgreSQL in Docker for integration paths.
  - Establish regression coverage against `docs/BASELINE.md`; document strategy/results/coverage/limitations in `docs/TESTING_STRATEGY.md` and `docs/TEST_REPORT.md` (with `TC_<MODULE>_<SCENARIO>_<NUMBER>` IDs and PASS/FAIL/BLOCKED/NOT RUN/SKIPPED/N/A statuses).
  - Do not fix production bugs found here; report via handoff for responsible phase (R8).
- **Risks:** Flaky external-service tests; effort to reach meaningful coverage on legacy/scaffold code.
- **Standing decisions:** No weakened tests; deterministic behavior-focused tests; honest reporting.
- **n/a:** Performance/load/mutation testing can be deferred or scheduled nightly if not justified for every PR (to be documented).

## 3. n/a summary
| Item | Status | Reason |
|---|---|---|
| Phase 12–17 in this audit | Out of Phase 3 execution scope | Planned program phases; not executed/documented beyond roadmap context here. |
| Real cluster/domain/secret deployment | n/a now | Requires Gate 9 and real environment inputs. |
| History rewrite / force push | n/a (prohibited now) | Gate 1 only if explicitly approved later. |
| PCI/PII-specialized controls | n/a (unconfirmed need) | No payment/regulated data flows evidenced. |

## 4. Risks and mitigations
| Risk | Impact | Mitigation |
|---|---|---|
| Compromised committed secrets remain usable until rotated | High | Immediate rotation request (this gate), placeholdering in tracked files, CI/pre-commit scanning (Phases 4/10). |
| DB-publish inconsistency causes duplicate/missing downstream effects | High | Prioritize consistency design + tests (Phases 8/9/11) before production reliance. |
| Major upgrades (Laravel/AMQP/PHPUnit/frontend majors) break behavior | Medium-High | Sequential upgrades in Docker, migration notes, rollback per step, Gate 5 if contract breaks. |
| CI initially slow/flaky due to service dependencies | Medium | Service healthchecks, caching, targeted integration scope, measured budgets. |
| Static audit missed runtime-only issues | Medium | Treat baseline/verify runs in Phase 5+ as source of truth and update docs/debt accordingly. |

## 5. Standing decisions — where they land for `atl_tech`
| Decision (CLAUDE.md) | Application in this repo |
|---|---|
| 1. Latest versions, always | Phase 6: registry-resolved latest stable for PHP/Laravel/composer deps/npm tooling/images/actions; blockers recorded in `docs/TECHNICAL_DEBT.md`/handoff. |
| 2. Docker-first | Phases 5–11: all build/test/verify evidence from containers; `make verify` as gate for `make up`. |
| 3. No new dependency before Phase 2 + decision entry | `projects/info/deps/atl_tech.md` exists (input); any added dep requires `docs/DEPENDENCY_DECISIONS.md` entry. |
| 4/5. Semantic Git & branch hygiene | Phases 4–11: Conventional Commits, one logical change/commit, PR-per-phase, merged branches deleted; no `--no-verify`. |
| 6. Dead code removed after proof | Phase 7 only with tooling+tests evidence; separate commits. |
| 7. Design principles with ADR where applied | Phase 8: apply DRY/KISS/YAGNI/SOLID/DDD/EDA only for present problems; KISS/YAGNI win. |
| 8. Per-phase best practices | Each phase agent satisfies its checklist before closure (R6 evidence). |
| 10. Skill learning loop | Curator-managed proposals/updates after green handoffs (R9/R10); this audit includes a proposal in handoff. |
| 11. Autonomous/parked gates | Gate 10 parked as `needs-approval` now; independent work cannot bypass it. |
| 12. No AI attribution | All commits/PRs/comments must be clean. |
| 13/14. Discussions/Wiki/Dependabot | Verify/configure in relevant phases per repo policy and platform capabilities. |

## 6. Approval (Gate 10)
- **Status:** `needs-approval`.
- **Requested approval:** Accept this Phase 3 audit/plan for `atl_tech` to proceed to Phase 4 (git hygiene) under the constraints above (no history rewrite without Gate 1, no unapproved contract-breaking changes without Gate 5, Docker-first verification, rotation of exposed secrets handled out-of-band).
- **Proposed rollback posture (planning level):** Because Phase 3 produced docs only, rollback is trivial (revert doc commits/PR). Execution-phase rollbacks must be per logical change/commit with verified baselines.
