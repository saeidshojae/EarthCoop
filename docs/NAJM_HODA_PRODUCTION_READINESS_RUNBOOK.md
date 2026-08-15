# Najm Hoda Production Readiness Runbook

Branch: `agent/najm-hoda-hardening`

## Purpose

This runbook turns the existing Najm Hoda governance/readiness services into an operational release gate.

## 1. GitHub / CI checks

Required before any merge or production autonomy change:

```bash
composer validate --no-check-publish
composer audit --no-dev --abandoned=report
php vendor/bin/phpunit --configuration phpunit.xml.dist tests/Feature/NajmHoda
```

The hardening branch has two permanent blocking workflows:

- **Najm Hoda Hardening CI** — clean Composer install, Composer validation, blocking production advisory audit, migrations, user-import boundary tests, and the full Najm Hoda regression suite.
- **Najm Hoda Production Readiness Gate** — clean database/environment bootstrap, two controlled GameDay dry-run cycles, evidence capture, and `najm-hoda:production-readiness --window=24 --strict`.

Deployment remains restricted to `main`.

### Verified CI evidence

On 2026-08-07, the strict readiness gate completed successfully with:

- decision: `GO`;
- blockers: `0`;
- warnings: `0`;
- governance: `OK`;
- drift: `OK`;
- runbooks: `OK`;
- approvals: `OK` (`pending=0`, `overdue=0`);
- GameDay: `2/2` cycles passed, pass rate `1.0`;
- evidence: `6` audit traces and `44` runtime events;
- rollback plan: ready.

The same branch state also passed the full hardening CI, including the Composer security advisory gate, migrations, boundary tests, and Najm Hoda regression tests.

This proves the software-level release gate in a clean CI environment. It does **not** replace target-server verification.

## 2. Production readiness command

Run on the target environment:

```bash
php artisan najm-hoda:production-readiness --window=24
```

Machine-readable form:

```bash
php artisan najm-hoda:production-readiness --window=24 --json
```

Strict release gate (only GO exits successfully):

```bash
php artisan najm-hoda:production-readiness --window=24 --strict
```

The command evaluates the existing `NajmHodaProductionReadinessService`, including:

- governance KPI breaches/warnings;
- decision/policy drift;
- active rollback runbooks;
- pending and overdue approvals;
- recent GameDay cycles and pass rate;
- compliance/evidence integrity and event/audit volume.

## 3. Environment checks that GitHub cannot prove

These must be verified on the actual server:

- `APP_ENV=production`
- `APP_DEBUG=false`
- Najm Hoda is enabled intentionally
- Najm Hoda mock mode is disabled
- real AI provider credentials are present and valid
- database connection succeeds
- migrations are current
- scheduler cron is actually running
- queue workers are actually running where required
- cache/Redis store is healthy and persistent enough for runtime state
- runtime event storage is receiving events
- kill switch / pause states are known
- approval backlog is operationally monitored
- `NAJM_HODA_PERMISSIONING_V2_ENFORCE_APPLY=true` for any environment where apply is allowed

Never print secrets while collecting this evidence.

## 4. Decision policy

### GO

All automated readiness checks pass; CI tests pass; production environment checks are verified; supervised apply paths have resource authorization, delegation, safety and approval coverage.

### CONDITIONAL GO

Suitable for supervised/propose operation while warnings remain. Apply autonomy stays disabled or tightly scoped.

### NO-GO

Any readiness blocker, failed Najm Hoda tests, unverified scheduler/queue state for required workflows, missing evidence/runbooks, security blocker, or failed production dependency prevents autonomous apply.

## 5. Current hardening-branch assessment

The branch now has **verified CI-level GO**: both the hardening test/security workflow and the strict production-readiness workflow pass on a clean environment, with zero readiness blockers and zero warnings.

This is sufficient evidence that the branch is software-release ready for the next deployment-validation stage. It is **not yet a claim of full production autonomy readiness**, because GitHub Actions cannot prove the live server's scheduler, queue workers, persistent cache, provider credentials, production configuration, or real operational evidence stream.

Therefore:

- **software / CI release posture: GO**;
- **full live production autonomy: pending target-server verification**.

## 6. Next evidence to collect on the target server

1. Confirm production config values without exposing secrets.
2. Run migrations/status checks and verify database connectivity.
3. Capture `php artisan schedule:list` and verify the system cron that invokes Laravel scheduler.
4. Verify queue worker/process manager state.
5. Verify persistent cache/Redis and runtime event storage.
6. Run the required controlled GameDay cycles in the approved production/staging procedure.
7. Run `najm-hoda:production-readiness --window=24 --json` and then `--strict` on the target environment.
8. Preserve the final evidence hash and GO/NO-GO decision before any autonomy expansion.
