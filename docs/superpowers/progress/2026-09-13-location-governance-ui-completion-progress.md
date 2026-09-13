# Location / Governance UI Completion — Progress Handoff

**Branch:** `agent/location-governance-ui-completion-20260913`  
**Base:** `main@e253797392257a95e6e84c78ceaf7c936516ceb4`  
**Draft PR:** #112  
**Design:** `docs/superpowers/specs/2026-09-13-location-governance-ui-completion-design.md`  
**Implementation plan:** `docs/superpowers/plans/2026-09-13-location-governance-ui-completion.md`

This file is the durable handoff/checkpoint for continuing in a new chat. Update it after each meaningful RED/GREEN checkpoint. Do not infer Production rollout from this file; all Location/Governance feature flags remain dark by default unless separately verified and explicitly enabled.

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
- RED contract was followed by implementation and root-cause fix in `ResidenceService::currentPrimaryResidence()`.
- Full Validation #2585 (`34786853086`) on commit `c276703d12ef39b0ea9b05372c921973c07bddde` completed SUCCESS, including Location/Governance and Full Project.
- Page exposes approved residence, pending exact intent, official GovernanceArea chain, active/observer memberships, and Communities separately.
- Sidebar entry and endpoint are dark-launched behind `runtime_enabled`; endpoint returns 404 while flag is off.

### Task 9 — Policy-safe Community Area UX
- COMPLETE / GREEN.
- RED test commit: `e40e8793a117cca5b032f23e4056db8977af7eb4`.
- Full Validation #2586 (`34787507853`) produced the intended isolated RED: Location/Governance and Full Project failed only because the new Community create action/route were absent; existing Community creation/election-boundary regressions stayed green.
- Implemented `CommunityAreaController`, authenticated `location-governance.community.store`, policy-backed create-action state, and separate Community copy on the user page.
- POST delegates to `CommunityAreaService::createFor()`; eligibility remains canonical in `CommunityCreationPolicy` and is not hard-coded in Blade.
- No create action is exposed for a pending residence intent, an existing Community, missing approved residence, or policy rejection.
- Community creation remains idempotent and does not create a formal systemic-election tier.
- Exact verified HEAD: `682d8a8f60b54af934aa3c0483f8d25f7d4b3c94`.
- Full Validation #2591 (`34789091796`) completed SUCCESS on that exact HEAD, including Location/Governance and Full Project.

## Current task

### Task 10 — Admin Location/Governance Control Center completion

**Status:** ready to begin RED contracts.

Current control center already has:
- human-gated proposal review and Hoda recommendations;
- import run summary;
- governance summary counts;
- explicit audited approve/reject/merge/request-evidence actions.

Still required by the approved plan:
- focused proposal queue partial;
- reference geography explorer;
- official governance topology view;
- Community overview;
- import diagnostics;
- health diagnostics;
- bounded read models for those sections;
- no raw official-topology mutation in this phase.

## Safety invariants still locked
- `Location != GovernanceArea != Group`.
- Pending Location Proposal is never a canonical Location FK and never automatically creates formal governance.
- Community Area is optional/on-demand and remains outside official systemic-election topology by default.
- Community eligibility comes from `CommunityCreationPolicy`; creation comes from `CommunityAreaService`.
- Admin Control Center additions in Task 10 are operational read models plus already-existing human-gated proposal writes; no raw topology mutation is permitted.
- No direct changes to `main`.
- Draft PR #112 remains unmerged.
- No destructive Production operation.
- Feature flags remain dark/default false unless separately verified and explicitly approved.

## Next exact action
1. Add RED contracts to `tests/Feature/Admin/LocationGovernanceControlCenterTest.php` for proposal queue, reference explorer, official topology, Community overview, import diagnostics, and health diagnostics.
2. Run Full Validation and confirm failure is isolated to the new Task 10 contracts.
3. Add bounded controller read models and the exact focused Blade partials required by the plan.
4. Re-run to GREEN before starting Task 11.
5. Update this handoff file with Task 10 RED/GREEN commit and CI evidence.
6. Do not merge to `main`; final merge remains gated by Task 12 full exact-candidate validation and explicit user approval.
