# M6 Flutter Native Client Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the first production-grade Flutter native client foundation for EarthCoop, consuming the existing `/api/v1` contracts without duplicating Laravel business rules, and prove the vertical slice from bootstrap/login through groups, notifications, typed deep links, offline replay, media, and real GMS/Huawei push delivery.

**Architecture:** Flutter lives at `apps/mobile/` in the existing monorepo. Laravel remains the sole business authority; Flutter owns presentation, transport adapters, secure session state, non-authoritative local cache, offline queue metadata, and platform integrations. Provider/vendor code is isolated behind interfaces; all feature behavior consumes shared client services and `/api/v1`.

**Tech Stack:** Flutter stable (exact version pinned at implementation start), Dart, Riverpod, Dio, go_router, Drift/SQLite, flutter_secure_storage, json_serializable/build_runner, Firebase Messaging for GMS/iOS path, Huawei Push Kit for non-GMS Android, Laravel 12/PHP 8.2+ backend, PHPUnit 11, GitHub Actions.

**Spec:** `docs/superpowers/specs/2026-09-29-m6-flutter-native-client-foundation-design.md` plus normative clarifications in `docs/superpowers/specs/2026-09-29-m6-flutter-native-client-foundation-clarifications.md`.

**Plan self-review supplement:** `docs/superpowers/plans/2026-09-29-m6-flutter-native-client-foundation-review-clarifications.md` is normative where it is more specific, including FCM auth, Huawei Push Kit V3 server delivery, base-URL/environment rules, and accessibility coverage.

## Global Constraints

- Flutter is the official production Native client path; do not create NativePHP scaffolding or compatibility work.
- App source lives under `apps/mobile/`; Flutter dependencies must not enter Composer/NPM runtime paths.
- Laravel `/api/v1` remains authoritative for all business rules and authorization.
- Native auth uses M1 bearer/device-session contracts; no browser cookie emulation.
- Android minimum API 24 unless a required selected plugin proves a higher floor unavoidable; iOS minimum 15.
- Persian RTL and system/light/dark theme support exist from the first usable slice.
- Credentials and bearer tokens are stored only in platform secure storage/memory, never Drift tables or ordinary logs.
- Realtime and push are hints/optimizations; authoritative recovery uses API cursor/delta reads.
- Offline queued mutations preserve the original idempotency key and must pass current server auth/policy on replay.
- No financial mutation, election ballot, Hoda consent/apply, or sensitive content creation is queued offline in M6.
- Push provider contract extends additively to `fcm|apns|hms`; provider identity never becomes authorization.
- FCM/HMS delivery credentials are deployment secrets and never committed to git or bundled into Flutter.
- Use official provider HTTP/server APIs behind `PushDeliveryGateway` adapters; feature/domain services must not import provider SDKs.
- Do not run repository Full Validation for routine Dart-only checkpoints; use Flutter-targeted/affected backend tests and one final full gate on the exact final candidate.

## Review Focus

1. **Stale bootstrap + reconnect:** an app launched offline from a previously valid snapshot may show degraded cached state, but after connectivity returns no queued mutation replays until a fresh bootstrap succeeds; `update_required=true` blocks replay.
2. **Revoked/changed authority:** a locally queued notification-read or other future allowlisted mutation must not bypass current server authorization merely because it was queued while authority/session was valid.
3. **Non-GMS Android:** Huawei runtime must not attempt Firebase-only initialization as a correctness requirement; HMS registration and real delivery must work on a non-GMS device.
4. **Duplicate/reordered events:** notification cursor sync, push hints, app resume and offline replay must remain duplicate-safe and recover authoritative state without depending on arrival order.
5. **Secret/log leakage:** bearer tokens, push tokens, passwords, provider credentials and sensitive bodies must be redacted from diagnostics, exceptions and CI artifacts.

---

## File/Module Map

### Flutter app

