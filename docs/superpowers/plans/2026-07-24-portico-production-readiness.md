# Portico Production Readiness Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Evolve the existing Mivo fork into the production-ready internal application named Portico without changing its framework, rewriting the application, or breaking existing operational features.

**Architecture:** Keep the current PHP custom MVC, RouterOS integration, Tailwind, vanilla JavaScript, and SQLite runtime. Apply security, authorization, branding, dashboard, database, UI, and deployment changes in isolated phases with explicit regression gates and small commits.

**Tech Stack:** PHP 8.x, custom MVC/router/middleware, PDO SQLite, RouterOS API, Tailwind CSS, vanilla JavaScript, Nginx, Docker Compose.

---

## Chunk 1: Continuity Ledger

### Canonical Decisions

- Product name: **Portico**.
- `APP_NAME` canonical value after Phase 3: `Portico`.
- Do not rewrite the application or replace its framework.
- SQLite remains the default database until Phase 5.
- MariaDB and PostgreSQL support must not change business logic.
- UI remains an internal operational dashboard, not a marketing site.
- Every authorization rule must be enforced server-side through middleware.
- Existing features remain compatible unless a deprecated compatibility path is explicitly documented.

### Required Phase Order

- [ ] Phase 1: Security baseline.
- [ ] Phase 2: RBAC, authorization, and audit log.
- [ ] Phase 3: Portico branding and removal of Mivo/community branding.
- [ ] Phase 4: Operational dashboard.
- [ ] Phase 5: Database abstraction.
- [ ] Phase 6: UI/UX improvement.
- [ ] Phase 7: Docker, Dokploy, and documentation.

Do not start the next phase until the current phase gate passes.

### Current Repository State

Batch 1A and Batch 1B are implemented but uncommitted because `.git` is read-only in the current environment.

Completed Batch 1A:

- Environment-driven `APP_ENV`, `APP_DEBUG`, and `APP_KEY` handling.
- Production-safe debug behavior.
- Session strict mode, cookie-only mode, `HttpOnly`, `SameSite=Lax`, and HTTPS-aware `Secure` cookies.
- Session ID regeneration after login.
- Complete session and cookie destruction on logout.
- Removal of the hardcoded application-key fallback.

Completed Batch 1B:

- Session-backed CSRF tokens.
- Automatic CSRF middleware on non-API `POST`, `PUT`, `PATCH`, and `DELETE` routes.
- Server-side hidden field injection for rendered POST forms.
- `X-CSRF-Token` support for state-changing `fetch` calls.
- Primary `POST /logout` flow.
- Deprecated `GET /logout` compatibility route.

Current validation commands:

```bash
php tests/security_batch_1a.php
php tests/security_batch_1b.php
rg --files -g '*.php' -0 | xargs -0 -n1 php -l
composer validate --no-check-publish
git diff --check
```

Expected:

- Both security scripts print `PASS`.
- Every PHP file reports no syntax errors.
- Composer validation passes.
- `git diff --check` produces no output.

Known environment limitations:

- `npm test` is a repository placeholder and exits with `Error: no test specified`.
- Host PHP lacks `pdo_sqlite` and `sqlite3`, so database-backed integration tests cannot run locally.
- The sandbox cannot bind a local HTTP port.
- `.git` is read-only, so staging and commits fail with `index.lock: Read-only file system`.

### Plan Review Record

Reviewed on 2026-07-24:

- Master Chunk 1: approved.
- Master Chunk 2: approved.
- Master Chunk 3: approved after making the validation manifest explicit, deriving the health port from Compose, and waiting for service health.
- Phase 1 Chunk 1: approved.
- Phase 1 Chunk 2: approved.
- Phase 1 Chunk 3: approved after adding the router-target regression command to Step 6.

### Resume Protocol

Every new implementation session must perform these steps before editing:

- [ ] **Step 1: Read this roadmap and the active phase plan**

Active phase plan: `docs/superpowers/plans/2026-07-24-portico-phase-1-security.md`.

- [ ] **Step 2: Inspect repository state**

Run:

```bash
git status --short
git diff --check
```

Expected: only the Batch 1A/1B files listed below and these exact plan paths are present:

```text
docs/superpowers/plans/2026-07-24-portico-production-readiness.md
docs/superpowers/plans/2026-07-24-portico-phase-1-security.md
```

Never revert unknown user changes.

- [ ] **Step 3: Re-run completed security checks**

Run:

```bash
php tests/security_batch_1a.php
php tests/security_batch_1b.php
```

