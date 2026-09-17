# Residence Proposal Support, Structural Claims, and Pending Groups Design

**Date:** 2026-09-17  
**Status:** Proposed design for review  
**Branch:** `agent/location-production-uat-hardening-20260917`

## 1. Purpose

This design closes the remaining gap between EarthCoop's canonical Location/Governance architecture and the real residence-selection contract required for registration, profile editing, crowdsourced verification, and location-scoped groups.

It deliberately separates two tracks:

- **Track A — Production selector recovery:** restore the currently broken registration/profile location picker, existing-path hydration, and Persian labels using the real imported Iran reference dataset.
- **Track B — Contract completion:** make committed use of a pending location count as support, model single-region/single-neighborhood structural claims without fake locations, and allow group shells for pending scopes without activating formal governance prematurely.

Track A must be releasable independently. Track B must not delay recovery of the Production registration flow.

---

## 2. Non-negotiable invariants

1. `Location != GovernanceArea != Group` remains true.
2. A `LocationProposal` is never treated as a canonical `Location` before approval/merge.
3. A pending proposal may be selected and used to complete registration/profile editing.
4. Selecting an option in a dropdown is not support. **Successfully saving residence with that proposal is support.**
5. Each user contributes at most one support record per proposal.
6. The default distinct-user threshold is 10. Reaching it means `ready_for_review`, never automatic approval.
7. Human/admin review remains required for final approval, merge, rejection, or evidence requests.
8. Pending location/group shells cannot participate in official elections, official delegation, official governance voting, or formal upstream representation.
9. Official geographic group formation remains exact through the smallest verified official residence scope. Below the neighborhood-equivalent official base, groups are optional Community groups only.
10. No pseudo-locations such as `Single Region` or `Single Neighborhood` are inserted into the canonical Location tree.
11. Project market scope remains a separate contract: a project may intentionally stop at any geographic level. Residence minimum-depth rules must not leak into project scope selection.

---

## 3. Residence completion contract

### 3.1 General rule

Residence selection must reach the smallest required official local scope before the submit button becomes valid. Levels below that official base are optional residence detail.

For the ordinary urban path:

`country → province → county → section → city → urban region → neighborhood`

For the ordinary rural path:

`country → province → county → section → rural district → village → neighborhood`

A user may continue below the official base where the schema permits, e.g.:

`neighborhood → street → alley → complex → building`

These lower levels remain optional.

### 3.2 City with one urban region

A city may have no separate urban-region record because it is structurally single-region. This is not a missing fake location.

- The user may choose an existing verified `single_urban_region` structural claim for the city.
- If none exists, the user may propose that structural claim.
- The city itself represents the regional scope for governance purposes while that structural fact is verified.
- The selector may then continue directly from the city to the neighborhood layer under the effective branch rule.
- A neighborhood is still required unless another separate residence rule explicitly makes the city itself the smallest official local scope. This design does not introduce that exception.

### 3.3 Village with one neighborhood

A village may have no separate neighborhood records because it is structurally single-neighborhood.

- The user may choose an existing verified `single_neighborhood` structural claim for the village.
- If none exists, the user may propose that structural claim.
- For a verified single-neighborhood village, the village itself is a valid residence endpoint and smallest official governance base; no synthetic neighborhood is created.
- For a pending single-neighborhood claim, registration must still complete. The canonical village is stored as the residence anchor, while the structural claim remains pending and any local group whose validity depends on that claim remains pending until review.

### 3.4 Missing mandatory nodes

If a mandatory node is missing from the catalog, the user may propose it and continue immediately:

- urban path: missing urban region may be proposed; the user can then propose/select the neighborhood under that pending region;
- rural path: missing village may be proposed; the user can then propose/select the neighborhood under that pending village;
- both paths support deep proposal chains without waiting for intermediate admin approval.

Registration/profile save must never be blocked solely because the selected required node is pending review.

---

## 4. Structural claims are not locations

Introduce a dedicated structural-claim concept instead of abusing `LocationProposal`.

### 4.1 Model

`LocationStructureClaim`

Minimum fields:

- `id`
- `location_id` — canonical city or village being described
- `claim_type` — initially `single_urban_region` or `single_neighborhood`
- `status` — `pending`, `ready_for_review`, `needs_evidence`, `approved`, `rejected`
- `proposer_user_id`
- `reviewed_by_user_id`
- `review_reason`
- timestamps / reviewed timestamp
- metadata / audit log

A companion evidence table must enforce uniqueness on `(location_structure_claim_id, user_id)`.

### 4.2 Reuse and support

Only one open claim of the same type may exist for a canonical location. Later users reuse it rather than create duplicates.

The same 10-distinct-user threshold contract applies:

- each user counts once;
- successful committed use counts as support;
- threshold moves the claim only to `ready_for_review`;
- final review remains human.

### 4.3 Selector representation

Structural claims appear as explicit branch choices, not ordinary location options. Examples in Persian UI:

- `این شهر تک‌منطقه‌ای است — در انتظار تأیید`
- `این روستا تک‌محله‌ای است — در انتظار تأیید`

The UI must distinguish these from location proposals.

---

## 5. Location proposal discovery and support contract

### 5.1 Discovery

Open location proposals with statuses:

- `pending`
- `ready_for_review`
- `needs_evidence`

remain visible to later authenticated users under the same effective parent branch and are rendered with `در انتظار تأیید`.

Deep proposal descendants remain visible and selectable through proposal-parent traversal.

### 5.2 Support event

Support is registered only after a residence change is successfully committed.

The following operations count as support for the selected deepest pending proposal and every still-open proposal ancestor in that selected pending chain:

- successful registration Step 3 completion;
- successful profile residence save;
- successful admin residence edit when performed on behalf of the affected user only if product policy explicitly treats that affected user's residence selection as evidence. By default, admin edits do **not** add admin support.

Merely opening the selector, highlighting an option, or creating a proposal without completing residence save does not count as support.

The proposer becomes the first supporter only when they actually complete residence save using that proposal.

### 5.3 Atomicity

Residence persistence, `PendingResidenceIntent`, and support/evidence updates must be transactionally consistent. A failed save must not leave a support record behind.

Repeated saving by the same user must update/reuse that user's evidence, never increment the distinct count.

---

## 6. Pending residence semantics

When the deepest selected item is a pending `LocationProposal`:

1. registration/profile save succeeds;
2. formal residence remains anchored to the nearest canonical ancestor allowed by the residence policy;
3. the deepest pending proposal is stored in `PendingResidenceIntent`;
4. the full open proposal chain is preserved for UI hydration;
5. support is recorded after successful commit;
6. approval of intermediate proposal ancestors reanchors the proposal chain but does not prematurely resolve the deepest residence intent;
7. when the deepest selected proposal is approved/merged, the intent resolves and formal residence moves to the resolved canonical location.

A pending structural claim follows the same non-blocking principle but is tracked separately from `PendingResidenceIntent` unless it also contains a pending location proposal chain.

---

## 7. Pending group architecture

### 7.1 Why existing Community creation is insufficient

Current `CommunityAreaService::createFor(Location, User)` requires an active canonical `Location` and immediately creates an active Community `GovernanceArea`. That cannot safely represent a group whose spatial scope is still a `LocationProposal`.

The solution must therefore avoid attaching a canonical FK to an unapproved proposal and avoid creating an active formal governance area early.

### 7.2 Pending group shell

Introduce `LocationScopedGroupRequest` as the durable pending shell.

Minimum fields:

- `id`
- `requester_user_id`
- nullable `location_id`
- nullable `location_proposal_id`
- nullable `location_structure_claim_id`
- `scope_kind` — `official_public` or `community`
- `status` — `pending_location`, `ready_to_materialize`, `materialized`, `rejected`, `cancelled`
- nullable `group_id`
- nullable `governance_area_id`
- metadata / audit log / timestamps

Exactly one spatial source is required: canonical location, pending location proposal, or structural claim context.

This request is what the UI presents as **گروه در انتظار تأیید**. It is intentionally not an active `Group` row yet, preventing accidental chat/election/membership leakage through legacy group queries.

### 7.3 Official pending scopes

For required official governance levels (including neighborhood and valid single-neighborhood village bases):

- selecting a pending official location during residence save may create/reuse an `official_public` pending group request;
- it remains non-participatory while the relevant location/structural claim is pending;
- after approval, the normal official GovernanceArea topology is resolved/materialized first;
- then the public Group is created/reused through the normal governance-scoped group service;
- the request links to the materialized records and becomes `materialized`.

