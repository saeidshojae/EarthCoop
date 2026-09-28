# Location/Governance Staged Activation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Activate EarthCoop's canonical Location/Governance runtime in Production safely, one independent flag boundary at a time, while preserving legacy rollback scaffolding and collecting Production evidence for every stage.

**Architecture:** C13 preparation is complete and Production readiness is `READY`, but all canonical runtime flags are still OFF. Activation is operationally staged as runtime → registration/profile → groups → elections → projects. Every stage is fail-closed: fresh backup/restore evidence first, explicit owner authorization before each flag mutation, Production smoke checks immediately after, and rollback of only the current stage on failure.

**Tech Stack:** Laravel 9/PHP 8.2 runtime, MySQL Production database, cPanel `.env`, GitHub Actions validation/deploy, canonical Location/Governance services and feature flags.

**Spec:** `docs/superpowers/specs/2026-09-10-global-location-governance-architecture-design.md`

## Global Constraints

- Current Production application SHA: `cae358c4b01dabfd45cd588f82bd5f2c1d657956`.
- C13 Production preparation is `READY`; see `docs/location-governance/2026-09-12-c13-production-hard-stop-checkpoint.md`.
- All five rollout flags start `false` and must never be enabled together.
- Deployment Console starts disabled and must remain disabled unless separately approved/re-secured with a new secret.
- No `migrate:fresh`, `migrate:reset`, `migrate:rollback`, truncate, destructive reset, broad DROP, or legacy retirement in this plan.
- C14 is outside scope and remains a hard stop.
- Production database writes are not part of these activation tasks unless explicitly called out and separately approved.
- A failed smoke check blocks the next stage.
- A green CI result is not a substitute for Production smoke evidence.
- If a defect is discovered, stop activation and fix it on a new isolated branch using TDD; do not patch Production manually.
- Every Production flag mutation requires explicit owner approval at that stage.

---

## File / operational boundary map

No application code is expected to change during a healthy activation. The primary mutable surface is Production `.env` through cPanel.

Operational inputs:

- Production `.env`
  - `LOCATION_GOVERNANCE_RUNTIME_ENABLED`
  - `LOCATION_GOVERNANCE_REGISTRATION_ENABLED`
  - `LOCATION_GOVERNANCE_GROUPS_ENABLED`
  - `LOCATION_GOVERNANCE_ELECTIONS_ENABLED`
  - `LOCATION_GOVERNANCE_PROJECTS_ENABLED`
- `docs/location-governance/PRODUCTION_CUTOVER_RUNBOOK.md`
- `docs/location-governance/PRODUCTION_ROLLBACK_RUNBOOK.md`
- `docs/location-governance/2026-09-12-c13-production-hard-stop-checkpoint.md`

Evidence to capture during execution:

- backup ID/path, timestamp, size, provider snapshot/checksum where available;
- isolated restore target and PASS/FAIL evidence;
- application SHA at each activation boundary;
- exact flag values before/after each stage;
- smoke observations and screenshots/output;
- start/end time for each stage;
- rollback decisions if any.

---

### Task 1: Satisfy the blocking backup and isolated restore rehearsal gate

**Files:**
- Read: `docs/location-governance/PRODUCTION_CUTOVER_RUNBOOK.md`
- Read: `docs/location-governance/PRODUCTION_ROLLBACK_RUNBOOK.md`
- Update after evidence exists: `docs/location-governance/2026-09-12-c13-production-hard-stop-checkpoint.md` on an isolated documentation branch, not directly on `main`.

**Interfaces:**
- Consumes: current healthy Production state with all canonical flags OFF.
- Produces: auditable `BACKUP_*` and `RESTORE_REHEARSAL_*` evidence accepted for Stage A.

- [ ] **Step 1: Capture current Production identity before backup**

Record:

```text
APPLICATION_SHA=cae358c4b01dabfd45cd588f82bd5f2c1d657956
RUNTIME_ENABLED=false
REGISTRATION_ENABLED=false
GROUPS_ENABLED=false
ELECTIONS_ENABLED=false
PROJECTS_ENABLED=false
DEPLOYMENT_CONSOLE_ENABLED=false
```

Expected: values match the C13 HARD STOP checkpoint. Any mismatch is a STOP condition.

