# cPanel Fresh Canonical Start Checklist

This checklist adapts the C13 Location/Governance cutover package to the current EarthCoop hosting model (GitHub -> FTPS/cPanel), the approved Fresh Canonical Start policy, and the temporary browser deployment console used when cPanel has no Terminal/SSH.

It does **not** authorize feature-flag activation or destructive database work.

## A. Before touching Production

Record:

```text
APPROVED_APPLICATION_SHA=
CURRENT_PRODUCTION_SHA_OR_RELEASE_REFERENCE=
BACKUP_ID_OR_FILENAME=
BACKUP_CREATED_AT=
OPERATOR=
```

Required safety rule: keep the existing Production database. Fresh Canonical Start means only that existing users' old geographic/profile rows do not have to be bulk-converted before launch.

Never use:

```bash
php artisan migrate:fresh
php artisan migrate:reset
php artisan migrate:rollback
```

Never truncate/drop the Production database.

## B. Create a current cPanel/database backup

Use the hosting provider/cPanel-supported backup mechanism or phpMyAdmin export for the actual Production database before any write action.

Record at minimum:

```text
BACKUP_ID_OR_FILENAME=
BACKUP_SIZE=
BACKUP_CREATED_AT=
```

If cPanel provides a full account/database backup identifier, record that identifier instead of inventing a checksum.

If a separate restore rehearsal database is practical, restore the backup there and record the result. If hosting limitations make that impractical, record the limitation honestly before any write; do not label restore as verified when it was not performed.

## C. Keep canonical rollout flags and deployment console OFF

Before code/data preparation, confirm these remain disabled unless the owner has separately approved activation:

```text
LOCATION_GOVERNANCE_RUNTIME_ENABLED=false
LOCATION_GOVERNANCE_REGISTRATION_ENABLED=false
LOCATION_GOVERNANCE_GROUPS_ENABLED=false
LOCATION_GOVERNANCE_ELECTIONS_ENABLED=false
LOCATION_GOVERNANCE_PROJECTS_ENABLED=false
DEPLOYMENT_CONSOLE_ENABLED=false
```

If the Location/Governance variables do not yet exist in Production `.env`, omission/default-false must be verified from the deployed configuration before proceeding. The deployment console must remain explicitly disabled until the backup/preflight checkpoint has been accepted.

## D. Deploy preparation code only

The existing repository deploy workflow uploads Production files from `main` via FTPS after its safety gates. Do not merge/deploy a moving branch accidentally. The release must identify the exact approved commit.

The FTP workflow does **not** run Production Artisan migrations, bootstrap, reference imports, topology imports, policy activation, cache clears, or feature-flag changes. `.env` is excluded from the FTP sync.

After the approved code is deployed, do not enable the temporary deployment console until Section B is complete and the owner has accepted the preflight checkpoint.

### D1. Check Laravel configuration cache before changing `.env`

The deployment console and Location/Governance switches are read through Laravel configuration. If `bootstrap/cache/config.php` exists, an older cached configuration can cause later `.env` edits to appear ineffective.

Using cPanel File Manager, inspect whether this file exists:

```text
bootstrap/cache/config.php
```

Record:

```text
CONFIG_CACHE_PRESENT=yes|no
CONFIG_CACHE_ACTION=none|removed-through-cpanel-file-manager
```

If it exists, remove **only that generated cache file** through cPanel File Manager before relying on new `.env` values. Do not delete the `bootstrap/cache` directory and do not delete unrelated cache files as a substitute for understanding the state. If the file cannot be safely inspected/removed, STOP and do not assume changed environment values are active.

After the backup/preflight checkpoint is accepted, configure only these temporary values through the cPanel environment/.env editor:

```text
DEPLOYMENT_CONSOLE_ENABLED=true
DEPLOYMENT_CONSOLE_SECRET=<temporary high-entropy secret>
```

Do not reuse an account password as the deployment secret. Do not send the secret in screenshots or chat logs.

Then sign in as the founder/admin and open:

```text
/admin/deployment-console
```

The console must show that all five Location/Governance rollout flags are still OFF. If the console remains unavailable after an intentional enablement, STOP and re-check configuration-cache state rather than changing unrelated files.

## E. Inspect migration status in the browser console

Run the fixed `migration status` operation from the deployment console. It maps only to Laravel `migrate:status` and does not write to the database.

Review the pending migration list before any write. Unexpected or unrelated pending migrations are a STOP condition until reviewed. The canonical UI release requires, among its additive migrations, `2026_09_13_000001_create_pending_residence_intents_table`.

## F. Apply additive migrations only

After the pending list is accepted and the exact write has explicit approval, use the console's fixed `migrate` operation and enter the exact secondary confirmation phrase:

```text
MIGRATE
```

The only server-side command mapping for this action is:

```bash
php artisan migrate --force
```

Any migration error is a STOP condition. Do not improvise a destructive rollback.

Afterward, run `migration status` again and confirm the intended migrations are complete.

