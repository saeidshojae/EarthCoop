# Location / Governance UI Completion — Progress Handoff

**Branch:** `agent/location-governance-ui-completion-20260913`  
**Base:** `main@e253797392257a95e6e84c78ceaf7c936516ceb4`  
**Draft PR:** #112  
**Design:** `docs/superpowers/specs/2026-09-13-location-governance-ui-completion-design.md`  
**Implementation plan:** `docs/superpowers/plans/2026-09-13-location-governance-ui-completion.md`

This file is the durable handoff/checkpoint for this workstream. Do not infer Production rollout from this file. All Location/Governance feature flags remain dark by default unless separately verified and explicitly enabled.

## Completed tasks

### Task 1 — Pending Residence Intent
- COMPLETE / GREEN.
- Added persistent pending residence intent domain model/migration and relations.

### Task 2 — Residence lifecycle and safe proposal convergence
- COMPLETE / GREEN.
- Proposal approval/merge resolves only current, non-stale intents.
- Proposal convergence is refinement, not explicit residence transfer; transfer quota is not consumed.

### Task 3 — Shared Location Picker API read model
- COMPLETE / GREEN.
- Root/children payloads expose canonical Locations, open proposals, and allowed child types.
- Crowdsourced proposal policy is schema-metadata driven and default-deny.

### Task 4 — Shared Location Picker proposal UX
- COMPLETE / GREEN.
- Registration/profile share arbitrary-depth selector with stable `location:<id>` / `proposal:<id>` identities.
- Pending proposals remain visibly pending and never become canonical Location IDs.

### Task 5 — Registration pending exact residence
- COMPLETE / GREEN.
- Exactly one approved Location or open Proposal is accepted.
- Approved proposal parent remains the canonical residence anchor while exact intent is pending.

### Task 6 — Profile residence editing/status
- COMPLETE / GREEN.
- Approved changes use true transfer semantics.
- Pending refinement under current anchor does not consume transfer quota.
- Pending proposal under another approved anchor performs one real anchor transfer then stores intent.

### Task 7 — Admin canonical residence editing
- COMPLETE / GREEN.
- Separate canonical residence card via safe `SafeUserController` wrapper.
- Focused `UserResidenceController`, required human reason, actor/audit preservation, policy-safe pending proposal handling.

### Task 8 — “My Location & Governance” page
- COMPLETE / GREEN.
- Page exposes approved residence, pending exact intent, official GovernanceArea chain, active/observer memberships, and Communities separately.
- Sidebar entry and endpoint remain dark-launched behind the Location/Governance runtime flag.

### Task 9 — Policy-safe Community Area UX
- COMPLETE / GREEN.
- Community creation delegates to `CommunityAreaService::createFor()` and canonical eligibility remains in `CommunityCreationPolicy`.
- Pending proposals never create formal governance or a Community automatically.
- Community remains outside official systemic-election topology by default.

### Task 10 — Admin Location/Governance Control Center
- COMPLETE / GREEN.
- Added bounded read models and focused views for proposal queue, reference explorer, official topology, Community overview, import diagnostics, and health diagnostics.
- Existing approve/reject/merge/request-evidence actions remain explicit, CSRF-protected, human-gated writes; Hoda remains recommendation-only.
- No raw official-topology mutation was added.
- Verified Task 10 GREEN checkpoint: `382228eec478cb2f96541ce7a9a2d3b5d387657c`.
- Full Validation #2606 (`34821466451`) completed SUCCESS on that exact checkpoint.

