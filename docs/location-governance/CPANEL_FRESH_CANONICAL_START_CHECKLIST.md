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

The temporary deployment console is disabled by default. After the approved code is deployed, configure only these temporary values through the cPanel environment/.env editor:

```text
DEPLOYMENT_CONSOLE_ENABLED=true
DEPLOYMENT_CONSOLE_SECRET=<temporary high-entropy secret>
```

Do not reuse an account password as the deployment secret. Do not send the secret in screenshots or chat logs.

Then sign in as the founder/admin and open:

```text
/admin/deployment-console
```

The console must show that Location/Governance rollout flags are still OFF.

## E. Inspect migration status in the browser console

Run the fixed `migration status` operation from the deployment console. It maps only to Laravel `migrate:status` and does not write to the database.

Review the pending migration list before any write. Unexpected or unrelated pending migrations are a STOP condition until reviewed.

## F. Apply additive migrations only

After the pending list is accepted, use the console's fixed `migrate` operation and enter the exact secondary confirmation phrase:

```text
MIGRATE
```

The only server-side command mapping for this action is:

```bash
php artisan migrate --force
```

Any migration error is a STOP condition. Do not improvise a destructive rollback.

Afterward, run `migration status` again and confirm the intended migrations are complete.

## G. Bootstrap canonical metadata

Use the fixed bootstrap operation and exact confirmation phrase:

```text
BOOTSTRAP
```

The server-side mapping is only:

```bash
php artisan db:seed --class=LocationGovernanceBootstrapSeeder --force
```

This idempotent bootstrap creates canonical schema/type/dimension/policy metadata. It is not the real geography dataset. Existing users do not need canonical Primary Residence rows at this point.

## H. Iran reference dry-run

Use the console's fixed `reference dry-run` operation before any reference geography apply.

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

If the dry-run is accepted, use the fixed reference apply action with exact confirmation phrase:

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

## J. Run read-only readiness

Configure the approved release evidence in Production:

```text
LOCATION_GOVERNANCE_VALIDATION_SHA=<approved exact 40-char SHA>
LOCATION_GOVERNANCE_UAT_EVIDENCE=<approved validation/UAT reference>
LOCATION_GOVERNANCE_TARGET_COUNTRY=IR
LOCATION_GOVERNANCE_TARGET_SCHEMA=ir-reference-v1
LOCATION_GOVERNANCE_TARGET_DATASET_SOURCE=earthcoop-reference
LOCATION_GOVERNANCE_TARGET_DATASET_VERSION=v1
```

Use the console's fixed `readiness` operation. Its server-side mapping is only:

```bash
php artisan location-governance:readiness
```

Fresh Canonical Start explicitly permits zero existing-user canonical residence mappings here. Missing user mappings alone are not a failure.

`NOT READY` is a STOP condition.

## K. Disable the temporary console and HARD STOP

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

Then set:

```text
DEPLOYMENT_CONSOLE_ENABLED=false
```

Do **not** enable any canonical runtime flag yet. The deployment console intentionally provides no flag-mutation action.

A separate explicit owner approval is required before Stage A (`LOCATION_GOVERNANCE_RUNTIME_ENABLED=true`) and the staged activation sequence described in `PRODUCTION_CUTOVER_RUNBOOK.md`.

This is a mandatory **HARD STOP**.

## L. Existing users after later activation

Under Fresh Canonical Start:

- existing users may be prompted to establish/correct canonical Primary Residence through profile;
- no mass legacy-address conversion is required before launch;
- new registrations use canonical location selection after the registration/profile stage is enabled;
- legacy geography remains intact through C13;
- C14 retirement remains a separate audit and approval.
