# C13 Production HARD STOP Checkpoint — 2026-09-12

## Purpose

This document is the canonical handoff/checkpoint for the EarthCoop Global Location/Governance C13 rollout after Production preparation reached a verified HARD STOP. It is intended to let a new chat/session resume safely without reconstructing state from conversation history.

## Repository / release identity

- Repository: `saeidshojae/EarthCoop`
- Current `main`: `cae358c4b01dabfd45cd588f82bd5f2c1d657956`
- Merge commit: PR #107 — `C13 explicit reference governance topology rollout hotfix`
- Validated PR head: `b5583b1f210a2b834714b4eb6f0ea5186ec486a8`
- Git tree for both validated head and deployed merge commit: `82ab841988ea51e3dc3a9a2281cd5ba2f3a0d339`
- Full Validation: run #2471, run ID `34653544458`, SUCCESS on `b5583b1f210a2b834714b4eb6f0ea5186ec486a8`
- Production FTP Deploy: run #35, run ID `34656118434`, SUCCESS on `cae358c4b01dabfd45cd588f82bd5f2c1d657956`
- FTP deployment gates passed: Najm Hoda Safety Gate, Najm Hoda Strict Production Readiness, Deploy via FTP, and `Sync files to cPanel via FTP`.

The validated PR head and deployed merge commit have the same Git tree, so the deployed application content is the same content that passed Full Validation.

## Production preparation evidence completed

### Additive migrations

All intended C13 additive migrations were applied successfully. No `migrate:fresh`, `migrate:reset`, `migrate:rollback`, truncate, or broad destructive reset was used.

### Canonical bootstrap

`LocationGovernanceBootstrapSeeder` completed successfully in Production after the schema-version hotfix (`v1`).

### Iran reference geography

First Production dry-run: `create=11, update=0, deactivate=0, conflict=0, unchanged=0`.
Production apply: `create=11, update=0, deactivate=0, conflict=0, unchanged=0`.
Post-apply idempotency dry-run: `create=0, update=0, deactivate=0, conflict=0, unchanged=11`.

### Explicit Governance topology

PR #107 added an explicit, versioned Governance topology importer rather than inferring Governance from Location types or inserting manual readiness-only rows.

First Production topology dry-run: `create=5, update=0, conflict=0, unchanged=0`.
Production topology apply: `create=5, update=0, conflict=0, unchanged=0`.
Post-apply idempotency dry-run: `create=0, update=0, conflict=0, unchanged=5`.

### Production readiness

Final `location-governance:readiness` reached all PASS checks and `READY`, exit code `0`, including required migrations, target schema, reference import/status/conflicts, governance mappings, rollout flags, proposal fatal conflicts, validation SHA, and UAT evidence.

Production evidence variables include:

```text
LOCATION_GOVERNANCE_VALIDATION_SHA=cae358c4b01dabfd45cd588f82bd5f2c1d657956
LOCATION_GOVERNANCE_TARGET_COUNTRY=IR
LOCATION_GOVERNANCE_TARGET_SCHEMA=ir-reference-v1
LOCATION_GOVERNANCE_TARGET_DATASET_SOURCE=earthcoop-reference
LOCATION_GOVERNANCE_TARGET_DATASET_VERSION=v1
```

`LOCATION_GOVERNANCE_UAT_EVIDENCE` records Full Validation #2471 / run `34653544458`, the validated head, identical Git tree, deployed merge SHA, and FTP Deploy #35 / run `34656118434`.

## Current Production rollout flags — MUST remain OFF at this checkpoint

```text
LOCATION_GOVERNANCE_RUNTIME_ENABLED=false
LOCATION_GOVERNANCE_REGISTRATION_ENABLED=false
LOCATION_GOVERNANCE_GROUPS_ENABLED=false
LOCATION_GOVERNANCE_ELECTIONS_ENABLED=false
LOCATION_GOVERNANCE_PROJECTS_ENABLED=false
```

No canonical runtime flag has been activated yet.

## Temporary Deployment Console status

`DEPLOYMENT_CONSOLE_ENABLED=false` and `/admin/deployment-console` returned `404 NOT FOUND` after disablement.

