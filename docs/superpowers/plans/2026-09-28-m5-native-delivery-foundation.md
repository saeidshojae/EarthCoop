# M5 Native Delivery Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the provider-neutral native delivery and resilience layer required before the EarthCoop Native Mobile PoC, without coupling the backend to Flutter, NativePHP, or any mobile SDK.

**Architecture:** Extend the existing M1 NativeDevice/session boundary rather than adding a parallel device registry; evolve the existing Laravel notification boundary to typed deep links and push fan-out; add one canonical media resource; reuse existing `/api/v1` request/idempotency semantics for offline replay; treat realtime as an optimization with authoritative recovery; expose deterministic app compatibility policy through `/api/v1/bootstrap`.

**Tech Stack:** PHP 8.2+, Laravel 12, Laravel Sanctum 4, Laravel Notifications/Queues/Broadcasting, PHPUnit 11, existing EarthCoop `/api/v1` transport and application services.

**Spec:** `docs/superpowers/specs/2026-09-28-m5-native-delivery-foundation-design.md`

## Global Constraints

- M5 must remain client-framework neutral: no Flutter-, NativePHP-, FCM-SDK-, APNs-SDK- or UI-specific business contract.
- Existing `/api/v1` response envelope, request IDs, auth/device binding and idempotency remain authoritative.
- Push token is a delivery address, never authority; device ID is context, never authority.
- Revoked native sessions/devices must be excluded from protected push delivery at delivery time.
- Provider push tokens must never be returned from public API, logged plaintext, or used as public identities.
- Typed deep links are allowlisted application routes; opening them still runs ordinary auth/resource authorization.
- Realtime is best effort; authoritative state must be recoverable after gaps/reconnect.
- Offline replay uses the original idempotency key and always re-evaluates current auth/policy/domain state.
- Media public identity is server-generated `media_id`, never filename/path.
- App-version policy is per platform and uses deterministic semantic-version comparison.
- Existing Web/PWA behavior and legacy upload paths remain compatibility surfaces.
- Use targeted tests during iteration; Full Validation only at milestone/final gates.
- No changes directly on `main`; no destructive Production-data operation.

## Review Focus

1. A push token reused or rotated between devices/users must never let one user hijack another user's delivery address; Task 1 pins ownership/token-uniqueness behavior.
2. A notification queued before device revocation but executed after revocation must not deliver protected content; Task 4 pins delivery-time eligibility.
3. A typed deep-link route with arbitrary/unrecognized route/params must fail closed rather than becoming an open navigation/URL injection surface; Task 3 pins allowlisting.
4. Uploaded content with deceptive extension/MIME or oversize payload must be rejected centrally and must never expose a raw storage path; Task 5 pins validation/privacy identity.
5. Offline replay after authorization/resource state changes must return a stable conflict/forbidden outcome rather than replaying old authority; Task 7 pins current-state re-evaluation.

---

## File Structure

### Device/push
- Modify: `app/Models/NativeDevice.php`
- Create: `database/migrations/2026_09_28_000001_add_push_delivery_state_to_native_devices.php`
- Create: `app/Services/Push/PushRegistrationService.php`
- Create: `app/Services/Push/PushDeliveryGateway.php`
- Create: `app/Services/Push/NullPushDeliveryGateway.php`
- Create: `app/Services/Push/PushEnvelope.php`
- Create: `app/Services/Push/PushDeliveryResult.php`
- Create: `app/Services/Push/PushEligibilityResolver.php`
- Create: `app/Http/Controllers/API/V1/DevicePushController.php`
- Create: `app/Http/Requests/API/V1/DevicePushRequest.php`
- Modify: `routes/api-v1.php`

### Notifications/deep links
- Create: `app/Support/Notifications/NotificationLink.php`
- Create: `app/Support/Notifications/NotificationLinkRegistry.php`
- Modify: `app/Notifications/GenericNotification.php`
- Modify: `app/Services/NotificationService.php`
- Modify: `app/Http/Controllers/API/V1/NotificationController.php`
- Create: `app/Services/Push/PushNotificationDispatcher.php`

### Media
- Create: `app/Models/Media.php`
- Create: `database/migrations/2026_09_28_000002_create_media_table.php`
- Create: `app/Services/Media/MediaPolicy.php`
- Create: `app/Services/Media/MediaService.php`
- Create: `app/Http/Controllers/API/V1/MediaController.php`
- Create: `app/Http/Requests/API/V1/StoreMediaRequest.php`
- Create: `app/Http/Resources/API/V1/MediaResource.php`

