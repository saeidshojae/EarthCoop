# Residence Picker Deep Hardening Design

## Context
Production UAT after PR #124 exposed four related defects in the canonical residence picker: location-type labels leak canonical English names into Persian UI and breadcrumb; the proposal action does not have a stable visual contract in every runtime state; a pending proposal cannot be the parent of a deeper pending proposal; and therefore a resident whose street is absent cannot express a deeper alley/complex/building intent without waiting for administrative approval at each level.

## Goals
1. Persian residence UI must use Persian type labels for all known canonical type keys, while unknown types retain a safe server-provided fallback.
2. Breadcrumb/path must use localized selected labels and must never substitute English type names for known Persian type keys.
3. The real runtime “مکان من در فهرست نیست” control must have a stable green, touch-safe treatment in registration/profile contexts without changing the overall Step 3 visual baseline.
4. A user may create a chain of open proposals below the last approved Location, e.g. approved neighborhood → pending street → pending alley → pending complex → pending building, when every parent→child type relation and proposal policy permits it.
5. Pending proposals never become canonical Location or GovernanceArea merely by being selected or used as a proposal parent.
6. Primary residence remains anchored to the nearest approved Location while PendingResidenceIntent records the deepest open proposal selected by the user.

## Data model
Add nullable `parent_location_proposal_id` to `location_proposals`. Exactly one parent reference is required: either `parent_location_id` for the first pending node under a canonical Location, or `parent_location_proposal_id` for a deeper pending node. Existing rows remain valid and unchanged.

A proposal-parent chain inherits `location_schema_id` and `country_code` from its root approved Location. Each child type must be an allowed schema child of the proposal parent’s type and must have `crowdsourced_proposal_allowed=true`.

No canonical FK is fabricated for a pending node. Governance derivation always stops at canonical approved ancestry.

## API/read model
`LocationOptionsController` localizes known type labels by locale. For `fa`, known keys map to: country کشور; province استان / ایالت; county شهرستان / ناحیه; section بخش; city شهر; rural_district دهستان; village روستا; urban_region منطقه شهری; neighborhood محله; street خیابان; alley کوچه; complex مجتمع; building ساختمان.

Open proposal serialization gains enough information to request its children. A proposal-children endpoint/read path returns open child proposals plus allowed child types; canonical `data` is empty because an unresolved proposal cannot have canonical Location children. The POST proposal endpoint accepts exactly one of `parent_location_id` or `parent_location_proposal_id` and validates open-parent status, schema/type relation and policy.

## Proposal resolution semantics
Approving a parent proposal creates its canonical Location as today. Direct open child proposals are then re-anchored transactionally from `parent_location_proposal_id` to the newly approved `parent_location_id`; their deeper descendants remain proposal-parented. This preserves the chain without auto-approving descendants.

Merging a parent proposal into an existing canonical Location performs the same re-anchor to the merge target. Rejecting a proposal with open descendants must not orphan them: rejection is blocked until descendants are resolved/rejected, with a clear domain error. This is safer than silently cascading rejection or deleting user intent.

## Residence intent
The picker may select the deepest open proposal and submit it. Existing PendingResidenceIntent continues to reference that deepest proposal while its anchor relationship points to the nearest approved Location. When the deepest proposal eventually resolves, existing ResidenceService behavior resolves the intent; intermediate approvals merely re-anchor the chain and do not prematurely change the official residence.

## UI behavior
Selecting an open proposal no longer terminates traversal. If the proposal has allowed deeper proposal types, the picker loads another level and permits a child proposal. Existing pending proposals are reusable/selectable and visibly marked “در انتظار تأیید”. All known type labels shown in selects, proposal type selectors and breadcrumb are localized in Persian.

The proposal toggle receives a semantic runtime class and registration/profile UX supplies the green visual treatment. No hidden visual hint, duplicate action or wholesale Step 3 redesign is introduced.

## Error/state behavior
Network failure while loading proposal children preserves the deepest valid selection and shows stale/error state. Terminal/rejected/merged proposal parents cannot accept new children. Duplicate detection/reuse remains scoped to the same effective parent and type. Refresh/edit hydration must reconstruct a path containing canonical ancestors followed by the pending proposal chain.

## Testing
TDD coverage must include: Persian API labels for every known type; unknown fallback; Persian breadcrumb; runtime green proposal control; approved-parent proposal; pending-parent child proposal; policy/type-relation rejection; reuse/duplicate under proposal parent; multi-depth chain; approve/merge re-anchor; rejection blocked with open descendants; deepest PendingResidenceIntent anchored to nearest canonical Location; refresh/edit hydration; urban and rural branches; mobile/RTL/touch; loading/empty/error/stale; and invariants `Location != GovernanceArea` and pending proposal never canonical.

Run focused PHP/JS tests throughout. Run Full Validation only after the final candidate diff is audited and focused suites are green.

## Production safety
No direct `main` changes. No Production writes or destructive operations during implementation. Any Production migration/deployment follows the established deployment workflow and UAT after merge/deploy.