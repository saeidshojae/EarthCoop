# Najm Hoda Production Readiness Runbook

Branch: `agent/najm-hoda-hardening`

## Purpose

This runbook turns the existing Najm Hoda governance/readiness services into an operational release gate.

## 1. GitHub / CI checks

Required before any merge or production autonomy change:

```bash
composer validate --no-check-publish
composer audit --no-dev
php artisan test tests/Feature/NajmHoda
```

The hardening branch CI is configured to run the Najm Hoda feature suite before deployment. Deployment remains restricted to `main`.

Current observation: there has not yet been a GitHub Actions run for `agent/najm-hoda-hardening`; therefore CI behavior is not yet empirically proven.

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
- recent Game Day cycles and pass rate;
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

## 4. Decision policy

### GO

All automated readiness checks pass; CI tests pass; production environment checks are verified; supervised apply paths have resource authorization, delegation, safety and approval coverage.

### CONDITIONAL GO

Suitable for supervised/propose operation while warnings remain. Apply autonomy stays disabled or tightly scoped.

### NO-GO

Any readiness blocker, failed Najm Hoda tests, unverified scheduler/queue state for required workflows, missing evidence/runbooks, security blocker, or failed production dependency prevents autonomous apply.

## 5. Current hardening-branch assessment

Code-level posture has improved substantially, but the branch has not yet been executed by GitHub Actions and no production-server snapshot has been collected. Therefore the present evidence supports **CONDITIONAL GO for continued hardening/testing only**, not a GO for full production autonomy.

## 6. Next evidence to collect

1. Run the branch workflow and inspect test failures.
2. Run `najm-hoda:production-readiness --window=24 --json` on the target server.
3. Capture `php artisan schedule:list` and verify the system cron that invokes Laravel scheduler.
4. Verify queue worker/process manager state.
5. Verify production config values without exposing secrets.
6. Run at least the required Game Day cycles and preserve their evidence.
7. Re-run readiness and compare the final decision before any autonomy expansion.
