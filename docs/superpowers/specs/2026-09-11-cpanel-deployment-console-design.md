# cPanel Deployment Console Design

Date: 2026-09-11

## Context

EarthCoop Production currently runs on cPanel hosting without Terminal/SSH access. The Production database was originally created from a Laravel database built locally through migrations and then imported into cPanel. The current Production database must be preserved; re-importing a newer local database is forbidden because it could overwrite real users, groups, messages, Najm Bahar state, elections, projects and other data created since the original import.

The approved Location/Governance rollout uses Fresh Canonical Start: existing users' legacy geographic/profile rows are not a blocking prerequisite for canonical launch, but all other Production data remains protected. Location/Governance activation flags must remain OFF until a separate explicit owner approval.

## Goal

Provide a temporary, browser-accessible deployment console inside EarthCoop that lets the founder/admin perform only a tightly allowlisted set of Laravel deployment operations needed for the Location/Governance C13 preparation when shell access is unavailable.

The console must make dangerous general-purpose execution impossible.

## Security boundary

The console is disabled by default and must require all of the following:

1. authenticated application user;
2. FounderOperationsMiddleware authorization (`is_admin` or `super-admin`);
3. explicit deployment-console enable flag from environment/config;
4. a deployment secret supplied by the operator and checked with constant-time comparison;
5. normal web CSRF protection for every POST;
6. throttle middleware on read and write actions;
7. explicit operation confirmation phrase for every write-capable action.

The deployment secret must never be written to logs, flashed to session messages, rendered back into HTML, or stored in the database.

The console must not expose shell input, arbitrary Artisan command input, arbitrary arguments, arbitrary SQL, eval, file execution, or user-supplied command names.

## Allowed operations

The UI and backend expose only these fixed operations:

### Read-only

- `migration_status`: inspect Laravel migration status / pending migrations.
- `reference_dry_run`: run `location:reference-import IR --dataset-version=v1 --dry-run`.
- `readiness`: run `location-governance:readiness`.
- `flag_status`: display the five Location/Governance rollout flags without modifying them.

### Write-capable preparation operations

- `migrate`: run only `migrate --force`.
- `bootstrap`: run only `db:seed --class=LocationGovernanceBootstrapSeeder --force`.
- `reference_apply`: run only `location:reference-import IR --dataset-version=v1 --apply`.

No activation operation is present in this console. The five rollout flags remain external configuration and OFF until a separate owner approval.

## Explicitly forbidden operations

The console must not provide any route, button, operation mapping or free-form path capable of invoking:

- `migrate:fresh`
- `migrate:reset`
- `migrate:rollback`
- database truncate/drop/rebuild
- arbitrary SQL
- arbitrary Artisan commands
- arbitrary shell commands
- feature-flag activation
- C14 legacy geography retirement

## Operation confirmation

Each write action requires an exact confirmation phrase independent of the deployment secret:

- migrate: `MIGRATE`
- bootstrap: `BOOTSTRAP`
- reference apply: `APPLY-IR`

A wrong or missing phrase returns a validation error and no command is invoked.

Read-only operations do not require a confirmation phrase but still require founder authorization, console enablement, CSRF where POST is used, and deployment-secret validation.

## Deployment secret handling

Configuration keys:

- `deployment-console.enabled` from `DEPLOYMENT_CONSOLE_ENABLED`, default false.
- `deployment-console.secret` from `DEPLOYMENT_CONSOLE_SECRET`, default null.

The request secret is compared with `hash_equals` after first verifying both configured and supplied values are non-empty strings. It is not persisted.

The console must fail closed when disabled or when the secret is absent. Disabled access should behave as unavailable rather than exposing operational details.

## Components

### Routes

Create a dedicated route file loaded by `RouteServiceProvider` under:

`/admin/deployment-console`

Middleware stack:

- `web`
- `AdminMiddleware`
- `FounderOperationsMiddleware`
- read/write throttle as appropriate

Routes:

- GET `/` — render console status page.
- POST `/run/{operation}` — run one fixed allowlisted operation.

The `{operation}` value is resolved only through a server-side enum/map. Unknown operations return 404/validation failure and are never passed into Artisan.

### Controller

`Admin\DeploymentConsoleController`

Responsibilities:

- verify console enabled;
- render status page;
- validate deployment secret without persisting it;
- validate allowlisted operation;
- validate confirmation phrase for write actions;
- delegate execution to a service;
- return sanitized command result to the UI.

The controller must not construct arbitrary command strings.

### Service

`Deployment\DeploymentConsoleService`

Responsibilities:

- own the immutable operation map;
- invoke `Artisan::call()` only with hard-coded command names and hard-coded arguments;
- classify read vs write actions;
- expose required confirmation phrase metadata;
- capture exit code and Artisan output;
- expose flag status using configuration only;
- append a sanitized audit record for each attempted operation.

### Audit logging

Audit logging must not depend on a new database migration, because the console itself may be needed before migrations are applied.

