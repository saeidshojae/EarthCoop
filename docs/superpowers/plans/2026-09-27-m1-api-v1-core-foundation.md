# M1 API v1 Core Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build EarthCoop's stable `/api/v1` transport foundation, native auth/device boundary and a minimal set of core-journey adapters without duplicating domain logic or changing frozen Product/UX behavior.

**Architecture:** Add a new isolated v1 route surface and shared transport middleware/support classes, then layer native device-bound Sanctum authentication over existing users. Migrate/adapt capabilities incrementally by calling existing canonical services/controllers/query services rather than copying web business logic. Existing `/api/*` and web routes remain compatibility surfaces throughout M1.

**Tech Stack:** PHP 8.2+, Laravel 12, Laravel Sanctum 4, PHPUnit 11, existing EarthCoop application/domain services.

**Spec:** `docs/superpowers/specs/2026-09-27-m0-api-constitution-mobile-readiness-design.md`

## Global Constraints

- Stable new client namespace is exactly `/api/v1/*`.
- Do not delete or repurpose legacy/web-support API routes in M1.
- Native authentication uses bearer tokens; browser cookie/session auth remains unchanged.
- Device identifiers and request context never grant authorization.
- All v1 JSON uses the M0 `status/data/error/meta/request_id` envelope and `X-Request-ID`.
- Canonical timestamps are UTC RFC 3339; locales are `fa`, `en`, `ar`; native locale is header-based.
- Najm Bahar authoritative monetary amounts are integer Gol; no floating-point financial authority.
- Mutations with meaningful side effects use idempotency semantics from the M0 constitution.
- Product/UX frozen backlog, C14 legacy retirement, broad Marketplace/Company, reverse geocoder and full Najm Hoda autonomy are out of scope.
- Use targeted tests for iteration; Full Validation only at checkpoints/final integration gate.
- No direct changes to `main` and no production-data-destructive operations.

## Review Focus

1. A bearer token from one user/device must never authorize a second user's device/resource; Task 4 pins cross-device and cross-user cases.
2. Browser session/cookie behavior must remain unchanged while v1 bearer auth is added; Task 4 includes a regression test proving web login/session still works independently.
3. Reusing an idempotency key with a changed payload must fail deterministically and must not perform the second mutation; Task 3 pins this.
4. Unsupported locale/filter/sort/page inputs must produce stable v1 errors instead of framework HTML/redirects; Tasks 2 and 5 pin these inputs.
5. Existing canonical Location/Governance and Group Chat semantics must not be reimplemented or silently altered by v1 adapters; Tasks 6 and 7 compare adapter output/authorization to canonical services/runtime contracts.

---

## File Structure

### New v1 transport files

- `routes/api-v1.php` — authoritative v1 route registry only.
- `app/Http/Middleware/ApiV1RequestContext.php` — request ID, locale and API context initialization.
- `app/Http/Middleware/ApiV1ResponseEnvelope.php` — normalize successful JSON responses into the v1 envelope without double-wrapping already-normalized responses.
- `app/Http/Middleware/ApiV1Idempotency.php` — generalized idempotency transport middleware derived from the mature Group Chat behavior.
- `app/Http/Middleware/ApiV1DeviceSession.php` — binds authenticated token/session to an active owned native device.
- `app/Http/Support/Api/V1/ApiResponse.php` — explicit success/error response factory.
- `app/Http/Support/Api/V1/ApiRequestContext.php` — immutable request metadata object used by controllers/services.
- `app/Http/Support/Api/V1/Pagination.php` — bounded page/cursor parsing helpers.
- `app/Http/Support/Api/V1/QueryOptions.php` — filter/sort whitelist parsing.
- `app/Exceptions/ApiV1ExceptionRenderer.php` — v1-only exception-to-error mapping.

### Native auth/device files

- `app/Models/NativeDevice.php` — user-owned native device record.
- `database/migrations/<timestamp>_create_native_devices_table.php` — device persistence.
- `database/migrations/<timestamp>_create_api_v1_idempotency_keys_table.php` — generalized transport idempotency persistence.
- `app/Http/Controllers/API/V1/Auth/NativeSessionController.php` — login/current-session/logout/rotate endpoints.
- `app/Http/Requests/API/V1/Auth/NativeLoginRequest.php` — login transport validation.
- `app/Http/Resources/API/V1/UserResource.php` — stable current-user representation.
- `app/Services/Auth/NativeSessionService.php` — credential verification, device upsert, token issue/rotation/revocation.

