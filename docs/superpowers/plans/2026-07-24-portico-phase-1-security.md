# Portico Phase 1 Security Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete the security baseline for Portico while preserving the current PHP MVC architecture, RouterOS behavior, existing encrypted data, and public API contracts.

**Architecture:** Extend the existing shared helpers and middleware instead of adding a new security framework. Add one standard-library regression script per security boundary, keep legacy decrypt and GET logout compatibility only for documented transition periods, and commit each completed task independently.

**Tech Stack:** PHP 8.x standard library, OpenSSL, existing custom router/middleware, PDO SQLite, vanilla JavaScript.

## Plan Review Record

Reviewed on 2026-07-24:

- Chunk 1: approved.
- Chunk 2: approved.
- Chunk 3: approved after its Step 6 command set was corrected to run `tests/security_router_target.php`.

---

## Chunk 1: Existing Baseline and Recovery

### Task 1: Preserve and Commit Batch 1A/1B

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

- [ ] **Step 1: Run the existing checks**

```bash
php tests/security_router_target.php
php tests/security_batch_1a.php
php tests/security_batch_1b.php
rg --files -g '*.php' -0 | xargs -0 -n1 php -l
composer validate --no-check-publish
git diff --check
```

Expected:

```text
PASS: Batch 1A security checks
PASS: Batch 1B CSRF checks
```

The PHP lint command reports no syntax errors, Composer reports `composer.json is valid`, and `git diff --check` produces no output.

- [ ] **Step 2: Confirm compatibility routes**

Run:

```bash
rg -n "get\('/logout|post\('/logout" routes/web.php
```

Expected: both deprecated GET and primary POST logout routes are present.

- [ ] **Step 3: Commit when Git metadata is writable**

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

## Chunk 2: XSS Hardening

### Task 2: Escape Error and Public Status Output

**Files:**

- Create: `tests/security_xss.php`
- Create: `tests/security_xss_status.js`
- Create: `public/assets/js/status-renderer.js`
- Modify: `app/Views/errors/default.php`
- Modify: `app/Views/layouts/header_main.php`
- Modify: `app/Views/public/status.php`
- Modify: `app/Views/print/custom.php`

- [ ] **Step 1: Write the failing output-escaping test**

Create `tests/security_xss.php` with a hostile payload and assertions against the actual error view plus the public status script:

```php
<?php

declare(strict_types=1);

define('ROOT', dirname(__DIR__));
require_once ROOT.'/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
ini_set('session.save_path', '/tmp');
\App\Core\Session::start();

$payload = '<img src=x onerror=alert(1)>';
$code = 500;
$message = $payload;
$description = $payload;

ob_start();
require ROOT.'/app/Views/errors/default.php';
$renderedError = ob_get_clean();

if (str_contains($renderedError, $payload) || str_contains($renderedError, '<img')
    || preg_match('/<title>[^<]*<img/i', $renderedError)) {
    throw new RuntimeException('FAIL: error view rendered hostile HTML');
}

$title = 'Check Voucher Status';
$session = 'fixture';
ob_start();
require ROOT.'/app/Views/public/status.php';
$renderedStatus = ob_get_clean();
if (! str_contains($renderedStatus, 'status-renderer.js')
    || ! str_contains($renderedStatus, 'renderStatusDetails')) {
    throw new RuntimeException('FAIL: rendered public status view is not wired to the safe renderer');
}

$currentTemplate = 'default';
$templates = [];
$users = [[
    'username' => $payload,
    'password' => $payload,
    'price' => $payload,
    'validity' => $payload,
    'timelimit' => $payload,
    'datalimit' => $payload,
    'profile' => $payload,
    'comment' => $payload,
    'hotspotname' => $payload,
    'dns_name' => $payload,
    'login_url' => 'https://router.example/login',
]];
$templateContent = '<div>{{username}} {{password}}</div>';
$logoMap = [];
$_SERVER['REQUEST_URI'] = '/print';
ob_start();
require ROOT.'/app/Views/print/custom.php';
$renderedPrint = ob_get_clean();
if (str_contains($renderedPrint, $payload) || str_contains($renderedPrint, '<img src=x')) {
    throw new RuntimeException('FAIL: custom print view rendered hostile voucher data');
}

\App\Core\Session::destroy();
echo "PASS: XSS escaping checks\n";
```

- [ ] **Step 2: Run the test and document the vulnerable render paths**

Run:

```bash
php tests/security_xss.php
node tests/security_xss_status.js
rg -n "innerHTML|errorMessage|errorDescription|echo \$html" \
  app/Views/public/status.php app/Views/errors/default.php app/Views/print/custom.php
```

Expected: the PHP test fails before the view changes because the error view or custom print view emits the payload, or the status page is not wired to the renderer. The JavaScript test fails before the status renderer exists because status data is interpolated into the HTML template.

