# EarthCoop Location/Governance UI Completion Design

**Status:** Approved design awaiting written-spec review  
**Date:** 2026-09-13  
**Baseline:** `main@e253797392257a95e6e84c78ceaf7c936516ceb4`  
**Branch:** `agent/location-governance-ui-completion-20260913`  
**Parent architecture:** `docs/superpowers/specs/2026-09-10-global-location-governance-architecture-design.md`

## 1. Purpose

Stage C Location/Governance now has a canonical Location tree, country-aware schemas, independent Governance topology, canonical residence, hierarchical membership resolution, crowdsourced Location proposals, Community Areas, and an initial admin control center. The remaining gap is a coherent production-quality UI/UX and the minimal supporting contracts needed so users and administrators can actually operate those capabilities without falling back to the legacy fixed Iran hierarchy.

This phase completes the UI and interaction layer before broad Production activation of canonical grouping/governance.

The design is intentionally global and schema-driven. It must not hard-code `province → county → district → city → region → neighborhood → street → alley` as the universal path.

## 2. Confirmed product decisions

The following are inherited decisions, not open questions:

1. Registration, user profile editing, and admin user editing must use one shared canonical Location selection behavior.
2. The selector must follow the country's Location Schema and support arbitrary valid depth, including levels below neighborhood such as street, alley, block, residential complex, building, campus, and future equivalents.
3. A rigid sequence such as `street → alley → complex → building` is forbidden. For example, a complex may be directly under a street or under an alley when the schema permits it.
4. Where crowdsourcing policy permits, a user can propose a missing location directly from the selector.
5. Open proposals (`pending`, `ready_for_review`, `needs_evidence`) are visibly selectable so later users can reuse/support the same candidate instead of creating duplicates.
6. A verification threshold means ready for review, not automatic approval.
7. Admin/Najm Hoda review may approve, reject, merge duplicates, or request more evidence; sensitive decisions remain human approval-gated.
8. A Location Proposal is not a Governance Area. A pending/approved micro-location does not automatically create a Community Area or formal Governance scope.
9. Official governance rights continue to derive only from the approved canonical Primary Residence path and official Governance topology.
10. Resolution of the same pending residence proposal after approval/merge is a data-quality refinement, not a new residence transfer and must not consume the ordinary residence-transfer quota.

## 3. Current-state findings and gaps

### 3.1 What already exists

- Canonical registration location view and shared `location-selector.js`.
- Canonical profile residence editor.
- Schema-driven Location root/children endpoints.
- Canonical Primary Residence service and history.
- Location Proposal model/service/controller with duplicate detection, reuse of existing open proposals, distinct-user evidence, configurable verification threshold, approve/reject/merge/request-evidence transitions, and audit trail.
- Registration step 3 is authenticated, so proposal submission does not require a weaker unauthenticated API.
- Initial admin Location/Governance control center with proposal queue, Hoda recommendation, approve/reject/merge/request-evidence forms, import summaries, and governance counts.
- Community Area service and policy are separate from Location verification.
- Canonical My Groups/profile membership adapters exist.

### 3.2 Gaps this phase must close

- The current selector returns only active canonical Locations; it does not surface allowed missing child types or open proposals.
- Registration currently accepts only an active `locations.id`; it cannot preserve a selected pending proposal.
- Profile residence editing has the same gap.
- Admin user edit is still a legacy-style identity form and does not provide canonical residence editing through the shared selector.
- There is no unified user-facing proposal UX for create/reuse/support/status/resolution.
- There is no user-facing “My Location & Governance” overview.
- Active versus observer canonical memberships are not explained/visualized as a coherent governance experience.
- Community Area eligibility/request/create/view experience is not complete.
- Admin topology visibility is too shallow for operational use.
- Pending proposal resolution is not yet linked safely to the user who selected it as residence intent.

## 4. Chosen architecture: one shared schema-driven Location Picker

EarthCoop will use one reusable Location Picker contract and frontend component across:

- registration step 3;
- profile residence edit;
- admin user create/edit where residence is managed;
- future flows that require canonical location selection.

Consumers provide context and permissions; the component owns traversal, proposal discovery, proposal creation, duplicate/reuse handling, and pending-state presentation.

Conceptually:

```text
Location Picker
  ├─ approved children
  ├─ open proposals under current parent
  ├─ schema-allowed next location types
  ├─ residence-endpoint metadata
  ├─ proposal eligibility/policy
  └─ current selection
       ├─ canonical Location
       └─ open Location Proposal
```