Security note: the temporary deployment-console secret appeared in a screenshot. Do not preserve or reuse it. Before any future console re-enable, remove/rotate `DEPLOYMENT_CONSOLE_SECRET` and generate a fresh high-entropy value. Never paste the new secret into chat or screenshots.

## Fresh Production backup + isolated restore rehearsal — PASS

The blocking pre-activation backup/restore gate was completed after C13 reached READY.

### Fresh backup

- Source database: `btboeapy_earthcoop` (Production)
- Backup mechanism: cPanel Backup Wizard → partial MySQL database backup
- Downloaded artifact: `btboeapy_earthcoop.sql.gz`
- Browser-reported size: approximately 2.7 MB
- Provider checksum/snapshot ID: not available/recorded; do not invent one.

### Isolated restore target

- A separate non-Production database was created: `btboeapy_restore_test`.
- Existing MySQL user `btboeapy_AdminEarthcoop` was granted privileges on the isolated restore database; no Production database privileges were removed.
- The fresh `btboeapy_earthcoop.sql.gz` backup was imported into `btboeapy_restore_test` using phpMyAdmin.
- phpMyAdmin reported: `Import has been successfully finished. 2329 queries executed.`

### Restored data verification

Read-only verification against `btboeapy_restore_test` confirmed representative critical data:

```text
users=6
groups=256
elections=1
najm_accounts=264
najm_transactions=11
locations=11
location_schemas=1
governance_areas=5
governance_area_locations=5
location/governance-related migrations=16
```

The Governance topology restored exactly five Governance Areas and five canonical mappings, matching the verified Production topology apply/idempotency evidence. The restore target also contains users, groups, elections, Najm Bahar accounts/transactions, canonical locations/schema, and Location/Governance migration history.

Result: **fresh backup + isolated restore rehearsal gate = PASS**.

Important: `btboeapy_restore_test` is a temporary rehearsal database. Do not point the EarthCoop Production `.env` at it. It may be removed later only after the activation evidence/checkpoint no longer needs the live rehearsal target and with an explicit cleanup checkpoint.

## Next official phase

The next phase is staged canonical activation, in this order only:

1. Stage A — `runtime_enabled`
2. Stage B — `registration_enabled`
3. Stage C — `groups_enabled`
4. Stage D — `elections_enabled`
5. Stage E — `projects_enabled`
6. Post-cutover observation/evidence
7. HARD STOP before C14 legacy retirement

The backup + isolated restore rehearsal prerequisite is now satisfied and recorded. Each Production flag change remains an independent authorization boundary. Never enable all flags together.

If a smoke check fails at any stage: STOP immediately; revert only the just-enabled flag to `false`; reload/clear configuration using the approved hosting procedure; verify legacy/fallback behavior; investigate on an isolated branch with TDD; do not continue to the next flag.

## Non-negotiable safety constraints

- Never run `migrate:fresh`, `migrate:reset`, `migrate:rollback`, truncate, destructive clean-slate, or broad DROP on Production.
- Do not remove legacy geography in C13.
- C14 retirement is separate and requires new explicit approval.
- Do not activate any Location/Governance rollout flag without an explicit owner checkpoint for that stage.
- Do not re-enable the Deployment Console with the old secret.
- Do not treat CI as a substitute for Production smoke evidence.

## Resume prompt for a new chat

> Continue EarthCoop Global Location/Governance from `docs/location-governance/2026-09-12-c13-production-hard-stop-checkpoint.md` and `docs/superpowers/plans/2026-09-12-location-governance-staged-activation.md`. C13 Production preparation is READY; the fresh Production backup and isolated restore rehearsal gate is PASS; `main`/deployed SHA is `cae358c4b01dabfd45cd588f82bd5f2c1d657956`; all five rollout flags remain OFF; Deployment Console is disabled. The next authorized boundary is Stage A only (`LOCATION_GOVERNANCE_RUNTIME_ENABLED=true`) after reviewing its exact smoke/rollback procedure. Do not mutate `main`, Production data, or any later rollout flag without the plan's explicit checkpoints and owner authorization. C14 is prohibited.