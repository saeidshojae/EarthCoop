# Najm Bahar Production Deployment Readiness

This checklist is the operational contract for freezing and deploying the Najm Bahar economic-system hardening stack. It does not authorize automatic repair or monetary retry.

## 1. Freeze prerequisites

A Najm Bahar production-hardening head is freeze-eligible only when all of the following are true:

- Economic Production Hardening CI is green on the exact candidate commit.
- Release D cleanup, Release C canonical adapters, Release B Governance, and Release A monetary regression gates are green on that same commit.
- `php artisan najm-bahar:production-readiness --json` exits with code `0` on a production-like database snapshot after all candidate migrations are applied.
- `account_failures`, `ledger_failures`, `idempotency_failures`, `recovery_failures`, and `operational_failures` are all empty.
- No unresolved historical duplicate idempotency key is present.
- No non-completed transaction has ledger effects.
- No overdue pending scheduled transfer exists.
- No failed scheduled transfer remains without explicit operator review and disposition.
- No scheduled transaction references a missing placeholder transaction.
- The candidate commit is not changed after the final green run. Any code, migration, workflow, or financial-policy change invalidates the freeze and requires a new full hardening run.

## 2. Pre-deployment checklist

Before applying the candidate to production:

1. Record the exact commit SHA and PR number being deployed.
2. Take and verify a recoverable database backup/snapshot before financial migrations.
3. Capture the pre-deploy output of `php artisan najm-bahar:production-readiness --json` and retain it with deployment records.
4. Review every readiness failure. Do not bypass or suppress a financial failure to proceed with deployment.
5. Confirm scheduled financial execution is not actively mutating records while the database migration window is open, using the environment's normal deployment/maintenance controls.
6. Confirm application and queue/worker processes will all run the same release after deployment; do not leave mixed old/new financial executors active.

## 3. Deployment sequence

1. Deploy the exact frozen application commit.
2. Run database migrations using the deployment environment's normal forced/non-interactive mechanism.
3. Restart or roll application workers so all financial executors use the new code.
4. Run `php artisan najm-bahar:production-readiness --json` immediately after migrations and worker convergence.
5. Do not resume normal monetary execution if readiness exits non-zero.

## 4. Post-deployment acceptance

Production acceptance requires all of the following:

- Readiness exits `0` after deployment.
- No account/sub-account invariant drift is reported.
- All completed transactions audited by readiness have valid canonical ledger shape and endpoints.
- No duplicate idempotency key is reported.
- No pending/failed transaction carries unexpected ledger effects.
- No overdue or failed scheduled operation remains unexplained.
- A small, explicitly authorized smoke transaction can be traced through transaction, ledger, and account state without manual balance correction.

The smoke transaction must use an existing canonical financial path. Do not create a special production-only bypass for smoke testing.

## 5. Failure and rollback rules

- Do not repair financial balances with ad-hoc SQL or direct model mutation during deployment recovery.
- If readiness fails after deployment, stop new monetary execution before investigating.
- Preserve the failing readiness JSON and relevant transaction/ledger identifiers before any recovery action.
- Prefer correcting the faulty migration/code and re-running the full hardening gate when forward recovery is safe.
- If rollback is required, roll application code and database state back according to the verified deployment backup/restore procedure; do not attempt a partial manual financial rollback that leaves code and schema on different release contracts.
- A replay/retry is allowed only through an existing canonical idempotent executor. Never manually duplicate a completed financial event.

## 6. Freeze definition

Production Hardening is considered frozen when:

1. The exact final head has a fully green Economic Production Hardening CI run.
2. The production-readiness command is clean on the migrated candidate database.
3. This checklist has no unresolved blocker.
4. PR scope contains no additional monetary behavior changes after the green head.
5. The head SHA is recorded as the release candidate and treated as immutable until deployment approval.

Freeze means the implementation is ready for controlled deployment review. It does not by itself merge the stacked PRs to `main`, deploy production, or authorize bypassing normal operational approval.
