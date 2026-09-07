# Founder Ministry Phase 4 Executive Agency Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an evidence-backed agency model to Founder Ministry so each report distinguishes executable-now, preparable, founder-decision-required, and blocked capabilities without creating a parallel execution path.

**Architecture:** Introduce a focused `FounderMinistryAgencyService` that combines the existing authority matrix, explicit delegation grants, connectivity evidence, and current ministry work items. `FounderMinistryChatService` supplies intent/domain scope; `FounderMinistryExecutivePresenter` attaches the agency object and keeps prose concise; `chat.blade.php` renders four agency lanes without client-side permission inference.

**Tech Stack:** Laravel 9, PHP 8.2, PHPUnit, Blade, GitHub Actions Full Validation.

**Spec:** `docs/superpowers/specs/2026-08-26-founder-ministry-executive-agency-design.md`

## Global Constraints

- Work only on `agent/najm-hoda-executive-uat-post-elections`.
- Do not merge to `main`.
- Preserve integrated elections ancestry.
- Existing `FounderActionExecutionService` remains the sole mutation execution gate.
- Typed ministry free text remains non-executable.
- Unknown actions remain fail-closed.
- Connectivity evidence, not policy declaration alone, determines actual capability.
- `secretariat.dispatch_formal_record` remains blocked until real transport exists.

---

### Task 1: Lock the agency contract with failing tests

**Files:**
- Create: `tests/Feature/NajmHoda/FounderMinistryAgencyServiceTest.php`

**Interfaces:**
- Consumes: `FounderActionAuthorityService::matrix()`, `FounderDelegationGrantService::active()`, `FounderExecutiveConnectivityService::report()`.
- Produces expected contract for `FounderMinistryAgencyService::describe(string $intent, array $items): array`.

- [ ] **Step 1: Write tests for delegated-safe without grant, active grant, pending approval, blocked dependency, forbidden action, and global current-work scoping.**
- [ ] **Step 2: Push tests only and confirm CI fails because `FounderMinistryAgencyService` does not exist.**
- [ ] **Step 3: Commit with `test(founder-ministry): define phase 4 agency contract`.**

### Task 2: Implement the focused agency service

**Files:**
- Create: `app/Services/NajmHoda/FounderOps/FounderMinistryAgencyService.php`
- Modify only if required for evidence access: `app/Services/NajmHoda/FounderOps/FounderExecutiveConnectivityService.php`

**Interfaces:**
- Produces: `describe(string $intent, array $items): array` with `scope`, `domain_keys`, `connected`, `active_delegations`, `may_do_now`, `may_prepare`, `needs_founder_decision`, `blocked`, `summary`.

- [ ] **Step 1: Implement canonical intent-to-domain mapping from the approved spec.**
- [ ] **Step 2: Derive executable-now only from connected `delegated_safe` actions with an active explicit grant.**
- [ ] **Step 3: Derive preparation capability from connected `propose`/`delegated_safe` actions without claiming execution.**
- [ ] **Step 4: Derive founder decisions only from current approval work items.**
- [ ] **Step 5: Derive blocked entries from `missing`, `blocked_dependency`, and `forbidden/protected` evidence relevant to scope.**
- [ ] **Step 6: Run the agency tests and confirm green.**
- [ ] **Step 7: Commit with `feat(founder-ministry): add evidence-backed agency model`.**

### Task 3: Integrate agency into ministry responses

**Files:**
- Modify: `app/Services/NajmHoda/FounderOps/FounderMinistryChatService.php`
- Modify: `app/Http/Controllers/Admin/FounderMinistryChatController.php`
- Modify: `tests/Feature/NajmHoda/FounderMinistryChatServiceTest.php`

**Interfaces:**
- `FounderMinistryChatService::respond()` continues returning the existing management payload.
- Controller/presenter path adds agency without enabling typed execution.

- [ ] **Step 1: Add failing tests that domain and global ministry responses contain the correct agency scope.**
- [ ] **Step 2: Wire `FounderMinistryAgencyService` into the response path after canonical items are decorated.**
- [ ] **Step 3: Keep readiness contract explicitly `read_only_decision_support`, `typed_execution_inference=false`, `approval_bypass=false`.**
- [ ] **Step 4: Run focused service/controller tests and confirm green.**
- [ ] **Step 5: Commit with `feat(founder-ministry): attach agency evidence to reports`.**

### Task 4: Make executive prose concise

**Files:**
- Modify: `app/Services/NajmHoda/FounderOps/FounderMinistryExecutivePresenter.php`
- Modify: `tests/Unit/NajmHoda/FounderMinistryExecutivePresenterTest.php`

**Interfaces:**
- Presenter preserves action assessment and action text.
- Domain prose no longer repeats all raw metric values; metric cards remain authoritative for detail.

- [ ] **Step 1: Add a failing test proving a quiet groups brief does not repeat `کل گروه‌ها: 81` in prose.**
- [ ] **Step 2: Add a failing test proving the agency summary is preserved in `management.executive.agency`.**
- [ ] **Step 3: Replace metric-dump domain prose with concise conclusion plus item count.**
- [ ] **Step 4: Run presenter tests and confirm green.**
- [ ] **Step 5: Commit with `refactor(founder-ministry): keep executive prose decision focused`.**

### Task 5: Render four agency lanes in the admin ministry UI

**Files:**
- Modify: `resources/views/admin/najm-hoda/chat.blade.php`
- Modify: `tests/Feature/NajmHoda/FounderMinistryChatViewTest.php`

**Interfaces:**
- Backend supplies all authority decisions.
- JavaScript only renders `management.executive.agency`.

- [ ] **Step 1: Add failing view-contract assertions for `توان اجرایی نجم هدا در این گزارش`, `خودم می‌توانم انجام دهم`, `می‌توانم آماده کنم`, `تصمیم شما لازم است`, `فعلاً مسدود است`.**
- [ ] **Step 2: Add a failing assertion that no JavaScript checks authority modes or delegation rules.**
- [ ] **Step 3: Add the four-lane markup and a renderer that consumes the backend agency object.**
- [ ] **Step 4: Render explicit empty-state text for empty lanes.**
- [ ] **Step 5: Run view tests and confirm green.**
- [ ] **Step 6: Commit with `feat(founder-ministry): show executive agency lanes`.**

### Task 6: Regression and Full Validation

**Files:**
- No production changes unless a regression is found.

**Interfaces:**
- Phase 3 semantics and all project gates remain intact.

- [ ] **Step 1: Run focused Phase 4 tests plus existing Founder Ministry tests.**
- [ ] **Step 2: Verify `secretariat.dispatch_formal_record` remains blocked and vote/result alteration remains forbidden.**
- [ ] **Step 3: Run Full Validation through the draft PR.**
- [ ] **Step 4: If any failure is caused by Phase 4, fix root cause with a new failing regression test first.**
- [ ] **Step 5: Confirm branch remains unmerged to `main` and report the final checkpoint SHA and CI run.**