- `apps/mobile/pubspec.yaml` — pinned mobile dependencies and Dart/Flutter SDK constraints.
- `apps/mobile/pubspec.lock` — committed dependency lock.
- `apps/mobile/lib/main.dart` — minimal entry point only.
- `apps/mobile/lib/app/bootstrap/` — startup orchestration and degraded-start/version gate.
- `apps/mobile/lib/app/router/` — go_router configuration and semantic deep-link registry.
- `apps/mobile/lib/app/theme/` — Material 3 EarthCoop theme.
- `apps/mobile/lib/app/localization/` — Persian-first localization/RTL infrastructure.
- `apps/mobile/lib/core/api/` — Dio transport, envelope/error decoding, request IDs, idempotency, retry policy.
- `apps/mobile/lib/core/auth/` — secure session repository and authenticated app state.
- `apps/mobile/lib/core/device/` — native device metadata/session binding.
- `apps/mobile/lib/core/local/` — Drift database and cache infrastructure.
- `apps/mobile/lib/core/offline/` — allowlisted mutation queue and replay engine.
- `apps/mobile/lib/core/deep_links/` — server semantic link parsing/validation.
- `apps/mobile/lib/core/push/` — provider-neutral push token source and adapters.
- `apps/mobile/lib/core/media/` — media upload client and picker abstraction.
- `apps/mobile/lib/core/logging/` — redacted diagnostics boundary.
- `apps/mobile/lib/features/auth/` — login/session UI/application flow.
- `apps/mobile/lib/features/home/` — authenticated shell/home.
- `apps/mobile/lib/features/groups/` — groups list/detail vertical slice.
- `apps/mobile/lib/features/notifications/` — list/recovery/read flow.
- `apps/mobile/test/` — unit/widget tests.
- `apps/mobile/integration_test/` — integration/acceptance tests.

### Backend additions/changes

- `app/Services/Push/PushRegistrationService.php` — extend provider allowlist to `hms`.
- `app/Services/Push/PushDeliveryGateway.php` — retained provider-neutral contract.
- `app/Services/Push/CompositePushDeliveryGateway.php` — provider dispatcher.
- `app/Services/Push/FcmHttpV1PushDeliveryGateway.php` — FCM HTTP v1 adapter.
- `app/Services/Push/HmsPushDeliveryGateway.php` — Huawei Push Kit server adapter.
- `app/Services/Push/FcmAccessTokenProvider.php` — short-lived OAuth2 access token source from deployment credentials.
- `app/Services/Push/HmsAccessTokenProvider.php` — Huawei server-auth/JWT access boundary according to current official API contract.
- `composer.json` / `composer.lock` — only if the self-review-required Google Auth backend dependency is added in Task 11.
- `config/services.php` — provider endpoints/project IDs/credential-path references only; no secrets committed.
- Existing push tests plus new provider-adapter tests.

### CI

- `.github/workflows/flutter-mobile-targeted.yml` — Flutter format/analyze/test/Android build and backend contract tests when shared API files change.
- Existing backend workflows remain unchanged except path coverage where required for M6 backend push files.

---

### Task 1: Scaffold the Flutter client and pin the toolchain

**Files:**
- Create: `apps/mobile/pubspec.yaml`
- Create: `apps/mobile/pubspec.lock`
- Create: `apps/mobile/analysis_options.yaml`
- Create: `apps/mobile/lib/main.dart`
- Create: `apps/mobile/lib/app/app.dart`
- Create: `apps/mobile/test/app_smoke_test.dart`
- Create/retain generated platform projects under `apps/mobile/android/` and `apps/mobile/ios/`
- Create: `apps/mobile/README.md`

**Interfaces:**
- Consumes: none.
- Produces: `EarthCoopApp`, pinned Flutter SDK metadata documented in `apps/mobile/README.md`, runnable Android/iOS Flutter project with no business behavior yet.

- [ ] **Step 1: Record the exact current Flutter stable version available in the implementation environment and document it as the required M6 version.**

- [ ] **Step 2: Create the minimal package manifest and first failing smoke test** asserting `EarthCoopApp` renders a deterministic app-root marker and Persian locale infrastructure is present; do not implement `EarthCoopApp` yet.

- [ ] **Step 3: Run the test to verify RED.**

Run: `cd apps/mobile && flutter test test/app_smoke_test.dart`

Expected: FAIL because `EarthCoopApp`/app root does not exist.

- [ ] **Step 4: Scaffold Android/iOS Flutter project files and implement the minimal `EarthCoopApp` needed for the smoke test.** Set Android minSdk to 24 and iOS deployment target to 15.0.

- [ ] **Step 5: Add Riverpod, Dio, go_router, Drift, secure storage, localization/codegen dependencies plus test/codegen tooling; run dependency resolution and commit `pubspec.lock`.** Use current stable package versions compatible with the pinned Flutter SDK; do not add Firebase/Huawei packages until the push task.

- [ ] **Step 6: Verify GREEN plus static analysis.**

Run:
- `flutter test test/app_smoke_test.dart`
- `dart format --set-exit-if-changed lib test`
- `flutter analyze`