- [ ] **Step 3: Replace public status `innerHTML` interpolation**

Create `public/assets/js/status-renderer.js` with a small `window.renderStatusDetails(data, translate)` function. Add `<script src="/assets/js/status-renderer.js"></script>` before the inline status logic in `app/Views/public/status.php`, then call that function instead of building an HTML string with RouterOS/API data.

Required pattern:

```javascript
const value = document.createElement('span');
value.textContent = String(data.user ?? '-');
container.replaceChildren(value);
```

Do not sanitize by stripping selected characters and do not assign API values to `innerHTML`.

- [ ] **Step 4: Add the status renderer regression test**

Create `tests/security_xss_status.js` using Node's built-in `vm` module and a minimal fake DOM. Execute the real `public/assets/js/status-renderer.js` with a hostile value; `window.renderStatusDetails()` must return its root node so the test can assert:

```javascript
if (root.innerHTML.includes('<img') || root.textContent !== hostileValue) {
    throw new Error('FAIL: status renderer accepted executable HTML');
}
```

The fake DOM must implement only `createElement`, `append`, `replaceChildren`, `textContent`, and `innerHTML`; it must not sanitize or transform the value itself.

- [ ] **Step 5: Escape server-rendered error text and title**

In `app/Views/errors/default.php`, render variables as:

```php
<?= htmlspecialchars((string) $errorMessage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
<?= htmlspecialchars((string) $errorDescription, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
```

In `app/Views/layouts/header_main.php`, escape the generated page title with the same flags before placing it inside `<title>`.

- [ ] **Step 6: Constrain custom print template substitutions**

Keep administrator-authored template HTML compatible, but escape every dynamic voucher value before substitution in `app/Views/print/custom.php`:

```php
$safeValue = htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
```

Use `json_encode(..., JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)` for values inserted into generated JavaScript. Do not escape the trusted template structure itself in this phase.

- [ ] **Step 7: Run checks**

```bash
php tests/security_xss.php
node tests/security_xss_status.js
php tests/security_batch_1a.php
php tests/security_batch_1b.php
php -l app/Views/errors/default.php
php -l app/Views/layouts/header_main.php
php -l app/Views/public/status.php
php -l app/Views/print/custom.php
git diff --check
```

Expected: all commands pass.

- [ ] **Step 8: Commit**

```bash
git add tests/security_xss.php tests/security_xss_status.js \
  public/assets/js/status-renderer.js app/Views/errors/default.php \
  app/Views/layouts/header_main.php app/Views/public/status.php \
  app/Views/print/custom.php
git commit -m "fix(security): escape untrusted output"
```

## Chunk 3: SSRF and Router Target Validation

### Task 3: Reject Unsafe Router Targets

**Files:**

- Create: `tests/security_router_target.php`
- Create: `tests/security_api_routes.php`
- Create: `app/Helpers/RouterTargetHelper.php`
- Modify: `app/Controllers/ApiController.php`
- Modify: `app/Controllers/PublicStatusController.php`
- Modify: `app/Controllers/SettingsController.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write the failing router-target test**

Test these cases:

```php
$cases = [
    ['192.168.88.1', true],
    ['10.0.0.1', true],
    ['fc00::1', true],
    ['router.internal.example', true],
    ['router-v6.internal.example', true],
    ['router-missing.internal.example', false],
    ['127.0.0.1', false],
    ['::1', false],
    ['fe80::1', false],
    ['::ffff:127.0.0.1', false],
    ['169.254.169.254', false],
    ['http://example.com', false],
    ['file:///etc/passwd', false],
    ['user@example.com', false],
];
```

Private RFC1918 addresses must remain valid because Portico manages internal routers.

- [ ] **Step 2: Run the test to verify it fails**

```bash
php tests/security_router_target.php
```

Expected: FAIL because `RouterTargetHelper` does not exist.

- [ ] **Step 3: Implement the minimum validator**

`RouterTargetHelper::resolve(string $target, ?callable $resolver = null): string` must:

- accept valid IPv4/IPv6 or a conservative hostname;
- reject URL schemes, credentials, paths, fragments, and query strings;
- reject loopback, unspecified, multicast, and link-local addresses;
- explicitly reject `169.254.169.254`;
- preserve private network addresses.

For hostnames, the production resolver must inspect both A and AAAA records with `dns_get_record`. Reject the target if any resolved address is unsafe or DNS resolution is unavailable. Return one validated resolved address and pass that address to RouterOS, preventing a second DNS lookup from creating a rebinding gap. The test passes deterministic resolver fixtures for `router.internal.example` and `router-v6.internal.example`, plus an empty fixture for `router-missing.internal.example`; it must not depend on public DNS. `validate()` may remain as a boolean wrapper around `resolve()` for form validation.

Use `filter_var`, `gethostbynamel`, and standard string checks. Do not add a dependency.

- [ ] **Step 4: Apply validation at every RouterOS connection boundary**

Before calling `RouterOSAPI::connect`, validate the configured or submitted target in:

- `ApiController`;
- `PublicStatusController`;
- router create/update methods in `SettingsController`.

Invalid targets in `ApiController` and `PublicStatusController` return JSON HTTP 422 with `{"error":"Invalid router target"}`. Invalid targets in `SettingsController` set the existing error flash and redirect without attempting a connection. Never choose between response styles within one endpoint.

- [ ] **Step 5: Require authentication for the settings interface-discovery API**

Update `routes/api.php` so only `/api/router/interfaces` uses the existing `auth` middleware, for example by attaching `->middleware('auth')` to that route. Leave public voucher/status endpoints unchanged until their contract receives a dedicated plan.

Create `tests/security_api_routes.php` with a route-level assertion that boots `routes/api.php`, inspects the Router route table, and proves `/api/router/interfaces` contains `auth` while `/api/status/check` does not.

- [ ] **Step 6: Run checks**

```bash
php tests/security_router_target.php
php tests/security_batch_1a.php
php tests/security_batch_1b.php
php tests/security_api_routes.php
php -l app/Helpers/RouterTargetHelper.php
php -l app/Controllers/ApiController.php
php -l app/Controllers/PublicStatusController.php
php -l app/Controllers/SettingsController.php
php -l routes/api.php
git diff --check
```

- [ ] **Step 7: Commit**

```bash
git add tests/security_router_target.php tests/security_api_routes.php \
  app/Helpers/RouterTargetHelper.php \
  app/Controllers/ApiController.php app/Controllers/PublicStatusController.php \
  app/Controllers/SettingsController.php routes/api.php
