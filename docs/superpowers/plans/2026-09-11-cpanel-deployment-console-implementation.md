# cPanel Deployment Console Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a temporary, browser-accessible, founder-only deployment console that safely runs only the fixed Location/Governance C13 preparation operations required on cPanel without Terminal/SSH.

**Architecture:** Add a dedicated disabled-by-default web surface under `/admin/deployment-console`, guarded by existing Admin + FounderOperations middleware and a separate deployment secret. All execution flows through one service with a hard-coded allowlist; no request value is ever used as an Artisan command name or arbitrary argument. Audit output goes to a dedicated file log so the console does not depend on pending database migrations.

**Tech Stack:** Laravel 9/PHP, Blade, Laravel Artisan facade, existing auth/role middleware, Monolog/Laravel logging, PHPUnit feature/unit tests.

**Spec:** `docs/superpowers/specs/2026-09-11-cpanel-deployment-console-design.md`

## Global Constraints

- The console is disabled by default via `DEPLOYMENT_CONSOLE_ENABLED=false`.
- Access requires authenticated founder/admin authorization plus a non-empty deployment secret.
- Secret values must never be persisted, logged, flashed, or rendered back.
- Only seven operation keys are permitted: `migration_status`, `reference_dry_run`, `readiness`, `flag_status`, `migrate`, `bootstrap`, `reference_apply`.
- Write operations require exact confirmation phrases: `MIGRATE`, `BOOTSTRAP`, `APPLY-IR`.
- No arbitrary Artisan, shell, SQL, eval, truncate/drop, rollback/reset/fresh, rollout-flag activation, or C14 retirement path may exist.
- Production is not modified by implementation or CI. No merge to `main` and no Production deployment occurs in this plan.
- Location/Governance rollout flags remain OFF.

---

## File Structure

**Create**
- `config/deployment-console.php` — disabled-by-default enable/secret configuration.
- `app/Services/Deployment/DeploymentConsoleService.php` — immutable operation catalog, secret validation, fixed Artisan execution, flag readout, sanitized audit logging.
- `app/Http/Controllers/Admin/DeploymentConsoleController.php` — HTTP validation and delegation only.
- `routes/deployment-console.php` — two dedicated routes with no free-form executor.
- `resources/views/admin/deployment-console/index.blade.php` — temporary operational UI.
- `tests/Unit/Deployment/DeploymentConsoleServiceTest.php` — exact allowlist/secret/command mapping contracts.
- `tests/Feature/Admin/DeploymentConsoleSecurityTest.php` — route/middleware/disabled/secret/confirmation security behavior.
- `tests/Feature/Admin/DeploymentConsoleExecutionTest.php` — fixed command invocation and response behavior.
- `tests/Feature/Admin/DeploymentConsoleSourceContractTest.php` — forbidden-command and free-form-execution source contracts.

**Modify**
- `config/logging.php` — add dedicated `deployment-console` single-file channel.
- `app/Providers/RouteServiceProvider.php` — load the new route file behind existing Admin + FounderOperations middleware.
- `.env.example` — document disabled-by-default console env keys without a real secret.
- `docs/location-governance/CPANEL_FRESH_CANONICAL_START_CHECKLIST.md` — replace shell-only operational instructions with browser-console equivalents while keeping cPanel/phpMyAdmin backup requirement.

---

### Task 1: Lock the security and allowlist contract RED

**Files:**
- Create: `tests/Feature/Admin/DeploymentConsoleSourceContractTest.php`
- Create: `tests/Unit/Deployment/DeploymentConsoleServiceTest.php`

**Interfaces:**
- Produces expected operation catalog keys and forbidden-command constraints for later implementation.
- Produces expected service API: `operations(): array`, `isEnabled(): bool`, `secretMatches(string $secret): bool`, `run(string $operation, int $userId, ?string $ip = null): array`, `flags(): array`, `confirmationFor(string $operation): ?string`.

- [ ] **Step 1: Write the failing source-contract test**