Expected: all PASS/exit 0.

- [ ] **Step 7: Commit.**

Commit message: `feat(mobile): scaffold Flutter client foundation`

---

### Task 2: Build the shared API transport and stable error model

**Files:**
- Create: `apps/mobile/lib/core/api/api_client.dart`
- Create: `apps/mobile/lib/core/api/api_envelope.dart`
- Create: `apps/mobile/lib/core/api/api_error.dart`
- Create: `apps/mobile/lib/core/api/request_context.dart`
- Create: `apps/mobile/lib/core/api/retry_policy.dart`
- Create: `apps/mobile/lib/core/logging/diagnostics.dart`
- Test: `apps/mobile/test/core/api/api_client_test.dart`
- Test: `apps/mobile/test/core/api/retry_policy_test.dart`
- Test: `apps/mobile/test/core/logging/diagnostics_test.dart`

**Interfaces:**
- Consumes: Dio; M0 `/api/v1` envelope/request-id/idempotency semantics.
- Produces: `ApiClient`, `ApiResult<T>`, `ApiFailure`, `RequestContext`, `RetryPolicy`, `DiagnosticsSink`.

- [ ] **Step 1: Write failing transport tests** covering success/error envelope decoding, `X-Request-ID`, bearer attachment hook, same idempotency key on mutation retry, `Retry-After`, `retryable=false`, malformed envelope, environment/base-URL selection, localhost rejection for production configuration, and redaction of Authorization/push token/password fields.

- [ ] **Step 2: Run only these tests and confirm expected RED due missing transport types.**

- [ ] **Step 3: Implement `ApiClient` as the only feature-facing HTTP boundary.** Features receive typed request methods; no feature may access raw Dio directly. Add typed `AppEnvironment`/base-URL configuration per the plan review supplement.

- [ ] **Step 4: Implement bounded read retry with jitter and mutation replay rules that never synthesize a new idempotency key.**

- [ ] **Step 5: Implement structured diagnostics redaction and request-id retention.**

- [ ] **Step 6: Run transport/logging tests plus analyze and confirm GREEN.**

- [ ] **Step 7: Commit.**

Commit message: `feat(mobile): add shared API transport`

---

### Task 3: Implement app bootstrap/version compatibility and degraded offline start

**Files:**
- Create: `apps/mobile/lib/app/bootstrap/app_bootstrap_service.dart`
- Create: `apps/mobile/lib/app/bootstrap/bootstrap_models.dart`
- Create: `apps/mobile/lib/app/bootstrap/bootstrap_snapshot_store.dart`
- Create: `apps/mobile/lib/app/bootstrap/bootstrap_state.dart`
- Create: `apps/mobile/lib/core/local/app_database.dart`
- Test: `apps/mobile/test/app/bootstrap/app_bootstrap_service_test.dart`
- Widget test: `apps/mobile/test/app/bootstrap/bootstrap_gate_test.dart`

**Interfaces:**
- Consumes: `ApiClient`, `GET /api/v1/bootstrap`, app platform/version metadata.
- Produces: `BootstrapDecision` (`compatible`, `recommendedUpdate`, `requiredUpdate`, `degradedOffline`, `unavailable`), persisted non-secret last-success snapshot.

- [ ] **Step 1: Write failing tests** for compatible/recommended/required version outcomes, missing/invalid bootstrap, first-ever offline launch, previously-valid offline snapshot, reconnect requiring fresh bootstrap, and `update_required` blocking queued replay.

- [ ] **Step 2: Verify RED.**

- [ ] **Step 3: Implement bootstrap service and non-secret snapshot persistence.** Snapshot must store enough compatibility metadata/time to enable the clarified degraded-start policy, but no bearer/session secret.

- [ ] **Step 4: Implement bootstrap gate UI states and prevent authenticated routing until policy allows it.**

- [ ] **Step 5: Run task tests and confirm GREEN.**

- [ ] **Step 6: Commit.**

Commit message: `feat(mobile): enforce app compatibility bootstrap`

---

### Task 4: Implement secure native session/device lifecycle

**Files:**
- Create: `apps/mobile/lib/core/auth/session_repository.dart`
- Create: `apps/mobile/lib/core/auth/session_models.dart`
- Create: `apps/mobile/lib/core/auth/session_controller.dart`
- Create: `apps/mobile/lib/core/auth/secure_session_store.dart`
- Create: `apps/mobile/lib/core/device/device_context.dart`
- Create: `apps/mobile/lib/features/auth/login_controller.dart`
- Create: `apps/mobile/lib/features/auth/login_screen.dart`
- Test: `apps/mobile/test/core/auth/session_controller_test.dart`
- Widget test: `apps/mobile/test/features/auth/login_screen_test.dart`