git commit -m "fix(security): validate router targets"
```

## Chunk 4: Authenticated Encryption

### Task 4: Add Versioned AES-256-GCM Encryption

**Files:**

- Create: `tests/security_encryption.php`
- Modify: `app/Helpers/EncryptionHelper.php`
- Verify: `app/Config/SiteConfig.php`

- [ ] **Step 1: Write failing compatibility tests**

The test must verify:

- new ciphertext starts with `v2:`;
- new ciphertext decrypts to the original value;
- changing one ciphertext byte causes decryption to fail closed;
- legacy CBC ciphertext generated with the current format still decrypts;
- missing/short `APP_KEY` throws and never falls back.

- [ ] **Step 2: Run the test to verify it fails**

```bash
php tests/security_encryption.php
```

Expected: FAIL because encryption still emits the legacy CBC format.

- [ ] **Step 3: Implement versioned GCM encryption**

New payload format:

```text
v2:<base64(iv || tag || ciphertext)>
```

Use:

```php
$iv = random_bytes(openssl_cipher_iv_length('aes-256-gcm'));
$ciphertext = openssl_encrypt(
    $text,
    'aes-256-gcm',
    SiteConfig::getSecretKey(),
    OPENSSL_RAW_DATA,
    $iv,
    $tag
);
```

Validate every OpenSSL return value. Throw `RuntimeException` on encryption failure.

- [ ] **Step 4: Preserve legacy decrypt compatibility**

`decrypt()` must:

- use authenticated GCM for `v2:` payloads;
- reject modified `v2:` payloads without returning ciphertext as plaintext;
- use the existing CBC parser only for unprefixed legacy values;
- keep existing plaintext fallback only for clearly non-encrypted legacy values.

- [ ] **Step 5: Run checks**

```bash
php tests/security_encryption.php
php tests/security_batch_1a.php
php -l app/Helpers/EncryptionHelper.php
git diff --check
```

- [ ] **Step 6: Commit**

```bash
git add tests/security_encryption.php app/Helpers/EncryptionHelper.php
git commit -m "fix(security): authenticate encrypted secrets"
```

## Chunk 5: Login Rate Limiting

### Task 5: Throttle Failed Login Attempts

**Files:**

- Create: `tests/security_rate_limit.php`
- Create: `app/Models/LoginAttempt.php`
- Modify: `app/Core/Migrations.php`
- Modify: `app/Controllers/AuthController.php`
- Modify: `.env.example`

- [ ] **Step 1: Write the failing rate-limit test**

Required defaults:

```dotenv
AUTH_MAX_ATTEMPTS=5
AUTH_LOCKOUT_SECONDS=900
```

The test must verify five failed attempts block the sixth attempt for the same normalized username and client IP, while a successful login clears prior attempts.

- [ ] **Step 2: Run the test to verify it fails**

```bash
php tests/security_rate_limit.php
```

Expected: FAIL because `LoginAttempt` does not exist. If host PHP lacks `pdo_sqlite`, run the same command inside the application container after `docker compose build`.

- [ ] **Step 3: Add the SQLite table**

Add to the current migration path without changing unrelated schema:

```sql
CREATE TABLE IF NOT EXISTS login_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL,
    ip_address TEXT NOT NULL,
    attempted_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_login_attempt_lookup