Expected: both print `PASS`.

- [ ] **Step 4: Recover the baseline commit when Git is writable**

If Batch 1A/1B is still uncommitted and `.git` is writable, complete Task 1 in this ledger before any new implementation. If `.git` remains read-only, record the failed command and exact error in Current Repository State, then continue without mixing unrelated edits into the security baseline.

- [ ] **Step 5: Resume the first unchecked task in the active phase plan**

Use `@test-driven-development` for behavior changes and `@verification-before-completion` before marking a task complete.

- [ ] **Step 6: Update this ledger after every completed batch**

Record:

- files changed;
- validation commands and results;
- compatibility impact;
- commit hash, or the exact reason a commit was impossible;
- the next unchecked task.

### Task 1: Recover the Security Baseline Commit

**Files:**

- Include: `.env.example`
- Include: `app/Config/SiteConfig.php`
- Include: `app/Controllers/AuthController.php`
- Include: `app/Controllers/InstallController.php`
- Include: `app/Core/Console.php`
- Include: `app/Core/Controller.php`
- Include: `app/Core/Router.php`
- Include: `app/Core/Session.php`
- Include: `app/Helpers/CsrfHelper.php`
- Include: `app/Helpers/FlashHelper.php`
- Include: `app/Middleware/CsrfMiddleware.php`
- Include: `app/Views/layouts/footer_main.php`
- Include: `app/Views/layouts/navbar_main.php`
- Include: `app/Views/layouts/sidebar_session.php`
- Include: `public/index.php`
- Include: `routes/web.php`
- Include: `tests/security_batch_1a.php`
- Include: `tests/security_batch_1b.php`

- [ ] **Step 1: Verify no unrelated files are included**

Run:

```bash
git status --short
git diff --check
```

Expected: only the files listed above plus these plan documents are changed.

- [ ] **Step 2: Run security validation**

Run:

```bash
php tests/security_batch_1a.php
php tests/security_batch_1b.php
rg --files -g '*.php' -0 | xargs -0 -n1 php -l
composer validate --no-check-publish
git diff --check
```

Expected: both security scripts print `PASS`, every PHP file reports no syntax errors, Composer reports `composer.json is valid`, and `git diff --check` produces no output.

- [ ] **Step 3: Commit after `.git` becomes writable**

```bash
git add .env.example app/Config/SiteConfig.php app/Controllers/AuthController.php \
  app/Controllers/InstallController.php app/Core/Console.php app/Core/Controller.php \
  app/Core/Router.php app/Core/Session.php app/Helpers/CsrfHelper.php \
  app/Helpers/FlashHelper.php app/Middleware/CsrfMiddleware.php \
  app/Views/layouts/footer_main.php app/Views/layouts/navbar_main.php \
  app/Views/layouts/sidebar_session.php public/index.php routes/web.php \
  tests/security_batch_1a.php tests/security_batch_1b.php
git commit -m "fix(security): harden sessions and csrf"
```

Expected: one recovery commit containing only Batch 1A and Batch 1B. Future batches return to one small commit per task.

## Chunk 2: Phase Plans

This chunk is a future-plan registry, not permission to implement a future phase from summary bullets. Only the active detailed plan is executable. At each phase boundary:

- [ ] Run `@writing-plans` against the then-current codebase.
- [ ] Replace the `YYYY-MM-DD` filename below with the actual system date.
- [ ] Include exact files, failing tests, implementation snippets, commands, expected results, and commits.
- [ ] Complete the writing-plans review loop for every chunk.
- [ ] Link the approved plan in this registry before editing production code.
- [ ] Execute it with `@subagent-driven-development` or `@executing-plans`.

### Phase 1: Security Baseline

Active detailed plan:

`docs/superpowers/plans/2026-07-24-portico-phase-1-security.md`

Remaining scope:

- [ ] XSS output hardening.
- [ ] Router target validation and SSRF controls.
- [ ] Versioned authenticated encryption with legacy decrypt compatibility.
- [ ] Login rate limiting.
- [ ] Remove deprecated GET logout after compatibility verification.
- [ ] Run the Phase 1 regression gate.

### Phase 2: RBAC, Authorization, and Audit Log

Before implementation, create:

`docs/superpowers/plans/YYYY-MM-DD-portico-phase-2-rbac-audit.md`

Required role model:

- Super Admin: full access, role/user management, backup/restore, plugins, system settings/update, shutdown/reboot.
- Administrator: dashboard, routers, hotspot users, vouchers, active sessions, reports, limited settings.
- Operator: dashboard, hotspot users, vouchers, active sessions.
- Viewer: read-only operational data.

