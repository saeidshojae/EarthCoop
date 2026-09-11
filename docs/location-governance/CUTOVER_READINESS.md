# Location / Governance Cutover Readiness

This document records **readiness evidence only**. It is not authorization to change Production.

## Validated C13 preparation candidate

- Implementation candidate: `c34f7ef406cac4bf512ee789c31688e65c764189`
- Branch: `agent/global-location-governance-implementation-20260910`
- Draft PR: `#103`
- PR base: `main@767a276b962a62234f70878071d007930903577a`
- Full Validation: run `#2424` / ID `34610982735`
- Validation job: `103301574562`
- Result: `success`

Run #2424 completed successfully on the exact C13 preparation candidate. The dedicated `Regression — Location / Governance` gate, all retained mature subsystem gates, Full Project PHPUnit, diagnostics upload and final enforcement all succeeded.

A later documentation-only checkpoint `943804fbcac2c702790466ae266cce8a2557a0d5` was also fully validated by run `#2426` / ID `34616614013`, job `103320370800`.

## Fresh Canonical Start launch policy

The owner has explicitly chosen **Fresh Canonical Start** for the current early Production population. The small set of existing users' legacy spatial/profile geography does not need to be bulk-converted into canonical Location/Governance before launch.

This means:

- an empty or partially populated `user_location_relationships` table is not by itself a readiness failure;
- existing users may establish/correct canonical Primary Residence through the new canonical profile flow after activation;
- no one-off destructive or risky user-geography conversion is required for initial cutover;
- new users will use the canonical schema-driven flow once the registration/profile stage is separately approved and enabled.

The complete decision and boundaries are recorded in `docs/location-governance/FRESH_CANONICAL_START_DECISION.md`.

This policy does **not** authorize deleting the Production database or mature subsystem state. Users, authentication, groups, elections, Najm Bahar, Stock, messages, projects and other existing state remain protected. No `migrate:fresh`, reset, truncate, table drop or destructive legacy retirement is permitted.

## C13 package contents

The production preparation package now includes:

- read-only, fail-closed command: `php artisan location-governance:readiness`;
- readiness coverage for required additive migrations, active target schema, exact reference dataset identity, import status/conflicts, governance mappings, rollout flag types, fatal proposal conflicts, immutable validation SHA and UAT evidence;
- canonical reference identity aligned with the actual importer: country `IR`, schema `ir-reference-v1`, source `earthcoop-reference`, dataset version `v1`;
- `docs/location-governance/PRODUCTION_CUTOVER_RUNBOOK.md`;
- `docs/location-governance/PRODUCTION_ROLLBACK_RUNBOOK.md`;
- `docs/location-governance/FRESH_CANONICAL_START_DECISION.md`;
- staged rollout order: `runtime -> registration/profile -> groups -> elections -> remaining consumers/projects`;
- smoke/regression checkpoint after each rollout step;
- rollback by feature flags first and application SHA second;
- explicit prohibition on dropping legacy geography during initial cutover.

The readiness command itself does not import data, apply migrations, change flags, or mutate Production.

## Defects caught during C13 preparation

C13 testing caught two readiness-contract defects before Production:

1. the readiness command checked a nonexistent `location_schemas.is_active` column; the schema contract actually uses `status = active`;
2. readiness defaults used `reference/1`, while the real importer and repository dataset use `earthcoop-reference/v1`.

Both were corrected through RED/GREEN validation before the final C13 candidate was accepted.

## Architecture readiness

The canonical architecture is present and tested for:

- schema-driven global Location topology rather than fixed Iran-specific runtime tiers;
- independent Official Governance topology and capability inheritance;
- historical Primary Residence relationships and audited transfer limits;
- explicit exclusion of work/study/other relationships from parallel official geographic voting rights;
- deterministic membership dimensions: `public`, `profession`, `specialty`, `age`, `gender`;
- canonical Group and Election scope with reversible rollout controls;
- Secretariat, Projects and Polls governance-scope consumers;
- on-demand Community behavior for micro-locations outside formal official election topology by default;
- crowdsourced proposals with duplicate detection, distinct-user evidence and human review;
- optional geolocation assistance that never proves or overwrites declared residence;
- Admin control center and Najm Hoda read/recommend capabilities with explicit human approval required for sensitive writes;
- fresh disposable bootstrap and permanent global architecture scenarios.

## Final release-gate evidence

On Full Validation #2424 all of the following completed successfully:

- route and command boot;
- Group Chat regression;
- Group Admin / Identity regression;
- Najm Hoda + n8n regression;
- Governance regression;
- Location / Governance regression;
- Najm Bahar regression;
- Stock regression;
- Group Chat JavaScript regression;
- reset of the disposable PHPUnit database;
- Full Project PHPUnit;
- regression diagnostics upload;
- final regression enforcement.

The same complete gate set also passed on documentation checkpoint #2426. No mature release gate was removed or made non-blocking.

## UAT / disposable-data evidence

The permanent Location/Governance acceptance suite and fresh-bootstrap scenarios remain part of the blocking Location/Governance gate. They cover urban/rural topology, village and micro-location behavior, membership dimensions, Primary Residence voting authority, election boundaries, crowdsourced proposal review, geolocation assistance and location lifecycle history.

No Production data was used to manufacture this validation result.

## Production backup boundary

No Production backup or restore rehearsal has been performed from this workspace because this workspace has GitHub/CI access but no cPanel/SSH/MySQL Production connection.

Fresh Canonical Start removes migration of existing user geography from the critical path, but does not make the rest of the Production database disposable. Before additive schema/import writes, a current recoverable hosting/database backup or provider snapshot must still exist. If the hosting environment cannot practically support an isolated restore rehearsal, that limitation must be recorded honestly and the owner must explicitly accept that operational risk; it must not be represented as verified restore evidence.

## Data and rollback safety

No Production database has been reset, truncated or destructively migrated. Legacy geography remains available as rollback support. Reference geography import is versioned and auditable. Fresh bootstrap is deliberately small and does not create real geography records by itself.

Rollback for the initial cutover is non-destructive: disable canonical rollout flags in reverse order and, if required, restore the prior application SHA. Canonical tables/data and legacy tables remain intact for diagnosis and later controlled action.

## Human-approval boundary — activation still stopped

Preparation may continue under the Fresh Canonical Start decision, but canonical runtime activation remains a separate approval checkpoint.

Until that activation approval, do not:

- enable any `LOCATION_GOVERNANCE_*_ENABLED` Production flag;
- delete or retire any legacy geography data/table/column;
- perform destructive database operations.

Legacy retirement is not part of C13. C14 requires a post-cutover audit, observation evidence and a second separate explicit approval before any destructive cleanup migration is authored or executed.

## Readiness verdict

**C13 technical cutover package: PREPARED AND CI-VALIDATED.**

**Fresh Canonical Start policy: APPROVED for the current small existing user population.**

**Production canonical activation: NOT YET AUTHORIZED.** The next operational step is safe Production backup/preflight plus additive deployment/bootstrap/reference import preparation; feature flags must remain off until a separate explicit activation approval.