### Realtime/offline/bootstrap
- Create: `app/Services/Notifications/NotificationCursor.php`
- Modify: `app/Http/Controllers/API/V1/NotificationController.php`
- Modify: `app/Http/Middleware/ApiV1Idempotency.php` only where stable replay classification is missing.
- Create: `app/Services/ClientCompatibility/ClientVersionPolicy.php`
- Create: `app/Services/ClientCompatibility/ClientCompatibilityService.php`
- Create: `app/Http/Controllers/API/V1/BootstrapController.php`
- Create: `config/client-compatibility.php`

### Tests
- Create: `tests/Feature/Api/V1/DevicePushContractTest.php`
- Create: `tests/Unit/Push/PushDeliveryGatewayTest.php`
- Create: `tests/Feature/Api/V1/NotificationDeliveryContractTest.php`
- Create: `tests/Feature/Api/V1/MediaContractTest.php`
- Create: `tests/Feature/Api/V1/RealtimeRecoveryContractTest.php`
- Create: `tests/Feature/Api/V1/OfflineReplayContractTest.php`
- Create: `tests/Feature/Api/V1/BootstrapCompatibilityTest.php`
- Create: `tests/Feature/Api/V1/M5NativeDeliveryAcceptanceTest.php`

---

### Task 1: Extend the existing NativeDevice with protected push registration state

**Files:**
- Modify: `app/Models/NativeDevice.php`
- Create: `database/migrations/2026_09_28_000001_add_push_delivery_state_to_native_devices.php`
- Create: `app/Services/Push/PushRegistrationService.php`
- Create: `app/Http/Requests/API/V1/DevicePushRequest.php`
- Create: `app/Http/Controllers/API/V1/DevicePushController.php`
- Modify: `routes/api-v1.php`
- Test: `tests/Feature/Api/V1/DevicePushContractTest.php`

**Interfaces:**
- Consumes: current `NativeDevice`, `ApiV1DeviceSession`, authenticated current device binding.
- Produces: `PUT /api/v1/devices/{device}/push`, `DELETE /api/v1/devices/{device}/push`; `PushRegistrationService::register(NativeDevice $device, string $provider, string $token): NativeDevice`; `PushRegistrationService::disable(NativeDevice $device): NativeDevice`.

- [ ] **Step 1: Write RED tests** proving current-device ownership, register, rotate, disable, no token echo, no cross-user/cross-device mutation, and one provider token cannot remain active for two user/device owners.
- [ ] **Step 2: Run** `php artisan test tests/Feature/Api/V1/DevicePushContractTest.php` **and verify expected failure because the routes/service/schema do not exist.**
- [ ] **Step 3: Add additive push-delivery columns** (`push_provider`, encrypted token storage, token hash, updated/enabled/disabled timestamps, last success/failure metadata) and casts/fillable rules. Plaintext provider token must not be serializable.
- [ ] **Step 4: Implement `PushRegistrationService`** using normalized provider `fcm|apns`, SHA-256 diagnostic/uniqueness hash, encrypted token at rest, and ownership-safe rotation/disable semantics.
- [ ] **Step 5: Add v1 controller/request/routes** under existing native device-session middleware; route model/device lookup must fail closed for non-current device ownership.
- [ ] **Step 6: Run targeted test and existing** `tests/Feature/Api/V1/NativeSessionTest.php`; expect GREEN.
- [ ] **Step 7: Commit** `feat(mobile): add device push registration state`.

### Task 2: Introduce a provider-neutral push delivery boundary

**Files:**
- Create: `app/Services/Push/PushDeliveryGateway.php`
- Create: `app/Services/Push/NullPushDeliveryGateway.php`
- Create: `app/Services/Push/PushEnvelope.php`
- Create: `app/Services/Push/PushDeliveryResult.php`
- Create: `app/Services/Push/PushEligibilityResolver.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Unit/Push/PushDeliveryGatewayTest.php`

**Interfaces:**
- Consumes: Task 1 push-enabled NativeDevice state.
- Produces: `PushDeliveryGateway::send(NativeDevice $device, PushEnvelope $envelope): PushDeliveryResult`; `PushEligibilityResolver::eligibleFor(User $user): Collection`.