- [ ] **Step 2: Create a fresh Production database backup using the hosting provider/cPanel-supported mechanism**

Record without inventing unavailable metadata:

```text
BACKUP_STARTED_AT=
BACKUP_COMPLETED_AT=
BACKUP_ID_OR_PATH=
BACKUP_SIZE=
BACKUP_CHECKSUM_OR_PROVIDER_SNAPSHOT_ID=
```

Expected: a new backup exists and is identifiable independently of chat history.

- [ ] **Step 3: Restore that backup to an isolated non-Production database/clone**

Never restore over Production.

Record:

```text
RESTORE_REHEARSAL_TARGET=
RESTORE_REHEARSAL_STARTED_AT=
RESTORE_REHEARSAL_COMPLETED_AT=
RESTORE_REHEARSAL_RESULT=PASS|FAIL
RESTORE_REHEARSAL_NOTES=
```

Expected: restore completes without unresolved errors.

- [ ] **Step 4: Verify restored critical data families**

Acceptance checks:

```text
users/accounts queryable = PASS
Najm Bahar ledger/account tables queryable = PASS
Groups tables queryable = PASS
Elections tables queryable = PASS
Location tables queryable = PASS
Governance tables queryable = PASS
row counts plausible against source snapshot = PASS
unresolved restore errors = 0
```

Expected: all PASS. Any FAIL blocks Stage A.

- [ ] **Step 5: Record checkpoint and request explicit Stage A authorization**

Do not change any rollout flag yet.

---

### Task 2: Stage A — activate canonical runtime only

**Files:**
- Modify operationally: Production `.env`
- Read: `docs/location-governance/PRODUCTION_ROLLBACK_RUNBOOK.md`

**Interfaces:**
- Consumes: Task 1 PASS evidence and explicit owner authorization for Stage A.
- Produces: canonical runtime enabled with all consumer flags still disabled.

- [ ] **Step 1: Reconfirm pre-mutation state**

Require:

```text
LOCATION_GOVERNANCE_RUNTIME_ENABLED=false
LOCATION_GOVERNANCE_REGISTRATION_ENABLED=false
LOCATION_GOVERNANCE_GROUPS_ENABLED=false
LOCATION_GOVERNANCE_ELECTIONS_ENABLED=false
LOCATION_GOVERNANCE_PROJECTS_ENABLED=false
```

Expected: exact match.

- [ ] **Step 2: Obtain explicit owner approval for this exact Production change**

Approved change must be only:

```text
LOCATION_GOVERNANCE_RUNTIME_ENABLED=true
```

No other rollout flag may change in this step.

- [ ] **Step 3: Change only the runtime flag and save Production `.env`**

Result:

```text
runtime=true
registration=false
groups=false
elections=false
projects=false
```

- [ ] **Step 4: Reload application configuration using the hosting-approved procedure**

Do not invent a shell command if cPanel has no Terminal. If no config cache is present, refresh/reload through normal request lifecycle and verify observed behavior. If config cache is present, use only the hosting-approved cache-clear mechanism.

- [ ] **Step 5: Run Stage A Production smoke checks**

Check with safe existing/test accounts only:

```text
authentication/session boot = PASS
home/dashboard boot = PASS
canonical Location tree read = PASS
Primary Residence read path = PASS
legacy user without canonical residence fallback = PASS
critical 5xx/log/queue anomaly = NONE
```

- [ ] **Step 6: Stage A rollback rule if any smoke check fails**

Immediately set only:

```text
LOCATION_GOVERNANCE_RUNTIME_ENABLED=false
```

Reload config, confirm legacy behavior returns, capture failure evidence, and stop the plan. Do not continue to Stage B.

- [ ] **Step 7: Record Stage A evidence and request explicit Stage B authorization**

---

### Task 3: Stage B — activate canonical registration/profile

**Files:**
- Modify operationally: Production `.env`

**Interfaces:**
- Consumes: Stage A PASS and explicit owner authorization for Stage B.
- Produces: canonical schema-driven registration/profile location flow enabled.

- [ ] **Step 1: Confirm Stage A remains healthy**

Expected flags:

```text
runtime=true
registration=false
groups=false
elections=false
projects=false
```

