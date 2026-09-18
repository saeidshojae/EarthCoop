# Structural Location Claims Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add truthful structural claims for collapsed/absent urban-region and neighborhood tiers, while keeping residence-detail traversal independent from the official governance/election endpoint.

**Architecture:** `LocationStructureClaim` describes structural facts about canonical Locations; it never creates fake Locations. A policy/resolver computes effective residence traversal and the smallest verified Official GovernanceArea, while micro-locations remain optional residence detail and Community-only below that base. Pending claims are non-blocking for residence but non-authoritative for formal governance until human approval.

**Tech Stack:** Laravel 9/PHP, Eloquent, Blade, Vite ES modules, Node test runner, existing Location/Governance services and Full Validation.

**Spec:** `docs/superpowers/specs/2026-09-18-structural-location-claims-design.md`

## Global Constraints
- Preserve `Location != GovernanceArea != Group`.
- No synthetic Region/Neighborhood Locations.
- Additive, rollback-safe migrations only; no destructive Production mutation or backfill without a separate explicit checkpoint.
- Pending claims grant no systemic-election/delegation/official-management/upstream authority.
- Support counts only after successful committed residence use; one user counts once; 10 distinct supporters means `ready_for_review`, never approval.
- Official-governance endpoint and residence-detail endpoint are independent; schema-valid micro-locations remain optional below a collapsed official base.
- Project market-scope behavior is unchanged.
- Every production behavior follows RED → observed failure → minimal GREEN → regression.
- No direct changes to `main`; merge only after exact-head Full Validation and explicit approval.

---

## File structure

New focused units:
- `app/Models/LocationStructureClaim.php` — claim state/relations.
- `app/Models/LocationStructureClaimEvidence.php` — distinct-user committed support.
- `app/Services/LocationGovernance/LocationStructureClaimPolicy.php` — valid claim/type combinations and effective next location types.
- `app/Services/LocationGovernance/LocationStructureClaimService.php` — reuse/create/support/review transitions.
- `app/Services/LocationGovernance/OfficialGovernanceBaseResolver.php` — resolves smallest authoritative official scope independently from exact residence leaf.
- additive migrations for claims/evidence and residence reliance references.
Existing controllers/services consume these units; election code consumes GovernanceArea topology only and receives regression tests rather than Iran-specific branches.

### Task 1: Claim persistence and invariants
**Files:** Create claim/evidence migrations and models; modify `User.php`, `Location.php`; add feature/unit tests.

**Interfaces:** Produces `LocationStructureClaim::openFor(Location $location, string $type)`, evidence uniqueness `(claim_id,user_id)`, typed statuses and claim types.

- [ ] Write RED tests proving valid persisted claim/evidence, one open same-type claim per Location, one evidence per user, and no Location row is created.
- [ ] Run focused tests and record expected schema/model failures.
- [ ] Add minimal additive migrations/models/relations.
- [ ] Run focused tests GREEN.
- [ ] Add both migrations to deployment-readiness required migration list and test it.
- [ ] Commit checkpoint.

### Task 2: Country/schema structural-claim policy
**Files:** Create `LocationStructureClaimPolicy.php`; modify schema resolver only through this interface; add unit tests.

**Interfaces:** `allowedClaimTypes(Location $location): array`; `effectiveChildTypeCodes(Location $location, Collection $effectiveClaims): array`.

- [ ] RED: city permits `single_urban_region/no_urban_region`; urban_region and village permit `single_neighborhood/no_neighborhood`; invalid combinations rejected.
- [ ] RED: single-region city exposes neighborhood; no-neighborhood region/village exposes schema-valid micro types; combined city exposes micro types.
- [ ] Implement minimal policy without a generic arbitrary skip-tier engine.
- [ ] GREEN + existing schema resolver regressions.
- [ ] Commit checkpoint.

### Task 3: Claim lifecycle, reuse and committed support
**Files:** Create `LocationStructureClaimService.php`; tests for service/transactions.

**Interfaces:** `findOrCreateOpenClaim(Location, type, User)`; `recordCommittedSupport(claim, User)`; review transitions.

- [ ] RED: repeated proposal reuses open claim; selector interaction alone creates no evidence.
- [ ] RED: successful committed support is idempotent; tenth distinct supporter transitions to `ready_for_review`, never `approved`.
- [ ] RED: rejected claim cannot be used for a new residence commit.
- [ ] Implement transaction-safe lifecycle and audit metadata.
- [ ] GREEN and commit checkpoint.

### Task 4: Residence persistence and hydration
**Files:** Modify `ResidenceService.php`, `PendingResidenceIntent.php` or a focused companion relation if needed, Step3/Profile/Admin residence controllers, hydration builder, tests.

**Interfaces:** committed residence stores real canonical anchor/leaf plus structural claim IDs relied on by that path.

- [ ] RED: pending structural claim does not block registration/profile save.
- [ ] RED: failed residence save leaves no claim evidence.
- [ ] RED: refresh/edit rehydrates structural choices distinctly from LocationProposal.
- [ ] RED: admin edit does not accidentally count the admin as supporter.
- [ ] Implement atomic persistence/support and hydration.
- [ ] GREEN + pending-proposal residence regressions.
- [ ] Commit checkpoint.