```php
<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;

class DeploymentConsoleSourceContractTest extends TestCase
{
    public function test_deployment_console_has_no_forbidden_execution_surface(): void
    {
        $paths = [
            app_path('Services/Deployment/DeploymentConsoleService.php'),
            app_path('Http/Controllers/Admin/DeploymentConsoleController.php'),
            base_path('routes/deployment-console.php'),
        ];

        foreach ($paths as $path) {
            $this->assertFileExists($path);
            $source = file_get_contents($path);
            $this->assertStringNotContainsString('migrate:fresh', $source);
            $this->assertStringNotContainsString('migrate:reset', $source);
            $this->assertStringNotContainsString('migrate:rollback', $source);
            $this->assertStringNotContainsString('shell_exec', $source);
            $this->assertStringNotContainsString('exec(', $source);
            $this->assertStringNotContainsString('eval(', $source);
            $this->assertStringNotContainsString('DB::statement', $source);
        }
    }
}
```

- [ ] **Step 2: Write the failing service catalog test**

```php
public function test_operation_catalog_is_exact_and_write_confirmations_are_fixed(): void
{
    $service = app(\App\Services\Deployment\DeploymentConsoleService::class);

    $this->assertSame([
        'migration_status',
        'reference_dry_run',
        'readiness',
        'flag_status',
        'migrate',
        'bootstrap',
        'reference_apply',
    ], array_keys($service->operations()));

    $this->assertSame('MIGRATE', $service->confirmationFor('migrate'));
    $this->assertSame('BOOTSTRAP', $service->confirmationFor('bootstrap'));
    $this->assertSame('APPLY-IR', $service->confirmationFor('reference_apply'));
    $this->assertNull($service->confirmationFor('migration_status'));
}
```

- [ ] **Step 3: Run focused tests and verify RED**

Run:
```bash
php artisan test tests/Unit/Deployment/DeploymentConsoleServiceTest.php tests/Feature/Admin/DeploymentConsoleSourceContractTest.php
```
Expected: FAIL because the service/controller/route files do not exist.

- [ ] **Step 4: Commit RED checkpoint**

```bash
git add tests/Unit/Deployment/DeploymentConsoleServiceTest.php tests/Feature/Admin/DeploymentConsoleSourceContractTest.php
git commit -m "test: lock deployment console execution boundary"
```

---

### Task 2: Implement the immutable service, config and audit channel GREEN

**Files:**
- Create: `config/deployment-console.php`
- Create: `app/Services/Deployment/DeploymentConsoleService.php`
- Modify: `config/logging.php`
- Modify: `.env.example`
- Test: `tests/Unit/Deployment/DeploymentConsoleServiceTest.php`

**Interfaces:**
- `operations(): array<string,array{command:?string,arguments:array,write:bool,confirmation:?string}>`
- `isEnabled(): bool`
- `secretMatches(string $secret): bool`
- `confirmationFor(string $operation): ?string`
- `flags(): array<string,bool>`
- `run(string $operation, int $userId, ?string $ip = null): array{operation:string,exit_code:int,success:bool,output:string}`

- [ ] **Step 1: Extend RED tests for config/secret/flags**

```php
public function test_console_is_disabled_by_default_and_secret_compare_fails_closed(): void
{
    config()->set('deployment-console.enabled', false);
    config()->set('deployment-console.secret', null);
    $service = app(\App\Services\Deployment\DeploymentConsoleService::class);

    $this->assertFalse($service->isEnabled());
    $this->assertFalse($service->secretMatches('anything'));

    config()->set('deployment-console.secret', 'temporary-secret');
    $this->assertTrue($service->secretMatches('temporary-secret'));
    $this->assertFalse($service->secretMatches('wrong-secret'));
}
```

- [ ] **Step 2: Run and verify RED for missing implementation**

Run:
```bash
php artisan test tests/Unit/Deployment/DeploymentConsoleServiceTest.php
```
Expected: FAIL because the service/config do not exist.

- [ ] **Step 3: Add disabled-by-default config**

