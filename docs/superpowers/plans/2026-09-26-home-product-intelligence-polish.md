# Home Product Intelligence & Polish Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn the current redesigned Home into a state-aware personal civic dashboard without changing domain behavior or losing the Welcome/Register visual language.

**Architecture:** Add one focused read-model service that composes already-authoritative state for Home. Keep `HomeController` responsible for orchestration only, keep Blade presentation-only, and extend the existing responsive/static contracts plus focused feature tests. Visual polish is limited to Home/sidebar/Najm Hoda launcher presentation.

**Tech Stack:** Laravel 9, Blade, Eloquent, existing EarthCoop services/read models, Node `node:test` responsive contracts.

**Spec:** `docs/superpowers/specs/2026-09-26-home-product-intelligence-polish-design.md`

## Global Constraints

- Visual reference: Welcome/Landing + Register Step 1/2.
- No new domain authority in Blade.
- No Location/Governance, election, membership, invitation, or Najm Bahar business-rule changes.
- Mobile and desktop both require deliberate composition.
- Keep existing Home slider/admin content and canonical group routes/count semantics.
- Use targeted RED/GREEN first; Full Validation only as final gate.
- No direct changes on `main`.

## Review Focus

- A user with no Najm Bahar account must never see an "active" account state; test task 1.
- A user with election action required must outrank invite/poll recommendations; test task 1.
- Poll counts must exclude polls already voted by the user and expired/inactive polls; test task 1.
- Zero-state must not render a noisy four-metric grid; test task 2.
- Najm Hoda launcher must not cover mobile Home CTA/footer content; static responsive contract in task 4.

---

### Task 1: Home civic state read model

**Files:**
- Create: `app/Services/Home/HomeCivicDashboardService.php`
- Create: `tests/Feature/Home/HomeCivicDashboardServiceTest.php`
- Modify: `app/Http/Controllers/HomeController.php`

**Interfaces:**
- Consumes: `ProfileCompletionService::hasRequiredResidence(User)`, `AccountService::hasMainAccount(int)`, `InvitationLifecycleService::remainingSlots(User)`, `CurrentElectionCenterService::forUser(User)`, canonical pending-location-group service when enabled, existing Poll/Group membership relations.
- Produces: `HomeCivicDashboardService::forUser(User $user, array $groupCounts, int $pendingLocationGroupCount = 0): array` with keys `journey`, `today`, `next_action`.

- [ ] **Step 1: Write failing feature tests**
  - Journey reports canonical group total from supplied counts.
  - Najm Bahar card reports inactive when `hasMainAccount()` is false.
  - Invitation card reports `remaining_slots` from `InvitationLifecycleService`.
  - `today.election_action_required` comes from `CurrentElectionCenterService` summary.
  - `today.poll_action_required` counts only active, unexpired main polls in active user groups with no user vote.
  - Recommendation priority is Najm Bahar → election → poll → invitation → civic-anchor fallback (residence defensive fallback before all).

- [ ] **Step 2: Run focused test and verify RED**

Run: `php artisan test tests/Feature/Home/HomeCivicDashboardServiceTest.php`
Expected: FAIL because service does not exist.

- [ ] **Step 3: Implement `HomeCivicDashboardService::forUser(...)`**

Keep the service read-only. Return presentation-ready labels/counts/route names but no HTML.

- [ ] **Step 4: Wire `HomeController` to the service**

Controller keeps existing canonical group materialization/count logic, passes exact counts/pending count to the read model, and sends `$homeDashboard` to Blade.

- [ ] **Step 5: Re-run focused test and verify GREEN**

Run: `php artisan test tests/Feature/Home/HomeCivicDashboardServiceTest.php`
Expected: PASS.

---

### Task 2: State-aware Home journey and Today surface

**Files:**
- Modify: `resources/views/home.blade.php`
- Modify: `tests/js/responsive/home-onboarding-contract.test.js`
- Create: `tests/Feature/Home/HomeDashboardViewContractTest.php`

**Interfaces:**
- Consumes: `$homeDashboard['journey']`, `$homeDashboard['today']`, `$homeDashboard['next_action']` from task 1.
- Produces: state-aware journey cards, compact Today surface, deterministic next-action panel.

