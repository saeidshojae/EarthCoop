# Location / Governance Production Cutover Runbook

> **HARD STOP / authorization boundary**
>
> This document is an operator procedure, not authorization to execute it. Do not run any Production-changing command, change any Production feature flag, or apply the reference dataset until the owner explicitly approves the exact Production action after reviewing the final C13 candidate and evidence.

## 1. Scope and invariants

This cutover activates the additive canonical `Location -> Governance -> Membership` architecture while preserving the legacy geography as rollback scaffolding.

The initial cutover MUST NOT:

- run `migrate:fresh`, truncate, drop, or rebuild Production tables;
- remove legacy geography tables, columns, foreign keys, Address data, or legacy runtime code;
- execute a destructive cleanup migration;
- allow Najm Hoda to autonomously approve/merge/reject sensitive location proposals;
- turn all rollout flags on in one step;
- proceed after a failed readiness or smoke check.

C14 legacy retirement is a separate hard stop and requires a second explicit approval.

## 2. Canonical release target

Before the maintenance window, record these immutable values in the execution record:

```text
APPLICATION_SHA=<exact approved C13 SHA>
PREVIOUS_APPLICATION_SHA=<currently deployed SHA>
TARGET_COUNTRY=IR
TARGET_SCHEMA=ir-reference-v1
TARGET_DATASET_SOURCE=earthcoop-reference
TARGET_DATASET_VERSION=v1
FULL_VALIDATION_RUN=<exact successful run ID>
FULL_VALIDATION_JOB=<exact successful job ID>
UAT_EVIDENCE=<approved UAT evidence reference>
```

The repository reference dataset currently lives at `database/reference/ir/v1`. Geography import evidence uses source `earthcoop-reference` with dataset version `v1`; the explicit Governance topology is separately versioned in the same reviewed dataset directory and must not be inferred from government geography.

Do not substitute a different dataset/version during the window. A new dataset version requires its own review and readiness evidence.

## 3. Required pre-window evidence

All items below are blocking:

- final C13 candidate SHA is immutable and reviewed;
- Full Validation is green on that exact SHA, including the blocking `Regression — Location / Governance` gate and all mature EarthCoop gates;
- UAT evidence is attached to the candidate;
- current Production application SHA is captured;
- current Production database identity and backup destination are captured;
- a fresh, restorable Production backup exists;
- restore rehearsal of that backup has succeeded on an isolated clone/non-Production database;
- restore rehearsal includes integrity checks and records timestamps/checksums or provider snapshot IDs;
- operators know how to revert the deployed application SHA;
- operators know how to set each Location/Governance rollout flag independently;
- maintenance window and responsible operator are recorded.

If backup/restore evidence is absent, **do not begin cutover**.

## 4. Backup and restore rehearsal

Use the hosting/database provider's supported consistent backup mechanism. The exact provider command must be recorded in the execution record before approval; do not invent or substitute a command during the window.

Minimum backup evidence:

```text
BACKUP_STARTED_AT=
BACKUP_COMPLETED_AT=
BACKUP_ID_OR_PATH=
BACKUP_SIZE=
BACKUP_CHECKSUM_OR_PROVIDER_SNAPSHOT_ID=
RESTORE_REHEARSAL_TARGET=
RESTORE_REHEARSAL_STARTED_AT=
RESTORE_REHEARSAL_COMPLETED_AT=
RESTORE_REHEARSAL_RESULT=PASS|FAIL
RESTORE_REHEARSAL_NOTES=
```

The rehearsal target MUST be isolated from Production. Never restore over Production merely to prove that restore works.

Restore rehearsal acceptance checks must include at least:

- database opens and expected tables are queryable;
- user/account counts are plausible against the captured source snapshot;
- critical Najm Bahar ledger/account tables are queryable;
- Groups, Elections, Governance and Location tables are queryable;
- no restore errors remain unresolved.

Any failed restore rehearsal blocks cutover.

## 5. Enter maintenance window

1. Announce/start the approved maintenance window.
2. Prevent competing deploys and schema-changing jobs.
3. Capture the currently deployed application SHA again and compare it with `PREVIOUS_APPLICATION_SHA`.
4. Capture a final database backup/checkpoint if the approved provider procedure requires one at window start.
5. Confirm the five rollout flags are known booleans and record their current values.

Expected flag names:

```text
LOCATION_GOVERNANCE_RUNTIME_ENABLED
LOCATION_GOVERNANCE_REGISTRATION_ENABLED
LOCATION_GOVERNANCE_GROUPS_ENABLED
LOCATION_GOVERNANCE_ELECTIONS_ENABLED
LOCATION_GOVERNANCE_PROJECTS_ENABLED
```

Do not proceed if the deployed SHA or database changed unexpectedly after evidence was approved.

## 6. Deploy exact approved application SHA

Deploy the exact approved `APPLICATION_SHA`. Do not deploy a moving branch tip.

After deployment, verify the application reports/runs from the intended SHA and that Laravel boots successfully before changing data or flags.

## 7. Additive migrations only

After explicit Production authorization, run the normal additive migration path used by this deployment environment:

```bash
php artisan migrate --force
```

Forbidden in Production:

```bash
php artisan migrate:fresh
php artisan migrate:reset
php artisan migrate:rollback
```

Immediately after migration:

```bash
php artisan migrate:status
php artisan location-governance:readiness
```

At this point readiness may still be `NOT READY` if bootstrap/reference-import/topology evidence is not yet present. That is expected only if the approved procedure has not reached those steps. Any unexpected migration failure triggers rollback/stop; do not improvise schema repair during the window.

## 8. Bootstrap canonical schema/types/policies

Only after explicit Production authorization, seed the small idempotent Location/Governance bootstrap:

```bash
php artisan db:seed --class=LocationGovernanceBootstrapSeeder --force
```

This bootstrap is intended for schemas/types/dimensions/policies only. It must not be treated as the real reference geography import and it does not create Governance Areas or Location-to-Governance mappings.

Run boot/read-only checks before proceeding.

## 9. Reference geography dry-run

First run the importer without writes:

```bash
php artisan location:reference-import IR --dataset-version=v1 --dry-run
```

Record the reported counts:

```text
create=
update=
deactivate=
conflict=
unchanged=
```

Blocking rules:

- any unexpected mass update/deactivation stops the cutover;
- any unresolved conflict stops the cutover;
- dataset/version/source must match `IR / v1 / earthcoop-reference`;
- operator must compare the dry-run summary with the reviewed dataset expectations.

## 10. Reference geography apply

Only if the dry-run is accepted and the exact write has explicit authorization:

```bash
php artisan location:reference-import IR --dataset-version=v1 --apply
```

Record the resulting import audit row and counts. Do not manually edit audit evidence to force readiness.

Re-run the geography dry-run after apply:

```bash
php artisan location:reference-import IR --dataset-version=v1 --dry-run
```

Require an idempotent result (`create=0`, `update=0`, `deactivate=0`, `conflict=0`) before continuing.

## 10A. Explicit Governance topology dry-run and apply

Governance topology is intentionally independent from government geography. Do not generate Governance Areas merely by mirroring Location types and do not insert manual mappings solely to satisfy readiness.

First run the reviewed topology without writes:

```bash
php artisan location-governance:reference-topology IR --dataset-version=v1 --dry-run
```

Record:

```text
create=
update=
conflict=
unchanged=
```

Any conflict is a STOP condition. Compare the proposed Governance Areas, their parentage, ranks/types and Location mappings with the reviewed versioned topology dataset.

Only after the dry-run is accepted and the exact write has separate Production authorization:

```bash
php artisan location-governance:reference-topology IR --dataset-version=v1 --apply
```

In the browser Deployment Console the corresponding write action is `topology_apply` and requires the exact secondary confirmation phrase:

```text
APPLY-GOV-IR
```

The importer may create/update only Governance Areas explicitly owned by the reviewed reference-topology dataset. An existing key not owned by that dataset is a conflict, not permission to overwrite unrelated governance history.

After apply, re-run:

```bash
php artisan location-governance:reference-topology IR --dataset-version=v1 --dry-run
```

Require `create=0`, `update=0`, `conflict=0` with the expected areas reported as unchanged before readiness.

Then provide required release evidence through the deployment environment/config for the exact approved candidate:

```text
LOCATION_GOVERNANCE_VALIDATION_SHA=<exact approved 40-char SHA>
LOCATION_GOVERNANCE_UAT_EVIDENCE=<exact approved evidence reference>
LOCATION_GOVERNANCE_TARGET_COUNTRY=IR
LOCATION_GOVERNANCE_TARGET_SCHEMA=ir-reference-v1
LOCATION_GOVERNANCE_TARGET_DATASET_SOURCE=earthcoop-reference
LOCATION_GOVERNANCE_TARGET_DATASET_VERSION=v1
```