### Core adapter files created as M1 reaches them

- `app/Http/Controllers/API/V1/ProfileController.php`
- `app/Http/Controllers/API/V1/LocationGovernanceController.php`
- `app/Http/Controllers/API/V1/GroupController.php`
- `app/Http/Controllers/API/V1/GroupFeedController.php`
- `app/Http/Controllers/API/V1/ElectionController.php`
- `app/Http/Controllers/API/V1/ProjectController.php`
- `app/Http/Controllers/API/V1/NotificationController.php`

Controllers must delegate to existing application/domain services. If an existing web controller contains reusable business logic that is not yet in a service, extract the smallest application/query service needed by both surfaces; do not call web controllers from API controllers.

### Tests

- `tests/Feature/Api/V1/TransportContractTest.php`
- `tests/Feature/Api/V1/IdempotencyContractTest.php`
- `tests/Feature/Api/V1/NativeSessionTest.php`
- `tests/Feature/Api/V1/QueryContractTest.php`
- `tests/Feature/Api/V1/LocationGovernanceContractTest.php`
- `tests/Feature/Api/V1/GroupContractTest.php`
- `tests/Feature/Api/V1/ElectionProjectNotificationContractTest.php`
- `tests/Feature/Api/V1/CoreJourneyTest.php`

---

### Task 1: Establish the isolated `/api/v1` route boundary

**Files:**
- Create: `routes/api-v1.php`
- Modify: `app/Providers/RouteServiceProvider.php`
- Test: `tests/Feature/Api/V1/TransportContractTest.php`

**Interfaces:**
- Consumes: existing Laravel `api` middleware group.
- Produces: route group prefix `/api/v1`, route-name prefix `api.v1.`, and a temporary `GET /api/v1/health` contract used only to prove the transport boundary until later tasks replace/augment it.

- [ ] **Step 1: Write the failing boundary test**

Add `test_v1_route_surface_is_isolated_and_versioned()` asserting:

- `GET /api/v1/health` returns JSON and `200`;
- an existing legacy route remains reachable at its old path and is not automatically treated as v1;
- v1 route name is `api.v1.health`;
- no Product/UX route is moved.

- [ ] **Step 2: Run the targeted test and confirm RED**

Run:

```bash
php artisan test tests/Feature/Api/V1/TransportContractTest.php --filter=v1_route_surface_is_isolated_and_versioned
```

Expected: FAIL because `/api/v1/health` and `routes/api-v1.php` do not exist.

- [ ] **Step 3: Add the minimal route registration**

Register `routes/api-v1.php` in `RouteServiceProvider` under middleware `api`, explicit `throttle:api-v1`, prefix `api/v1` and route-name prefix `api.v1.`. Add a dedicated `api-v1` limiter keyed by authenticated user ID or IP; do not depend on the currently commented global API throttle.

- [ ] **Step 4: Re-run the targeted test and confirm GREEN**

- [ ] **Step 5: Commit**

```bash
git add routes/api-v1.php app/Providers/RouteServiceProvider.php tests/Feature/Api/V1/TransportContractTest.php
git commit -m "feat(api): establish v1 route boundary"
```

### Task 2: Add v1 request context, response envelope and exception mapping

**Files:**
- Create: `app/Http/Middleware/ApiV1RequestContext.php`
- Create: `app/Http/Middleware/ApiV1ResponseEnvelope.php`
- Create: `app/Http/Support/Api/V1/ApiRequestContext.php`
- Create: `app/Http/Support/Api/V1/ApiResponse.php`
- Create: `app/Exceptions/ApiV1ExceptionRenderer.php`
- Modify: `app/Http/Kernel.php`
- Modify: `app/Exceptions/Handler.php`
- Modify: `routes/api-v1.php`
- Test: `tests/Feature/Api/V1/TransportContractTest.php`

**Interfaces:**
- Produces: `ApiRequestContext` with `requestId(): string`, `locale(): string`, `timezone(): ?string`, `deviceId(): ?string`; `ApiResponse::success(mixed $data, int $status = 200, array $meta = []): JsonResponse`; `ApiResponse::error(string $code, string $message, int $status, mixed $details = null, bool $retryable = false): JsonResponse`.

