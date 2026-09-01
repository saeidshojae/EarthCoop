# Membership Participation Gate Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Require a canonical Najm Bahar account plus paid current membership fee before member invitations or group-chat message sending, while keeping group reading available and making invitation-page rules dynamic.

**Architecture:** Add one shared eligibility service that derives `no_najm_bahar_account`, `membership_fee_due`, or `eligible` from AccountService plus canonical membership-fee transactions. Invitation issuance and MessageController consume the service directly. Group Chat receives the eligibility payload through ChatController/chat_runtime and loads one small dedicated JS gate; the invitation surface is moved to a focused dynamic view/controller route rather than risk rewriting the legacy oversized Blade.

**Tech Stack:** Laravel 9/PHP, Eloquent, Blade, existing Najm Bahar AccountService/Transaction model, PHPUnit Feature tests, GitHub Actions validation.

**Spec:** `docs/superpowers/specs/2026-09-01-membership-participation-gate-design.md`

## Global Constraints
- Work only on `agent/economic-system-current-integration`.
- Never merge to `main`.
- Preserve existing invitation lifecycle, quota race protection, profile-completion reward timing, and public invitation-request flow.
- Preserve Group Chat reading and all existing role/session/profile restrictions.
- Do not gate voting, polls, posts, comments, reactions, elections, private chat, or unrelated capabilities in this phase.
- Avoid whole-file rewrites of oversized UI files; use focused partial/runtime files.

---

### Task 1: Shared participation eligibility

**Files:**
- Create: `app/Services/MembershipParticipationEligibilityService.php`
- Test: `tests/Feature/NajmBahar/MembershipParticipationEligibilityTest.php`

**Interfaces:**
- Produces: `status(User $user): string`, `isEligible(User $user): bool`, `membershipPaymentYear(User $user): int`
- Status constants: `NO_NAJM_BAHAR_ACCOUNT`, `MEMBERSHIP_FEE_DUE`, `ELIGIBLE`

- [ ] Write failing tests for no account, account without fee, and account with canonical fee split.
- [ ] Run Full Validation/targeted CI and verify RED is caused by the missing service.
- [ ] Implement the minimal shared service using `AccountService` and Najm Bahar `Transaction` metadata.
- [ ] Re-run validation and verify the eligibility tests pass.

### Task 2: Invitation backend and dynamic page

**Files:**
- Modify: `app/Services/InvitationLifecycleService.php`
- Modify: `app/Http/Controllers/Profile/MemberInvitationController.php`
- Modify: `routes/member-invitations.php`
- Create: `resources/views/profile/member-invitations.blade.php`
- Test: `tests/Feature/Invitation/InvitationParticipationGateTest.php`

**Interfaces:**
- Consumes: `MembershipParticipationEligibilityService::isEligible()` / `status()`
- Produces: authenticated GET `/my-invation-code` and existing GET `/profile/invation-code-generate` using the same canonical lifecycle data.

- [ ] Write failing tests proving account-only users cannot issue invitations and paid members can.
- [ ] Write failing source/view contract assertions proving quota/expiry/reward are dynamic and prerequisite CTAs use canonical routes.
- [ ] Implement eligibility inside `canIssueMemberInvitation()` and locked issuance authority.
- [ ] Add `index()` controller data for quota, expiry, invite reward weight, occupied/successful/remaining counts and eligibility status.
- [ ] Override the legacy invitation page route in `routes/member-invitations.php` and render the focused dynamic Blade.
- [ ] Verify public invitation-request routes remain unchanged.

### Task 3: Group chat composer and POST gate

**Files:**
- Modify: `app/Http/Controllers/Group/ChatController.php`
- Modify: `app/Http/Controllers/Group/MessageController.php`
- Modify: `resources/views/groups/partials/chat_runtime.blade.php`
- Create: `public/js/membership-participation-gate.js`
- Test: `tests/Feature/GroupChat/MembershipParticipationGateContractTest.php`

**Interfaces:**
- ChatController passes `membershipParticipation` with `status`, `eligible`, `agreementUrl`, `dashboardUrl`.
- `window.GroupChatConfig.membershipParticipation` exposes the same payload.
- JS replaces only `#chatForm` when membership participation is not eligible.
- MessageController rejects non-eligible POSTs with HTTP 403 and a stable JSON message.

- [ ] Write failing contracts for ChatController payload, runtime config/script include, and MessageController backend check.
- [ ] Implement ChatController payload with no changes to existing read authorization.
- [ ] Include the dedicated JS in `chat_runtime.blade.php`.
- [ ] Implement JS that replaces only the existing chat form with the correct CTA card.
- [ ] Implement MessageController eligibility enforcement after group/member authorization and before rate limiting/persistence.
- [ ] Verify existing chat transport and composer code remains untouched for eligible members.

### Task 4: Verification and freeze

**Files:**
- No production changes unless verification exposes a defect.

- [ ] Run targeted invitation/Najm Bahar/Group Chat tests in CI.
- [ ] Run Responsive Contract Validation.
- [ ] Run EarthCoop Integration Full Validation on the final HEAD.
- [ ] Verify branch HEAD and report exact commit/run statuses; do not call the work complete unless fresh validation is green.