### Task 5: Effective residence traversal API
**Files:** Modify `LocationOptionsController`/residence options serialization and selector core; add PHP + JS tests.

**Interfaces:** options response carries localized structural choices separately from canonical/proposed Locations and returns effective child types.

- [ ] RED: Kiassar-like city can choose single/no-region and continue to real neighborhoods without fake region.
- [ ] RED: no-neighborhood urban region/village can stop officially yet continue to street/micro children.
- [ ] RED: combined city can continue directly to micro.
- [ ] Implement API contract and selector traversal.
- [ ] GREEN and commit checkpoint.

### Task 6: Location selector UX completion
**Files:** Modify `location-selector-core.js`, `location-selector.js`, `registration-location-ux.js`, profile/register Blade only as required; JS/source-contract tests.

**Interfaces:** Persian structural-claim controls; one-type proposal form hides redundant type picker; schema labels localized naturally.

- [ ] RED: no raw `neighborhood/street/alley / building / complex` labels in Persian UI.
- [ ] RED: exactly one proposal type renders no redundant visible type selector.
- [ ] RED: structural claim copy distinguishes pending/approved and no-tier vs single-tier facts.
- [ ] RED: collapsed official base still permits optional micro continuation.
- [ ] Implement compact mobile-first UI without changing project-scope selector semantics.
- [ ] GREEN and commit checkpoint.

### Task 7: Official Governance Base resolver
**Files:** Create `OfficialGovernanceBaseResolver.php`; modify governance materialization/membership consumers only behind resolver; add integration tests.

**Interfaces:** `resolveForResidence(UserLocationRelationship $residence): ?GovernanceArea` and/or focused location+claims equivalent.

- [ ] RED: ordinary urban resolves neighborhood.
- [ ] RED: single/no-neighborhood urban region resolves the region itself.
- [ ] RED: single/no-neighborhood village resolves village itself.
- [ ] RED: city with collapsed/absent region and neighborhood resolves city itself.
- [ ] RED: street/building residence beneath those scopes still resolves the same official base.
- [ ] Implement deterministic resolver and idempotent materialization.
- [ ] GREEN + membership/group regressions.
- [ ] Commit checkpoint.

### Task 8: Public groups and pending authority boundary
**Files:** Modify official public-group materialization/pending group request path if already present; otherwise introduce only the minimal pending shell required by the approved spec; integration tests.

**Interfaces:** exactly one authoritative public group per effective Official GovernanceArea; pending claim-dependent scope is non-authoritative.

- [ ] RED: no duplicate public group for collapsed region/neighborhood.
- [ ] RED: pending claim cannot produce formal membership/manager/inspector authority.
- [ ] RED: approval reconciles/materializes idempotently.
- [ ] Implement minimal reconciliation.
- [ ] GREEN and commit checkpoint.

### Task 9: Election topology regression
**Files:** Election/Governance integration tests; production election code only if RED exposes direct Location assumptions.

**Interfaces:** formal elections consume active Official GovernanceArea topology, never claim/location type special cases.

- [ ] RED/regression contracts: no artificial election for collapsed tier; micro Community never becomes systemic election tier; pending claim grants no vote/delegation/candidacy/upstream representation.
- [ ] Run tests; if already GREEN, make no production election change.
- [ ] If a failure exposes a legacy direct-location dependency, patch only through GovernanceArea resolution and rerun.
- [ ] Commit checkpoint.

### Task 10: Admin review and readiness
**Files:** Admin Location/Governance controller/view, readiness command/tests.

**Interfaces:** queue exposes claim type, location context, distinct support, threshold/status, dependencies; approve/reject/needs-evidence audited.

- [ ] RED admin queue/review authorization/status tests.
- [ ] Implement localized review UI/actions.
- [ ] GREEN + readiness migration checks.
- [ ] Commit checkpoint.

### Task 11: End-to-end permanent scenarios
**Files:** Feature scenario tests and JS tests only unless gaps are exposed.

- [ ] Kiassar-like city: collapsed region → real neighborhood → optional street.
- [ ] Urban region with no neighborhood: region is official base → optional street/alley/building.
- [ ] Village with no neighborhood: village is official base → optional micro.
- [ ] Combined small city: city official base → optional micro.
- [ ] Verify exact residence leaf differs from official governance base where appropriate.
- [ ] Verify no synthetic Locations and no duplicate GovernanceArea/public group/election.
- [ ] Run Location/Governance focused suites GREEN.
- [ ] Commit checkpoint.

### Task 12: Full validation and release audit
- [ ] Audit diff against spec and ensure no project-market-scope or unrelated subsystem changes.
- [ ] Run Full Validation on exact candidate SHA.
- [ ] Require all Location/Governance PHP+JS, Elections, Groups, Najm Hoda, Najm Bahar, Stock and Full Project gates GREEN.
- [ ] Review migrations as additive/idempotent; no Production backfill included.
- [ ] Prepare PR; do not merge without explicit approval.
- [ ] After approved merge/deploy, UAT registration/profile for the four permanent structural scenarios.