No page-specific hard-coded geography cascade is allowed in the canonical path.

## 5. Selector/API contract

### 5.1 Read model

The canonical options API must provide enough information for the client to render both existing options and valid proposal actions. For a selected parent it needs at least:

- active canonical child Locations;
- open reusable Location Proposals under that parent;
- schema-allowed child Location Types;
- localized labels/type labels;
- `is_residence_endpoint`;
- whether deeper traversal is possible;
- whether proposing a missing child of each allowed type is permitted by policy;
- stable identity distinguishing `location:<id>` from `proposal:<id>`.

This may be implemented by extending the current root/children payloads or by adding a focused companion endpoint. The implementation plan should prefer the smallest contract that keeps the picker cohesive and avoids duplicate schema logic in JavaScript.

### 5.2 Existing and pending options

Approved and pending options must be visually distinct:

- approved canonical Location: normal selectable option;
- pending/ready/needs-evidence proposal: selectable with a clear badge such as “در انتظار تأیید”;
- resolved approved/merged proposal: client must converge to the resolved canonical Location;
- rejected proposal: not selectable as a valid residence; affected users receive an actionable status.

### 5.3 “Not in the list” action

At every parent for which policy permits crowdsourced children, the picker exposes a contextual action equivalent to:

> «مکان من در فهرست نیست»

The proposal form must not ask the user to understand database hierarchy. The current parent and schema context are already known. The user chooses among only the child types allowed by the schema, enters the location name, and may provide optional evidence/details supported by policy.

Before creating a proposal, the server remains authoritative for duplicate detection. If an approved Location or reusable open proposal already matches, the UI selects that existing identity instead of creating another candidate.

## 6. Pending Residence Intent

A pending proposal must be selectable without pretending it is already an approved canonical Location.

The residence domain will therefore distinguish:

1. **effective approved residence anchor** — the deepest approved canonical Location currently safe for official governance resolution;
2. **exact pending residence intent** — the open Location Proposal the user selected as their desired more precise residence.

The implementation must persist this relationship explicitly and auditably; it must not encode it as unstructured UI-only state.

### 6.1 Governance while proposal is pending

While the exact proposal is unresolved:

- registration/profile completion may proceed;
- the user sees the proposed exact location and its pending status;
- official Governance resolution and official memberships use only the deepest approved canonical ancestor/anchor;
- the proposal itself creates no official Governance Area, official group, election scope, or official voting right;
- supporting/reusing the proposal is allowed according to proposal policy.

This reconciles the approved “pending is selectable” decision with the rule that official governance must use canonical approved geography.

### 6.2 Resolution after approve/merge

When the proposal becomes approved or merged:

- users whose **current** residence intent still points to that proposal are converged to the resolved canonical Location;
- this convergence preserves residence history/audit provenance;
- it does not consume the normal Primary Residence transfer quota because the user has not changed real residence;
- canonical memberships are reconciled after the effective approved residence becomes more precise;
- stale intent must never move a user who changed residence after submitting/selecting the proposal.

The implementation must therefore include a currentness/staleness guard rather than blindly updating every historic selector of a proposal.

### 6.3 Rejection

If a selected proposal is rejected:

- official governance remains on the approved anchor;
- the user is notified in the Location/Governance UI that the exact detail was rejected;
- the UI offers correction/reselection/proposal of a different valid location;
- no automatic destructive residence change occurs.

## 7. Registration UX

Canonical registration step 3 becomes a complete “Residence” step:

1. optional consent-based location detection remains assistive;
2. user can manually traverse the schema;
3. any valid approved endpoint can complete registration;
4. user may continue deeper where optional micro-location levels exist;
5. where an exact location is missing, the user can create or reuse an open proposal;
6. selecting an open proposal completes registration using the approved ancestor as the temporary official governance anchor and the proposal as exact pending residence intent;
7. success messaging must distinguish approved exact residence from pending exact residence.

Registration must never be blocked merely because the user's legitimate micro-location awaits review.

## 8. User profile residence editing

The profile uses the same picker and proposal flow.

It must show:

- current approved residence path;
- exact pending detail, if any;
- proposal status and resolution outcome;
- residence-transfer quota information when a real Primary Residence transfer is being made;
- a clear distinction between refining the same residence via proposal resolution and actually moving to a different residence.

