# Reputation + Invitation + Private Messaging Integration Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Reconcile the frozen R6 Reputation/Participation Points implementation into the latest private-messaging branch so the branch contains the final Reputation, invitation lifecycle, participation gate, and private messaging work together without changing `main`.

**Architecture:** Treat `agent/private-messaging-professionalization` as the integration target and `agent/r3-reputation-close` / freeze commit `329ccee36be96543c42641672cdedf96b9b45d0e` as the Reputation source. Preserve all non-overlapping R6 files verbatim. Reconcile only overlapping invitation/economic call sites (`NajmBaharController`, `NajmBaharMembershipFeeController`, `config/reputation.php`, and the shared reputation contract test) so invitation hardening and stable Reputation event identity both survive. Validate user surfaces and all project gates before freezing the integrated head.

**Tech Stack:** Laravel/PHP, Blade, PHPUnit, GitHub Actions.

**Spec:** `docs/REPUTATION_PARTICIPATION_POINTS_HANDOFF.fa.md`

## Global Constraints

- Never merge or push directly to `main` during this integration.
- Preserve all current invitation lifecycle, membership participation gate, and private messaging Phase 1 behavior.
- Preserve the frozen R6 Reputation semantics and public/private privacy boundary.
- Use exact-head validation evidence before declaring the branch ready.
- PRs targeting `main`, if needed only to trigger CI, are validation-only and must never be merged.

---

### Task 1: Establish integration baseline

**Files:**
- Read: current branch / R6 compare metadata

- [ ] Confirm current private-messaging head and R6 freeze head.
- [ ] Compare both lineages from common merge base and enumerate overlaps.
- [ ] Confirm no changes to `main`.

### Task 2: Integrate R6 into latest private-messaging branch

**Files:**
- Integrate all R6-changed files from `329ccee36be96543c42641672cdedf96b9b45d0e`.
- Reconcile overlaps in:
  - `app/Http/Controllers/NajmBaharController.php`
  - `app/Http/Controllers/NajmBaharMembershipFeeController.php`
  - `config/reputation.php`
  - `tests/Feature/Reputation/ParticipationCallSiteIdempotencyContractTest.php`

- [ ] Merge R6 lineage into the private-messaging target branch through an integration PR.
- [ ] If GitHub reports conflicts, resolve only the overlapping files and preserve both feature contracts.
- [ ] Confirm Reputation service, migrations, R6 tests, profile/history wrappers, wallet, and admin audit files are present on the integrated head.

### Task 3: Verify the regression reported by UAT

**Files:**
- `resources/views/profile/profile-member.blade.php`
- `resources/views/profile/profile-member-base.blade.php`
- `resources/views/history/index.blade.php`
- `resources/views/history/index-base.blade.php`
- `app/Services/ParticipationPointSummaryService.php`
- `app/Http/Controllers/Profile/HistoryController.php`

- [ ] Confirm public profile renders the R6 Reputation card contract.
- [ ] Confirm `مشارکت‌های من → امتیازات من` uses the R6 private point summary and conversion CTA.
- [ ] Confirm public profile contains no private conversion/economic fields.

### Task 4: Integrated validation

**Tests:**
- Reputation feature/contracts
- Invitation lifecycle and participation-gate tests
- Private messaging Phase 1 tests
- Full project validation
- Responsive validation

- [ ] Run/trigger exact-head validations available on the branch.
- [ ] If the normal Full workflow requires a `main`-target PR, create/update a clearly marked validation-only PR and never merge it.
- [ ] Inspect any failure to root cause before changing code.
- [ ] Freeze the integrated head only after Full and Responsive validations are green.

### Task 5: Handoff

- [ ] Record integrated checkpoint, PR/run numbers, and UAT URLs.
- [ ] Keep functional work isolated from `main` until explicit final merge approval.