- [ ] **Step 1: Write failing envelope/context tests**

Pin:

- server generates UUID request ID when absent;
- valid `X-Request-ID` is preserved;
- every response carries matching `X-Request-ID` and `request_id` body field;
- `Accept-Language: en` yields `Content-Language: en`; unsupported locale falls back to `fa`;
- v1 validation/auth/not-found/server exceptions return JSON envelope, never an HTML redirect/error page;
- error codes remain locale-independent.

- [ ] **Step 2: Run `TransportContractTest` and confirm RED**

- [ ] **Step 3: Implement request context and response helpers**

`ApiV1RequestContext` validates/preserves a safe request ID, selects `fa|en|ar`, accepts an optional valid IANA timezone header and stores an immutable `ApiRequestContext` in request attributes/container scope.

- [ ] **Step 4: Implement v1-only exception rendering**

`ApiV1ExceptionRenderer` maps authentication, authorization, model-not-found, validation, throttle and unknown exceptions to the M0 codes/statuses. `Handler` delegates only requests matching `api/v1/*`; existing Group Chat and web exception behavior remains untouched.

- [ ] **Step 5: Implement success response normalization**

`ApiV1ResponseEnvelope` wraps plain successful `JsonResponse` payloads once and leaves responses created by `ApiResponse` unchanged.

- [ ] **Step 6: Run targeted tests and confirm GREEN**

- [ ] **Step 7: Commit**

```bash
git add app/Http/Middleware/ApiV1RequestContext.php app/Http/Middleware/ApiV1ResponseEnvelope.php app/Http/Support/Api/V1 app/Exceptions/ApiV1ExceptionRenderer.php app/Exceptions/Handler.php app/Http/Kernel.php routes/api-v1.php tests/Feature/Api/V1/TransportContractTest.php
git commit -m "feat(api): standardize v1 transport envelope"
```

### Task 3: Generalize transport idempotency

**Files:**
- Create: `database/migrations/<timestamp>_create_api_v1_idempotency_keys_table.php`
- Create: `app/Http/Middleware/ApiV1Idempotency.php`
- Modify: `app/Http/Kernel.php`
- Modify: `routes/api-v1.php`
- Test: `tests/Feature/Api/V1/IdempotencyContractTest.php`
- Reference: `app/Http/Middleware/GroupChatIdempotency.php`

**Interfaces:**
- Consumes: authenticated user where route requires auth; `Idempotency-Key` header; normalized v1 response contract.
- Produces: generalized per-actor/per-route idempotency replay with 24-hour minimum transport retention and headers `Idempotency-Replayed`, `Retry-After`.

- [ ] **Step 1: Write failing idempotency tests**

Create a test-only v1 mutation route/controller fixture and assert:

- first keyed request mutates once;
- exact replay returns original response and `Idempotency-Replayed: true`;
- same key/different payload returns `409 idempotency_key_reused` and no second mutation;
- processing duplicate returns `409 request_in_progress` + `Retry-After`;
- 5xx releases the transport key so a later retry may execute;
- malformed key returns `422` v1 envelope.

- [ ] **Step 2: Run targeted tests and confirm RED**

- [ ] **Step 3: Implement storage schema and middleware**

Persist actor identity, route scope, key, request fingerprint, processing/completed state, response status/body, timestamps and expiry. Derive fingerprint from normalized non-CSRF inputs plus file size/MIME/SHA-256 as Group Chat currently does.

- [ ] **Step 4: Run targeted tests and confirm GREEN**

- [ ] **Step 5: Commit**

```bash
git add database/migrations app/Http/Middleware/ApiV1Idempotency.php app/Http/Kernel.php routes/api-v1.php tests/Feature/Api/V1/IdempotencyContractTest.php
git commit -m "feat(api): add generalized v1 idempotency"
```

### Task 4: Add native device-bound Sanctum session lifecycle