No formal election or official membership is granted before materialization.

### 7.4 Optional groups below neighborhood

Street, alley, complex, building, and future below-neighborhood micro-locations are not official systemic-governance levels.

Users may request an optional Community group for these scopes through `مکان و حکمرانی`.

- Canonical approved micro-location: request may materialize immediately if policy allows.
- Pending micro-location proposal: request is accepted and remains `pending_location`.
- When the location proposal is approved/merged, the request materializes a Community GovernanceArea and then its group.
- `CommunityCreationPolicy` must support configured micro-location types rather than only hard-coded `complex`/`building`; initial required set is `street`, `alley`, `complex`, `building`.

### 7.5 Rejection / merge handling

- If a proposal is merged into an existing canonical location, pending group requests follow the merge target and materialize idempotently there.
- If a proposal/structural claim is rejected, dependent pending group requests become `rejected` unless explicitly re-parented by an administrator.
- Duplicate requests for the same effective scope/kind must be idempotent.

---

## 8. Group and governance boundaries

Official systemic governance ends at the verified smallest official residence scope:

- ordinary urban: neighborhood;
- ordinary rural with neighborhoods: neighborhood;
- verified single-neighborhood village: village itself;
- city can represent its own regional layer when verified single-region, but neighborhood remains the normal smallest urban official base.

Below that base:

- no systemic elections;
- no automatic official manager/inspector hierarchy;
- no automatic upstream representation;
- optional Community groups may have ordinary group capabilities and internal governance as separately configured.

Pending group requests have none of those active capabilities until materialized.

---

## 9. Track A — Production selector recovery

This is a release-blocking repair and must be implemented first.

### 9.1 Real reference-data regression

Add a test that:

1. runs the real `ReferenceGeographyImporter` for `IR/v1`;
2. resolves `IR-COUNTRY` through its external identity;
3. calls `GET /location/options/{iran}/children`;
4. asserts HTTP 200;
5. asserts Mazandaran is returned with Persian label `مازندران` when locale is `fa`;
6. continues at least one more child hop to prove the imported branch is traversable.

This closes the current gap where API tests use factories but do not prove the imported Production dataset works end-to-end.

### 9.2 Existing residence hydration

Profile edit must render and replay the complete canonical residence path even when there is no pending proposal. Pending proposal chains append to that canonical path.

Requirements:

- the current residence path is visible from root to endpoint;
- the selector is preselected through that path after page load;
- changing an ancestor clears stale descendants;
- Persian locale uses `localized_names.fa` with canonical fallback only when localization is absent;
- server-rendered current-location summary uses the same localization helper/precedence as the picker.

### 9.3 Registration picker

Registration Step 3 and profile edit must use the same selector core and the same API contract. Selecting Iran must load its actual imported children. Errors must remain explicit (`loading`, `empty`, `error`, `stale`) and must not silently collapse into an empty picker.

### 9.4 Service worker

Location picker endpoints are ordinary same-origin network requests. Service-worker behavior must not cache stale location JSON or mask server errors. Tests should verify the selector uses network responses and reports non-2xx responses as errors.

---

## 10. Submit-button validity

Residence submit validity is policy-driven, not simply `is_residence_endpoint` on every type.

The button remains disabled until one of these conditions is true:

1. a verified normal neighborhood or a deeper allowed residence detail is selected;
2. a deepest pending proposal chain has reached the required neighborhood-equivalent depth;
3. a verified single-neighborhood village is selected;
4. a canonical village plus a pending `single_neighborhood` structural claim is selected, allowing non-blocking registration while the claim remains under review.

A city, urban region, rural district, or ordinary multi-neighborhood village alone must not enable residence submission.

Server-side validation must enforce the same rule. Client-side state is only UX, never the security/integrity boundary.

---

## 11. Localization

All user-facing location labels follow:

1. requested/current app locale from `localized_names`;
2. Persian fallback for Persian UI where appropriate;
3. canonical name only as final fallback.

The current Production symptom where `Sari Reference Neighborhood` is shown in Persian profile UI must be covered by a regression test.