A genuine user-initiated residence change continues through `ResidenceService` history/quota rules. Merely approving/merging the currently selected pending exact location does not count as a transfer.

## 9. Admin user create/edit

Admin user management must stop being a special geography exception.

The admin create/edit experience will embed the same canonical picker in admin context. Admin may select approved canonical locations and, where policy allows, create/reuse proposals through the same domain workflow rather than writing raw geography IDs.

Admin editing must:

- display current canonical residence/pending intent;
- preserve audit actor/reason when an administrator changes another user's residence;
- distinguish correction from actual transfer where domain policy requires it;
- trigger canonical membership reconciliation after effective residence/demographic changes when Stage C groups are enabled;
- never bypass Location Schema validation.

The existing large legacy admin user form may be incrementally adapted; unrelated admin-user refactoring is outside this phase.

## 10. “My Location & Governance” user page

Add a user-facing page, linked from a natural authenticated navigation location, that explains the user's spatial/governance identity without exposing implementation details.

The page contains four coherent sections:

### 10.1 My residence

- exact selected residence path;
- approved versus pending markers;
- pending proposal status/action if applicable;
- link to edit residence.

### 10.2 My official governance chain

Display the official Governance Areas derived from approved Primary Residence, smallest applicable official scope upward to global. This chain comes from canonical Governance topology, not by rendering Location ancestors as if they were Governance Areas.

### 10.3 My canonical memberships

Group memberships by dimension:

- Public;
- Profession;
- Specialty;
- Age;
- Gender.

Clearly explain:

- **Active** membership at the base/current official scope;
- **Observer/upstream** membership at higher scopes;
- active/observer is not a statement of user account status.

The reference 81-membership scenario remains a test/reference case, not a fixed UI promise.

### 10.4 Communities

Show eligible/existing Community Areas related to the user's approved micro-location. Community membership/creation is displayed separately from official governance so users cannot confuse a building/complex community with an official systemic-election tier.

## 11. Community Area UX

Community Area remains optional, on-demand, and policy-controlled.

For an eligible approved micro-location, the UI can show one of:

- existing Community Area → open/view it;
- eligible but not created → request/create action according to policy;
- not eligible → no misleading creation affordance.

Creating a Community Area must call the canonical Community Area domain service/policy and remain idempotent. A pending Location Proposal cannot create a Community Area until it resolves to an approved canonical Location.

Community UI must label internal/community capabilities separately from formal systemic EarthCoop governance/elections.

## 12. Admin Location/Governance Control Center completion

The existing control center is retained and expanded into operational workflows, not raw CRUD tables.

Required views/workflows:

1. **Proposal queue** — filters by status/type/country/age/evidence; duplicate candidate; proposer/context; evidence summary; approve/reject/merge/request-more-evidence; audit history.
2. **Reference Location explorer** — schema-driven tree/path browsing with lifecycle/provenance/source visibility.
3. **Governance topology explorer** — official Governance tree, mapped Locations, base-scope behavior, skipped/collapsed country levels, Community Areas clearly distinguished.
4. **Community overview** — existing Community Areas, source Location, parent official area, capabilities/policy status.
5. **Import diagnostics** — latest imports, versions, creates/updates/conflicts and drill-down diagnostics where available.
6. **Operational diagnostics** — orphaned mappings, unresolved proposals, invalid schema relations, residence/pending-intent inconsistencies, and other safe read-only health indicators.

Consequential actions remain audited and human-approved. Najm Hoda may summarize, detect likely duplicates/anomalies, and recommend; it does not autonomously approve/reject/merge.

## 13. Visual and interaction requirements

The Location/Governance UI must conform to the project's unified responsive experience.

Requirements:

- Persian-first RTL with localization-ready strings;
- responsive mobile/desktop behavior;
- no assumptions about a fixed number of Location levels;
- progressive disclosure so long paths do not become visually overwhelming;
- clear approved/pending/observer/community badges with textual labels, not color alone;
- keyboard-accessible controls and useful validation messages;
- loading, empty, error, duplicate, stale, and resolved states are explicit;
- preserve existing dark-mode compatibility where the hosting layout supports it;
- avoid large page-specific inline CSS when shared component styling is appropriate.

## 14. Error and concurrency handling

The server is authoritative for schema validity, duplicate detection, proposal state, residence transfer policy, and governance resolution.

The UI must gracefully handle:

- another user creating the same proposal between load and submit;
- a pending proposal becoming approved/merged/rejected while the picker is open;
- a parent Location becoming inactive/superseded;
- stale residence intent after the user moves;
- proposal type no longer allowed by current schema;
- permission/policy changes;
- network failure after proposal submission;
- canonical membership reconciliation failure without corrupting residence state.

Idempotency/reuse must be preferred over duplicate creation.

## 15. Security and authority boundaries

- Proposal submission/support requires an authenticated user.
- Users cannot directly create authoritative managed reference Locations.
- Users cannot create official Governance Areas via the picker.
- Admin review actions remain CSRF-protected, authorized, audited, and explicit.
- Client-provided parent/type/location/proposal IDs are revalidated server-side against schema and policy.
- Raw GPS coordinates are not treated as authoritative residence proof and are not retained beyond defined consent/purpose policy.
- No UI path may bypass residence history/quota rules for an actual move.

## 16. Feature-flag and rollout behavior

The current Location/Governance feature flags remain the rollout boundary.

This phase must preserve dark-launch safety:

- canonical UI/runtime changes can be built/tested behind existing flags;
- `groups_enabled`, elections, and projects are not enabled merely because UI completion code lands;
- Production flag values must be verified separately before cutover;
- rollout/UAT must prove registration, profile edit, admin edit, proposal flows, governance display, communities, and responsive behavior before broad activation.

## 17. Testing strategy

Implementation must be test-first and checkpointed.

### 17.1 Domain/feature contracts

Cover at minimum:

- schema-allowed next types are exposed correctly;
- active children and reusable open proposals appear together without identity collision;
- duplicate proposal submission returns/reuses canonical Location or existing open proposal;
- pending proposal may be selected as exact residence intent;
- official governance during pending state resolves only through approved canonical anchor;
- approval/merge converges only current matching intents;
- stale intent cannot move a user;
- rejection leaves approved anchor intact and requires user correction;
- proposal resolution does not consume transfer quota;
- actual user move still does consume/enforce quota;
- admin canonical residence edits obey the same schema/domain rules;
- Community creation requires approved eligible Location and remains separate from official topology.

### 17.2 UI contracts

Test registration, profile, and admin surfaces for:

- shared picker markers/contracts;
- proposal action availability only when schema/policy permits;
- arbitrary-depth micro-location paths;
- pending badges and selectable state;
- responsive markup/contracts;
- user “My Location & Governance” sections;
- active versus observer labels;
- Community separation;
- admin topology/proposal diagnostics surfaces.

### 17.3 Regression scenarios

Retain existing Stage C regression tests and add representative paths:

- urban: neighborhood → street → complex → building;
- urban: neighborhood → street → alley → complex → building;
- residence endpoint at an intermediate level with optional deeper children;
- rural village without neighborhood;
- alternate country schema with skipped/different tiers;
- proposal reused by multiple users;
- proposal approved, merged, rejected, and needs-evidence;
- user moves before proposal resolves.

### 17.4 Validation gates

Use targeted RED/GREEN cycles first, then Location/Governance suite, affected Registration/Profile/Admin suites, responsive/UI contracts, and full project validation before integration.

## 18. Implementation boundaries

This phase may add/adjust the minimal domain persistence and endpoints required for pending residence intent and shared picker data. It must not redesign unrelated identity, election, market, Najm Bahar, or generic admin systems.

Legacy geography fields may remain during dark launch where still required by old paths, but new canonical UI must not introduce new dependencies on them.

No destructive Production data operation is part of this phase.

## 19. Completion criteria

This phase is complete only when all of the following are true:

1. Registration, profile edit, and admin user residence editing use the shared schema-driven canonical picker.
2. Valid lower-than-neighborhood levels are selectable to arbitrary schema depth.
3. Missing permitted locations can be proposed in-context.
4. Existing open proposals are visible/reusable/selectable with explicit pending state.
5. Registration/profile can proceed with pending exact residence without granting unapproved official governance identity.
6. Approval/merge/rejection safely resolves selected pending residence intents.
7. Official governance chain and canonical active/observer memberships are understandable to users.
8. Community Areas are usable but clearly separate from official governance.
9. Admins have an operational Location/Governance control center rather than only raw summary cards.
10. All changes are test-first, responsive, localized, audit-safe, flag-safe, and pass full validation.
11. No direct change is made to `main` until a separately reviewed integration/merge checkpoint.