Required permission model:

- Router: `view`, `create`, `edit`, `delete`.
- Hotspot User: `view`, `create`, `delete`.
- Voucher: `generate`, `print`, `delete`.
- Settings: `view`, `update`.
- System-only permissions for roles, plugins, backup/restore, updates, shutdown, and reboot.

Phase 2 gate:

- [ ] Every protected route has permission middleware.
- [ ] UI visibility matches server authorization but is never the enforcement layer.
- [ ] Super Admin bootstrap/migration is tested.
- [ ] Forbidden requests return 403.
- [ ] Audit events exist for login/logout, vouchers, routers, backup/restore, plugins, and settings.

The Phase 2 plan must provide runnable permission-matrix tests and audit-log persistence tests. The expected server result for every denied endpoint is HTTP 403, regardless of menu visibility.

### Phase 3: Portico Branding

Before implementation, create:

`docs/superpowers/plans/YYYY-MM-DD-portico-phase-3-branding.md`

Canonical configuration:

```dotenv
APP_NAME=Portico
APP_COMPANY=
APP_LOGO=/assets/img/logo.svg
APP_FAVICON=/assets/img/favicon.png
APP_COPYRIGHT=
APP_DESCRIPTION=
```

Required implementation targets:

- `app/Config/SiteConfig.php`
- `.env.example`
- `app/Views/layouts/header_main.php`
- `app/Views/layouts/header_public.php`
- `app/Views/layouts/navbar_main.php`
- `app/Views/layouts/sidebar_session.php`
- `app/Views/layouts/footer_main.php`
- `app/Views/layouts/footer_public.php`
- `app/Views/login.php`
- `app/Views/install.php`
- `app/Views/public/status.php`
- `app/Views/errors/development.php`
- `app/Helpers/TemplateHelper.php`
- `app/Core/Console.php`
- `public/lang/en.json`
- `public/lang/id.json`

Phase 3 rules:

- [ ] Replace visible Mivo/MIVO branding with configuration-backed Portico branding.
- [ ] Keep historical code comments or third-party attribution only where legally/technically necessary.
- [ ] Remove Docs, Community, and Repo elements.
- [ ] Do not hardcode Portico repeatedly in views; views read configuration.
- [ ] Logo and favicon paths must be configurable and escaped.

Phase 3 verification:

```bash
rg -n "MIVO|Mivo|mivodev" app/Views app/Helpers app/Core public/lang .env.example
```

Expected: no user-facing Mivo branding remains. Any retained technical references must be documented line by line.

The Phase 3 plan must also create `tests/branding_config.php` to prove environment overrides for `APP_NAME`, logo, favicon, company, copyright, and description reach rendered output with HTML escaping. A string-removal scan alone is not sufficient.

### Phase 4: Operational Dashboard

Before implementation, create:

`docs/superpowers/plans/YYYY-MM-DD-portico-phase-4-dashboard.md`

Required metrics:

- Total Router, Router Online, Router Offline.
- Total Hotspot Users from RouterOS.
- Active Users and Active Sessions.
- Voucher Generated and Voucher Active, limited to vouchers generated by Portico.
- Router CPU, memory, and traffic.
- Recent Activity from the audit log.

Required source mapping:

- router inventory and credentials: existing `Config` model and `routers` table;
- router online, CPU, memory, traffic, users, and sessions: RouterOS responses through the existing connection path;
- generated/active vouchers: Portico-owned voucher records introduced by the approved dashboard plan, never inferred from every RouterOS hotspot user;
- recent activity: the Phase 2 audit-log repository.

Phase 4 gate:

- [ ] No demo/mock business metrics remain.
- [ ] Voucher and RouterOS user counts are separate and labeled.
- [ ] Partial router failures produce explicit unavailable states.
- [ ] Dashboard is responsive and keyboard accessible.

### Phase 5: Database Layer

Before implementation, create:

`docs/superpowers/plans/YYYY-MM-DD-portico-phase-5-database.md`

Requirements:

- [ ] SQLite remains default.
- [ ] `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` are documented.
- [ ] PDO connection creation is configuration-driven.
- [ ] SQL portability is assessed before claiming MariaDB/PostgreSQL support.
- [ ] Business logic does not construct driver-specific connections.
- [ ] Migrations become versioned and driver-aware.

The Phase 5 plan must create `docs/database-portability.md` containing the SQLite/MariaDB/PostgreSQL syntax matrix and exact test matrix. Support may only be claimed after the same repository tests pass against all three configured PDO drivers.

