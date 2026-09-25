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
- restore rehearsal of that backup has succeeded on an isolated clone/non-Production database, or a hosting limitation preventing rehearsal is explicitly recorded and accepted;
- restore evidence includes integrity checks and records timestamps/checksums or provider snapshot IDs where available;
- operators know how to revert the deployed application SHA;
- operators know how to set each Location/Governance rollout flag independently;
- the Laravel configuration-cache state is known before relying on `.env` changes;
- maintenance window and responsible operator are recorded.

If backup evidence is absent, **do not begin cutover**.

## 4. Backup and restore rehearsal

Use the hosting/database provider's supported consistent backup mechanism. The exact provider procedure must be recorded in the execution record before approval; do not invent or substitute a command during the window.

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
RESTORE_REHEARSAL_RESULT=PASS|FAIL|NOT_PRACTICAL
RESTORE_REHEARSAL_NOTES=
```

The rehearsal target MUST be isolated from Production. Never restore over Production merely to prove that restore works.

Restore rehearsal acceptance checks, when practical, must include at least:

- database opens and expected tables are queryable;
- user/account counts are plausible against the captured source snapshot;
- critical Najm Bahar ledger/account tables are queryable;
- Groups, Elections, Governance and Location tables are queryable;
- no restore errors remain unresolved.

A failed restore rehearsal blocks cutover. If hosting limitations make an isolated rehearsal impractical, record that limitation honestly and require explicit acceptance of that operational risk before any write.

## 5. Enter maintenance window

1. Announce/start the approved maintenance window.
2. Prevent competing deploys and schema-changing jobs.
3. Capture the currently deployed application SHA again and compare it with `PREVIOUS_APPLICATION_SHA`.
4. Capture a final database backup/checkpoint if the approved provider procedure requires one at window start.
5. Confirm the five rollout flags are known booleans and record their current values.
6. Confirm `DEPLOYMENT_CONSOLE_ENABLED=false` until the separately approved preparation window.
7. Inspect Laravel configuration-cache state before relying on any later `.env` edit. On the current cPanel hosting model, `bootstrap/cache/config.php`, if present, can preserve stale environment-derived configuration. Follow `CPANEL_FRESH_CANONICAL_START_CHECKLIST.md`; do not delete unrelated cache files.

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

The current GitHub-to-cPanel workflow uploads application files over FTPS; it does not itself execute Production migrations, bootstrap, reference imports, topology imports, Stage C policy activation, cache clearing, or feature-flag changes, and it excludes `.env` from sync.

After deployment, verify the application reports/runs from the intended release and that Laravel boots successfully before changing data or flags.

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

The required canonical migration set includes the UI-era `2026_09_13_000001_create_pending_residence_intents_table`, because registration/profile proposal flows persist pending exact-residence intent there.

Immediately after migration:

```bash
php artisan migrate:status
php artisan location-governance:readiness
```

At this point readiness should still be `NOT READY` until bootstrap/reference-import/topology/Stage-C-policy/release evidence is present. Any unexpected migration failure triggers rollback/stop; do not improvise schema repair during the window.

## 8. Bootstrap canonical schema/types/policies

Only after explicit Production authorization, seed the small idempotent Location/Governance bootstrap:

```bash
php artisan db:seed --class=LocationGovernanceBootstrapSeeder --force
```

This bootstrap is intended for schemas/types/dimensions/policies only. It must not be treated as the real reference geography import and it does not create Governance Areas or Location-to-Governance mappings. Its systemic group-creation policies deliberately remain conservative/on-demand until the separate Stage C policy transition below.

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

## 10A.1 Iran 1404 v2 shared-database preflight — READ ONLY

Before any future v2 apply is authorized on Production, run the three fixed Deployment Console operations:

```text
iran_v1_v2_runtime_audit
iran_v2_reference_dry_run
iran_v2_topology_dry_run
```

They execute only the read-only audit/dry-run commands for the current Production database. Preserve their complete outputs as release evidence.

This checkpoint does **not** permit:
- v2 geography apply;
- v2 governance-topology apply;
- neutral settlement-catalog import;
- retirement or rewriting of v1 identities;
- enabling Iran settlement feature flags.

Any conflict or unexpected v1 dependency is a STOP condition. A separate reviewed Production write path with explicit confirmation tokens, idempotency, and rollback/fail-closed behavior is required before v2 data is mutated.

## 10B. Stage C canonical group policy transition

The bootstrap intentionally does not turn systemic group creation automatic. Before release readiness can pass, apply the dedicated reviewed idempotent transition for `public`, `profession`, `specialty`, `age`, and `gender`:

```bash
php artisan db:seed --class=StageCCanonicalGroupPolicySeeder --force
```

In the browser Deployment Console the corresponding write action is `stage_c_group_policy_apply` and requires:

```text
APPLY-GROUP-POLICY
```

This does **not** enable `LOCATION_GOVERNANCE_GROUPS_ENABLED`; all rollout flags remain OFF during preparation. Any incomplete policy/default capability error is a STOP condition. Do not hand-edit policy rows to satisfy readiness.

Then provide required release evidence through the deployment environment/config for the exact approved candidate:

```text
LOCATION_GOVERNANCE_VALIDATION_SHA=<exact approved 40-char SHA>
LOCATION_GOVERNANCE_UAT_EVIDENCE=<exact approved evidence reference>
LOCATION_GOVERNANCE_TARGET_COUNTRY=IR
LOCATION_GOVERNANCE_TARGET_SCHEMA=ir-reference-v1
LOCATION_GOVERNANCE_TARGET_DATASET_SOURCE=earthcoop-reference
LOCATION_GOVERNANCE_TARGET_DATASET_VERSION=v1
```

If these values are changed through `.env`, verify that Laravel is not still serving stale cached configuration before trusting readiness output.

Run:

```bash
php artisan location-governance:readiness
```

The command is read-only and fail-closed. It must report all checks as passing before any canonical runtime flag is enabled. In addition to prior checks it now requires the pending-residence migration, an idempotently applied reviewed Governance topology, and the five Stage C automatic group policies. Do not proceed on `NOT READY`.

## 11. Staged rollout — one flag boundary at a time

After every step below, reload application configuration using the deployment platform's reviewed safe procedure and verify the intended value is actually active. Never enable the next stage until the current stage is accepted.

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
- pending exact-residence proposals persist safely when the requested micro-location is not yet canonical;
- profile completion succeeds without requiring a legacy Address for canonical users;
- optional geolocation assist never overwrites manual residence selection.

### Stage C — groups

Set:

```text
LOCATION_GOVERNANCE_GROUPS_ENABLED=true
```

Smoke checks:

- public/profession/specialty/age/gender memberships resolve through the current canonical Governance scope;
- the base/current local official scope is active and upstream official scopes have the intended observer role unless a valid privileged role already exists;
- after a Primary Residence transfer, stale canonical groups from the previous branch become inactive and the new branch becomes active;
- `/groups` uses the canonical cutover path and does not leak legacy spatial groups into the canonical systemic lists;
- specifically, for the existing test account moved from Tehran/Sohanak to the Sari reference neighborhood, the canonical My Groups list must follow the reviewed Sari ancestry and must not retain Tehran/Sohanak as current canonical memberships;
- legacy group-membership rows remain preserved as rollback/history scaffolding; do not delete them merely to make the UI clean;
- no empty-group explosion;
- existing mature Group Chat/Admin flows remain healthy;
- Community creation remains on-demand according to policy.

While `LOCATION_GOVERNANCE_GROUPS_ENABLED=false`, `/groups` intentionally remains on the legacy controller path; legacy memberships and canonical rows previously materialized during controlled testing can therefore coexist in that dark-launch view. That pre-activation display is not evidence that stale canonical reconciliation failed.

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
- Stage C group-policy apply evidence;
- smoke-test results for each stage;
- application/queue/error-log observations;
- start/end time of each stage;
- operator identity;
- any rollback or exception decision.

A green local/CI test result is not a substitute for Production smoke evidence.

## 13. Success criteria

Cutover is accepted only when:

- exact approved release is deployed and recorded;
- backup requirements and any restore-rehearsal limitation/evidence are recorded and accepted;
- additive migrations completed without unresolved errors, including the pending-residence-intent table;
- target reference geography import is completed with zero unresolved conflicts;
- explicit reference Governance topology is applied with zero unresolved conflicts and an idempotent post-apply dry-run;
- Stage C canonical group policies are applied successfully while feature flags are still dark;
- `location-governance:readiness` returns success;
- each rollout stage passed its smoke checks before the next was enabled;
- no critical regression is observed in mature subsystems;
- legacy geography and legacy group-membership history remain intact and available for rollback;
- evidence has been recorded.

Otherwise follow `PRODUCTION_ROLLBACK_RUNBOOK.md`.

## 14. Explicit stop before legacy retirement

Do not drop or rewrite legacy geography after a successful C13 cutover. Observe the canonical runtime first. C14 must audit residual legacy dependencies and requires a **new, separate explicit approval** before even authoring/executing destructive retirement work.