**Files:**
- Modify: `app/Models/User.php`
- Create: `app/Models/NativeDevice.php`
- Create: `database/migrations/<timestamp>_create_native_devices_table.php`
- Create: `app/Services/Auth/NativeSessionService.php`
- Create: `app/Http/Requests/API/V1/Auth/NativeLoginRequest.php`
- Create: `app/Http/Controllers/API/V1/Auth/NativeSessionController.php`
- Create: `app/Http/Resources/API/V1/UserResource.php`
- Create: `app/Http/Middleware/ApiV1DeviceSession.php`
- Modify: `app/Http/Kernel.php`
- Modify: `routes/api-v1.php`
- Test: `tests/Feature/Api/V1/NativeSessionTest.php`

**Interfaces:**
- Produces routes:
  - `POST /api/v1/auth/session` — verify credentials, register/update native device, issue finite Sanctum token.
  - `GET /api/v1/auth/session` — current authenticated user/device/session summary.
  - `POST /api/v1/auth/session/rotate` — rotate only current device/session token.
  - `DELETE /api/v1/auth/session` — revoke current token/session/device session without affecting unrelated devices.
- `NativeSessionService::issue(User $user, NativeDeviceData $device): IssuedNativeSession`
- `NativeSessionService::rotate(User $user, NativeDevice $device, PersonalAccessToken $current): IssuedNativeSession`
- `NativeSessionService::revokeCurrent(User $user, NativeDevice $device, PersonalAccessToken $current): void`

- [ ] **Step 1: Write failing auth/device tests**

Pin:

- valid credentials create one owned device and token;
- wrong credentials return generic `401 invalid_credentials` without account enumeration detail;
- expired/revoked token fails;
- token for device A cannot claim device B;
- user A cannot attach a device owned by user B;
- rotating device A revokes only the prior A token and leaves device B valid;
- logout revokes only current session;
- system identities cannot obtain interactive native sessions;
- existing browser login/session test remains green and independent.

- [ ] **Step 2: Run targeted tests and confirm RED**

- [ ] **Step 3: Add `HasApiTokens` to `User` and native-device relationship**

Do not change existing fillable/profile semantics beyond what the relationship/token trait requires.

- [ ] **Step 4: Implement `NativeDevice` and migration**

Persist exactly the M0 minimum device fields plus a stable server-generated public UUID/ULID used by clients. Do not use client-provided hardware identifiers as primary key or authority.

- [ ] **Step 5: Implement `NativeSessionService`**

Use Laravel password verification/authentication facilities already used by the application; issue finite Sanctum tokens with an explicit `native` ability plus server metadata linking token to device ID. Store no plaintext token.

- [ ] **Step 6: Implement controller/request/resource/device-session middleware**

`ApiV1DeviceSession` verifies that the authenticated access token's linked device exists, belongs to the user and is not revoked. Client `X-Device-ID` is comparison/context only.

- [ ] **Step 7: Run targeted tests and confirm GREEN**

- [ ] **Step 8: Commit**

```bash
git add app/Models/User.php app/Models/NativeDevice.php database/migrations app/Services/Auth/NativeSessionService.php app/Http/Requests/API/V1/Auth app/Http/Controllers/API/V1/Auth app/Http/Resources/API/V1/UserResource.php app/Http/Middleware/ApiV1DeviceSession.php app/Http/Kernel.php routes/api-v1.php tests/Feature/Api/V1/NativeSessionTest.php
git commit -m "feat(api): add native device sessions"
```

### Task 5: Add shared pagination, filter and sort parsing

**Files:**
- Create: `app/Http/Support/Api/V1/Pagination.php`
- Create: `app/Http/Support/Api/V1/QueryOptions.php`
- Test: `tests/Feature/Api/V1/QueryContractTest.php`

**Interfaces:**
- `Pagination::page(Request $request, int $default = 25, int $max = 100): PageOptions`
- `Pagination::cursor(Request $request, int $default = 50, int $max = 100): CursorOptions`
- `QueryOptions::filters(Request $request, array $allowed): array`
- `QueryOptions::sort(Request $request, array $allowed, array $default = []): array`

- [ ] **Step 1: Write failing query-contract unit/feature tests**

Assert bounded sizes, invalid integer handling, unsupported filter/sort rejection with stable `422` codes, descending `-field` parsing and unknown cursor handling.

- [ ] **Step 2: Run targeted tests and confirm RED**

- [ ] **Step 3: Implement immutable option objects/parsers**