## F1. Iran 1404 v2 Production preflight — READ ONLY

After migrations are complete, but **before any Iran 1404 production write or feature-flag change**, use these three fixed browser-console operations:

```text
iran_v1_v2_runtime_audit
iran_v2_reference_dry_run
iran_v2_topology_dry_run
```

They map only to:

```bash
php artisan location:iran-v1-v2-runtime-audit
php artisan location:reference-import IR --dataset-version=v2 --dry-run
php artisan location-governance:reference-topology IR --dataset-version=v2 --dry-run
```

These operations are read-only. They do not authorize a v2 apply. Record the complete outputs before designing or approving the Production write path.

STOP if:
- the runtime audit reports unreviewed v1 identities or unexpected dependencies;
- the v2 geography dry-run reports any conflict;
- the v2 topology dry-run reports any conflict;
- the observed create/update counts differ materially from the reviewed Iran 1404 contract.

The reviewed clean-source expectations are 6,158 administrative v2 locations and the corresponding explicit v2 governance topology. The 99,317 neutral settlements are a separate catalog import and are **not** written by either dry-run above.

After the three read-only outputs are reviewed and the exact additive write is explicitly approved, the only v2 write permitted at this checkpoint is:

```text
iran_v2_reference_apply
```

It requires the exact secondary confirmation phrase:

```text
APPLY-IR-1404-V2-PRODUCTION-ADDITIVE
```

This operation stages only the 6,158 administrative v2 reference locations alongside v1. It does not apply v2 Governance topology, import settlements, migrate residence/group dependencies, or change rollout flags. After it succeeds, rerun `iran_v2_reference_dry_run` and require `unchanged=6158` with create/update/deactivate/conflict all zero. Runtime/topology cutover remains dark until the dedicated final preflight is clean.

For the final transition, run the read-only `iran_v2_cutover_dry_run`. It must report 6,158 v2 references, zero topology update/conflict, `blocker_total: 0`, and `READY_FOR_FINAL_CUTOVER: YES`. Then—and only then—use `iran_v2_cutover_apply` with the exact confirmation `CUTOVER-IR-1404-V2-PRODUCTION`.

The final cutover stages v2 topology if needed, migrates only reviewed verified-identity live dependencies while preserving existing relationship/group/election history IDs, and activates v2 runtime last. Post-check by rerunning `iran_v2_cutover_dry_run` and require `CUTOVER_COMPLETE: YES` with `blocker_total: 0`. The 99,317 settlement catalog remains outside this operation.

## G. Bootstrap canonical metadata

Use the fixed bootstrap operation and exact confirmation phrase:

```text
BOOTSTRAP
```

The server-side mapping is only:

```bash
php artisan db:seed --class=LocationGovernanceBootstrapSeeder --force
```

This idempotent bootstrap creates canonical schema/type/dimension/policy metadata. It is not the real geography dataset and it does not create Governance Areas or Location-to-Governance mappings. Its group-creation defaults are deliberately conservative/on-demand; Stage C automatic group policies are activated separately in Section K.

Existing users do not need canonical Primary Residence rows at this point.

## H. Iran reference geography dry-run

Use the console's fixed `reference_dry_run` operation before any reference geography apply.

The mapping is only:

```bash
php artisan location:reference-import IR --dataset-version=v1 --dry-run
```

Record and review:

```text
create=
update=
deactivate=
conflict=
unchanged=
```

STOP if conflicts are unresolved or counts look unexpectedly destructive.

## I. Apply reference geography only after accepted dry-run

If the dry-run is accepted and the exact write has explicit approval, use the fixed reference apply action with exact confirmation phrase:

```text
APPLY-IR
```

The mapping is only:

```bash
php artisan location:reference-import IR --dataset-version=v1 --apply
```

The expected identity is:

```text
country=IR
schema=ir-reference-v1
source=earthcoop-reference
dataset_version=v1
```

Re-run `reference_dry_run` after apply and require an idempotent result before continuing:

```text
create=0
update=0
deactivate=0
conflict=0
```

## J. Apply explicit Governance topology

Governance topology is intentionally independent from government geography. Do **not** infer Governance Areas from Location types and do not insert manual mappings merely to satisfy readiness.

First run the fixed `topology_dry_run` operation. Its mapping is only:

```bash
php artisan location-governance:reference-topology IR --dataset-version=v1 --dry-run
```

Review `create`, `update`, `conflict`, and `unchanged`. Any conflict is a STOP condition.

After the dry-run is accepted and the exact write has explicit approval, use the fixed `topology_apply` operation with exact confirmation phrase:

```text
APPLY-GOV-IR
```

The mapping is only:

```bash
php artisan location-governance:reference-topology IR --dataset-version=v1 --apply
```

The versioned topology dataset explicitly owns its reference Governance Areas and Location mappings. It must not rewrite unrelated Governance Areas.

Re-run `topology_dry_run` after apply and require:

```text
create=0
update=0
conflict=0
```

with the expected reference areas reported as unchanged.

## K. Activate reviewed Stage C canonical group policies

Bootstrap intentionally leaves the five systemic membership dimensions in `on_demand` mode. Before readiness can pass for the reviewed Stage C package, apply the dedicated idempotent policy transition.

Use the fixed `stage_c_group_policy_apply` operation with the exact confirmation phrase:

```text
APPLY-GROUP-POLICY
```

The server-side mapping is only:

```bash
php artisan db:seed --class=StageCCanonicalGroupPolicySeeder --force
```

This write changes the reviewed default policies for `public`, `profession`, `specialty`, `age`, and `gender` to canonical automatic materialization and sets the default Governance capability to `group_creation_mode=automatic`. It does **not** enable `LOCATION_GOVERNANCE_GROUPS_ENABLED`; all rollout flags must still remain OFF at this preparation stage.

Any incomplete-policy or missing-capability error is a STOP condition. Do not manually edit policy rows to force readiness.

## L. Run read-only readiness

Configure the approved release evidence in Production only after the final approved release candidate is known:

```text
LOCATION_GOVERNANCE_VALIDATION_SHA=<approved exact 40-char SHA>
LOCATION_GOVERNANCE_UAT_EVIDENCE=<approved validation/UAT reference>
LOCATION_GOVERNANCE_TARGET_COUNTRY=IR
LOCATION_GOVERNANCE_TARGET_SCHEMA=ir-reference-v1
LOCATION_GOVERNANCE_TARGET_DATASET_SOURCE=earthcoop-reference
LOCATION_GOVERNANCE_TARGET_DATASET_VERSION=v1
```

If `.env` is edited for this evidence, re-check Section D1 so Laravel is not still serving an older cached configuration.

Use the console's fixed `readiness` operation. Its server-side mapping is only:

```bash
php artisan location-governance:readiness
```

Fresh Canonical Start explicitly permits zero existing-user canonical residence mappings here. Missing user mappings alone are not a failure.

Current readiness is fail-closed and requires at least:

- all required additive Location/Governance migrations, including `pending_residence_intents`;
- active target schema and successful conflict-free reference geography import;
- the reviewed versioned Governance topology to be fully applied and idempotent (`create=0`, `update=0`, `conflict=0` on its diff);
- Stage C automatic group policies for all five systemic dimensions;
- valid rollout flag types and no fatal proposal conflicts;
- immutable validation SHA format and non-empty approved UAT/Full Validation evidence.

`NOT READY` is a STOP condition.

## M. Disable the temporary console and HARD STOP

At this point report:

```text
DEPLOYED_SHA=
BACKUP_ID=
CONFIG_CACHE_PRESENT=
CONFIG_CACHE_ACTION=
MIGRATIONS=PASS|FAIL
BOOTSTRAP=PASS|FAIL
REFERENCE_DRY_RUN=PASS|FAIL
REFERENCE_APPLY=PASS|FAIL
TOPOLOGY_DRY_RUN=PASS|FAIL
TOPOLOGY_APPLY=PASS|FAIL
STAGE_C_GROUP_POLICY_APPLY=PASS|FAIL
READINESS=READY|NOT_READY
CURRENT_FLAGS=
```

Then set:

```text
DEPLOYMENT_CONSOLE_ENABLED=false
```

If Laravel configuration caching is in use, verify that the disabled value is actually active before considering the temporary surface closed.

Do **not** enable any canonical runtime flag yet. The deployment console intentionally provides no flag-mutation action.

A separate explicit owner approval is required before Stage A (`LOCATION_GOVERNANCE_RUNTIME_ENABLED=true`) and the staged activation sequence described in `PRODUCTION_CUTOVER_RUNBOOK.md`.

This is a mandatory **HARD STOP**.

## N. Existing users and My Groups after later activation

Under Fresh Canonical Start:

- existing users may be prompted to establish/correct canonical Primary Residence through profile;
- no mass legacy-address conversion is required before launch;
- new registrations use canonical location selection after the registration/profile stage is enabled;
- legacy geography and legacy group-membership records remain intact through C13 as rollback/history scaffolding;
- while `LOCATION_GOVERNANCE_GROUPS_ENABLED=false`, `/groups` intentionally remains on its mature legacy path and can display legacy spatial memberships alongside canonical rows that were materialized during controlled testing;
- once Stage C groups are explicitly enabled, `/groups` must resolve from the current canonical Primary Residence and display only the current canonical systemic geography chain; stale canonical memberships are inactive and legacy spatial groups must not leak into the canonical list;
- for a test user moved from Tehran/Sohanak to the Sari reference neighborhood, the Stage C smoke check must confirm that the active canonical path follows the reviewed Sari governance ancestry and does not retain the former Tehran/Sohanak branch in the canonical My Groups list;
- no legacy membership should be deleted merely to make this smoke check pass;
- C14 retirement remains a separate audit and approval.
