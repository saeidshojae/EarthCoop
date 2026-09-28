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

Owner explicitly authorized Stage A. Production changed only:

```text
LOCATION_GOVERNANCE_RUNTIME_ENABLED=true
```

Stage A smoke evidence:

- authenticated session survived flag activation = PASS
- `/home` dashboard rendered successfully = PASS
- `/profile/edit` rendered successfully = PASS
- no observed 500, blank page, or redirect loop = PASS
- canonical root endpoint with `?country=IR` returned active country node = PASS
- canonical children endpoint `/location/options/1/children` returned active province child = PASS

Result: **Stage A canonical runtime/read-side = PASS**. No rollback required.

## Stage B — canonical registration/profile — PASS on current controlled reference dataset

Owner explicitly authorized Stage B. Production changed only:

```text
LOCATION_GOVERNANCE_REGISTRATION_ENABLED=true
```

### Stage B smoke evidence

- `/profile/edit` switched from legacy location UI to canonical Primary Residence editor = PASS
- manual canonical cascade loaded successfully = PASS
- available reference path loaded through Iran → Mazandaran → Sari County → Chahardangeh Section → Chahardangeh Rural District → Chahardangeh Reference Village = PASS
- terminal village was recognized as a valid residence endpoint = PASS
- saving Primary Residence succeeded and success message rendered = PASS
- redirected `/profile/edit` displayed persisted current location `Chahardangeh Reference Village` = PASS
- no observed 500, blank page, or redirect loop during save = PASS

Result: **Stage B registration/profile path = PASS for the currently loaded reference/pilot dataset**. No rollback required.

### Observed group behavior after Stage B save

Existing group memberships/listing did not change after Primary Residence was saved. This is expected while:

```text
LOCATION_GOVERNANCE_GROUPS_ENABLED=false
```

Current `GroupService` switches to canonical MembershipEngine/GovernanceScopedGroupService resolution only when `groups_enabled=true`; otherwise legacy group resolution remains active. Therefore unchanged groups are **not a Stage B failure**. They are a mandatory Stage C verification item.

Detailed follow-up checklist:

`docs/superpowers/plans/2026-09-13-stage-b-observations-and-stage-c-followups.md`

### Observed geography coverage limitation

The current Production selector is backed by the small versioned `earthcoop-reference / v1` architecture/UAT dataset. The limited set of selectable paths is therefore a **known reference-data coverage limitation**, not a selector UI failure.

Before broad public onboarding, complete a separate reviewed/versioned geography-data rollout for full intended Iran coverage and later country-by-country global expansion. Use the importer/dry-run/apply/idempotency process; do not use ad-hoc SQL.

## Current Production flag matrix

```text
LOCATION_GOVERNANCE_RUNTIME_ENABLED=true
LOCATION_GOVERNANCE_REGISTRATION_ENABLED=true
LOCATION_GOVERNANCE_GROUPS_ENABLED=false
LOCATION_GOVERNANCE_ELECTIONS_ENABLED=false
LOCATION_GOVERNANCE_PROJECTS_ENABLED=false
DEPLOYMENT_CONSOLE_ENABLED=false
```

## Next official boundary

Next stage is **Stage C — canonical group membership resolution**, and it is NOT yet authorized.

Only after explicit owner approval may this one additional flag change:

```text
LOCATION_GOVERNANCE_GROUPS_ENABLED=true
```

Stage C must verify:

- Primary Residence drives canonical public/spatial membership resolution;
- expected profession/specialty/age/gender memberships still resolve correctly;
- obsolete legacy-location-derived exposure is not incorrectly authoritative;
- no empty-group explosion or mass meaningless materialization occurs;
- Group Chat, listing, Admin/Control Center remain healthy;
- membership behavior is deterministic/idempotent.

If Stage C fails, immediately revert only:

```text
LOCATION_GOVERNANCE_GROUPS_ENABLED=false
```

Keep Stage A+B enabled if still healthy, capture evidence, and STOP before Stage D.

## Remaining staged order

1. Stage C — `groups_enabled`
2. Stage D — `elections_enabled`
3. Stage E — `projects_enabled`
4. Post-cutover observation/evidence
5. HARD STOP before C14 legacy retirement

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

> Continue EarthCoop Global Location/Governance from `docs/location-governance/2026-09-12-c13-production-hard-stop-checkpoint.md`, `docs/superpowers/plans/2026-09-12-location-governance-staged-activation.md`, and `docs/superpowers/plans/2026-09-13-stage-b-observations-and-stage-c-followups.md`. C13 preparation is READY; fresh backup + isolated restore rehearsal is PASS; deployed/main SHA is `cae358c4b01dabfd45cd588f82bd5f2c1d657956`; Stage A runtime PASS; Stage B registration/profile PASS on controlled reference data; `runtime=true`, `registration=true`, `groups=false`, `elections=false`, `projects=false`; Deployment Console disabled. Group memberships intentionally remained legacy because `groups_enabled=false`; verify/rematerialize only in Stage C. Current geography coverage is reference/pilot-only and needs a separate complete Iran data rollout before broad public onboarding. The next authorization boundary is Stage C only (`LOCATION_GOVERNANCE_GROUPS_ENABLED=true`). C14 is prohibited.