- [ ] **Step 1: Write RED unit tests** for gateway contract, safe preview envelope, active-device eligibility, revoked-device exclusion, disabled-token exclusion, and failure result taxonomy (`delivered`, `temporary_failure`, `invalid_token`).
- [ ] **Step 2: Run targeted unit tests and verify RED.**
- [ ] **Step 3: Implement immutable envelope/result value objects and gateway interface.**
- [ ] **Step 4: Implement null/fake-safe gateway and eligibility resolver** with delivery-time `revoked_at` and push-enabled checks.
- [ ] **Step 5: Bind default gateway in container** without production FCM/APNs credentials.
- [ ] **Step 6: Run tests and expect GREEN.**
- [ ] **Step 7: Commit** `feat(mobile): add provider neutral push gateway`.

### Task 3: Evolve notifications to typed deep links while retaining legacy URL compatibility

**Files:**
- Create: `app/Support/Notifications/NotificationLink.php`
- Create: `app/Support/Notifications/NotificationLinkRegistry.php`
- Modify: `app/Notifications/GenericNotification.php`
- Modify: `app/Services/NotificationService.php`
- Modify: `app/Http/Controllers/API/V1/NotificationController.php`
- Test: `tests/Feature/Api/V1/NotificationDeliveryContractTest.php`

**Interfaces:**
- Consumes: existing `NotificationService::notifyUser/notifyMany`, existing database/broadcast notification flow.
- Produces: typed payload `link={version,route,params,fallback_url}` while keeping legacy `url` additive compatibility; `NotificationLinkRegistry::validate(NotificationLink $link): void`.

- [ ] **Step 1: Write RED tests** proving legacy URL callers still work, typed link serializes in database + `/api/v1/notifications`, arbitrary route names are rejected, params stay data not executable routes, and deep links grant no resource authority.
- [ ] **Step 2: Run targeted notification test and verify RED.**
- [ ] **Step 3: Implement `NotificationLink` and allowlist registry** with initial routes needed by existing project/group/notification journeys only.
- [ ] **Step 4: Add optional typed-link parameter to NotificationService/GenericNotification** without breaking current call signatures.
- [ ] **Step 5: Project typed link in NotificationController serializer** while preserving `url` for older v1 clients.
- [ ] **Step 6: Run targeted plus existing M1 notification contract tests; expect GREEN.**
- [ ] **Step 7: Commit** `feat(notifications): add typed deep link contract`.

### Task 4: Fan canonical notifications out to eligible push devices

**Files:**
- Create: `app/Services/Push/PushNotificationDispatcher.php`
- Modify: `app/Services/NotificationService.php`
- Modify: `app/Models/NativeDevice.php`
- Test: `tests/Feature/Api/V1/NotificationDeliveryContractTest.php`

**Interfaces:**
- Consumes: Task 2 gateway/eligibility; Task 3 canonical typed notification payload.
- Produces: `PushNotificationDispatcher::dispatch(User $user, array $notificationPayload): void`.

- [ ] **Step 1: Add RED tests** proving one canonical notification remains recorded even if push fails, all eligible active devices receive a delivery attempt, a device revoked after notification creation but before dispatch is excluded, invalid-token result disables push delivery but not the native auth session, temporary failure does not revoke/disable the session, and user notification preferences still govern delivery.
- [ ] **Step 2: Run targeted tests and verify RED.**
- [ ] **Step 3: Implement dispatcher** resolving devices at dispatch time and sending only minimal safe preview + typed destination hint.
- [ ] **Step 4: Wire NotificationService to dispatch asynchronously/after canonical notification intent** without making push success transactional with database notification storage.
- [ ] **Step 5: Persist delivery success/failure metadata on device safely.**
- [ ] **Step 6: Run targeted test plus election/project/Bahar notification regressions; expect GREEN.**
- [ ] **Step 7: Commit** `feat(notifications): deliver canonical notifications to native devices`.

### Task 5: Add the shared `/api/v1/media` resource