- [ ] **Step 1: Write failing view/static contracts**
  - Home reads `$homeDashboard`, not `AccountService` or domain models directly.
  - Four journey cards render status/value text from the read model.
  - Today surface exposes notifications/elections/polls/pending-location signals.
  - Zero-state copy exists when all Today signals are zero.
  - Next action renders route/label/description from `next_action`.

- [ ] **Step 2: Run focused contracts and verify RED**

Run: `php artisan test tests/Feature/Home/HomeDashboardViewContractTest.php`
Run: `node --test tests/js/responsive/home-onboarding-contract.test.js`
Expected: new assertions FAIL against current Home.

- [ ] **Step 3: Implement minimal state-aware Blade**

Preserve canonical group cards and admin slider/content. On mobile, completed journey cards use compact density; incomplete/action-required states remain visually stronger.

- [ ] **Step 4: Verify focused contracts GREEN**

Run the two commands above; both must pass.

---

### Task 3: Visual restraint + secondary-content hierarchy

**Files:**
- Modify: `resources/views/home.blade.php`
- Modify: `tests/js/responsive/home-onboarding-contract.test.js`

**Interfaces:**
- Consumes: existing Home visual primitives.
- Produces: one primary identity stripe, lighter secondary surfaces, lower-weight admin/news content.

- [ ] **Step 1: Add RED assertions**
  - Only the Hero identity surface uses the full tri-color stripe pseudo-element.
  - Secondary section surfaces do not reuse `home-identity-surface::before` semantics.
  - Admin/news section appears after personal journey/today/groups surfaces in markup.

- [ ] **Step 2: Run responsive contract and verify RED**

- [ ] **Step 3: Simplify secondary section decoration and typography**

No new colors, no stronger shadows, no new animation family.

- [ ] **Step 4: Run responsive contract and verify GREEN**

---

### Task 4: Sidebar hierarchy and Najm Hoda safe area

**Files:**
- Modify: `resources/views/partials/sidebar-unified.blade.php`
- Modify: `resources/views/components/najm-hoda-widget.blade.php`
- Create: `tests/js/responsive/home-shell-polish-contract.test.js`

**Interfaces:**
- Consumes: existing sidebar routes/items and Najm Hoda widget behavior.
- Produces: visual section labels/grouping only; safe-area-aware launcher positioning.

- [ ] **Step 1: Write failing static responsive tests**
  - Sidebar contains lightweight section labels separating network/governance/economy/account-support groups without changing route names.
  - Mobile launcher uses `env(safe-area-inset-bottom)` in its offset and a smaller Home-mobile footprint.
  - Launcher remains above footer/CTA area and retains touch target >= 44px.

- [ ] **Step 2: Run test and verify RED**

Run: `node --test tests/js/responsive/home-shell-polish-contract.test.js`
Expected: FAIL.

- [ ] **Step 3: Implement presentation-only grouping/safe-area CSS**

No route or capability changes.

- [ ] **Step 4: Run test and verify GREEN**

---

### Task 5: Focused regression + visual contract gate

**Files:**
- Modify only if failures prove a real defect in tasks 1–4.

**Interfaces:**
- Consumes: all prior tasks.
- Produces: fixed-SHA candidate ready for real-device UAT.

- [ ] **Step 1: Run focused Home tests**

Run:
`php artisan test tests/Feature/Home/HomeCivicDashboardServiceTest.php tests/Feature/Home/HomeDashboardViewContractTest.php`

- [ ] **Step 2: Run responsive contracts**

Run: `node --test tests/js/responsive/*.test.js`

- [ ] **Step 3: Static/diff audit**

Confirm no domain migrations/config/flags changed, no route changed, no canonical count semantics changed, and no new palette token was introduced.

- [ ] **Step 4: Run repository Full Validation once on fixed SHA**

Expected: all mature subsystem regressions + Full Project + Enforce gate pass.

- [ ] **Step 5: Deploy only after fixed-SHA green and perform desktop/mobile visual UAT**

UAT focus: hierarchy in 1366 desktop, 375/390 mobile, Home total scroll length, compact completed journey cards, Today zero/action states, sidebar scanability, Najm Hoda overlap, footer clearance.
