# Google OAuth Registration Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make Google OAuth obey the same registration gate as normal registration while preserving existing-account login and rejecting system identities.

**Architecture:** Keep OAuth state owned by Laravel Socialite/session and store the EarthCoop intent (`login` vs `register`) separately in the server session. Registration mode must pass terms/invitation preconditions before redirect, and callback must re-check them; new-user creation plus invitation consumption happens in one database transaction with row locking, mirroring normal registration.

**Tech Stack:** Laravel 12, Laravel Socialite 5.18, PHPUnit 11, Eloquent/DB transactions.

**Spec:** User-approved six-point hardening request from 2026-09-29.

## Global Constraints

- Do not change the normal email/password registration behavior.
- Do not use request/OAuth `state` to determine `login` versus `register`.
- Do not call Socialite `stateless()`.
- Existing users may sign in with Google; system identities may not.
- New Google users require accepted terms and, while invitations are enabled, a still-valid unused invitation code.
- Invitation claim and user creation must be atomic and concurrency-safe.
- Missing Google OAuth configuration must produce a Persian in-site error instead of redirecting to a provider error.
- Keep the change limited to Google authentication and focused regression tests.

## Review Focus

- Callback query `state` is maliciously changed: session intent still controls behavior.
- Invitation expires or is consumed between redirect and callback: no user is created and no partial claim remains.
- Registration session loses terms acceptance before callback: no user is created.
- Google returns missing/malformed email: no user is created.
- OAuth credentials are incomplete: provider redirect is never attempted and a Persian error is shown.

---

### Task 1: Lock the security contract with regression tests

**Files:**
- Create: `tests/Feature/Auth/GoogleOAuthRegistrationTest.php`

**Interfaces:**
- Consumes: current `/auth/google` and `/auth/google/callback` routes.
- Produces: behavioral regression coverage for stateful Socialite, session intent, registration gates, invitation atomicity, existing login, invalid email, system identity, and missing configuration.

- [ ] Write focused failing feature tests for the approved contract.
- [ ] Run the targeted test in CI and confirm RED failures are caused by the current insecure implementation.
- [ ] Do not change production code before the RED evidence exists.

### Task 2: Harden Google OAuth controller

**Files:**
- Modify: `app/Http/Controllers/Auth/GoogleController.php`
- Test: `tests/Feature/Auth/GoogleOAuthRegistrationTest.php`

**Interfaces:**
- Stores server-side intent as `google_oauth_mode` in session.
- Uses Socialite `user()` statefully.
- Uses the existing registration session keys `registration_terms_accepted`, `registration_terms_accepted_at`, `registration_invitation_code`, and `fingerprint_id`.

- [ ] Before redirect, validate OAuth config and registration preconditions.
- [ ] Let Socialite generate/validate OAuth state; never overload it with application mode.
- [ ] At callback, pull the mode from session and reject missing/unknown mode safely.
- [ ] Validate the provider email before lookup/create.
- [ ] Preserve existing-user login and reject system identities.
- [ ] For new registration, re-check terms/invitation and create user + claim invitation inside one `DB::transaction()` with `lockForUpdate()`.
- [ ] Clear registration-only session keys after successful Google registration or a terminal invalid-invitation failure.
- [ ] Return Persian, in-site errors for incomplete OAuth configuration and user-correctable registration failures.
- [ ] Run targeted tests until GREEN.

### Task 3: Final verification

**Files:**
- Review only the controller, test, and this plan.

- [ ] Run the focused Google OAuth regression test.
- [ ] Run the smallest relevant authentication/invitation regression group available in CI.
- [ ] Review the diff for accidental changes outside the requested scope.
- [ ] Only after those checks are green, mark the PR ready for merge; do not merge automatically.
