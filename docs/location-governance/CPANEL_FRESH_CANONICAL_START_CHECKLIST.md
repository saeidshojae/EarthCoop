# cPanel Fresh Canonical Start Checklist

This checklist adapts the C13 Location/Governance cutover package to the current EarthCoop hosting model (GitHub -> FTPS/cPanel) and the approved Fresh Canonical Start policy.

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

Use the hosting provider/cPanel-supported backup mechanism or phpMyAdmin export for the actual Production database.

Record at minimum:

```text
BACKUP_ID_OR_FILENAME=
BACKUP_SIZE=
BACKUP_CREATED_AT=
```

If cPanel provides a full account/database backup identifier, record that identifier instead of inventing a checksum.

If a separate restore rehearsal database is practical, restore the backup there and record the result. If hosting limitations make that impractical, record the limitation honestly before any write; do not label restore as verified when it was not performed.

## C. Keep canonical rollout flags OFF

Before code/data preparation, confirm these remain disabled unless the owner has separately approved activation:

```text
LOCATION_GOVERNANCE_RUNTIME_ENABLED=false
LOCATION_GOVERNANCE_REGISTRATION_ENABLED=false
LOCATION_GOVERNANCE_GROUPS_ENABLED=false
LOCATION_GOVERNANCE_ELECTIONS_ENABLED=false
LOCATION_GOVERNANCE_PROJECTS_ENABLED=false
```

If the variables do not yet exist in Production `.env`, omission/default-false must be verified from the deployed configuration before proceeding.

## D. Deploy preparation code only

The existing repository deploy workflow uploads Production files from `main` via FTPS after its safety gates. Do not merge/deploy a moving branch accidentally. The release must identify the exact approved commit.

After code deployment but before any Location/Governance flag activation:

```bash
php artisan about
php artisan migrate:status
```

The application must boot normally.

## E. Apply additive migrations only

Only during the separately approved Production preparation window:

```bash
php artisan migrate --force
```

Then:

```bash
php artisan migrate:status
```

Any migration error is a STOP condition. Do not improvise a destructive rollback.

## F. Bootstrap canonical metadata

Run the idempotent small bootstrap:

```bash
php artisan db:seed --class=LocationGovernanceBootstrapSeeder --force
```

This creates canonical schema/type/dimension/policy metadata. It is not the real geography dataset.

Existing users do not need canonical Primary Residence rows at this point.

## G. Dry-run Iran reference geography

Before writes:

```bash
php artisan location:reference-import IR --dataset-version=v1 --dry-run
```

Record:

```text
create=
update=
deactivate=
conflict=
unchanged=
```

STOP if conflicts are unresolved or the counts look unexpectedly destructive.

## H. Apply reference geography only after the dry-run is accepted

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

## I. Run read-only readiness

Configure the approved release evidence in Production:

```text
LOCATION_GOVERNANCE_VALIDATION_SHA=<approved exact 40-char SHA>
LOCATION_GOVERNANCE_UAT_EVIDENCE=<approved validation/UAT reference>
LOCATION_GOVERNANCE_TARGET_COUNTRY=IR
LOCATION_GOVERNANCE_TARGET_SCHEMA=ir-reference-v1
LOCATION_GOVERNANCE_TARGET_DATASET_SOURCE=earthcoop-reference
LOCATION_GOVERNANCE_TARGET_DATASET_VERSION=v1
```

Then:

```bash
php artisan config:clear
php artisan location-governance:readiness
```

Fresh Canonical Start explicitly permits zero existing-user canonical residence mappings here. Missing user mappings alone are not a failure.

`NOT READY` is a STOP condition.

## J. Stop before activation

At this point report:

```text
DEPLOYED_SHA=
BACKUP_ID=
MIGRATIONS=PASS|FAIL
BOOTSTRAP=PASS|FAIL
REFERENCE_DRY_RUN=PASS|FAIL
REFERENCE_APPLY=PASS|FAIL
READINESS=READY|NOT_READY
CURRENT_FLAGS=
```

Do **not** enable any canonical runtime flag yet.

A separate explicit owner approval is required before Stage A (`LOCATION_GOVERNANCE_RUNTIME_ENABLED=true`) and the staged activation sequence described in `PRODUCTION_CUTOVER_RUNBOOK.md`.

## K. Existing users after later activation

Under Fresh Canonical Start:

- existing users may be prompted to establish/correct canonical Primary Residence through profile;
- no mass legacy-address conversion is required before launch;
- new registrations use canonical location selection after the registration/profile stage is enabled;
- legacy geography remains intact through C13;
- C14 retirement remains a separate audit and approval.