`config/deployment-console.php`:
```php
<?php

return [
    'enabled' => (bool) env('DEPLOYMENT_CONSOLE_ENABLED', false),
    'secret' => env('DEPLOYMENT_CONSOLE_SECRET'),
];
```

`.env.example` additions:
```dotenv
DEPLOYMENT_CONSOLE_ENABLED=false
DEPLOYMENT_CONSOLE_SECRET=
```

- [ ] **Step 4: Add dedicated audit log channel**

Add to `config/logging.php` channels:
```php
'deployment-console' => [
    'driver' => 'single',
    'path' => storage_path('logs/deployment-console.log'),
    'level' => 'info',
],
```

- [ ] **Step 5: Implement the service with a literal operation map**

The service must use an internal constant/array equivalent to:
```php
private const OPERATIONS = [
    'migration_status' => ['command' => 'migrate:status', 'arguments' => [], 'write' => false, 'confirmation' => null],
    'reference_dry_run' => ['command' => 'location:reference-import', 'arguments' => ['country' => 'IR', '--dataset-version' => 'v1', '--dry-run' => true], 'write' => false, 'confirmation' => null],
    'readiness' => ['command' => 'location-governance:readiness', 'arguments' => [], 'write' => false, 'confirmation' => null],
    'flag_status' => ['command' => null, 'arguments' => [], 'write' => false, 'confirmation' => null],
    'migrate' => ['command' => 'migrate', 'arguments' => ['--force' => true], 'write' => true, 'confirmation' => 'MIGRATE'],
    'bootstrap' => ['command' => 'db:seed', 'arguments' => ['--class' => 'LocationGovernanceBootstrapSeeder', '--force' => true], 'write' => true, 'confirmation' => 'BOOTSTRAP'],
    'reference_apply' => ['command' => 'location:reference-import', 'arguments' => ['country' => 'IR', '--dataset-version' => 'v1', '--apply' => true], 'write' => true, 'confirmation' => 'APPLY-IR'],
];
```

Unknown keys must throw `InvalidArgumentException` before calling Artisan. For `flag_status`, return a textual rendering from `flags()` and exit code 0 without Artisan.

Secret check:
```php
return is_string($configured)
    && $configured !== ''
    && $secret !== ''
    && hash_equals($configured, $secret);
```

Audit log context must contain only `user_id`, `operation`, `write`, `exit_code`, `success`, `ip`; never request payload or secret.

- [ ] **Step 6: Verify service tests GREEN**

Run:
```bash
php artisan test tests/Unit/Deployment/DeploymentConsoleServiceTest.php tests/Feature/Admin/DeploymentConsoleSourceContractTest.php
```
Expected: PASS.

- [ ] **Step 7: Commit service checkpoint**

```bash
git add config/deployment-console.php config/logging.php .env.example app/Services/Deployment/DeploymentConsoleService.php tests
git commit -m "feat: add allowlisted deployment console service"
```

---

### Task 3: Add founder-only HTTP surface with fail-closed secret and confirmation checks

**Files:**
- Create: `app/Http/Controllers/Admin/DeploymentConsoleController.php`
- Create: `routes/deployment-console.php`
- Modify: `app/Providers/RouteServiceProvider.php`
- Create: `tests/Feature/Admin/DeploymentConsoleSecurityTest.php`

**Interfaces:**
- GET route name: `admin.deployment-console.index`
- POST route name: `admin.deployment-console.run`
- POST fields: `deployment_secret` required string, `confirmation` nullable string.

- [ ] **Step 1: Write RED security tests**