The same display-name resolver should be reused by picker serialization, profile current-residence summary, governance summaries, and pending proposal labels where possible.

---

## 12. Review queue and admin behavior

Admin review must show:

- proposal/claim type;
- localized display name;
- parent/effective parent context;
- distinct supporter count and configured threshold;
- `pending`, `ready_for_review`, or `needs_evidence` status;
- dependent pending group requests;
- whether descendants are waiting for parent resolution.

The 10-user threshold is evidence for review, not approval authority.

Approving/merging a location proposal must trigger dependent pending-group reconciliation idempotently. Rejecting must transition dependent requests safely and must preserve audit history.

---

## 13. Testing strategy

Every production change follows RED → GREEN → full regression.

Minimum new/updated contracts:

1. real imported Iran root→province traversal;
2. canonical profile path hydration without pending intent;
3. Persian current-residence summary;
4. mandatory minimum residence depth;
5. pending urban-region→pending neighborhood chain can complete registration;
6. pending village→pending neighborhood chain can complete registration;
7. single-region structural claim branch;
8. single-neighborhood structural claim registration;
9. successful residence commit records one support per user;
10. mere selector change does not record support;
11. 10 unique committed users → `ready_for_review`, never approved;
12. later users see and can select open proposals;
13. pending official scope creates/reuses pending official-public group request without formal membership/elections;
14. pending street/alley/complex/building can receive Community group request;
15. pending group request materializes after location approval/merge;
16. rejected proposal rejects dependent request;
17. official governance chain ends at neighborhood-equivalent scope;
18. below-neighborhood Community never enters systemic elections;
19. project-scope stop-at-any-level regression remains green;
20. Full Validation including Location/Governance, JS, Najm Hoda, Najm Bahar, Stock, and Full Project remains green.

---

## 14. Delivery sequence

### Release 1 — selector recovery

Only Track A changes. No structural-claim or pending-group schema changes. Goal: unblock Production registration/profile UAT immediately.

### Release 2 — committed support and minimum-depth enforcement

Make residence save record support transactionally; enforce policy-based minimum depth in UI and backend; preserve deep pending chains.

### Release 3 — structural claims

Add single-region/single-neighborhood claim lifecycle, support threshold, selector branch behavior, and admin review.

### Release 4 — pending group requests

Add pending group shell, official/community materialization, approval/merge/rejection reconciliation, and UI.

Each release requires its own exact-SHA Full Validation and UAT checkpoint before merge/deploy.

---

## 15. Migration and production safety

- Never mutate or delete existing Production location/reference rows merely to introduce structural claims.
- New schema changes must be additive and rollback-safe.
- Existing canonical residence relationships remain valid.
- Existing approved GovernanceArea and Group records are not downgraded automatically.
- Backfill, if required, must be explicit, idempotent, auditable, and separately approved before Production execution.
- `main` is never edited directly; all work remains on feature/integration branches and is merged only after exact-candidate validation.

---

## 16. Acceptance examples

### Urban missing region and neighborhood

`Iran → Mazandaran → ... → City A → proposed Region R → proposed Neighborhood N`

Registration completes. Region R and Neighborhood N remain pending. The residence anchor remains nearest canonical ancestor. Saving contributes one distinct support by that user to the selected open chain. A pending official-public group request may exist for N but no official election or formal governance membership activates until review succeeds.

### Single-region city

`City B → "این شهر تک‌منطقه‌ای است" (pending claim) → proposed/selected Neighborhood Q`

No fake `Single Region` Location is created. Registration can continue to neighborhood. The structural claim and location proposal are reviewed independently but their dependencies are visible to admin.

### Single-neighborhood village

`Rural District → Village V → "این روستا تک‌محله‌ای است" (pending claim)`

Registration completes with canonical Village V as residence anchor. The claim is pending. A local group whose neighborhood-equivalent legitimacy depends on this claim remains pending until claim approval. On approval, Village V becomes the verified smallest official base without creating a synthetic neighborhood.

### Pending micro-location Community

`Neighborhood → proposed Street S`

The user may request a Community group for Street S immediately. The request is visible as pending but is not an active Group/GovernanceArea. If S is approved or merged, the request materializes idempotently. If S is rejected, the request is rejected without leaking into systemic governance.
