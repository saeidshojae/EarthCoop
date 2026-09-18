# Structural Location Claims and Collapsed Governance Tiers Design

**Date:** 2026-09-18
**Status:** Approved design
**Baseline:** `main@d4b84125cdef2c85c0af20ceb0b8e22113490052`

## Purpose
EarthCoop must represent absent local administrative tiers without fake Locations. Structural facts change effective traversal/governance interpretation of a canonical Location while preserving `Location != GovernanceArea != Group`.

## Invariants
1. Structural claims are not Locations; no synthetic “Region 1” or “Neighborhood 1”.
2. Country/schema policy controls valid claim/location-type combinations.
3. Pending claims never block registration/profile completion and may open the effective next real level immediately.
4. Pending claims grant no new formal election, delegation, candidacy, management, inspection, voting, or upstream representation authority.
5. Support counts only after committed residence use, once per user; default 10 distinct users means `ready_for_review`, never automatic approval.
6. No duplicate GovernanceArea, public group, or systemic election is created for a collapsed tier with the same scope/electorate.
7. Project market-scope semantics remain independent.

## Model
Introduce `LocationStructureClaim`: `location_id`, `claim_type` (`single_urban_region`, `single_neighborhood`), status (`pending`, `ready_for_review`, `needs_evidence`, `approved`, `rejected`), proposer/reviewer, review reason, timestamps, metadata. Companion support/evidence records are unique per claim/user. Only one open same-type claim per canonical Location.

## Initial Iran policy
- `city + single_urban_region`: city carries the absent regional scope.
- `urban_region + single_neighborhood`: region is the base official local scope.
- `village + single_neighborhood`: village is the base official local scope.
- `city + single_neighborhood` is valid only when effective `single_urban_region` also applies; rare small-city/township case makes city the base official scope.

This is intentionally narrower than a generic arbitrary skip-tier engine.

## Effective traversal
Normal urban: `city → urban_region → neighborhood`.
Single-region city: `city → neighborhood`.
Single-neighborhood urban region: `urban_region → optional micro-location`.
Single-neighborhood village: `village → optional micro-location`.
Combined city: `city → optional micro-location`.

Pending claims allow immediate selection/proposal of the next real level and successful registration/profile save. No fake tier is inserted.

## Persistence and support
Residence stores the real canonical anchor/leaf plus explicit structural claim references relied upon by the committed path. Successful commit creates idempotent support for every still-open required claim. Selection without save does not count. Rejected claims remain historical but cannot be used for new saves.

## Governance, groups and elections
Approved claims affect effective official Governance topology. Collapsed tiers do not materialize duplicate official scopes. The surviving GovernanceArea receives the public group and policy-enabled dimensions. Pending claim-dependent shells may support continuity/local collaboration but remain non-authoritative.

Formal systemic elections operate only on active Official GovernanceAreas. No artificial region/neighborhood election is created for collapsed tiers. Approval deterministically/idempotently reconciles authoritative topology, after which the existing election engine operates without Iran-specific exceptions.

## UX
Structural choices are distinct from Locations/LocationProposals:
- `این شهر تک‌منطقه‌ای است — در انتظار تأیید`
- `این منطقه تک‌محله‌ای است — در انتظار تأیید`
- `این روستا تک‌محله‌ای است — در انتظار تأیید`

After selection the picker exposes the effective next real level. The choice appears in the selected-path summary and survives refresh/edit hydration. Approved claims render as established structural facts.

## Review
Open claims are reused. At 10 distinct committed supporters they become `ready_for_review`. Admin/Najm Hoda review may approve, reject, request evidence, or consolidate duplicates; consequential transitions are audited.

## Integration boundaries
Integrate registration Step 3, profile/admin residence editing, persistence/hydration/history, schema traversal, GovernanceArea resolution/materialization, pending group behavior, Membership/auto-grouping, formal election topology, admin/readiness, and Location/Governance JS. Do not change project market scope or make micro-locations mandatory.

## Permanent tests
Cover: Kiassar-like single-region city; single-neighborhood urban region; single-neighborhood village; combined small city; pending continuation; idempotent support; tenth support ready-for-review only; rejected-claim new-save rejection; refresh/edit hydration; no synthetic Location; no duplicate GovernanceArea/public group/election; no pending formal authority; deterministic approval reconciliation; all mature regression suites.

## Safety and acceptance
Implement on an isolated feature branch, additively and test-first. No destructive Production mutation. Required migrations enter readiness gates. Complete means all four collapsed-tier cases work without fake locations, pending/approved persistence and hydration work, support is deduplicated, governance/group/election topology has no artificial duplicate tiers, pending claims grant no formal authority, and Full Validation is green.