Tests must cover:
```php
public function test_console_is_unavailable_when_disabled(): void
{
    config()->set('deployment-console.enabled', false);
    $user = User::factory()->create(['is_admin' => true]);
    $this->actingAs($user)->get('/admin/deployment-console')->assertNotFound();
}

public function test_non_founder_role_cannot_access_console(): void
{
    config()->set('deployment-console.enabled', true);
    config()->set('deployment-console.secret', 'secret');
    // create ordinary scoped Role and attach it, matching AdminMiddlewareAuthorizationTest pattern
    // request must redirect to /home through FounderOperationsMiddleware
}

public function test_wrong_secret_and_wrong_confirmation_invoke_nothing(): void
{
    config()->set('deployment-console.enabled', true);
    config()->set('deployment-console.secret', 'secret');
    $user = User::factory()->create(['is_admin' => true]);
    \Illuminate\Support\Facades\Artisan::shouldReceive('call')->never();

    $this->actingAs($user)->post('/admin/deployment-console/run/migrate', [
        'deployment_secret' => 'wrong',
        'confirmation' => 'MIGRATE',
    ])->assertSessionHasErrors('deployment_secret');
}
```
Also test unknown operation returns 404 and never calls Artisan, and wrong `confirmation` for `migrate`, `bootstrap`, and `reference_apply` never calls Artisan.

- [ ] **Step 2: Run security tests and verify RED**

Run:
```bash
php artisan test tests/Feature/Admin/DeploymentConsoleSecurityTest.php
```
Expected: FAIL because routes/controller do not exist.

- [ ] **Step 3: Implement controller fail-closed behavior**

Controller rules:
```php
if (! $service->isEnabled() || ! is_string(config('deployment-console.secret')) || config('deployment-console.secret') === '') {
    abort(404);
}
```
For POST:
1. reject unknown operation using service catalog before execution;
2. validate secret;
3. validate exact confirmation when `confirmationFor()` returns non-null;
4. call service with authenticated user ID and request IP;
5. redirect/render with only sanitized result; never flash input secret.

- [ ] **Step 4: Add routes and provider registration**

`routes/deployment-console.php`:
```php
<?php

use App\Http\Controllers\Admin\DeploymentConsoleController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DeploymentConsoleController::class, 'index'])->name('index');
Route::post('/run/{operation}', [DeploymentConsoleController::class, 'run'])
    ->middleware('throttle:najm-hoda-autonomy-write')
    ->name('run');
```

Register through `RouteServiceProvider` with:
```php
Route::middleware([
    'web',
    \App\Http\Middleware\AdminMiddleware::class,
    \App\Http\Middleware\FounderOperationsMiddleware::class,
    'throttle:najm-hoda-autonomy-read',
])->prefix('admin/deployment-console')
  ->name('admin.deployment-console.')
  ->group(base_path('routes/deployment-console.php'));
```

- [ ] **Step 5: Verify security tests GREEN**

Run:
```bash
php artisan test tests/Feature/Admin/DeploymentConsoleSecurityTest.php tests/Feature/Admin/AdminMiddlewareAuthorizationTest.php
```
Expected: PASS.

- [ ] **Step 6: Commit HTTP security checkpoint**

```bash
git add app/Http/Controllers/Admin/DeploymentConsoleController.php routes/deployment-console.php app/Providers/RouteServiceProvider.php tests/Feature/Admin/DeploymentConsoleSecurityTest.php
git commit -m "feat: add founder-only deployment console boundary"
```

---

### Task 4: Verify exact operation execution and no secret leakage

**Files:**
- Create: `tests/Feature/Admin/DeploymentConsoleExecutionTest.php`
- Modify: `app/Services/Deployment/DeploymentConsoleService.php`
- Modify: `app/Http/Controllers/Admin/DeploymentConsoleController.php`

**Interfaces:** same service/controller APIs from Tasks 2-3.

- [ ] **Step 1: Write RED execution tests for exact commands**

Use `Artisan::shouldReceive('call')` with exact arguments for each operation. Required assertions:

```php
Artisan::shouldReceive('call')
    ->once()
    ->with('migrate', ['--force' => true])
    ->andReturn(0);
```

Equivalent exact expectations:
- `migrate:status`, `[]`
- `location:reference-import`, `['country' => 'IR', '--dataset-version' => 'v1', '--dry-run' => true]`
- `location-governance:readiness`, `[]`
- `db:seed`, `['--class' => 'LocationGovernanceBootstrapSeeder', '--force' => true]`
- `location:reference-import`, `['country' => 'IR', '--dataset-version' => 'v1', '--apply' => true]`

Test `flag_status` performs zero Artisan calls and returns the five config flags.