**Interfaces:**
- Consumes: M1 `POST/GET/POST rotate/DELETE /api/v1/auth/session`, secure storage abstraction, `ApiClient`.
- Produces: `SessionRepository`, `SessionController`, `SessionState`, `DeviceContext`, authenticated bearer provider for `ApiClient`.

- [ ] **Step 1: Write failing tests** for login success/failure, token never entering Drift, restore + `/auth/session` validation, revoked session, rotate, logout cleanup, device metadata binding, and diagnostics redaction.

- [ ] **Step 2: Verify RED.**

- [ ] **Step 3: Implement secure-store-backed session repository and device context.**

- [ ] **Step 4: Implement session state machine and login UI with Persian/RTL-ready copy.**

- [ ] **Step 5: Implement logout ordering:** server revoke when reachable → push disable hook → clear credentials → clear user-scoped local state → unauthenticated shell. Define safe local cleanup even when server revoke cannot be reached.

- [ ] **Step 6: Run auth tests, analyze and confirm GREEN.**

- [ ] **Step 7: Commit.**

Commit message: `feat(mobile): add secure native session lifecycle`

---

### Task 5: Establish router, typed deep links, theme and localization

**Files:**
- Create: `apps/mobile/lib/app/router/app_router.dart`
- Create: `apps/mobile/lib/core/deep_links/semantic_link.dart`
- Create: `apps/mobile/lib/core/deep_links/deep_link_registry.dart`
- Create: `apps/mobile/lib/app/theme/earthcoop_theme.dart`
- Create: `apps/mobile/lib/app/localization/` generated/localization source files
- Create: `apps/mobile/lib/features/home/home_screen.dart`
- Test: `apps/mobile/test/core/deep_links/deep_link_registry_test.dart`
- Widget test: `apps/mobile/test/app/router/router_test.dart`
- Widget test: `apps/mobile/test/app/theme/rtl_theme_test.dart`

**Interfaces:**
- Consumes: M5 typed link `{version, route, params, fallback_url}`.
- Produces: `SemanticLink`, `DeepLinkRegistry.resolve()`, authenticated `GoRouter`, Material 3 light/dark themes and Persian locale.

- [ ] **Step 1: Write failing tests** for allowlisted `group.detail`, missing/invalid params, unknown route, arbitrary URL rejection, auth-required destination, safe fallback behavior, RTL directionality, theme switching, large text scaling without clipping primary controls, and semantic labels for primary M6 interactions.

- [ ] **Step 2: Verify RED.**

- [ ] **Step 3: Implement semantic route registry independent from concrete URL paths.**

- [ ] **Step 4: Implement app router guards using bootstrap + session state; deep links must route only after normal authorization/read flow is possible.**

- [ ] **Step 5: Implement Persian localization and Material 3 light/dark themes with accessibility-safe primary controls.**

- [ ] **Step 6: Run task tests and confirm GREEN.**

- [ ] **Step 7: Commit.**

Commit message: `feat(mobile): add routing localization and theme foundation`

---

### Task 6: Implement Groups list/detail vertical slice

**Files:**
- Create: `apps/mobile/lib/features/groups/group_dto.dart`
- Create: `apps/mobile/lib/features/groups/group_repository.dart`
- Create: `apps/mobile/lib/features/groups/groups_controller.dart`
- Create: `apps/mobile/lib/features/groups/groups_screen.dart`
- Create: `apps/mobile/lib/features/groups/group_detail_screen.dart`
- Test: `apps/mobile/test/features/groups/group_repository_test.dart`
- Widget test: `apps/mobile/test/features/groups/groups_screen_test.dart`
- Widget test: `apps/mobile/test/features/groups/group_detail_screen_test.dart`

**Interfaces:**
- Consumes: `GET /api/v1/groups`, `GET /api/v1/groups/{group}`.
- Produces: typed client projections only; no copied membership/role/election authority rules.

- [ ] **Step 1: Write failing repository/widget tests** for list/detail, empty/loading/retryable/non-retryable/forbidden states, unknown additive fields, stale cached projection, and deep-link-opened group requiring normal API read.

- [ ] **Step 2: Verify RED.**

