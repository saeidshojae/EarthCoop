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

## C13 package contents

The production preparation package now includes:

- read-only, fail-closed command: `php artisan location-governance:readiness`;
- readiness coverage for required additive migrations, active target schema, exact reference dataset identity, import status/conflicts, governance mappings, rollout flag types, fatal proposal conflicts, immutable validation SHA and UAT evidence;
- canonical reference identity aligned with the actual importer: country `IR`, schema `ir-reference-v1`, source `earthcoop-reference`, dataset version `v1`;
- `docs/location-governance/PRODUCTION_CUTOVER_RUNBOOK.md`;
- `docs/location-governance/PRODUCTION_ROLLBACK_RUNBOOK.md`;
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

No mature release gate was removed or made non-blocking to obtain this result.

## UAT / disposable-data evidence

The permanent Location/Governance acceptance suite and fresh-bootstrap scenarios remain part of the blocking Location/Governance gate and pass in #2424. These scenarios use disposable test/CI data only and cover urban/rural topology, village and micro-location behavior, membership dimensions, Primary Residence voting authority, election boundaries, crowdsourced proposal review, geolocation assistance and location lifecycle history.

No Production data was used to manufacture this validation result.

## Backup / restore evidence — still required before cutover

**No Production backup or Production restore rehearsal has been performed as part of C13 preparation.** This is intentional: no Production-changing or Production-data operation is authorized yet.

Before any approved cutover window can begin, the operator must capture and attach evidence for:

1. a current Production database backup taken immediately before cutover;
2. checksum/size/timestamp or equivalent backup identity;
3. a verified restore rehearsal into a separate non-Production database/environment;
4. successful application boot and essential integrity checks against that restored copy;
5. the exact pre-cutover Production application SHA and current rollout flag values.

If this evidence is absent or the restore rehearsal fails, the cutover is a **NO-GO** regardless of CI status.

## Data and rollback safety

No Production database has been reset, truncated or destructively migrated. Legacy geography remains available as rollback support. Reference geography import is versioned and auditable. Fresh bootstrap is deliberately small and does not create real geography records by itself.

Rollback for the initial cutover is non-destructive: disable canonical rollout flags in reverse order and, if required, restore the prior application SHA. Canonical tables/data and legacy tables remain intact for diagnosis and later controlled action.

## Human-approval boundary — C13 HARD STOP

C13 preparation is now at the mandatory hard stop.

The following actions remain **unauthorized** until a new explicit approval for the exact Production action is given after backup/restore evidence is available:

- running Production migrations;
- running the reference importer with `--apply` against Production;
- seeding or altering Production canonical data;
- changing any Location/Governance Production rollout flag;
- switching Production application SHA as part of cutover;
- deleting or retiring any legacy geography data/table/column.

Approval of the architecture plan or approval to prepare C13 is not cutover approval.

Legacy retirement is not part of C13. C14 requires a post-cutover audit, observation evidence and a second separate explicit approval before any destructive cleanup migration is authored or executed.

## Readiness verdict

**C13 technical cutover package: PREPARED AND CI-VALIDATED.**

**Production cutover: NOT AUTHORIZED / NO-GO until Production backup + verified restore rehearsal evidence exists and the user explicitly approves the exact cutover action.**