- [ ] **Step 2: Add secret non-leakage test**

After a request with `deployment_secret='ultra-secret-value'`, assert:
```php
$response->assertDontSee('ultra-secret-value');
$this->assertSame('', old('deployment_secret', ''));
```
When audit logging is faked/spied, verify context has no `secret`, `deployment_secret`, `_token`, request body or env values.

- [ ] **Step 3: Run and verify RED for any output/audit gaps**

Run:
```bash
php artisan test tests/Feature/Admin/DeploymentConsoleExecutionTest.php
```
Expected: FAIL until output capture/audit behavior is complete.

- [ ] **Step 4: Implement minimal output/audit behavior**

After Artisan call:
```php
$exitCode = Artisan::call($command, $arguments);
$output = trim(Artisan::output());
```
Return only operation/exit code/success/output. Sanitize unexpected exceptions into a generic user-visible failure while logging exception details through the normal server log without request input.

- [ ] **Step 5: Verify execution tests GREEN**

Run:
```bash
php artisan test tests/Unit/Deployment/DeploymentConsoleServiceTest.php tests/Feature/Admin/DeploymentConsoleSecurityTest.php tests/Feature/Admin/DeploymentConsoleExecutionTest.php tests/Feature/Admin/DeploymentConsoleSourceContractTest.php
```
Expected: PASS.

- [ ] **Step 6: Commit execution checkpoint**

```bash
git add app tests/Feature/Admin/DeploymentConsoleExecutionTest.php
git commit -m "test: verify deployment console fixed operations"
```

---

### Task 5: Add the temporary operational Blade UI

**Files:**
- Create: `resources/views/admin/deployment-console/index.blade.php`
- Modify: `app/Http/Controllers/Admin/DeploymentConsoleController.php`
- Test: `tests/Feature/Admin/DeploymentConsoleExecutionTest.php`

**Interfaces:** controller index passes `operations`, `flags`; run passes/redirects `result` without request secret.

- [ ] **Step 1: Add RED UI assertions**

For an enabled founder/admin GET, assert page contains:
```text
کنسول استقرار موقت
قبل از هر عملیات نوشتنی از دیتابیس Production خروجی کامل بگیرید
فعال‌سازی Location/Governance در این صفحه امکان‌پذیر نیست
MIGRATE
BOOTSTRAP
APPLY-IR
```
Assert the page has no inputs for command name, SQL, shell, flag mutation, or arbitrary arguments.

- [ ] **Step 2: Run UI test and verify RED**

Run:
```bash
php artisan test tests/Feature/Admin/DeploymentConsoleExecutionTest.php
```
Expected: FAIL because view does not exist.

- [ ] **Step 3: Build minimal operational UI**

Requirements:
- use existing admin layout conventions without redesigning global UI;
- read-only five flag statuses;
- seven fixed operation cards/forms;
- password input named `deployment_secret` on each operation form with no `value` attribute;
- confirmation input only on write cards;
- destructive-warning box;
- last result area with operation, exit code, success/failure, escaped output in `<pre>`;
- no public/global navigation entry.

- [ ] **Step 4: Verify UI and security tests GREEN**

Run:
```bash
php artisan test tests/Feature/Admin/DeploymentConsoleExecutionTest.php tests/Feature/Admin/DeploymentConsoleSecurityTest.php
```
Expected: PASS.

- [ ] **Step 5: Commit UI checkpoint**

```bash
git add resources/views/admin/deployment-console/index.blade.php app/Http/Controllers/Admin/DeploymentConsoleController.php tests
git commit -m "feat: add deployment console operational UI"
```

---

### Task 6: Update cPanel runbook and lock deployment sequence

**Files:**
- Modify: `docs/location-governance/CPANEL_FRESH_CANONICAL_START_CHECKLIST.md`
- Create or extend: `tests/Feature/Admin/DeploymentConsoleSourceContractTest.php`

**Interfaces:** operational documentation consumes route names/env keys from previous tasks.

- [ ] **Step 1: Add RED documentation contract assertions**