- [ ] **Step 2: Obtain explicit approval for only this change**

```text
LOCATION_GOVERNANCE_REGISTRATION_ENABLED=true
```

- [ ] **Step 3: Save flag and reload configuration**

Expected flags:

```text
runtime=true
registration=true
groups=false
elections=false
projects=false
```

- [ ] **Step 4: Test urban registration/profile path with a test user**

Require:

```text
schema-driven country branch loads = PASS
urban hierarchy loads = PASS
Primary Residence create/update = PASS
profile completion = PASS
legacy Address not required for canonical user = PASS
```

- [ ] **Step 5: Test rural path with a test user**

Require:

```text
rural hierarchy loads = PASS
village without neighborhood path loads = PASS
Primary Residence persists correctly = PASS
```

- [ ] **Step 6: Verify geolocation assist safety**

Require:

```text
manual residence selection is never silently overwritten by geolocation assist = PASS
```

- [ ] **Step 7: Roll back Stage B on any failure**

Set only:

```text
LOCATION_GOVERNANCE_REGISTRATION_ENABLED=false
```

Keep runtime enabled only if Stage A remains healthy. Reload config, capture evidence, stop before Stage C.

- [ ] **Step 8: Record Stage B evidence and request explicit Stage C authorization**

---

### Task 4: Stage C — activate canonical group membership resolution

**Files:**
- Modify operationally: Production `.env`

**Interfaces:**
- Consumes: Stage A+B PASS and explicit Stage C authorization.
- Produces: canonical governance-scope resolution for group membership while preserving mature Group Chat/Admin behavior.

- [ ] **Step 1: Confirm expected pre-stage flags**

```text
runtime=true
registration=true
groups=false
elections=false
projects=false
```

- [ ] **Step 2: Obtain explicit approval and set only**

```text
LOCATION_GOVERNANCE_GROUPS_ENABLED=true
```

- [ ] **Step 3: Reload configuration and verify group pages boot**

Require no new 5xx.

- [ ] **Step 4: Smoke public/profession/specialty/age/gender membership resolution**

Require representative canonical users to resolve expected membership scopes.

- [ ] **Step 5: Verify no empty-group explosion**

Check that activation does not mass-create meaningless group records outside on-demand policy.

- [ ] **Step 6: Recheck mature Group Chat/Admin flows**

Require:

```text
group listing = PASS
group view/chat boot = PASS
admin/control-center boot = PASS
existing membership navigation = PASS
```

- [ ] **Step 7: Roll back Stage C on any failure**

Set only:

```text
LOCATION_GOVERNANCE_GROUPS_ENABLED=false
```

Retain earlier healthy stages, reload config, capture evidence, and stop before Stage D.

- [ ] **Step 8: Record Stage C evidence and request explicit Stage D authorization**

---

### Task 5: Stage D — activate canonical election scope

**Files:**
- Modify operationally: Production `.env`

**Interfaces:**
- Consumes: Stages A–C PASS and explicit Stage D authorization.
- Produces: formal systemic elections scoped by canonical Governance Areas and Primary Residence.

- [ ] **Step 1: Confirm expected pre-stage flags**

```text
runtime=true
registration=true
groups=true
elections=false
projects=false
```

- [ ] **Step 2: Obtain explicit approval and set only**

```text
LOCATION_GOVERNANCE_ELECTIONS_ENABLED=true
```

- [ ] **Step 3: Reload configuration and verify election pages/jobs boot**

No election lifecycle mutation is needed merely for smoke verification.

- [ ] **Step 4: Verify formal spatial election scope**

Require a canonical user's formal election scope to resolve through `GovernanceArea` topology.

- [ ] **Step 5: Verify voting-right basis**

Require:

```text
Primary Residence is official spatial voting basis = PASS
work relationship does not create parallel official spatial vote = PASS
study relationship does not create parallel official spatial vote = PASS
other relationships do not create parallel official spatial vote = PASS
```

- [ ] **Step 6: Verify Community internal elections remain distinct**

Community/internal election behavior must not be silently conflated with formal systemic topology.

- [ ] **Step 7: Roll back Stage D on any failure**

Set only:

```text
LOCATION_GOVERNANCE_ELECTIONS_ENABLED=false
```