- [ ] **Step 3: Implement DTO/repository/controller and minimal mobile-native list/detail UI.**

- [ ] **Step 4: Add bounded non-authoritative group cache only if required to prove degraded view; never infer access from cache.**

- [ ] **Step 5: Run task tests and confirm GREEN.**

- [ ] **Step 6: Commit.**

Commit message: `feat(mobile): add groups vertical slice`

---

### Task 7: Implement notification cursor recovery and local projection

**Files:**
- Create: `apps/mobile/lib/features/notifications/notification_dto.dart`
- Create: `apps/mobile/lib/features/notifications/notification_repository.dart`
- Create: `apps/mobile/lib/features/notifications/notification_sync_service.dart`
- Create: `apps/mobile/lib/features/notifications/notifications_controller.dart`
- Create: `apps/mobile/lib/features/notifications/notifications_screen.dart`
- Modify: `apps/mobile/lib/core/local/app_database.dart`
- Test: `apps/mobile/test/features/notifications/notification_sync_service_test.dart`
- Widget test: `apps/mobile/test/features/notifications/notifications_screen_test.dart`

**Interfaces:**
- Consumes: M5 `/api/v1/notifications` cursor contract and typed links.
- Produces: duplicate-safe local notification projection, persisted next cursor, authoritative sync-on-resume method.

- [ ] **Step 1: Write failing tests** for first sync, multi-page continuation, duplicate push/resume hint, out-of-order local triggers, invalid cursor recovery behavior, authoritative deletion/change refresh, and typed-link projection.

- [ ] **Step 2: Verify RED.**

- [ ] **Step 3: Implement cursor sync with server notification identity as dedupe key.**

- [ ] **Step 4: Implement notification screen states and typed-link navigation.**

- [ ] **Step 5: Run task tests and confirm GREEN.**

- [ ] **Step 6: Commit.**

Commit message: `feat(mobile): add notification cursor recovery`

---

### Task 8: Implement the offline mutation queue using notification-read as the first operation

**Files:**
- Create: `apps/mobile/lib/core/offline/offline_operation.dart`
- Create: `apps/mobile/lib/core/offline/offline_queue_repository.dart`
- Create: `apps/mobile/lib/core/offline/offline_replay_engine.dart`
- Create: `apps/mobile/lib/core/offline/offline_operation_registry.dart`
- Modify: `apps/mobile/lib/core/local/app_database.dart`
- Modify: `apps/mobile/lib/features/notifications/notification_repository.dart`
- Test: `apps/mobile/test/core/offline/offline_replay_engine_test.dart`
- Test: `apps/mobile/test/features/notifications/notification_read_offline_test.dart`

**Interfaces:**
- Consumes: M5 idempotency contract; bootstrap/session state; `POST /api/v1/notifications/{notification}/read`.
- Produces: queue entries containing `idempotency_key`, `created_at`, `resource`, `operation`, `payload_hash`, `client_sequence`, `payload`, `state`, `attempt_count`, `last_error_code`; `OfflineReplayEngine.replayEligible()`.

- [ ] **Step 1: Write failing tests** for one read queued offline, exact idempotency-key preservation, duplicate tap coalescing/no duplicate side effect, retryable transient retry, non-retryable conflict stop, revoked session, fresh-bootstrap prerequisite after reconnect, `update_required` replay block, and queue ownership cleanup on logout.

- [ ] **Step 2: Verify RED.**

- [ ] **Step 3: Implement registry with only `notification.mark_read` allowlisted.** Reject unknown operations rather than generically replaying arbitrary requests.

- [ ] **Step 4: Implement deterministic payload hashing, monotonic client sequence and replay engine.** Connectivity is a hint only; API result controls success.

- [ ] **Step 5: Integrate notification read optimistic/local UI state while preserving authoritative sync correction.**

- [ ] **Step 6: Run offline tests and confirm GREEN.**

- [ ] **Step 7: Commit.**

Commit message: `feat(mobile): add idempotent offline replay queue`

---

### Task 9: Implement shared media upload client

**Files:**
- Create: `apps/mobile/lib/core/media/media_client.dart`
- Create: `apps/mobile/lib/core/media/media_resource.dart`
- Create: `apps/mobile/lib/core/media/media_picker.dart`
- Test: `apps/mobile/test/core/media/media_client_test.dart`
- Integration test: `apps/mobile/integration_test/media_upload_test.dart`