ON login_attempts (username, ip_address, attempted_at);
```

- [ ] **Step 4: Implement the minimal model**

`LoginAttempt` owns only:

- `isBlocked(string $username, string $ip): bool`;
- `recordFailure(string $username, string $ip): void`;
- `clear(string $username, string $ip): void`;
- deletion of expired rows during record/check operations.

Read numeric limits from environment, clamp invalid values to safe defaults, and use existing parameterized database queries.

- [ ] **Step 5: Integrate with login**

In `AuthController::login()`:

- normalize username with `trim` and lowercase only for rate-limit lookup;
- derive IP from `REMOTE_ADDR` only in this phase;
- reject blocked attempts before password verification;
- record failed authentication;
- clear attempts after successful authentication;
- keep the same generic invalid-credentials message.

- [ ] **Step 6: Run checks**

```bash
php tests/security_rate_limit.php
php tests/security_batch_1a.php
php tests/security_batch_1b.php
php -l app/Models/LoginAttempt.php
php -l app/Core/Migrations.php
php -l app/Controllers/AuthController.php
git diff --check
```

When host PHP lacks `pdo_sqlite`, run the database-backed scripts with `docker compose run --rm app php tests/security_rate_limit.php` and record the container output.

- [ ] **Step 7: Commit**

```bash
git add tests/security_rate_limit.php app/Models/LoginAttempt.php \
  app/Core/Migrations.php app/Controllers/AuthController.php .env.example
git commit -m "fix(auth): throttle failed logins"
```

## Chunk 6: Compatibility Removal and Phase Gate

### Task 6: Remove Deprecated GET Logout

**Files:**

- Modify: `routes/web.php`
- Verify: `app/Views/layouts/navbar_main.php`
- Verify: `app/Views/layouts/sidebar_session.php`

- [ ] **Step 1: Confirm no application UI uses GET logout**

```bash
rg -n 'href="/logout"|get\('/logout' app routes public
```

Expected before removal: only the deprecated route remains.

- [ ] **Step 2: Remove the deprecated route**

Delete only:

```php
$router->get('/logout', [AuthController::class, 'logout']);
```

- [ ] **Step 3: Run CSRF and syntax checks**

```bash
php tests/security_batch_1b.php
php -l routes/web.php
git diff --check
```

- [ ] **Step 4: Commit**

```bash
git add routes/web.php
git commit -m "fix(auth): require POST logout"
```

### Task 7: Run the Phase 1 Gate

**Files:**

- Verify: `tests/security_batch_1a.php`
- Verify: `tests/security_batch_1b.php`
- Verify: `tests/security_xss.php`
- Verify: `tests/security_router_target.php`
- Verify: `tests/security_api_routes.php`
- Verify: `tests/security_encryption.php`
- Verify: `tests/security_rate_limit.php`
- Modify: `docs/superpowers/plans/2026-07-24-portico-production-readiness.md`

- [ ] **Step 1: Run all security checks**

```bash
php tests/security_batch_1a.php
php tests/security_batch_1b.php
php tests/security_xss.php
php tests/security_router_target.php
php tests/security_api_routes.php
php tests/security_encryption.php
php tests/security_rate_limit.php
```

Expected: every script prints `PASS`.

- [ ] **Step 2: Run repository validation**

```bash
rg --files -g '*.php' -0 | xargs -0 -n1 php -l
composer validate --no-check-publish
git diff --check
git status --short
```

Expected: PHP and Composer validation pass, diff check is clean, and only deliberate plan-led changes are present.

- [ ] **Step 3: Run container-backed integration when available**

Use the repository Docker runtime so PDO SQLite is present:

```bash
docker compose build
docker compose up -d
docker compose ps
```

Expected: the application container is running. Record any missing healthcheck as Phase 7 work rather than expanding Phase 1.

- [ ] **Step 4: Verify authentication manually**

Check:

- login succeeds with valid credentials;
- failed attempts are throttled;
- session cookie has `HttpOnly`, `SameSite=Lax`, and `Secure` over HTTPS;
- logout uses POST and destroys the session;
- POST without CSRF returns 403;
- unsafe router targets are rejected;
- existing encrypted router credentials still decrypt.

- [ ] **Step 5: Update the master ledger**

Mark Phase 1 complete in `docs/superpowers/plans/2026-07-24-portico-production-readiness.md`, record commit hashes and validation evidence, then create the detailed Phase 2 RBAC/audit plan with `@writing-plans` before editing Phase 2 code.
