# Structural Location Claims and Collapsed Governance Tiers Design

**Date:** 2026-09-18
**Status:** Approved design
**Baseline:** `main@a5db7480ca0b2731234de882bb6e7b3d075a885b`

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
Introduce `LocationStructureClaim`: `location_id`, `claim_type` (`single_urban_region`, `no_urban_region`, `single_neighborhood`, `no_neighborhood`), status (`pending`, `ready_for_review`, `needs_evidence`, `approved`, `rejected`), proposer/reviewer, review reason, timestamps, metadata. Companion support/evidence records are unique per claim/user. Only one open same-type claim per canonical Location.

## Initial Iran policy
- `city + single_urban_region`: city carries the collapsed regional scope and traversal continues to real neighborhoods when they exist.
- `city + no_urban_region`: city has no separate official urban-region tier.
- `urban_region + single_neighborhood` or `no_neighborhood`: region itself is the base official local scope.
- `village + single_neighborhood` or `no_neighborhood`: village itself is the base official local scope.
- `city + single/no_neighborhood` is valid when its effective regional tier is also collapsed/absent; the city itself becomes the base official local scope.

`single_*` and `no_*` remain distinct facts even where their governance consequence is identical. This preserves truthful geography, auditability, and future country mappings.

This is intentionally narrower than a generic arbitrary skip-tier engine.

## Effective traversal
Normal urban: `city → urban_region → neighborhood`.
Single-region city: `city → neighborhood`.
Single/no-neighborhood urban region: `urban_region [official governance base] → optional micro-location`.
Single/no-neighborhood village: `village [official governance base] → optional micro-location`.
Combined city with no separate region/neighborhood tier: `city [official governance base] → optional micro-location`.

**The official-governance endpoint is not the residence-detail endpoint.** After an effective official base, the residence picker may continue through every schema-valid micro-location path (for example street, alley, complex, building). These micro-locations refine residence only and do not create additional Official GovernanceAreas or systemic-election tiers.

Pending claims allow immediate selection/proposal of the next real level and successful registration/profile save. No fake tier is inserted.

## Persistence and support
Residence stores the real canonical anchor/leaf plus explicit structural claim references relied upon by the committed path. Successful commit creates idempotent support for every still-open required claim. Selection without save does not count. Rejected claims remain historical but cannot be used for new saves.

## Governance, groups and elections
Approved claims affect effective official Governance topology. Collapsed tiers do not materialize duplicate official scopes. The surviving GovernanceArea receives the public group and policy-enabled dimensions. Pending claim-dependent shells may support continuity/local collaboration but remain non-authoritative.

Formal systemic elections operate only on active Official GovernanceAreas. No artificial region/neighborhood election is created for collapsed tiers. Approval deterministically/idempotently reconciles authoritative topology, after which the existing election engine operates without Iran-specific exceptions.

## UX
Structural choices are distinct from Locations/LocationProposals:
- `این شهر تک‌منطقه‌ای است — در انتظار تأیید`
- `این شهر منطقه‌بندی جداگانه ندارد — در انتظار تأیید`
- `این منطقه تک‌محله‌ای است — در انتظار تأیید`
- `این منطقه محله‌بندی ندارد — در انتظار تأیید`
- `این روستا تک‌محله‌ای است — در انتظار تأیید`
- `این روستا محله‌بندی ندارد — در انتظار تأیید`

After selection the picker exposes the effective next real level. The choice appears in the selected-path summary and survives refresh/edit hydration. Approved claims render as established structural facts.

## Review
Open claims are reused. At 10 distinct committed supporters they become `ready_for_review`. Admin/Najm Hoda review may approve, reject, request evidence, or consolidate duplicates; consequential transitions are audited.

## Integration boundaries
Integrate registration Step 3, profile/admin residence editing, persistence/hydration/history, schema traversal, GovernanceArea resolution/materialization, pending group behavior, Membership/auto-grouping, formal election topology, admin/readiness, and Location/Governance JS. Do not change project market scope or make micro-locations mandatory.

## Permanent tests
Cover: Kiassar-like single-region/no-separate-region city; single/no-neighborhood urban region; single/no-neighborhood village; city with no separate region and no neighborhood; micro-location continuation below every collapsed official base; pending continuation; idempotent support; tenth support ready-for-review only; rejected-claim new-save rejection; refresh/edit hydration; no synthetic Location; no duplicate GovernanceArea/public group/election; no pending formal authority; deterministic approval reconciliation; all mature regression suites.

## Safety and acceptance
Implement on an isolated feature branch, additively and test-first. No destructive Production mutation. Required migrations enter readiness gates. Complete means all four collapsed-tier cases work without fake locations, pending/approved persistence and hydration work, support is deduplicated, governance/group/election topology has no artificial duplicate tiers, pending claims grant no formal authority, and Full Validation is green.