**Interfaces:**
- Consumes: M5 `POST /api/v1/media` multipart contract.
- Produces: `MediaClient.upload({required String purpose, required SelectedMedia file, required String idempotencyKey}) -> MediaResource` with progress/cancellation.

- [ ] **Step 1: Write failing tests** for opaque returned media ID, purpose propagation, multipart file body, same idempotency key on retry, validation errors, progress, cancellation, and no server storage path assumption.

- [ ] **Step 2: Verify RED.**

- [ ] **Step 3: Implement media client and platform-neutral picker interface.**

- [ ] **Step 4: Add one controlled real-backend image upload integration test; do not redesign legacy web upload paths.**

- [ ] **Step 5: Run unit/integration test in controlled environment and confirm GREEN.**

- [ ] **Step 6: Commit.**

Commit message: `feat(mobile): add shared media upload client`

---

### Task 10: Extend backend push registration contract to HMS

**Files:**
- Modify: `app/Services/Push/PushRegistrationService.php`
- Modify if needed: `app/Http/Requests/API/V1/DevicePushRequest.php`
- Test: `tests/Feature/Api/V1/DevicePushContractTest.php`
- Test: `tests/Unit/Services/Push/PushRegistrationServiceTest.php` if service-specific coverage is clearer.

**Interfaces:**
- Consumes: existing M5 device/push contract.
- Produces: additive accepted provider enum `fcm|apns|hms`; no other auth/device behavior changes.

- [ ] **Step 1: Add failing backend tests** proving current device may register/rotate/disable an `hms` token, provider token is never echoed, uniqueness/ownership rules remain intact, and unsupported providers still fail closed.

- [ ] **Step 2: Run only push registration tests and confirm RED due `hms` rejection.**

- [ ] **Step 3: Make the minimal allowlist/validation change to support `hms`.**

- [ ] **Step 4: Run M5 device push tests and confirm GREEN.**

- [ ] **Step 5: Commit.**

Commit message: `feat(push): support Huawei delivery registrations`

---

### Task 11: Add real server-side FCM and HMS delivery adapters behind the M5 gateway

**Files:**
- Create: `app/Services/Push/CompositePushDeliveryGateway.php`
- Create: `app/Services/Push/FcmHttpV1PushDeliveryGateway.php`
- Create: `app/Services/Push/HmsPushDeliveryGateway.php`
- Create: `app/Services/Push/FcmAccessTokenProvider.php`
- Create: `app/Services/Push/HmsAccessTokenProvider.php`
- Modify: `app/Providers/PushServiceProvider.php`
- Modify: `config/services.php`
- Modify: `composer.json` and `composer.lock` only for the mature Google Auth dependency required by the review supplement.
- Test: `tests/Unit/Services/Push/CompositePushDeliveryGatewayTest.php`
- Test: `tests/Unit/Services/Push/FcmHttpV1PushDeliveryGatewayTest.php`
- Test: `tests/Unit/Services/Push/HmsPushDeliveryGatewayTest.php`
- Existing: `tests/Feature/Api/V1/PushNotificationDeliveryTest.php`

**Interfaces:**
- Consumes: existing `PushDeliveryGateway::send(NativeDevice $device, PushEnvelope $envelope): PushDeliveryResult`; encrypted device token; deployment secrets/config.
- Produces: provider-dispatched FCM/HMS delivery using official server APIs, normalized invalid-token/temporary/permanent failure taxonomy.

- [ ] **Step 1: Write failing tests** with fake HTTP clients/token providers for: FCM provider dispatch, HMS provider dispatch, unknown provider fail-safe, credential missing fail-safe, invalid-token normalization, 429/5xx temporary failure, provider 4xx permanent failure, no token/credential logging, and revocation eligibility still checked before dispatch.

- [ ] **Step 2: Verify RED.**

- [ ] **Step 3: Implement composite dispatcher.** Existing null/fake gateway remains available only for test/local non-provider environments; production binding selects configured real composite gateway.

- [ ] **Step 4: Add/use mature Google Auth infrastructure rather than handwritten JWT/OAuth, and implement FCM HTTP v1 adapter** for `POST https://fcm.googleapis.com/v1/projects/{project_id}/messages:send` using a short-lived token scoped for Firebase Messaging. Service-account credentials are referenced via deployment secret/file path, never committed.

- [ ] **Step 5: Implement HMS V3 server adapter** for `POST https://push-api.cloud.huawei.com/v3/{projectId}/messages:send` using the current Huawei service-account/JWT Bearer contract defined in the review supplement. Do not use legacy V1/V2 endpoints silently.