Do not expose database column names implicitly; callers supply explicit external-name → query-field mappings.

- [ ] **Step 4: Run targeted tests and confirm GREEN**

- [ ] **Step 5: Commit**

```bash
git add app/Http/Support/Api/V1/Pagination.php app/Http/Support/Api/V1/QueryOptions.php tests/Feature/Api/V1/QueryContractTest.php
git commit -m "feat(api): add v1 query contracts"
```

### Task 6: Adapt canonical profile and Location/Governance to v1

**Files:**
- Create: `app/Http/Controllers/API/V1/ProfileController.php`
- Create: `app/Http/Controllers/API/V1/LocationGovernanceController.php`
- Create as needed: `app/Services/LocationGovernance/Api/LocationGovernanceQueryService.php`
- Create as needed: `app/Services/Profile/Api/ProfileQueryService.php`
- Modify: `routes/api-v1.php`
- Test: `tests/Feature/Api/V1/LocationGovernanceContractTest.php`
- Reference: canonical Location/Governance controllers/services from `app/Http/Controllers/LocationGovernance` and `app/Services/LocationGovernance`.

**Interfaces:**
- Provide authenticated v1 reads for current profile, current residence/governance summary and canonical location option traversal.
- Provide only the minimum launch-scope residence mutation needed by the existing registration/profile semantics; mutations call the same canonical service used by web after extraction if necessary.
- Preserve proposal, pending and structural-claim identities/statuses; do not translate back to legacy Address geography.

- [ ] **Step 1: Write failing parity tests against canonical runtime**

Pin city/no-region, urban-region/no-neighborhood, village/no-neighborhood, pending proposal traversal, active canonical residence and project stopping-point-safe identity representation.

- [ ] **Step 2: Run targeted tests and confirm RED**

- [ ] **Step 3: Extract shared query/application services only where web controllers currently own reusable logic**

The service must return domain DTO/arrays, not Blade views or redirects.

- [ ] **Step 4: Implement thin v1 adapters and routes**

All routes use bearer auth + device-session middleware for native protected calls.

- [ ] **Step 5: Run v1 tests plus dedicated canonical Location/Governance regression tests**

Expected: all green with no changed canonical semantics.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/API/V1/ProfileController.php app/Http/Controllers/API/V1/LocationGovernanceController.php app/Services/LocationGovernance/Api app/Services/Profile/Api routes/api-v1.php tests/Feature/Api/V1/LocationGovernanceContractTest.php
git commit -m "feat(api): expose canonical profile and governance v1"
```

### Task 7: Adapt groups and canonical feed/chat to v1

**Files:**
- Create: `app/Http/Controllers/API/V1/GroupController.php`
- Create: `app/Http/Controllers/API/V1/GroupFeedController.php`
- Extract only if required: focused group query/application services under `app/Services/Group/Api/`
- Modify: `routes/api-v1.php`
- Test: `tests/Feature/Api/V1/GroupContractTest.php`
- Reference: existing canonical group search, `GroupChatRequestContext`, feed/delta/unread services/controllers and group policies.

**Interfaces:**
- v1 group list/search/detail uses canonical governance/dimension identity and authoritative `group_user.role`.
- feed/delta/unread/read routes preserve canonical sequence/event semantics.
- write adapters reuse existing group policies/domain services and `ApiV1Idempotency` for supported mutations.

- [ ] **Step 1: Write failing group/security/feed parity tests**

Pin canonical member counts/roles, unauthorized group concealment, feed ordering, unread cursor, delta recovery, duplicate event behavior and one idempotent write path.

- [ ] **Step 2: Run targeted tests and confirm RED**

- [ ] **Step 3: Extract shared query/application service seams where controller logic is web-bound**

Do not duplicate chat feed assembly or role derivation.

- [ ] **Step 4: Implement v1 group/feed adapters**

- [ ] **Step 5: Run v1 tests plus mature Group Chat regression subset**

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/API/V1/GroupController.php app/Http/Controllers/API/V1/GroupFeedController.php app/Services/Group/Api routes/api-v1.php tests/Feature/Api/V1/GroupContractTest.php
git commit -m "feat(api): expose canonical groups and feed v1"
```

### Task 8: Adapt elections, projects and notifications to v1 core reads/mutations

