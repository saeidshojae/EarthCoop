# Residence Picker Deep Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the Production-UAT residence-picker defects in one coherent change: Persian localization, stable proposal UI, and safe multi-depth pending proposal chains.

**Architecture:** Keep canonical Locations immutable as the official tree and represent unresolved depth solely as a linked LocationProposal chain. Extend the existing options/proposal APIs rather than introducing a second picker. Re-anchor pending children only when a parent is approved/merged; never auto-canonicalize descendants.

**Tech Stack:** Laravel 9/PHP, Eloquent, Blade, vanilla JavaScript, PHPUnit, Node test runner.

**Spec:** `docs/superpowers/specs/2026-09-16-residence-picker-deep-hardening-design.md`

## Global Constraints
- `Location != GovernanceArea`; pending proposals never become canonical implicitly.
- Existing proposal rows remain valid.
- Exactly one effective parent for new proposals: canonical Location or open LocationProposal.
- No overall Step 3 redesign.
- No Production writes during implementation.
- Full Validation runs only after focused suites and final diff audit are green.

---

### Task 1: Lock localization and UI contracts RED→GREEN
**Files:** Modify `tests/Feature/LocationGovernance/LocationPickerProposalContractTest.php`; modify `tests/js/location-governance/location-selector.test.mjs`; modify `app/Http/Controllers/LocationGovernance/LocationOptionsController.php`; modify `resources/js/location-selector.js`; modify `resources/js/registration-location-ux.js`.

**Interfaces:** `serializeAllowedType()` returns locale-aware `label`; JS known-type mapping remains authoritative fallback; runtime toggle exposes `location-proposal-toggle`/green presentation.

- [ ] Add failing PHP assertions that every known Iranian schema type label is Persian under `fa` and unknown type labels safely fall back.
- [ ] Add failing JS assertions that breadcrumb/select/proposal controls never render known English type names and proposal toggle has semantic styling hook.
- [ ] Run only these PHP/JS tests and confirm expected RED failures.
- [ ] Implement minimal locale-aware type-label helper and stable proposal-toggle class/style.
- [ ] Re-run focused tests to GREEN and commit.

### Task 2: Add proposal-parent persistence contract RED→GREEN
**Files:** Create additive migration `database/migrations/2026_09_16_000001_add_parent_location_proposal_to_location_proposals.php`; modify `app/Models/LocationProposal.php`; modify proposal service tests.

**Interfaces:** `LocationProposal::parentProposal()` and `childProposals()`; nullable `parent_location_proposal_id`; existing `parent_location_id` remains supported.

- [ ] Write failing model/service tests proving legacy canonical-parent rows remain valid and pending-parent rows can be represented without canonical parent fabrication.
- [ ] Run focused test and confirm RED because column/relations do not exist.
- [ ] Add nullable self-FK migration and model relations/fillable field; enforce exactly-one-parent at service validation layer for cross-DB compatibility.
- [ ] Re-run focused tests to GREEN and commit.

### Task 3: Proposal policy/service supports pending parents RED→GREEN
**Files:** Modify `app/Services/LocationGovernance/LocationProposalPolicy.php`; modify `app/Services/LocationGovernance/LocationProposalService.php`; modify/create focused service tests.

**Interfaces:** service accepts canonical or proposal parent through explicit methods/union-safe internal parent context; child inherits schema/country; duplicate/reuse scope includes effective parent.

- [ ] Write failing tests for street→alley→complex→building chain, rural village→neighborhood→street chain, invalid relation, disallowed type, terminal parent, and duplicate/reuse under same proposal parent.
- [ ] Verify RED failures are feature-missing failures.
- [ ] Implement minimal pending-parent policy and proposal creation/reuse logic.
- [ ] Run focused tests to GREEN and commit.

### Task 4: Resolution/re-anchor semantics RED→GREEN
**Files:** Modify `app/Services/LocationGovernance/LocationProposalService.php`; focused proposal workflow tests.

**Interfaces:** approve/merge re-anchor direct open children to canonical location; reject throws DomainException when open descendants exist.

- [ ] Write failing tests for approve re-anchor, merge re-anchor, descendants staying pending, rejection blocked with open descendants, and no orphan/cascade deletion.
- [ ] Verify RED.
- [ ] Implement transactional re-anchor and rejection guard.
- [ ] Re-run focused tests to GREEN and commit.

### Task 5: API/read model for proposal children RED→GREEN
**Files:** Modify `app/Http/Controllers/Location/LocationProposalController.php`; modify `app/Http/Controllers/LocationGovernance/LocationOptionsController.php` or add narrowly scoped proposal-children action; modify routes; feature tests.

**Interfaces:** POST accepts exactly one of `parent_location_id`/`parent_location_proposal_id`; proposal children payload uses existing `{data, proposals, allowed_types}` shape and returns `data: []` for unresolved parent.

- [ ] Write failing endpoint tests for child loading, open-status enforcement, localized allowed types, exactly-one-parent validation and auth.
- [ ] Verify RED.
- [ ] Implement endpoint/read model and request validation.
- [ ] Re-run focused tests to GREEN and commit.

### Task 6: Picker traverses and hydrates proposal chains RED→GREEN
**Files:** Modify `resources/js/location-selector.js`; modify `resources/js/registration-location-ux.js`; modify `tests/js/location-governance/location-selector.test.mjs`; relevant residence feature tests.

**Interfaces:** proposal option carries children URL/parent identity; selecting proposal may append deeper proposal-only level; deepest proposal remains selected; canonical anchor is preserved separately.

- [ ] Add failing JS tests for proposal traversal, multi-depth chain, stale/network preservation, Persian path, refresh hydration and mobile/touch hooks.
- [ ] Verify RED.
- [ ] Implement minimal proposal-child loading, proposal-parent POST payload and path rendering/hydration.
- [ ] Re-run JS + residence feature tests to GREEN and commit.

### Task 7: PendingResidenceIntent integration and invariant audit
**Files:** Modify residence service/controller tests and production code only if tests expose a gap.

**Interfaces:** deepest proposal ID is intent target; anchor relationship remains nearest approved Location; intermediate parent resolution does not prematurely resolve deepest intent.

- [ ] Write failing integration test for a multi-depth proposal chain submitted as residence.
- [ ] Verify anchor/deepest-intent behavior and RED only where implementation is missing.
- [ ] Implement the minimum required integration correction.
- [ ] Run focused Location/Governance and registration/profile suites to GREEN and commit.

### Task 8: Final verification gate
**Files:** no production changes unless verification exposes a real regression.

- [ ] Audit branch diff against spec: localization, urban/rural, chain semantics, resolution, UI, invariants, no unrelated Step 3 redesign.
- [ ] Run focused PHP Location/Governance tests and JS tests; require zero failures/warnings attributable to change.
- [ ] Run Full Validation once on exact final HEAD.
- [ ] Inspect Full Validation logs and counts, then open/update PR with exact HEAD and evidence.
- [ ] Do not merge until explicit user approval.