- [ ] **Step 6: Normalize provider responses into existing `PushDeliveryResult`; invalid registration disables push only, not auth session.**

- [ ] **Step 7: Run provider unit tests + M5 push delivery regression and confirm GREEN without production credentials.**

- [ ] **Step 8: Commit.**

Commit message: `feat(push): add FCM and Huawei delivery gateways`

---

### Task 12: Implement Flutter push adapters and token lifecycle

**Files:**
- Create: `apps/mobile/lib/core/push/push_token_source.dart`
- Create: `apps/mobile/lib/core/push/push_registration_service.dart`
- Create: `apps/mobile/lib/core/push/push_provider_selector.dart`
- Create: `apps/mobile/lib/core/push/fcm_push_token_source.dart`
- Create: `apps/mobile/lib/core/push/hms_push_token_source.dart`
- Create/modify Android platform configuration files required by Firebase/Huawei plugins without committing secret signing credentials.
- Test: `apps/mobile/test/core/push/push_provider_selector_test.dart`
- Test: `apps/mobile/test/core/push/push_registration_service_test.dart`

**Interfaces:**
- Consumes: Flutter Firebase Messaging/Huawei Push Kit plugins; M5/M6 device push PUT/DELETE endpoints.
- Produces: `PushTokenSource`, `PushRegistrationService`, runtime provider selection and token-change registration.

- [ ] **Step 1: Add selected stable Firebase Messaging and Huawei Push Kit Flutter dependencies compatible with the pinned Flutter SDK.** Document any Android build constraints before accepting a minSdk increase; do not raise API 24 silently.

- [ ] **Step 2: Write failing tests** for GMS→FCM, non-GMS Huawei→HMS, no supported provider→push unavailable, token rotation, logout disable, no token logging, and repeated initialization idempotence.

- [ ] **Step 3: Verify RED.**

- [ ] **Step 4: Implement provider selector based on runtime capability, not Android brand string.**

- [ ] **Step 5: Implement FCM/HMS token sources behind the shared interface and register through `/api/v1/devices/{device}/push`.**

- [ ] **Step 6: Wire push open/tap to typed deep-link intake only; authoritative notification sync still runs on resume/open.**

- [ ] **Step 7: Run unit/widget tests and Android debug build; confirm GREEN.**

- [ ] **Step 8: Commit.**

Commit message: `feat(mobile): add provider-neutral push lifecycle`

---

### Task 13: Build the M6 end-to-end vertical slice and physical-device acceptance hooks

**Files:**
- Create: `apps/mobile/integration_test/m6_vertical_slice_test.dart`
- Create: `apps/mobile/integration_test/offline_reconnect_test.dart`
- Create: `apps/mobile/integration_test/deep_link_notification_test.dart`
- Create: `apps/mobile/docs/DEVICE_ACCEPTANCE.md`
- Modify only feature/foundation files when a genuine integration gap is exposed under RED→GREEN.

**Interfaces:**
- Consumes: Tasks 1–12.
- Produces: one acceptance journey: bootstrap → login → Home → Groups → Group detail → Notifications → typed deep link, with offline notification-read replay and media upload.

- [ ] **Step 1: Write failing integration/acceptance tests** against deterministic adapters plus a controlled real `/api/v1` environment for the core journey.

- [ ] **Step 2: Verify RED only for genuine unintegrated gaps.**

- [ ] **Step 3: Fix integration gaps minimally; do not broaden scope into marketplace/elections/Bahar/Hoda UI.**

- [ ] **Step 4: Verify Android emulator/device acceptance journey GREEN.**

- [ ] **Step 5: Execute physical GMS Android checklist:** login/session, token registration, real push receipt/tap, cursor recovery, logout/revocation delivery stop.

- [ ] **Step 6: Execute physical non-GMS Huawei checklist:** HMS registration, **real server-originated notification delivery**, tap/deep link, cursor recovery, token rotation where practical, logout/revocation delivery stop. Token registration alone is not acceptance.

- [ ] **Step 7: Execute iOS compile gate on macOS; where device access exists also verify native session and push. If physical iOS access is unavailable, record that explicitly as release-readiness work rather than falsely claiming device acceptance.**

- [ ] **Step 8: Commit only code/docs resulting from verified gaps or acceptance documentation.**

Commit message: `test(mobile): prove M6 vertical slice`

---