Reload config, capture evidence, and stop before Stage E.

- [ ] **Step 8: Record Stage D evidence and request explicit Stage E authorization**

---

### Task 6: Stage E — activate canonical project/spatial consumers

**Files:**
- Modify operationally: Production `.env`

**Interfaces:**
- Consumes: Stages A–D PASS and explicit Stage E authorization.
- Produces: project canonical governance scope enabled while legacy fallback remains available through C13.

- [ ] **Step 1: Confirm expected pre-stage flags**

```text
runtime=true
registration=true
groups=true
elections=true
projects=false
```

- [ ] **Step 2: Obtain explicit approval and set only**

```text
LOCATION_GOVERNANCE_PROJECTS_ENABLED=true
```

- [ ] **Step 3: Reload configuration and verify project pages boot**

- [ ] **Step 4: Verify canonical project governance scope**

Check both group-owned and explicitly user-scoped project paths where safely observable.

- [ ] **Step 5: Verify shared spatial consumers**

Require Secretariat/Polls/shared governance-scope readers that are part of the current implementation to continue resolving correctly.

- [ ] **Step 6: Verify legacy fallback still exists**

C13 must preserve rollback scaffolding; no legacy geography table/code retirement is allowed here.

- [ ] **Step 7: Roll back Stage E on any failure**

Set only:

```text
LOCATION_GOVERNANCE_PROJECTS_ENABLED=false
```

Reload config, capture evidence, and stop.

- [ ] **Step 8: Record Stage E evidence**

---

### Task 7: Post-cutover verification and observation checkpoint

**Files:**
- Read: `docs/location-governance/PRODUCTION_CUTOVER_RUNBOOK.md`
- Read: `docs/location-governance/PRODUCTION_ROLLBACK_RUNBOOK.md`
- Update evidence documentation on an isolated branch after observations are complete.

**Interfaces:**
- Consumes: all explicitly approved activation stages that reached PASS.
- Produces: final C13 activation evidence and a new HARD STOP before any C14 work.

- [ ] **Step 1: Capture final flag matrix**

If all stages were approved and passed:

```text
runtime=true
registration=true
groups=true
elections=true
projects=true
```

If rollout intentionally stops earlier, record the actual matrix and do not infer success for later stages.

- [ ] **Step 2: Run read-only Location/Governance readiness through an approved mechanism**

If the temporary Deployment Console must be re-enabled, do so only with separate approval and a brand-new secret; otherwise use an already approved operational path. The old exposed secret must never be reused.

Expected: `READY` / exit code `0`.

- [ ] **Step 3: Observe Production health after staged activation**

Record:

```text
5xx/error pattern =
queue failures =
registration failures =
group membership anomalies =
election scope anomalies =
project scope anomalies =
```

No critical unresolved regression may remain.

- [ ] **Step 4: Record final C13 activation evidence**

Include deployed SHA, backup evidence, restore rehearsal evidence, final flags, readiness, per-stage smoke results, timestamps, operator, and any rollback.

- [ ] **Step 5: Enter mandatory HARD STOP before C14**

Do not remove or rewrite legacy geography. Do not author/execute destructive retirement work without a separate C14 audit, plan, and explicit approval.

---

## Failure branch rule

If any task exposes a software defect rather than an operational configuration issue:

1. Stop Production activation at the current stage.
2. Revert only the current flag if needed.
3. Create a new isolated branch from the currently deployed `main`.
4. Write a failing regression test reproducing the Production symptom.
5. Run CI and capture the intended RED.
6. Implement the minimal fix.
7. Run focused regression + Full Validation.
8. Review/merge/deploy only after explicit approval.
9. Resume this activation plan from the failed stage, not from the beginning unless evidence became stale.

## Chat/session handoff

If conversation context is lost, first read:

1. `docs/location-governance/2026-09-12-c13-production-hard-stop-checkpoint.md`
2. this plan
3. `docs/location-governance/PRODUCTION_CUTOVER_RUNBOOK.md`
4. `docs/location-governance/PRODUCTION_ROLLBACK_RUNBOOK.md`

Then verify repository and Production state before any mutation. Never assume a stage was completed solely because it appears checked in an old chat.