**Files:**
- Create: `app/Http/Controllers/API/V1/ElectionController.php`
- Create: `app/Http/Controllers/API/V1/ProjectController.php`
- Create: `app/Http/Controllers/API/V1/NotificationController.php`
- Extract focused application/query services only if current controllers own reusable non-view logic.
- Modify: `routes/api-v1.php`
- Test: `tests/Feature/Api/V1/ElectionProjectNotificationContractTest.php`

**Interfaces:**
- Elections: current systemic election state/portal data and launch-scope member mutations, preserving existing eligibility/conflict/responsibility policies.
- Projects: list/detail and launch-scope create/update/submit using canonical `target_location_id` / `governance_area_id` and stop-at-valid-level semantics.
- Notifications: list/unread/read/preferences using existing `NotificationService`/settings and cursor/page contracts as appropriate.

- [ ] **Step 1: Write failing adapter contract tests**

Pin election permission/role semantics, project scope independence from residence, stop-at-valid-level persistence, notification ownership/read behavior and unsupported query handling.

- [ ] **Step 2: Run targeted tests and confirm RED**

- [ ] **Step 3: Extract minimal shared application/query services where required**

- [ ] **Step 4: Implement thin v1 adapters; apply idempotency to side-effecting routes**

- [ ] **Step 5: Run targeted v1 tests plus election/project/notification subsystem regressions**

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/API/V1/ElectionController.php app/Http/Controllers/API/V1/ProjectController.php app/Http/Controllers/API/V1/NotificationController.php app/Services routes/api-v1.php tests/Feature/Api/V1/ElectionProjectNotificationContractTest.php
git commit -m "feat(api): expose core governance project notification journeys"
```

### Task 9: M1-C non-browser core journey acceptance

**Files:**
- Create: `tests/Feature/Api/V1/CoreJourneyTest.php`
- Modify only files required to close defects exposed by this acceptance test.

**Interfaces:**
- Consumes all M1 route/contracts.
- Produces the M1-C acceptance guarantee that a mobile-style bearer client can complete the pilot/core journey without cookie session, CSRF or Blade responses.

- [ ] **Step 1: Write the end-to-end test before fixing any discovered gaps**

Scenario:

1. authenticate through native session endpoint;
2. fetch current profile;
3. traverse canonical location/residence data;
4. fetch My Groups and one group feed/delta/unread state;
5. fetch current election state;
6. fetch/create launch-scope project data using canonical target scope;
7. fetch/mark notification state;
8. rotate token and prove old token is rejected/new token remains valid;
9. logout and prove the final token is revoked.

The test client must send no browser session cookie and no CSRF token.

- [ ] **Step 2: Run and confirm RED for any remaining integration gaps**

- [ ] **Step 3: Fix only gaps required by the accepted M1 contract**

Do not pull M2 Najm Hoda or M3 Najm Bahar mobile contracts into M1.

- [ ] **Step 4: Run M1 targeted suite**

```bash
php artisan test tests/Feature/Api/V1
```

Expected: GREEN.

- [ ] **Step 5: Run mature impacted subsystem gates locally/targeted**

Run the repository's dedicated Location/Governance, Group Chat, Elections and Project regression subsets that cover touched services. Do not use GitHub Actions as the debugging loop.

- [ ] **Step 6: Commit integration fixes**

```bash
git add tests/Feature/Api/V1/CoreJourneyTest.php <only-required-M1-files>
git commit -m "test(api): close M1 core journey gate"
```

### Task 10: M1 checkpoint validation and handoff to M2/M3 planning

**Files:**
- Update: `docs/superpowers/plans/2026-09-27-m1-api-v1-core-foundation.md` only with factual checkpoint evidence after tests run.
- Create after M1 interfaces stabilize:
  - `docs/superpowers/plans/<date>-m2-najm-hoda-mobile-contract.md`
  - `docs/superpowers/plans/<date>-m3-najm-bahar-mobile-contract.md`

**Interfaces:**
- Produces stable M1 interfaces consumed by M2/M3: v1 envelope/request context, native bearer/device lifecycle, idempotency, query conventions and resource-policy boundary.

- [x] **Step 1: Verify M1-A, M1-B and M1-C on one fixed commit SHA**

Validated implementation SHA: `24f0e09c22c2caebee0affbe72b06037cd8533ac`.

- [x] **Step 2: Run Full Validation once as the checkpoint/final integration gate**

The first checkpoint attempt, Integration Full Validation **#3316** / run `36336035178`, isolated one test-harness defect: `NativeSessionTest` dropped shared full-schema `users`/token tables during teardown, causing a MySQL foreign-key failure and then cross-test duplicate-email contamination. All specialized subsystem gates in that run were green. The fix changed only `tests/Feature/Api/V1/NativeSessionTest.php` plus a targeted MySQL full-schema isolation step in `.github/workflows/m1-api-v1-targeted.yml`; no production file was changed for this defect.

Targeted verification run `36338191012` then succeeded, including migrated-MySQL `NativeSessionTest.php` + `SystemIdentityIsolationTest.php` and all preceding M1 targeted gates.

Final Integration Full Validation **#3318** / run `36338441103` succeeded on the fixed candidate. Boot validation, Deployment Console, Group Chat, Group Admin/Identity, Najm Hoda+n8n, Governance, Location/Governance, Location/Governance JavaScript, Najm Bahar, Stock, Group Chat JavaScript, database reset, **Full Project PHPUnit**, diagnostics upload and the final enforcement gate all completed successfully.

- [x] **Step 3: Audit diff for scope leakage**

Final audit against `main@b0f6e6082e3f248187a0bed66fb899dd0d7701c2`: candidate is 50 commits ahead / 0 behind with 54 changed files. The file set is limited to M0/M1 design/plan, v1 transport/auth/device/idempotency/query infrastructure, profile/location/groups/elections/projects/notifications adapters and their shared seams/tests/migrations/workflow. No C14 retirement, frozen Product/UX backlog, broad Marketplace/Company implementation, reverse geocoder, M2 Najm Hoda mobile implementation or M3 Najm Bahar financial API implementation entered M1.

- [x] **Step 4: Freeze the actual M1 interfaces and write detailed M2/M3 plans from those interfaces**

M1 implementation interfaces are frozen at `24f0e09c22c2caebee0affbe72b06037cd8533ac`. Detailed downstream plans:

- `docs/superpowers/plans/2026-09-27-m2-najm-hoda-mobile-contract.md`
- `docs/superpowers/plans/2026-09-27-m3-najm-bahar-mobile-contract.md`

M4 remains intentionally deferred until M2/M3 expose the concrete actor/ownership seams they consume. M5 remains after stable device/auth/notification/feed/media consumer contracts.

- [x] **Step 5: Commit only factual checkpoint documentation if needed**

This Task 10 documentation layer changes planning/evidence files only; it does not alter the validated M1 implementation SHA or require another production-code Full Validation.

## M1 Checkpoint Result

- **M1-A Transport Contract:** satisfied on the fixed candidate by targeted v1 transport/query/idempotency gates and final Full Validation #3318.
- **M1-B Auth & Device Security:** satisfied by native issue/revoke/rotate/device ownership/expiry tests plus the full-schema isolation regression.
- **M1-C Core Journey:** satisfied by `CoreJourneyTest`, which completed bearer-only login → profile → canonical location/governance → groups/feed/unread → current election → canonical project → notifications → token rotation → logout without browser session cookie, CSRF token or Blade dependency.
- **Validated implementation SHA:** `24f0e09c22c2caebee0affbe72b06037cd8533ac`.
- **Main baseline during validation:** `b0f6e6082e3f248187a0bed66fb899dd0d7701c2`; no main drift was observed before the final gate.
- **Validation PR:** #158 was used only as a temporary draft trigger for Full Validation and was closed without merge after #3318 succeeded.

## Dependency-ordered continuation after M1

- **M2 and M3:** may execute in parallel once M1 transport/auth contracts are fixed.
- **M4:** follows the concrete ownership/actor requirements exposed by M2/M3; it freezes the minimum future Organization/Marketplace-compatible actor boundary without building those products.
- **M5:** follows stable M1 device/notification/feed/media consumers and adds push, typed deep links, generic media, realtime recovery, offline replay and app compatibility.
- **Architecture Gate:** only after M1–M5 checkpoint criteria in the M0 spec are satisfied may Native PoC/technology finalization begin.