**Files:**
- Create: `app/Models/Media.php`
- Create: `database/migrations/2026_09_28_000002_create_media_table.php`
- Create: `app/Services/Media/MediaPolicy.php`
- Create: `app/Services/Media/MediaService.php`
- Create: `app/Http/Requests/API/V1/StoreMediaRequest.php`
- Create: `app/Http/Controllers/API/V1/MediaController.php`
- Create: `app/Http/Resources/API/V1/MediaResource.php`
- Modify: `routes/api-v1.php`
- Test: `tests/Feature/Api/V1/MediaContractTest.php`

**Interfaces:**
- Produces: `POST /api/v1/media`; `MediaService::store(User $uploader, UploadedFile $file, string $purpose): Media`; public identity `public_id`; purpose/MIME/size/hash/status/dimensions metadata.

- [ ] **Step 1: Write RED tests** for authenticated upload, server-generated ID, SHA-256, allowed MIME/size by purpose, extension/MIME deception rejection, unsupported purpose rejection, no raw storage path in API response, ownership enforcement, image metadata hook, and idempotent retry compatibility.
- [ ] **Step 2: Run targeted test and verify RED.**
- [ ] **Step 3: Add additive media schema/model** with uploader, purpose, visibility/status, storage disk/key internal fields, MIME/size/hash, width/height and scan/privacy state.
- [ ] **Step 4: Implement central MediaPolicy + MediaService** and keep existing ticket/chat upload endpoints untouched.
- [ ] **Step 5: Add controller/resource/route** behind auth + device session + v1 idempotency.
- [ ] **Step 6: Run targeted tests and existing upload regressions; expect GREEN.**
- [ ] **Step 7: Commit** `feat(api): add shared media resource`.

### Task 6: Add authoritative notification cursor recovery and realtime dedup semantics

**Files:**
- Create: `app/Services/Notifications/NotificationCursor.php`
- Modify: `app/Http/Controllers/API/V1/NotificationController.php`
- Modify: `app/Notifications/GenericNotification.php`
- Test: `tests/Feature/Api/V1/RealtimeRecoveryContractTest.php`
- Reference: existing Group Chat sequence/delta behavior.

**Interfaces:**
- Produces notification cursor query contract using opaque cursor and response `meta.pagination.next_cursor/has_more`; realtime payload carries stable `event_id`, `stream`, `cursor`, `type`, `occurred_at`.

- [ ] **Step 1: Write RED tests** for reconnect-after-gap recovery, duplicate event identity, stable ordering under new inserts, invalid cursor `422`, and proof existing Group Chat delta semantics remain unchanged.
- [ ] **Step 2: Run targeted test and verify RED.**
- [ ] **Step 3: Implement opaque notification cursor** based on stable `(created_at,id)` ordering, not page offsets.
- [ ] **Step 4: Extend notification API and broadcast payload** with additive cursor/event metadata.
- [ ] **Step 5: Run targeted + Group Chat delta regressions; expect GREEN.**
- [ ] **Step 6: Commit** `feat(notifications): add cursor based realtime recovery`.

### Task 7: Harden offline mutation replay on the existing v1 idempotency contract

**Files:**
- Modify: `app/Http/Middleware/ApiV1Idempotency.php` only if needed by failing tests.
- Test: `tests/Feature/Api/V1/OfflineReplayContractTest.php`
- Reference: one existing side-effecting v1 mutation with stable test fixture; do not invent a mobile-only domain mutation.

**Interfaces:**
- Consumes: existing `Idempotency-Key` semantics and current auth/resource policy.
- Produces stable replay outcomes: original replay, `idempotency_key_reused`, current authorization failure, state conflict, validation change, optional `expired_offline_action` where a route defines expiry.

- [ ] **Step 1: Write RED acceptance tests** around one real side-effecting v1 mutation proving exact replay does not duplicate side effects, changed payload conflicts, revoked/downgraded authority on replay is not revived by stale client intent, deleted/concealed resource stays unavailable, and retryable 5xx semantics remain safe.
- [ ] **Step 2: Run targeted test and distinguish existing-green behavior from missing classifications; only missing behavior may drive code changes.**
- [ ] **Step 3: Make the minimal idempotency/error-classification changes required** without duplicating domain idempotency.
- [ ] **Step 4: Run targeted plus existing `IdempotencyContractTest`; expect GREEN.**
- [ ] **Step 5: Commit** `test(api): harden offline replay semantics` or `feat(api): harden offline replay semantics` depending on whether production code changed.

### Task 8: Add per-platform app compatibility bootstrap

