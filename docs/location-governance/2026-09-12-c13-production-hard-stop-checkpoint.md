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
- FTP deployment gates passed:
  - Najm Hoda Safety Gate: SUCCESS
  - Najm Hoda Strict Production Readiness: SUCCESS
  - Deploy via FTP: SUCCESS
  - `Sync files to cPanel via FTP`: SUCCESS

The validated PR head and deployed merge commit have the same Git tree, so the deployed application content is the same content that passed Full Validation.

## Production preparation evidence completed

### Additive migrations

All intended C13 additive migrations were applied successfully. No `migrate:fresh`, `migrate:reset`, `migrate:rollback`, truncate, or broad destructive reset was used.

### Canonical bootstrap

`LocationGovernanceBootstrapSeeder` completed successfully in Production after the schema-version hotfix (`v1`).

### Iran reference geography

First Production dry-run:

```text
mode: dry-run
create: 11
update: 0
deactivate: 0
conflict: 0
unchanged: 0
```

Production apply:

```text
mode: apply
create: 11
update: 0
deactivate: 0
conflict: 0
unchanged: 0
```

Post-apply idempotency dry-run:

```text
mode: dry-run
create: 0
update: 0
deactivate: 0
conflict: 0
unchanged: 11
```

### Explicit Governance topology

A rollout gap was found because geography import did not create Governance Areas or `governance_area_locations` mappings. PR #107 added an explicit, versioned Governance topology importer rather than inferring Governance from Location types or inserting manual readiness-only rows.

First Production topology dry-run:

```text
mode: dry-run
create: 5
update: 0
conflict: 0
unchanged: 0
```

Production topology apply:

```text
mode: apply
create: 5
update: 0
conflict: 0
unchanged: 0
```

Post-apply idempotency dry-run:

```text
mode: dry-run
create: 0
update: 0
conflict: 0
unchanged: 5
```

### Production readiness

Final `location-governance:readiness` output reached:

```text
[PASS] required migrations
[PASS] target schema
[PASS] reference import
[PASS] reference import status
[PASS] reference import conflicts
[PASS] governance mappings
[PASS] rollout flags
[PASS] proposal fatal conflicts
[PASS] validation SHA
[PASS] UAT evidence
READY
```

Exit code: `0`.

Production evidence variables were configured as:

```text
LOCATION_GOVERNANCE_VALIDATION_SHA=cae358c4b01dabfd45cd588f82bd5f2c1d657956
LOCATION_GOVERNANCE_TARGET_COUNTRY=IR
LOCATION_GOVERNANCE_TARGET_SCHEMA=ir-reference-v1
LOCATION_GOVERNANCE_TARGET_DATASET_SOURCE=earthcoop-reference
LOCATION_GOVERNANCE_TARGET_DATASET_VERSION=v1
```

`LOCATION_GOVERNANCE_UAT_EVIDENCE` records Full Validation #2471 / run `34653544458`, the validated head, the identical Git tree, deployed merge SHA, and FTP Deploy #35 / run `34656118434`.

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

The temporary browser Deployment Console was disabled after final READY:

```text
DEPLOYMENT_CONSOLE_ENABLED=false
```

Verification: `/admin/deployment-console` returned `404 NOT FOUND` after disablement.

Security note: the temporary deployment-console secret value appeared in a screenshot during the session. Do not preserve or reuse that value. Before any future console re-enable, remove/rotate `DEPLOYMENT_CONSOLE_SECRET` and generate a fresh high-entropy value. Never paste the new secret into chat or screenshots.

## Production backup / restore evidence status

A Production backup existed before the earlier C13 write steps, but this handoff does **not** contain a verified backup ID/provider snapshot ID and does **not** contain evidence of a successful isolated restore rehearsal.

This matters because `docs/location-governance/PRODUCTION_CUTOVER_RUNBOOK.md` treats a fresh restorable backup **and isolated restore rehearsal** as blocking evidence before the canonical runtime activation/cutover stages.

Therefore:

> **DO NOT enable Stage A (`LOCATION_GOVERNANCE_RUNTIME_ENABLED=true`) until the backup/restore rehearsal gate is satisfied and recorded.**

Do not claim restore rehearsal passed unless it was actually performed on a non-Production target.

## Next official phase

The next phase is staged canonical activation, in this order only:

1. Pre-activation backup + isolated restore rehearsal gate
2. Stage A — `runtime_enabled`
3. Stage B — `registration_enabled`
4. Stage C — `groups_enabled`
5. Stage D — `elections_enabled`
6. Stage E — `projects_enabled`
7. Post-cutover observation/evidence
8. HARD STOP before C14 legacy retirement

Each Production flag change is an independent authorization boundary. Never enable all flags together.

If a smoke check fails at any stage:

- STOP immediately;
- revert only the just-enabled flag to `false`;
- reload/clear configuration using the approved hosting procedure;
- verify legacy/fallback behavior;
- investigate on an isolated branch with TDD;
- do not continue to the next flag.

## Non-negotiable safety constraints

- Never run `migrate:fresh`, `migrate:reset`, `migrate:rollback`, truncate, destructive clean-slate, or broad DROP on Production.
- Do not remove legacy geography in C13.
- C14 retirement is separate and requires new explicit approval.
- Do not activate any Location/Governance rollout flag without an explicit owner checkpoint for that stage.
- Do not re-enable the Deployment Console with the old secret.
- Do not treat CI as a substitute for Production smoke evidence.

## Resume prompt for a new chat

Use this exact handoff if a future chat starts without context:

> Continue EarthCoop Global Location/Governance from `docs/location-governance/2026-09-12-c13-production-hard-stop-checkpoint.md` and `docs/superpowers/plans/2026-09-12-location-governance-staged-activation.md`. C13 Production preparation is READY and at HARD STOP. `main`/deployed SHA is `cae358c4b01dabfd45cd588f82bd5f2c1d657956`; all five rollout flags remain OFF; Deployment Console is disabled. Before Stage A, satisfy and record the blocking fresh backup + isolated restore rehearsal evidence. Do not mutate `main`, Production data, or rollout flags without the plan's explicit checkpoints and owner authorization. C14 is prohibited.
