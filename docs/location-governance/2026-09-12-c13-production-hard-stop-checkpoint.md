# C13 Production HARD STOP Checkpoint — 2026-09-12

## Purpose

Canonical handoff/checkpoint for EarthCoop Global Location/Governance C13 rollout. This file is intended to let a new chat/session resume safely without reconstructing state from conversation history.

## Repository / release identity

- Repository: `saeidshojae/EarthCoop`
- Current `main` / deployed release: `cae358c4b01dabfd45cd588f82bd5f2c1d657956`
- PR #107 validated head: `b5583b1f210a2b834714b4eb6f0ea5186ec486a8`
- Git tree shared by validated head and deployed merge commit: `82ab841988ea51e3dc3a9a2281cd5ba2f3a0d339`
- Full Validation #2471 / run `34653544458`: SUCCESS
- FTP Deploy #35 / run `34656118434`: SUCCESS
- FTP gates passed: Najm Hoda Safety Gate, Najm Hoda Strict Production Readiness, Deploy via FTP, `Sync files to cPanel via FTP`.

## C13 preparation evidence

### Additive migrations

All intended C13 additive migrations applied successfully. No `migrate:fresh`, `migrate:reset`, `migrate:rollback`, truncate, or destructive clean-slate was used.

### Canonical bootstrap

`LocationGovernanceBootstrapSeeder` completed successfully after the schema-version hotfix (`v1`).

### Iran reference geography

- pre-apply dry-run: `create=11, update=0, deactivate=0, conflict=0, unchanged=0`
- apply: `create=11, update=0, deactivate=0, conflict=0, unchanged=0`
- post-apply dry-run: `create=0, update=0, deactivate=0, conflict=0, unchanged=11`

### Explicit Governance topology

- pre-apply dry-run: `create=5, update=0, conflict=0, unchanged=0`
- apply: `create=5, update=0, conflict=0, unchanged=0`
- post-apply dry-run: `create=0, update=0, conflict=0, unchanged=5`

### Production readiness

Final `location-governance:readiness`: all checks PASS, `READY`, exit code `0`.

Evidence variables:

```text
LOCATION_GOVERNANCE_VALIDATION_SHA=cae358c4b01dabfd45cd588f82bd5f2c1d657956
LOCATION_GOVERNANCE_TARGET_COUNTRY=IR
LOCATION_GOVERNANCE_TARGET_SCHEMA=ir-reference-v1
LOCATION_GOVERNANCE_TARGET_DATASET_SOURCE=earthcoop-reference
LOCATION_GOVERNANCE_TARGET_DATASET_VERSION=v1
```

`LOCATION_GOVERNANCE_UAT_EVIDENCE` records Full Validation #2471 / run `34653544458`, validated head, identical Git tree, deployed merge SHA, and FTP Deploy #35 / run `34656118434`.

## Fresh backup + isolated restore rehearsal — PASS

- Production DB: `btboeapy_earthcoop`
- Backup: cPanel Backup Wizard → MySQL partial backup
- Artifact: `btboeapy_earthcoop.sql.gz`
- Browser-reported size: ~2.7 MB
- Provider checksum/snapshot ID: unavailable/not recorded
- Isolated restore DB: `btboeapy_restore_test`
- phpMyAdmin import: SUCCESS, `2329 queries executed`

Read-only restored-data verification:

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

Result: **fresh backup + isolated restore rehearsal = PASS**.

`btboeapy_restore_test` is temporary and must never be used as Production DB. Cleanup requires its own explicit checkpoint later.

## Temporary Deployment Console

```text
DEPLOYMENT_CONSOLE_ENABLED=false
```

`/admin/deployment-console` returned `404 NOT FOUND` after disablement.

Security note: the old temporary deployment-console secret appeared in a screenshot. Never reuse it. Any future console re-enable requires a new high-entropy secret that must not be pasted into chat/screenshots.

## Stage A — canonical runtime — PASS

Owner explicitly authorized Stage A. Only this Production flag was changed:

```text
LOCATION_GOVERNANCE_RUNTIME_ENABLED=true
```

Other consumer flags remained disabled:

```text
LOCATION_GOVERNANCE_REGISTRATION_ENABLED=false
LOCATION_GOVERNANCE_GROUPS_ENABLED=false
LOCATION_GOVERNANCE_ELECTIONS_ENABLED=false
LOCATION_GOVERNANCE_PROJECTS_ENABLED=false
```

Deployment Console remained disabled.

### Stage A smoke evidence

- authenticated session survived flag activation = PASS
- `/home` dashboard rendered successfully with real group counts = PASS
- `/profile/edit` rendered successfully = PASS
- because `registration_enabled=false`, `/profile/edit` correctly remained on legacy profile/location UI = EXPECTED/PASS
- no observed 500, blank page, or redirect loop = PASS
- canonical root endpoint without country returned `{"data":[]}` instead of 404, confirming runtime flag is active = PASS
- canonical root endpoint with `?country=IR` returned active country node:
  - `id=1`
  - `type_key=country`
  - label = Iran
  - `is_residence_endpoint=false`
  - `has_children=true`
  - `status=active`
- canonical children endpoint `/location/options/1/children` returned active child node:
  - `id=2`
  - `type_key=province`
  - `is_residence_endpoint=false`
  - `has_children=true`
  - `status=active`

Result: **Stage A canonical runtime/read-side = PASS**.

No rollback was required.

## Current Production flag matrix

```text
LOCATION_GOVERNANCE_RUNTIME_ENABLED=true
LOCATION_GOVERNANCE_REGISTRATION_ENABLED=false
LOCATION_GOVERNANCE_GROUPS_ENABLED=false
LOCATION_GOVERNANCE_ELECTIONS_ENABLED=false
LOCATION_GOVERNANCE_PROJECTS_ENABLED=false
DEPLOYMENT_CONSOLE_ENABLED=false
```

## Next official boundary

Next stage is **Stage B — registration/profile**, and it is NOT yet authorized.

Only after explicit owner approval may this one additional flag change:

```text
LOCATION_GOVERNANCE_REGISTRATION_ENABLED=true
```

Before Stage B, keep runtime healthy and preserve all other flags as-is. Stage B smoke must cover schema-driven location selection, urban and rural branches, Primary Residence create/update, profile completion without requiring legacy Address, and geolocation assist not overwriting manual residence selection.

If Stage B fails, immediately revert only:

```text
LOCATION_GOVERNANCE_REGISTRATION_ENABLED=false
```

Keep `runtime_enabled=true`, reload configuration safely, verify legacy profile/registration behavior returns, capture evidence, and STOP. Do not continue to Groups.

## Remaining staged order

1. Stage B — `registration_enabled`
2. Stage C — `groups_enabled`
3. Stage D — `elections_enabled`
4. Stage E — `projects_enabled`
5. Post-cutover observation/evidence
6. HARD STOP before C14 legacy retirement

Each Production flag change is an independent authorization boundary. Never enable all flags together.

## Non-negotiable safety constraints

- Never run `migrate:fresh`, `migrate:reset`, `migrate:rollback`, truncate, destructive clean-slate, or broad DROP on Production.
- Do not remove legacy geography in C13.
- C14 retirement is separate and requires new explicit approval.
- Do not activate another Location/Governance flag without explicit owner approval for that stage.
- Do not re-enable Deployment Console with the old secret.
- Do not treat CI as a substitute for Production smoke evidence.
- If a code defect appears, fix on a new isolated branch using TDD; do not patch Production manually.

## Resume prompt for a new chat

> Continue EarthCoop Global Location/Governance from `docs/location-governance/2026-09-12-c13-production-hard-stop-checkpoint.md` and `docs/superpowers/plans/2026-09-12-location-governance-staged-activation.md`. C13 preparation is READY; fresh backup + isolated restore rehearsal is PASS; deployed/main SHA is `cae358c4b01dabfd45cd588f82bd5f2c1d657956`; Stage A runtime is PASS and `LOCATION_GOVERNANCE_RUNTIME_ENABLED=true`; registration/groups/elections/projects remain false; Deployment Console remains disabled. The next authorization boundary is Stage B only (`LOCATION_GOVERNANCE_REGISTRATION_ENABLED=true`). Do not mutate main, Production data, or later flags without explicit checkpoint approval. C14 is prohibited.