### Task 11 — UX hardening for alternate schemas, errors, accessibility, and responsive states
- COMPLETE / GREEN.
- Intentional RED contract checkpoint: `ef1a498d1d5b21457131c58834eb881c341e5cc6`.
- Full Validation #2612 isolated exactly three expected gaps: registration forced `IR`, inactive proposal parent was accepted, and global root discovery returned no roots without a country filter.
- GREEN implementation checkpoint: `418361c4a8157b19d52d88afdecaa92ba546b858`.
- Registration no longer hard-codes Iran; the selector treats country filtering as optional while preserving filtered root behavior when a country is supplied.
- Root discovery is global/schema-driven when no country is supplied.
- Inactive parents are rejected server-side with validation error before a Location proposal can be created.
- Client normalization removes inactive canonical locations and terminal proposals from stale payloads.
- Selection mapping rejects stale/terminal identities.
- Explicit `loading`, `empty`, `error`, and `stale` UI states are exposed with text/ARIA semantics; dynamically created levels have visible labels.
- Proposal network failure preserves typed input/path and prior valid selection.
- Arbitrary-depth/alternate-schema behavior remains server-driven; no Iran-specific micro-location ordering was introduced.
- Full Validation #2613 (`34826072015`) completed SUCCESS on the GREEN implementation checkpoint.
- The previously omitted frontend gate was then added to Full Validation: `npm run test:location-governance` now runs as `Regression — Location / Governance JavaScript` and participates in the final regression gate.
- Exact Task 11 verified HEAD after CI hardening: `ddc2e2c8d3e06bd2196668767faa635a984fc34e`.
- Full Validation #2614 (`34827091450`) completed SUCCESS on that exact HEAD.
- #2614 evidence: Location/Governance **198 tests / 1012 assertions**; Location/Governance JavaScript **9/9 passing**; Full Project PHPUnit **1687 tests / 9067 assertions**, 0 failures/errors, 2 skipped, 47 existing deprecations.

## Task 12 — Stage-C UI Completion Integration Gate / Release Readiness

**Status:** implementation complete; exact final documentation checkpoint awaiting its own fresh Full Validation before merge approval.

Release-readiness audit completed on `ddc2e2c8d3e06bd2196668767faa635a984fc34e`:
- Branch is **85 commits ahead and 0 behind** `main@e253797392257a95e6e84c78ceaf7c936516ceb4`.
- Diff is limited to Location/Governance UI/domain/supporting tests/docs plus the validation workflow/package script needed to enforce the new frontend regression gate.
- No Production `.env` file is changed by the PR.
- `config/location-governance.php` keeps `runtime_enabled`, `registration_enabled`, `groups_enabled`, `elections_enabled`, and `projects_enabled` defaulted to `false`.
- The only new migration in this UI-completion workstream creates `pending_residence_intents`; it does not drop or rewrite legacy tables in `up()`.
- No Production feature flag has been enabled.
- No destructive Production operation has been performed.
- PR #112 remains Draft and unmerged.

## Post-cutover UI/UAT and mobile-polish gate — REQUIRED before Location/Governance rollout is considered fully closed

- Re-verify every Location/Governance UI synchronization against real Production behavior after the backend cutover, not only against automated tests.
- Explicitly exercise registration, profile residence editing, admin residence editing, My Location & Governance, My Groups, Current Elections, election portal, proposal states, Community UX, and admin Location/Governance control-center flows with canonical Production data.
- Verify that every UI reads the same canonical Location/Governance/membership/election state as its backend contract and that no legacy spatial row leaks into a canonical screen.
- Perform a dedicated responsive/mobile UAT pass after functional rollout stability: phone-width navigation, cards/tables, hierarchy rendering, forms/selectors, dialogs, touch targets, overflow, RTL, loading/error/empty/stale states, and Hoda widget coexistence.
- Polish mobile layouts where desktop-first markup is merely technically responsive but not comfortably usable.
- Do not close the Location/Governance UI workstream until this real-Production synchronization pass and mobile-specific polish pass have explicit evidence/checkpoints.

## Safety invariants locked
- `Location != GovernanceArea != Group`.
- Pending Location Proposal is never a canonical Location FK and never automatically creates formal governance.
- Community Area is optional/on-demand and remains outside official systemic-election topology by default.
- Community eligibility comes from `CommunityCreationPolicy`; creation comes from `CommunityAreaService`.
- Admin Control Center additions are operational read models plus existing human-gated proposal writes; no raw topology mutation is permitted.
- No direct changes to `main`.
- No destructive Production operation.
- Feature flags remain dark/default false unless separately verified and explicitly approved.

## Next exact action
1. Run Full Validation on the exact documentation-updated HEAD.
2. Confirm Location/Governance PHP, Location/Governance JavaScript, build, migrations, mature subsystem regressions, Full Project PHPUnit, and final regression gate are all GREEN on that exact SHA.
3. Re-check PR #112 head/base/mergeability and verify no unexpected branch movement.
4. Stop and request explicit merge approval. Do **not** merge to `main` without that approval.