Use a dedicated Laravel log channel/file under `storage/logs`, recording at minimum:

- timestamp;
- authenticated user ID;
- operation name;
- read/write classification;
- exit code;
- success/failure;
- request IP where available.

Never log the deployment secret, CSRF token, raw request body, session cookie, database credentials or `.env` values.

## User interface

The page is operational, not a general admin dashboard. It should show:

- a large warning that this is a temporary Production deployment surface;
- console enabled/disabled state;
- current Location/Governance flags read-only;
- fixed operation cards/buttons;
- explicit destructive-operation warnings even though destructive commands are unavailable;
- deployment secret password field submitted with each operation;
- confirmation phrase field only for write actions;
- command exit code and sanitized textual output;
- reminder to take a current phpMyAdmin/cPanel database export before running any write action;
- reminder that flag activation is not available here.

No secret may be prefilled after a request.

## Data flow

1. Founder/admin signs into EarthCoop.
2. Operator opens `/admin/deployment-console`.
3. Route middleware enforces authenticated founder/admin access.
4. Controller fails closed unless `DEPLOYMENT_CONSOLE_ENABLED=true` and a non-empty secret is configured.
5. Operator chooses a fixed operation.
6. POST is protected by CSRF.
7. Controller validates operation key, supplied secret and any required confirmation phrase.
8. Service maps the key to a fixed Artisan command/arguments and executes it.
9. Service records a sanitized audit line.
10. UI shows exit code/output, with secret fields empty.
11. Operator stops after each significant step and shares output for review before continuing.

## Production sequence supported by the console

The intended preparation sequence is:

1. take a full current Production database export through cPanel/phpMyAdmin;
2. deploy approved application code with console disabled by default;
3. temporarily set deployment-console environment enablement + secret;
4. inspect migration status;
5. review pending migration list;
6. run `migrate --force` only after that review;
7. run Location/Governance bootstrap;
8. run Iran reference import dry-run;
9. inspect create/update/deactivate/conflict counts;
10. run reference apply only if dry-run is acceptable;
11. run readiness;
12. verify Location/Governance flags remain OFF;
13. disable the deployment console again;
14. HARD STOP before any Location/Governance runtime activation.

## Failure behavior

- Disabled console: fail closed/unavailable.
- Missing/incorrect secret: HTTP validation/authorization failure, no Artisan invocation.
- Unknown operation: no Artisan invocation.
- Wrong confirmation phrase: validation failure, no Artisan invocation.
- Non-zero Artisan exit code: display failure output, log failure, stop sequence.
- Migration/import/readiness failure: no automatic rollback, no destructive recovery.
- Any unexpected exception: sanitize user-visible error, log server-side failure without secrets, stop sequence.

## Testing strategy

### Security/HTTP tests

Verify:

- guest cannot access;
- ordinary authenticated user cannot access;
- generic non-founder admin-role paths do not accidentally bypass founder gate;
- console disabled by default is unavailable;
- missing/invalid deployment secret invokes no Artisan operation;
- CSRF-protected POST surface is used;
- unknown operation invokes nothing;
- wrong confirmation phrase invokes nothing;
- secret is not present in response/session/audit log.

### Allowlist tests

Verify only the seven approved operation keys exist and none maps to a forbidden command. Explicit source-contract assertions must reject `migrate:fresh`, `migrate:reset`, `migrate:rollback`, truncate/drop/shell/eval patterns and any use of request-provided command names.

### Execution tests

Use Laravel Artisan fakes/mocks only at the process boundary to verify exact command names/arguments for each allowlisted operation. Read-only flag status must not mutate configuration or database state.

### Regression validation

After focused tests are GREEN, run the full existing Integration Full Validation workflow. Location/Governance, Najm Hoda, Governance, Najm Bahar, Stock, Group Chat, JavaScript and Full Project PHPUnit must remain green.

## Non-goals

This design does not:

- merge or deploy to `main` automatically;
- take the cPanel/phpMyAdmin backup itself;
- provide a database browser;
- provide arbitrary maintenance tooling;
- change Location/Governance rollout flags;
- migrate every existing user's legacy geography;
- retire legacy geography;
- replace future proper SSH/CI deployment infrastructure.

## Removal / disablement policy

The console is temporary operational infrastructure. After C13 Production preparation is complete, `DEPLOYMENT_CONSOLE_ENABLED` must be set back to false. Code may remain for emergency controlled reuse until a later cleanup decision, but the default state is disabled and no public navigation entry is required.

## Acceptance criteria

The design is accepted when:

- the console is impossible to use unless founder/admin + enabled config + deployment secret all pass;
- only the fixed operation allowlist can reach Artisan;
- every write action requires exact secondary confirmation;
- no arbitrary shell/Artisan/SQL execution exists;
- audit logging works without relying on pending database migrations;
- flag activation is not exposed;
- the console can support the full C13 preparation sequence from a browser on cPanel without Terminal;
- focused security/execution tests and the full integration validation are green;
- Production remains unchanged until a separately approved deployment step.