Assert checklist contains:
```text
DEPLOYMENT_CONSOLE_ENABLED
DEPLOYMENT_CONSOLE_SECRET
/admin/deployment-console
phpMyAdmin
migration status
reference dry-run
readiness
HARD STOP
```
And retains explicit prohibition of `migrate:fresh`, `migrate:reset`, `migrate:rollback`, truncate/drop and flag activation.

- [ ] **Step 2: Run contract and verify RED if docs are stale**

Run:
```bash
php artisan test tests/Feature/Admin/DeploymentConsoleSourceContractTest.php
```
Expected: FAIL until browser-console procedure is documented.

- [ ] **Step 3: Update checklist to browser workflow**

Document exact operator sequence:
1. cPanel/phpMyAdmin full DB export;
2. deploy approved code with console disabled;
3. set temporary enable + secret through cPanel environment/.env editor;
4. open `/admin/deployment-console` as founder/admin;
5. inspect migration status and STOP for review;
6. execute MIGRATE only after review;
7. BOOTSTRAP;
8. reference dry-run and review counts;
9. APPLY-IR only after accepted dry-run;
10. readiness;
11. confirm flags OFF;
12. set `DEPLOYMENT_CONSOLE_ENABLED=false`;
13. HARD STOP before canonical activation.

- [ ] **Step 4: Verify docs contract GREEN**

Run:
```bash
php artisan test tests/Feature/Admin/DeploymentConsoleSourceContractTest.php
```
Expected: PASS.

- [ ] **Step 5: Commit documentation checkpoint**

```bash
git add docs/location-governance/CPANEL_FRESH_CANONICAL_START_CHECKLIST.md tests/Feature/Admin/DeploymentConsoleSourceContractTest.php
git commit -m "docs: add browser deployment console procedure"
```

---

### Task 7: Focused regression + full validation checkpoint

**Files:** no new feature files unless regression fixes are required through a new RED test first.

- [ ] **Step 1: Run all focused deployment-console tests**

```bash
php artisan test \
  tests/Unit/Deployment/DeploymentConsoleServiceTest.php \
  tests/Feature/Admin/DeploymentConsoleSecurityTest.php \
  tests/Feature/Admin/DeploymentConsoleExecutionTest.php \
  tests/Feature/Admin/DeploymentConsoleSourceContractTest.php \
  tests/Feature/Admin/AdminMiddlewareAuthorizationTest.php
```
Expected: PASS, zero failures.

- [ ] **Step 2: Validate route/command boot locally/CI**

Run the repository's existing route/command boot gate used by `.github/workflows/integration-full-validation.yml`.
Expected: PASS.

- [ ] **Step 3: Run the existing Full Validation workflow on the exact branch HEAD**

Required green gates:
- route/command boot;
- Group Chat;
- Group Admin / Identity;
- Najm Hoda + n8n;
- Governance;
- Location / Governance;
- Najm Bahar;
- Stock;
- Group Chat JavaScript;
- Full Project PHPUnit;
- diagnostics;
- enforcement.

- [ ] **Step 4: Record exact validation evidence**

Record:
```text
HEAD=<exact 40-char SHA>
FULL_VALIDATION_RUN=<run id/number>
FULL_VALIDATION_JOB=<job id>
CONCLUSION=success
```
Do not claim readiness before this evidence exists.

- [ ] **Step 5: Update PR/progress without merging**

Update PR #103 or create a dedicated Draft PR if branch separation makes review clearer. Keep Draft; do not merge to `main`; state explicitly that Production has not been modified and console remains disabled by default.

---

## Plan Self-Review

- Spec coverage: every security boundary, operation, confirmation phrase, audit constraint, UI rule, failure behavior, cPanel sequence and non-goal is mapped to Tasks 1-7.
- Placeholder scan: no TBD/TODO/"similar to" implementation gaps remain.
- Type consistency: service methods and route names are stable across Tasks 1-7.
- Safety check: no task authorizes merge, deployment, flag activation, database reset/fresh/rollback, arbitrary command execution or Production writes.
