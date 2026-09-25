# Shared Location Exception Panel UX

## Scope
This contract applies to the same canonical residence picker in three entry points:

1. Registration Step 3.
2. User profile residence edit.
3. Admin user residence edit.

The implementation must stay shared. No entry point may grow a separate copy of missing-location, structural-claim, or proposal UX.

## Depth contract
Registration Step 3 stops at Neighborhood or the deepest structurally valid governance base when Neighborhood does not exist. It must never require Street, Alley, Complex, or Building.

Profile and Admin edit keep the full schema-driven continuation below Neighborhood, including the already-supported branches:
- Neighborhood -> Street.
- Street -> Alley / Complex / Building.
- Alley -> Complex / Building.
- Complex -> Building.
- Any other server-authorized branch remains schema-driven rather than hard-coded.

## Normal state
At every rendered level, the primary UI is only the select for that level.

Exceptional actions are progressively disclosed by one small link directly below the select, phrased for the target level, for example:
- «منطقه من در فهرست نیست»
- «روستا یا آبادی من در فهرست نیست»
- «محله من در فهرست نیست»
- «خیابان من در فهرست نیست»
- «کوچه من در فهرست نیست»
- «مجتمع من در فهرست نیست»
- «ساختمان من در فهرست نیست»

The exception UI is collapsed by default. Opening it must not mutate location state.

## Exception panel
The disclosure opens one shared inline panel beneath the relevant select. The panel may contain, in this order when applicable:

1. Search/reuse from an authoritative reference catalog, such as the Iran 1404 settlement catalog below a Rural District.
2. Reuse of an already-open pending proposal.
3. An «افزودن ... جدید» action when crowdsourced proposal is permitted.
4. A structural absence action when that level can legally be skipped.

A successful selection or proposal closes the panel and continues traversal. Cancelling/closing the panel is side-effect free.

## Structural absence UX
The backend continues to retain all supported structural claim types, including single-region and single-neighborhood states for compatibility and future use.

The public UI intentionally exposes only the exceptional absence states:
- no_urban_region -> «این شهر منطقه‌بندی ندارد»
- no_neighborhood -> «این محدوده محله‌بندی ندارد»

The UI does not expose:
- single_urban_region
- single_neighborhood
- normal/multi-region or normal/multi-neighborhood buttons

The ordinary multi-level structure is the default and requires no explicit user action.

## Reference settlements
Below a verified Rural District, the Iran 1404 reference-settlement search belongs inside the same exception panel. It must not appear as a separate always-visible card.

Hydration may restore a selected ReferenceSettlement and its pending proposal descendants without opening the exception panel. The breadcrumb remains stable and includes the ReferenceSettlement between its canonical parent and pending Neighborhood.

## State and safety
- Existing canonical or pending selection must survive opening/closing the panel.
- Pending records never become canonical Location or GovernanceArea merely because they are selected.
- Search failure preserves the current valid selection.
- Rejected/terminal proposals are not reusable.
- Changing an ancestor clears incompatible descendant state.
- Selecting a real Region or Neighborhood must not silently retain a contradictory absence claim.
- Admin retains its mandatory reason/audit contract.

## Testing
Automated coverage must assert:
- all three entry points use the shared selector contract;
- Step 3 cannot descend into micro-address levels;
- Profile/Admin can descend through all server-authorized micro branches;
- exception panels are collapsed by default and side-effect free on cancel;
- the correct level-specific link copy is present;
- only no_urban_region and no_neighborhood are exposed in UI;
- single/multi structural buttons are absent from UI;
- settlement search mounts inside the shared disclosure;
- selection/proposal closes the panel and continues traversal;
- hydration restores canonical + ReferenceSettlement + pending descendant path;
- stale/network errors preserve the deepest valid selection.