### Phase 6: UI/UX Improvement

Before implementation, create:

`docs/superpowers/plans/YYYY-MM-DD-portico-phase-6-ui-ux.md`

Requirements:

- [ ] Reduce glass, blur, shadows, and nonessential animation.
- [ ] Standardize spacing, typography, forms, tables, and responsive behavior.
- [ ] Add empty, loading, and error states.
- [ ] Ensure keyboard focus, labels, ARIA names, and minimum touch targets.
- [ ] Preserve operational density and avoid marketing-page composition.

The Phase 6 plan must enumerate each modified view/layout and include desktop/mobile screenshot checks, keyboard-only navigation checks, focus visibility checks, and table overflow checks.

### Phase 7: Docker, Dokploy, and Documentation

Before implementation, create:

`docs/superpowers/plans/YYYY-MM-DD-portico-phase-7-deployment.md`

Requirements:

- [ ] Align Dockerfile and Compose PHP extensions with runtime requirements.
- [ ] Add healthcheck and remove unnecessary fixed container naming.
- [ ] Normalize database, uploads, and environment volume paths.
- [ ] Verify Docker Compose and Dokploy deployment.
- [ ] Update README, installation, Docker, deployment, and environment documentation.

The Phase 7 plan must add a non-authenticated dependency-aware `/health` endpoint, Docker healthcheck, `scripts/verify-clean-install.sh`, and `scripts/verify-sqlite-upgrade.sh`. Its verification must include `docker compose config`, `docker compose build`, `docker compose up -d --wait`, `docker compose ps`, `docker compose port app 80`, and a successful health request.

## Chunk 3: Global Completion Gate

### Task 2: Produce Release Evidence

**Files:**

- Create in Phase 7: `docs/reviews/production-readiness.md`
- Create when needed: `docs/security/accepted-risks.md`
- Verify: `scripts/verify-clean-install.sh`
- Verify: `scripts/verify-sqlite-upgrade.sh`
- Modify: `docs/superpowers/plans/2026-07-24-portico-production-readiness.md`

- [ ] **Step 1: Verify every phase gate**

Record each phase plan path, final commit hash, and validation output in `docs/reviews/production-readiness.md`.

Expected: Phases 1 through 7 are marked complete in this ledger and each links to an approved detailed plan.

- [ ] **Step 2: Resolve review findings**

Record all final Critical and High findings in `docs/reviews/production-readiness.md`.

Expected: no Critical or High finding remains open. Any explicitly accepted High risk must be recorded in `docs/security/accepted-risks.md` with rationale, compensating control, owner, approval date, and review date. Critical risks cannot be waived.

- [ ] **Step 3: Run the complete regression suite**

Run only the commands marked `Validation commands` in each approved phase plan. Before running them, copy those blocks verbatim into `docs/reviews/production-readiness.md` as the release validation manifest. Do not include setup, manual verification, or commit commands in this step. Then run:

```bash
rg --files -g '*.php' -0 | xargs -0 -n1 php -l
composer validate --no-check-publish
git diff --check
```

Expected: every command exits zero and every new behavior has at least one runnable regression check.

- [ ] **Step 4: Verify Docker health**

```bash
docker compose config
docker compose build
docker compose up -d --wait
docker compose ps
PORTICO_HTTP_PORT="$(docker compose port app 80 | sed -n 's/.*:\([0-9][0-9]*\)$/\1/p' | head -n1)"
test -n "$PORTICO_HTTP_PORT"
curl -fsS "http://127.0.0.1:${PORTICO_HTTP_PORT}/health"
```

Expected: Compose configuration and build succeed, `docker compose up -d --wait` waits until service `app` reports `healthy`, `docker compose port app 80` returns the configured host port, and the health endpoint returns HTTP 200 with `{"status":"ok"}`.

- [ ] **Step 5: Verify clean install and SQLite upgrade**

```bash
scripts/verify-clean-install.sh
scripts/verify-sqlite-upgrade.sh
```

Expected: both scripts exit zero, preserve their logs under `docs/reviews/evidence/`, and the upgrade check confirms existing users, routers, settings, and encrypted credentials remain readable.

- [ ] **Step 6: Verify commit and ledger history**

Run in an environment where `.git` is writable:

```bash
git status --short
git log --oneline --decorate -30
```

Expected: `git status --short` is empty. Every completed batch has a dedicated commit recorded in this ledger. A read-only `.git` directory is a release blocker, not an acceptable final-state exception.