Run:

```bash
php artisan location-governance:readiness
```

The command is read-only and fail-closed. It must report all checks as passing before any canonical runtime flag is enabled. Do not proceed on `NOT READY`.

## 11. Staged rollout — one flag boundary at a time

After every step below, clear/reload application configuration using the deployment platform's standard procedure and run the smoke checks for that stage. Never enable the next stage until the current stage is accepted.

### Stage A — canonical runtime

Set only:

```text
LOCATION_GOVERNANCE_RUNTIME_ENABLED=true
```

Keep registration, groups, elections and projects at their previous/disabled state unless already explicitly approved for this stage.

Smoke checks:

- authentication/session boot;
- Home/dashboard for a canonical test user;
- canonical Location tree read;
- Primary Residence read path;
- legacy user without canonical residence still follows the intended fallback behavior;
- no 5xx spike or critical queue/log anomaly.

### Stage B — registration/profile

Set:

```text
LOCATION_GOVERNANCE_REGISTRATION_ENABLED=true
```

Smoke checks with test users only:

- registration location selection follows schema-driven country branch;
- urban and rural paths load correctly;
- Primary Residence can be created/updated according to policy;
- profile completion succeeds without requiring a legacy Address for canonical users;
- optional geolocation assist never overwrites manual residence selection.

### Stage C — groups

Set:

```text
LOCATION_GOVERNANCE_GROUPS_ENABLED=true
```

Smoke checks:

- public/profession/specialty/age/gender memberships resolve through canonical governance scope;
- no empty-group explosion;
- existing mature Group Chat/Admin flows remain healthy;
- Community creation remains on-demand according to policy.

### Stage D — elections

Set:

```text
LOCATION_GOVERNANCE_ELECTIONS_ENABLED=true
```

Smoke checks:

- formal election scope resolves through Governance Area topology;
- Primary Residence is the official spatial voting basis;
- work/study/other relationships do not create parallel official voting rights;
- Community internal elections remain distinct from formal systemic topology;
- existing election lifecycle pages/jobs boot without regression.

### Stage E — remaining spatial consumers

For the currently implemented remaining consumer flag, set:

```text
LOCATION_GOVERNANCE_PROJECTS_ENABLED=true
```

Smoke checks:

- project canonical governance scope is used where the flag applies;
- legacy fallback remains available if the flag is reverted;
- Secretariat/Polls/shared governance-scope consumers continue to resolve correctly;
- no mature subsystem gate shows a new error pattern.

If future remaining-consumer flags are added, each must get its own independent stage; never bundle them silently into Stage E.

## 12. Post-cutover verification

After all approved stages:

```bash
php artisan location-governance:readiness
```

Then execute the approved Production-safe smoke suite. Do **not** run destructive test-database commands against Production.

Capture:

- deployed SHA;
- final flag values;
- readiness output (`--json` may be archived);
- geography import run ID and counts;
- Governance topology dry-run/apply/idempotency evidence;
- smoke-test results for each stage;
- application/queue/error-log observations;
- start/end time of each stage;
- operator identity;
- any rollback or exception decision.

A green local/CI test result is not a substitute for Production smoke evidence.

## 13. Success criteria

Cutover is accepted only when:

- exact approved SHA is deployed;
- backup and isolated restore rehearsal are proven;
- additive migrations completed without unresolved errors;
- target reference geography import is completed with zero unresolved conflicts;
- explicit reference Governance topology is applied with zero unresolved conflicts and an idempotent post-apply dry-run;
- `location-governance:readiness` returns success;
- each rollout stage passed its smoke checks before the next was enabled;
- no critical regression is observed in mature subsystems;
- legacy geography remains intact and available for rollback;
- evidence has been recorded.

Otherwise follow `PRODUCTION_ROLLBACK_RUNBOOK.md`.

## 14. Explicit stop before legacy retirement

Do not drop or rewrite legacy geography after a successful C13 cutover. Observe the canonical runtime first. C14 must audit residual legacy dependencies and requires a **new, separate explicit approval** before even authoring/executing destructive retirement work.