### Task 14: Add Flutter-targeted CI with affected-backend coverage

**Files:**
- Create: `.github/workflows/flutter-mobile-targeted.yml`
- Modify: `.github/workflows/m5-native-delivery-targeted.yml` path filters only if needed for new backend push adapter files.
- Test/validation: workflow syntax and an intentional mobile-only candidate.

**Interfaces:**
- Consumes: complete M6 file layout.
- Produces: fast gate for mobile changes and precise backend regression selection.

- [ ] **Step 1: Add workflow triggers for `apps/mobile/**`, M6 shared API contract files, backend push files and workflow itself.**

- [ ] **Step 2: Configure Flutter SDK from the exact pinned M6 version and dependency cache keyed by lockfile.**

- [ ] **Step 3: Fast mobile gate runs:** dependency resolution, `dart format --set-exit-if-changed`, `flutter analyze`, all unit/widget tests, Android debug build.

- [ ] **Step 4: When backend push/API files change, also run targeted PHPUnit M1/M5 push/device/idempotency contracts.** Do not run full repository validation on Dart-only UI commits.

- [ ] **Step 5: Add milestone/final iOS compile job on macOS with no production signing secrets required for compile validation.**

- [ ] **Step 6: Push workflow-only checkpoint and confirm the workflow actually triggers and is GREEN before changing other files.**

- [ ] **Step 7: Commit.**

Commit message: `ci: add Flutter mobile targeted gate`

---

### Task 15: Final M6 architecture/regression gate

**Files:**
- Modify: `docs/PRE_NATIVE_MOBILE_READINESS_STATUS.fa.md` or create a dedicated M6 status document only after evidence exists.
- Modify PR description with exact candidate SHA/run evidence.

**Interfaces:**
- Consumes: final candidate from Tasks 1–14.
- Produces: auditable M6 completion evidence; no code behavior.

- [ ] **Step 1: Freeze one candidate SHA.** Do not continue feature work while final gates run.

- [ ] **Step 2: Run Flutter targeted gate on that exact SHA and require GREEN.**

- [ ] **Step 3: Run affected backend M1/M5 push/device/idempotency suites on the same SHA and require GREEN.**

- [ ] **Step 4: Run physical GMS + Huawei acceptance evidence on the same product candidate/configuration.** Record device class/OS/app build and behavior, never provider tokens.

- [ ] **Step 5: Run iOS compile gate on the exact candidate.**

- [ ] **Step 6: Run repository Full Validation exactly once on the final candidate because M6 changes backend push contracts/providers.** Require integrated migrations, route boot, Hoda, Governance/Location, Bahar and Full Project PHPUnit to remain GREEN.

- [ ] **Step 7: Perform whole-branch architecture review:** no browser-cookie dependency; no mobile-only domain truth; no financial float authority; no Firebase/Huawei imports outside platform/push boundaries; no secrets in repo/logs; no raw provider token in API responses; no arbitrary deep-link route execution; offline replay rechecks fresh bootstrap/session/server policy.

- [ ] **Step 8: Update status/PR with exact run IDs and candidate SHA only after all required gates are green.**

- [ ] **Step 9: Request final review and fix all Critical/Important findings before marking PR ready.**

## Completion Gate

M6 may be called complete only when all of the following are true on the same final candidate:

- Flutter app exists under `apps/mobile` and is reproducibly pinned/locked.
- Bootstrap/version gate including degraded-offline semantics is tested.
- M1 native login/restore/logout is used without cookies.
- Groups list/detail and Notifications work through `/api/v1`.
- Typed deep links are allowlisted and do not grant access.
- Notification cursor recovery is duplicate-safe.
- Offline notification-read replay preserves its idempotency key and respects fresh bootstrap/current authority.
- Shared media upload returns/uses opaque M5 media IDs.
- Backend accepts `hms` additively and preserves M5 device security semantics.
- Real FCM delivery works on GMS Android path.
- Real HMS delivery works on a non-GMS Huawei device; registration alone is insufficient.
- Push failure/revocation semantics do not revoke authentication incorrectly or leak protected delivery after revocation.
- Persian RTL + light/dark theme are usable in the vertical slice.
- Flutter targeted CI is green.
- Affected M1/M5 backend regressions are green.
- iOS compile gate is green; physical iOS device acceptance is explicitly recorded if available, otherwise deferred transparently to release readiness.
- One final EarthCoop Full Validation is green on the exact candidate.
- No unresolved Critical/Important architecture or code-review finding remains.
