# Location / Governance Cutover Readiness

This document records **readiness evidence only**. It is not authorization to change Production.

## Validated C12 candidate

- Implementation candidate: `7ea742e7a4b8157de70029007bbdb4edd1409e94`
- Branch: `agent/global-location-governance-implementation-20260910`
- Draft PR: `#103`
- Full Validation: run `#2414` / ID `34605952857`
- Validation job: `103284291867`
- Result: `success`

The validation run completed successfully with the dedicated `Regression — Location / Governance` gate, all retained mature subsystem gates, Full Project PHPUnit, diagnostics upload and final enforcement.

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

## Release-gate readiness

The integration workflow retains the mature gates and adds a blocking focused Location/Governance gate. On run #2414 all of the following completed successfully:

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

No release gate was removed or made non-blocking to obtain this result.

## Data and rollback safety

C12 validation used disposable CI/test data only. No Production database was reset, truncated or destructively migrated. Legacy geography remains available as rollback support and the intended canonical runtime cutovers remain controlled by the relevant reversible flags until an approved Production procedure says otherwise.

Reference geography import is versioned and auditable. Fresh bootstrap is deliberately small and does not create real geography records. Production reference import/bootstrap must be treated as a separate operator action with preflight checks and backup/rollback evidence.

## Human-approval boundaries

Before any Production cutover, C13 must produce an operator-grade package that identifies at least:

1. the exact preflight/readiness checks and expected outputs;
2. the exact database backup/checkpoint requirement and verification method;
3. the exact additive migrations/imports/bootstrap commands, if any;
4. the exact feature-flag transition sequence;
5. post-cutover smoke/regression checks;
6. rollback triggers and exact rollback sequence;
7. evidence capture for the Production execution.

Preparation and review of that package are permitted as the next checkpoint. **Executing any Production-changing command is not permitted without a new explicit user approval for the exact action.**

Legacy retirement is not part of C13 cutover authorization. C14 requires a separate audit and a second explicit approval before any destructive cleanup migration or irreversible legacy removal is authored/executed.

## Readiness verdict

**C12 technical/UAT candidate: READY.**

**Production cutover: NOT YET AUTHORIZED.** The next safe step is C13 package preparation only, followed by a hard stop for explicit approval.
