# M6 Flutter Native Client Foundation — Implementation Plan Review Clarifications

**Status:** Normative supplement to `docs/superpowers/plans/2026-09-29-m6-flutter-native-client-foundation.md` after self-review. Where this file is more specific, it controls.

## 1. FCM server authentication

Task 11 must not hand-roll Google service-account JWT signing or OAuth token exchange.

Required implementation:

- add a mature Google Auth PHP library (prefer `google/auth`) as a backend infrastructure dependency if not already present;
- use service-account/Application Default Credentials semantics to obtain a short-lived token scoped for Firebase Messaging;
- call the official FCM HTTP v1 endpoint:

```text
POST https://fcm.googleapis.com/v1/projects/{project_id}/messages:send
```

- keep the service-account file/path/secret outside git;
- never log bearer access tokens, private keys, service-account JSON, or provider device tokens;
- adapter tests use fake token providers/HTTP clients and require no production credential.

`composer.json` and `composer.lock` therefore belong to Task 11 if the Google Auth dependency is added.

## 2. Huawei server delivery contract

For M6 Android/HMS delivery, use the current Huawei Push Kit **V3** server message API, not legacy V1/V2 message-send endpoints:

```text
POST https://push-api.cloud.huawei.com/v3/{projectId}/messages:send
```

Authentication is a Bearer JWT generated from the AppGallery Connect/service-account material required by the current official V3 Push Kit contract. JWT construction/signing must be isolated in the HMS infrastructure adapter/provider; feature/domain code never knows Huawei credential details.

If Huawei's official V3 documentation has materially changed by implementation time, stop and update this plan/spec explicitly before changing protocol or endpoint. Do not silently fall back to V1/V2.

Tests must pin:

- V3 URL construction using project ID;
- Bearer authorization header presence without exposing its value;
- provider-success normalization;
- invalid registration/token normalization;
- retryable 429/5xx behavior;
- permanent non-retryable provider errors;
- credential-missing fail-safe behavior;
- no secret/token leakage in diagnostics.

## 3. Environment/base URL policy

Task 2 must include a typed `AppEnvironment`/API-base configuration boundary. Base URLs are build configuration, never scattered literals in feature code.

Tests must prove:

- development/test/production base selection is explicit;
- production build cannot silently point to localhost;
- secrets are not accepted as ordinary compile-time source constants;
- every feature uses the shared `ApiClient` base configuration.

## 4. Accessibility and text scaling

Task 5 must include widget coverage for:

- Persian RTL directionality;
- system light/dark switching;
- large text scaling without clipping the primary login/home/groups navigation controls;
- semantic labels for primary interactive controls in the M6 vertical slice.

Accessibility is a first-slice requirement, not a later polish task.

## 5. Package/version selection

Task 1 pins the exact Flutter stable SDK available at implementation start. Package selection must use stable releases compatible with that SDK. Do not select a prerelease package merely to preserve an architectural preference.

If the official Huawei Flutter plugin requires raising Android `minSdk` above API 24, stop at that task and record the exact plugin/version/requirement before changing the platform floor; do not raise it silently.

## 6. Self-review result

After these clarifications, the implementation plan covers:

- Flutter scaffold/toolchain;
- shared transport/error/logging;
- compatibility bootstrap/degraded offline start;
- secure native session lifecycle;
- routing/deep links/theme/localization/accessibility;
- groups vertical slice;
- notification cursor recovery;
- offline idempotent replay;
- shared media;
- HMS registration extension;
- real FCM/HMS server delivery adapters;
- Flutter push token/provider lifecycle;
- vertical-slice and physical-device acceptance;
- targeted CI;
- exact-candidate final architecture/regression gate.

No M6 Spec requirement is intentionally left without an owning task. Full Elections, full Najm Bahar, full Najm Hoda, Marketplace, complex maps/background location, production store release, and full Web/PWA parity remain explicit non-goals.