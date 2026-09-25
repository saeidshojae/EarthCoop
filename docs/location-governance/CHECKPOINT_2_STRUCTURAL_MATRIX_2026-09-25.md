# Checkpoint 2 — Structural Residence/Governance Matrix

Checkpoint 2 closes the structural-state matrix for canonical and pending residence paths.

## Public UX contract

The shared Registration/Profile/Admin selector exposes only exceptional absence states:

- `no_urban_region` — city has no separate urban-region layer.
- `no_neighborhood` — the selected city/region/village has no separate neighborhood layer.

Legacy `single_urban_region` and `single_neighborhood` claim types remain backend-compatible but are not public UI actions.

## Canonical matrix

| Base | Structural state | Registration base | While claim open | After approval |
|---|---|---|---|---|
| City | normal | Neighborhood | n/a | n/a |
| City | `no_urban_region` | Neighborhood directly below City | claim may be selected/reused | direct Neighborhood remains valid |
| City | `no_urban_region` + `no_neighborhood` | City | City is pending/non-authoritative base | City becomes formal base |
| Urban Region | `no_neighborhood` | Urban Region | pending/non-authoritative base | Region becomes formal base |
| Village | `no_neighborhood` | Village | pending/non-authoritative base | Village becomes formal base |

Registration never collects Street/Alley/Complex/Building. Profile/Admin may continue to micro-address levels after the official base.

## Pending-location matrix

Pending City, Urban Region, and Village proposals can carry structural claims without creating fake Locations. A pending City may create `no_neighborhood` only after a valid `no_urban_region` prerequisite exists. The proposal children API exposes that conditional branch only after the prerequisite is explicitly effective in the current path.

When a pending proposal is approved or merged, its active structural claims are re-anchored to the resolved canonical Location before residence/group reconciliation. Merge fails closed if the target already has the same active structural claim instead of silently duplicating structural identity.

## Dependency lifecycle

Conditional structural claims are schema-driven. For a dependent claim:

- creation requires a valid open/approved prerequisite on the same canonical/proposed owner;
- an open prerequisite must be explicitly selected in a residence commit unless already approved;
- approval of the dependent requires an approved prerequisite;
- rejecting a prerequisite automatically rejects open dependents, cancels pending residence intents that depend on the rejected branch, and rejects both direct and metadata-linked pending group shells;
- an already-approved dependent blocks prerequisite rejection rather than being silently rewritten;
- committed support cannot be added after its prerequisite becomes invalid.

This keeps pending claims non-authoritative and prevents a rejected structural branch from activating governance.

## Regression gate

`StructuralStatusMatrixCheckpointTest` covers:

1. canonical City/Region/Village terminal matrix;
2. City prerequisite approval ordering;
3. prerequisite rejection cascade and pending-shell cleanup;
4. pending City no-region + no-neighborhood traversal;
5. pending City terminal registration;
6. structural claim re-anchor on proposal approval;
7. structural claim re-anchor before proposal-merge reconciliation;
8. fail-closed merge collision;
9. explicit selection of open prerequisites during residence commit;
10. immediate cancellation/rejection of pending residence/group state after prerequisite rejection;
11. the same complete City terminal branch through Registration, Profile, and Admin entry points.

Existing Location/Governance tests remain the regression source for normal multi-level paths, pending proposal chains, Profile/Admin entry points, Communities, elections, and responsive behavior.