**Files:**
- Create: `config/client-compatibility.php`
- Create: `app/Services/ClientCompatibility/ClientVersionPolicy.php`
- Create: `app/Services/ClientCompatibility/ClientCompatibilityService.php`
- Create: `app/Http/Controllers/API/V1/BootstrapController.php`
- Modify: `routes/api-v1.php`
- Test: `tests/Feature/Api/V1/BootstrapCompatibilityTest.php`

**Interfaces:**
- Produces: `GET /api/v1/bootstrap?platform=android|ios&version=<semver>`; `ClientCompatibilityService::evaluate(string $platform, string $version): ClientVersionPolicy`.

- [ ] **Step 1: Write RED tests** for Android/iOS independent minimum/latest versions, below minimum required update, between minimum/latest recommendation only, equal/above latest no recommendation, prerelease/semantic ordering, unsupported platform and malformed version stable error behavior.
- [ ] **Step 2: Run targeted test and verify RED.**
- [ ] **Step 3: Implement configuration + semantic-version comparison** without lexical comparison and without client-framework assumptions.
- [ ] **Step 4: Add bootstrap controller/route** safe before normal navigation and using standard v1 envelope.
- [ ] **Step 5: Run targeted transport/auth regressions; expect GREEN.**
- [ ] **Step 6: Commit** `feat(mobile): add client compatibility bootstrap`.

### Task 9: Prove the M5 native-delivery acceptance journey

**Files:**
- Create: `tests/Feature/Api/V1/M5NativeDeliveryAcceptanceTest.php`
- Modify only product files when a genuine acceptance gap is exposed, each gap under RED→GREEN.

**Interfaces:**
- Consumes all Tasks 1–8.
- Produces one end-to-end M5 acceptance journey independent of Flutter/NativePHP.

- [ ] **Step 1: Write the acceptance test**: native login/device → register push → create canonical typed notification → eligible delivery → revoke device/session → later protected delivery excluded → notification gap recovered via cursor → replay one keyed mutation without duplicate side effect → bootstrap old app returns `update_required=true`.
- [ ] **Step 2: Run acceptance test and verify any RED is a real integration gap.**
- [ ] **Step 3: Fix only real gaps one by one with focused RED→GREEN tests.**
- [ ] **Step 4: Run acceptance test plus Task 1–8 M5 tests; expect GREEN.**
- [ ] **Step 5: Commit** `test(mobile): prove M5 native delivery journey`.

### Task 10: M1–M5 regression gate and final architecture evidence

**Files:**
- Create or modify: `.github/workflows/m5-native-delivery-targeted.yml` following existing milestone targeted-gate conventions.
- Modify: `docs/PRE_NATIVE_MOBILE_READINESS_STATUS.fa.md` only after exact-candidate evidence exists.

**Interfaces:**
- Consumes: final M5 candidate SHA.
- Produces reproducible targeted gate and evidence package for Post-M5 Architecture Gate.

- [ ] **Step 1: Define targeted gate** covering NativeSession, transport/idempotency, M2 Hoda authority, M3 Bahar API/investment regressions, M4 actor/project boundary, and all M5 tests.
- [ ] **Step 2: Run targeted gate on exact candidate; fix any failure by root cause, never by weakening tests.**
- [ ] **Step 3: Run repository Full Validation once on the same exact candidate SHA.**
- [ ] **Step 4: Confirm architecture invariants**: no cookie dependency in pilot native journey; no provider SDK from business services; no framework-specific mobile contract; no raw media path identity; offline replay rechecks current authority; M2/M3/M4 invariants green.
- [ ] **Step 5: Update readiness status with exact run IDs/counts only after both gates are green.**
- [ ] **Step 6: Commit** `docs(mobile): record M5 architecture gate evidence`.

---

## Completion Contract

M5 is complete only when one exact candidate SHA has:

- all Task 1–9 targeted tests green;
- M1 native session/idempotency regressions green;
- M2 Najm Hoda authority regressions green;
- M3 Najm Bahar money/investment regressions green;
- M4 actor/project authority regressions green;
- M5 targeted workflow green;
- repository Full Validation green;
- no unresolved Critical/Important finding from final whole-branch review.

Only then may PR #163 move from draft/design+implementation candidate to merge candidate, after which the Post-M5 Architecture Gate can begin the Flutter vs NativePHP PoC comparison without backend coupling.
