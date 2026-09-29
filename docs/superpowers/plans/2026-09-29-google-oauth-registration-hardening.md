# Google OAuth Registration Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Make Google OAuth obey the same registration gate as normal registration while preserving existing-account login, rejecting system identities, and preserving the established Google-registration handoff in which the member chooses a local password in Step1.

**Architecture:** Keep OAuth state owned by Laravel Socialite/session and store the EarthCoop intent (`login` vs `register`) separately in the server session. Registration mode must pass terms/invitation preconditions before redirect, and callback must re-check them; new-user creation plus invitation consumption happens in one database transaction with row locking, mirroring normal registration. A newly created Google member deliberately has `password = null`; the `users.password` column is therefore nullable until Step1 requires `password` + `password_confirmation`, hashes the chosen password, and advances the member to Step2.

**Tech Stack:** Laravel 12, Laravel Socialite 5.18, PHPUnit 11, Eloquent/DB transactions.

**Spec:** User-approved six-point hardening request from 2026-09-29, plus the confirmed registration contract: Google callback → Step1 with no local password → required password + confirmation → Step2.

## Global Constraints

- Do not change the normal email/password registration behavior.
- Do not use request/OAuth `state` to determine `login` versus `register`.
- Do not call Socialite `stateless()`.
- Existing users may sign in with Google; system identities may not.
- New Google users require accepted terms and, while invitations are enabled, a still-valid unused invitation code.
- Invitation claim and user creation must be atomic and concurrency-safe.
- A new Google user's local password must remain `null` until Step1; do not inject a random placeholder password because Step1 uses nullness to require password creation.
- Step1 must require `password` and `password_confirmation` for such users, hash the chosen password, and redirect to Step2.
- Missing Google OAuth configuration must produce a Persian in-site error instead of redirecting to a provider error.
- Keep the change limited to Google authentication, the minimal password-nullability schema alignment, and focused regression tests.

## Review Focus

- Callback query `state` is maliciously changed: session intent still controls behavior.
- Invitation expires or is consumed between redirect and callback: no user is created and no partial claim remains.
- Registration session loses terms acceptance before callback: no user is created.
- Google returns missing/malformed email: no user is created.
- OAuth credentials are incomplete: provider redirect is never attempted and a Persian error is shown.
- New Google member reaches Step1 with `password = null`; Step1 rejects missing password, accepts matching password confirmation, hashes the password, and redirects to Step2.

---

### Task 1: Lock the security contract with regression tests

**Files:**
- Create: `tests/Feature/Auth/GoogleOAuthRegistrationTest.php`

**Interfaces:**
- Consumes: current `/auth/google`, `/auth/google/callback`, and Step1 registration routes.
- Produces: behavioral regression coverage for stateful Socialite, session intent, registration gates, invitation atomicity, existing login, invalid email, system identity, missing configuration, and the Step1 password handoff.

- [x] Write focused failing feature tests for the approved OAuth contract.
- [x] Confirm RED failures are caused by the insecure/incomplete implementation rather than unrelated suites.
- [x] Add an explicit regression assertion that Google-created members have no local password before Step1.
- [x] Add an end-to-end Step1 regression: password missing is rejected; matching password + confirmation is hashed and advances to Step2.

### Task 2: Harden Google OAuth controller

**Files:**
- Modify: `app/Http/Controllers/Auth/GoogleController.php`
- Test: `tests/Feature/Auth/GoogleOAuthRegistrationTest.php`

**Interfaces:**
- Stores server-side intent as `google_oauth_mode` in session.
- Uses Socialite `user()` statefully.
- Uses the existing registration session keys `registration_terms_accepted`, `registration_terms_accepted_at`, `registration_invitation_code`, and `fingerprint_id`.

- [x] Before redirect, validate OAuth config and registration preconditions.
- [x] Let Socialite generate/validate OAuth state; never overload it with application mode.
- [x] At callback, pull the mode from session and reject missing/unknown mode safely.
- [x] Validate the provider email before lookup/create.
- [x] Preserve existing-user login and reject system identities.
- [x] For new registration, re-check terms/invitation and create user + claim invitation inside one `DB::transaction()` with `lockForUpdate()`.
- [x] Clear registration-only session keys after successful Google registration or a terminal invalid-invitation failure.
- [x] Return Persian, in-site errors for incomplete OAuth configuration and user-correctable registration failures.

### Task 3: Align password persistence with the established Step1 contract

**Files:**
- Add: `database/migrations/2026_09_29_141900_make_users_password_nullable_for_social_registration.php`
- Test: `tests/Feature/Auth/GoogleOAuthRegistrationTest.php`

- [x] Make `users.password` nullable so a Google-created member can exist before choosing a local password.
- [x] Do not generate a random placeholder password in the Google callback.
- [x] Preserve current Step1 behavior: null password means `required|min:6|confirmed`; a submitted password is hashed before redirecting to Step2.

### Task 4: Final verification

**Files:**
- Review controller, migration, regression test, and this plan.

- [x] Full Validation #3549 passed on `b8fac471dd1ceaa5bee346d0debc9c90db520f78` after the production/schema fix and password-null assertion.
- [ ] Verify the additional Step1 end-to-end regression on the latest head.
- [ ] Review the final diff for accidental changes outside the requested scope.
- [ ] Only after the latest checks are green, mark the PR ready for merge; do not merge automatically.
