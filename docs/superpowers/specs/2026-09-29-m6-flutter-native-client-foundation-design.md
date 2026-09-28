# M6 — Flutter Native Client Foundation

**Date:** 2026-09-29  
**Baseline:** `main@9a9906d4ac8a21e3fd77370c5674261542bc4e95`  
**Parents:** M0 API Constitution + M1–M5 Mobile Readiness contracts  
**Status:** Draft for user review after approved architecture decision  
**Decision:** Flutter is the official Native client technology for EarthCoop Android and iOS. NativePHP is not developed as a parallel production path.

---

## 1. Purpose

M6 starts the real Native client only after the shared backend boundary was completed through M5.

The architectural target is intentionally simple:

```text
Flutter Android / iOS
        │
        ▼
     /api/v1
        │
        ▼
Laravel application services / policies / domain invariants
        │
        ▼
Governance / Elections / Projects / Najm Hoda / Najm Bahar / persistence
```

Flutter is a client, not a second domain runtime. Laravel remains the only authoritative implementation of business rules.

This decision preserves all M0–M5 investment and makes future replacement of the mobile framework possible without rewriting EarthCoop business logic.

---

## 2. Technology decision

### 2.1 Selected technology

EarthCoop will use **Flutter** for the official Native Android/iOS application.

Reasons specific to EarthCoop:

- mature Android/iOS ecosystem and long-term platform support;
- stronger coverage for custom UI, animation, accessibility, maps, realtime and third-party SDKs;
- broad Android device support important for a global and lower-cost-device user base;
- practical Huawei/non-GMS integration path;
- clean fit with the API-first M0–M5 architecture;
- stronger future hiring and maintenance market;
- lower architectural temptation to duplicate Laravel domain logic on-device.

### 2.2 NativePHP disposition

NativePHP remains technically interesting but is **not** a parallel implementation path.

No NativePHP PoC, duplicate app, or compatibility layer is required unless a future architectural review has concrete evidence that Flutter no longer meets EarthCoop requirements.

### 2.3 Decision permanence

This is the production direction, not a temporary experiment. Reopening the framework choice requires a material blocker demonstrated by implementation evidence, not preference or novelty.

---

## 3. Non-negotiable architecture invariants

1. **Laravel remains the single source of business truth.**
2. **Flutter never reimplements election, governance, Bahar, actor, responsibility, or Hoda authority rules.**
3. **All stable Native traffic uses `/api/v1`.**
4. **Native authentication uses the M1 bearer/device-session contract, never browser cookies.**
5. **Push and deep links never grant authorization.**
6. **Offline replay always returns to the server and is re-evaluated under current authority.**
7. **Financial mutations are not made locally authoritative.**
8. **Provider/device identity is context, not authorization.**
9. **Realtime is an optimization; recovery uses authoritative API cursors/deltas.**
10. **Secrets/tokens never enter ordinary logs, analytics, or local cache tables.**
11. **The mobile codebase must remain independently testable and understandable by feature.**
12. **Android/iOS platform code is isolated behind interfaces; feature code does not depend on Firebase/Huawei/native SDK details.**

---

## 4. Repository placement and ownership

The Flutter application lives in the existing EarthCoop monorepo:

```text
apps/mobile/
```

Reasons:

- API and client contract changes can be reviewed together;
- one repository keeps M0–M5 invariants visible to AI-assisted development;
- CI can detect backend/mobile compatibility changes;
- no separate repository governance or synchronization burden is introduced yet.

The Laravel root remains unchanged as the backend application. Flutter-specific dependencies never enter Composer/NPM runtime paths.

---

## 5. Flutter SDK/platform policy

### 5.1 SDK

Use the current Flutter **stable** channel at implementation start and pin the exact SDK version used by CI and documented local development.

Do not automatically float production builds to a newly released Flutter version. SDK upgrades are explicit maintenance changes with analyze/test/build gates.

### 5.2 Minimum platforms

Initial policy:

- Android: minimum API 24 unless a required plugin proves a higher floor unavoidable;
- iOS: minimum iOS 15;
- ARM64 is mandatory; Android ARM32 remains supported while the selected Flutter stable line supports it.

Any package that would unnecessarily raise the Android minimum version requires architectural review.

### 5.3 Development environment

The primary day-to-day development path is Android-first on Windows because that matches the current maintainer environment.

iOS architecture and source are maintained from day one, but actual iOS compile/sign/release validation requires macOS. To control cost, macOS CI should run at defined milestones/release gates rather than on every trivial commit until usage justifies broader coverage.

---

## 6. Application structure

M6 uses a **feature-first architecture with a small shared core**. It deliberately avoids a deep enterprise Clean Architecture hierarchy inside every feature.

```text
apps/mobile/lib/
  app/
    bootstrap/
    router/
    theme/
    localization/

  core/
    api/
    auth/
    device/
    errors/
    local/
    offline/
    deep_links/
    push/
    media/
    logging/

  features/
    auth/
    home/
    groups/
    notifications/

  main.dart
```

A feature may contain `data`, `application`, and `presentation` subfolders only when the feature is complex enough to need them. Do not create empty layers for ceremony.

Shared business concepts from the server are represented as client DTOs/view models, not copied domain entities with duplicated rules.

---

## 7. State management

Use **Riverpod stable** as the application state/dependency boundary.

Rules:

- providers expose async application state and services;
- widgets do not instantiate API/database/platform clients directly;
- feature state remains feature-owned;
- global providers are limited to true app-wide infrastructure/session/theme/locale concerns;
- experimental Riverpod offline persistence or mutation APIs are not used in M6; EarthCoop already has explicit M5 offline semantics and should not couple them to experimental framework features.

Riverpod is dependency orchestration, not a domain-rule engine.

---

## 8. Networking and API contract

Use **Dio** behind one EarthCoop API transport.

No feature performs arbitrary HTTP independently.

The transport owns:

- base URL/environment selection;
- bearer token attachment;
- native device/session headers required by M1;
- `X-Request-ID` generation/propagation;
- `Idempotency-Key` for approved mutations;
- API envelope decoding;
- stable error-code mapping;
- timeouts/cancellation;
- safe retry policy;
- `Retry-After` handling;
- network logging with token/body redaction.

### 8.1 Retry rules

- reads may retry only for explicitly retryable/transient failures using bounded exponential backoff and jitter;
- mutations never generate a new idempotency key during retry;
- `retryable=false` is not automatically retried;
- authorization/validation/conflict errors are surfaced to application state, not hidden by generic retry loops.

### 8.2 Serialization

Use explicit typed DTOs with stable JSON serialization. Prefer `json_serializable` for repetitive mapping; do not introduce Retrofit-style generated HTTP layers unless implementation evidence shows a real maintenance benefit.

Unknown additive optional fields from `/api/v1` must be tolerated where the contract allows them.

---

## 9. Session and secure storage

Use platform secure storage for sensitive Native credentials.

Sensitive values include at minimum:

- bearer/session token;
- device/session secret material, if any;
- identifiers whose exposure would materially aid session abuse.

Ordinary Drift/SQLite cache must not store bearer tokens.

Startup session flow:

```text
launch
  -> bootstrap compatibility check
  -> load secure session state
  -> validate current native session with /api/v1/auth/session
  -> authenticated shell OR login
```

On logout/revocation:

- revoke server session;
- unregister/disable push when appropriate;
- clear secure credentials;
- clear user-scoped local cache/queued mutations according to ownership policy;
- return to unauthenticated navigation state.

---

## 10. App bootstrap and version compatibility

`GET /api/v1/bootstrap` is the first server compatibility call and does not require login.

Flutter sends its platform and semantic app version according to the M5 contract.

Behavior:

- `update_required=true` blocks authenticated navigation and presents a forced-update state;
- `update_recommended=true` presents a non-blocking recommendation;
- unavailable/transient bootstrap must have an explicit degraded-start policy rather than silently treating an unknown version as compatible;
- API-version failure and app-version incompatibility remain distinct error states.

The app version and platform values must come from build metadata, never hard-coded widget strings.

---

## 11. Navigation and typed deep links

Use **go_router** as the application router.

Server typed links are mapped through one allowlisted client route registry:

```text
server route name
   -> validated params
   -> internal Flutter destination
```

Example:

```text
group.detail + group_id
  -> /groups/:id
```

Rules:

- unknown server route names do not execute arbitrary navigation;
- deep-link params are treated as untrusted input;
- opening a route triggers normal API authorization/read behavior;
- possession of an ID does not imply access;
- `fallback_url` may be used only through an explicit safe external/web fallback policy;
- router paths are a client implementation detail; server contracts use semantic route names.

---

## 12. Local persistence and offline architecture

Use **Drift/SQLite** for non-secret structured local persistence.

Initial local responsibilities:

- bounded read cache for selected API resources;
- notification/group recovery cursors where useful;
- offline mutation queue metadata/payload for specifically allowlisted low-risk operations;
- client-only UI state that genuinely needs durable persistence.

### 12.1 Cache is never authority

Cached data may render the last known state, but actions that require authority must be confirmed by the server.

Each cached record/category defines freshness behavior. The foundation must distinguish:

- fresh cache;
- stale-but-displayable cache;
- missing cache;
- server-authoritative refresh/error.

### 12.2 Offline mutation queue

Queue entries align with M5:

```text
idempotency_key
created_at
resource
operation
payload_hash
client_sequence
payload
state
attempt_count
last_error_code
```

Replay occurs on controlled triggers such as foreground/resume and regained usable network access. Connectivity status is only a hint; a real API response determines reachability.

Replay rules:

- preserve original idempotency key;
- preserve user intent; never silently rewrite payload;
- re-authenticate/re-authorize on server;
- stop or classify explicit non-retryable conflicts;
- avoid infinite retry loops;
- maintain ordering only where operation semantics require it.

### 12.3 First production offline mutation

The first M6 queued mutation is **mark notification read** using the existing idempotent `/api/v1/notifications/{notification}/read` endpoint.

It is deliberately chosen because it proves the queue/replay mechanism without putting financial, election, Hoda-consent, or sensitive content operations at offline risk.

No Najm Bahar financial mutation is enabled for offline replay in M6.

---

## 13. Notifications and recovery

The app consumes `/api/v1/notifications` using the M5 monotonic recovery cursor.

Correctness does not depend on push or a continuously connected realtime channel.

Flow:

```text
push/realtime hint
      -> app opens/resumes
      -> authoritative notification cursor sync
      -> dedupe by server identity
      -> update local projection
```

Push previews are not treated as complete authoritative records.

---

## 14. Push architecture: GMS, iOS and Huawei

Flutter exposes one client-side interface such as:

```text
PushTokenSource
  initialize()
  currentRegistration()
  tokenChanges()
  disable()
```

Feature/application code does not import Firebase or Huawei SDKs directly.

### 14.1 Provider strategy

Initial production strategy:

- Android with Google Mobile Services: FCM;
- iOS: FCM-backed APNs delivery initially, unless direct APNs later provides a concrete benefit;
- Huawei/non-GMS Android: Huawei Push Kit (HMS).

Runtime capability detection selects the applicable adapter. There must be no assumption that every Android device has Google Play Services.

### 14.2 Required additive backend extension

M5 currently validates push providers as `fcm|apns`. M6 must extend this provider contract additively to support `hms` before Huawei push is considered complete:

```text
fcm | apns | hms
```

This is a transport extension only. It must not change authentication, device authority, notification preferences, or domain semantics.

A Huawei token remains a delivery address exactly like an FCM/APNs token.

### 14.3 Push correctness

- push registration is device-bound;
- token rotation is handled without creating duplicate logical devices;
- invalid provider tokens disable delivery, not authentication;
- logout/revocation prevents protected delivery;
- push opens a typed deep link, then the app performs ordinary authorized API reads.

---

## 15. Media

All new Flutter uploads use M5 `POST /api/v1/media`.

Flutter receives and stores opaque media IDs. It never depends on server filesystem paths.

Foundation support includes:

- multipart upload through the shared API client;
- upload progress/cancellation;
- idempotency compatibility;
- typed validation errors;
- image/file picker abstraction isolated from feature UI;
- no assumption that selected filename or extension is trustworthy.

The first vertical slice proves one ordinary image upload but does not redesign all legacy web upload consumers.

---

## 16. UI, RTL, localization and theme

M6 is Persian-first but internationalization-ready.

Requirements from the first screen:

- Flutter localization infrastructure enabled;
- Persian (`fa`) complete for M6 screens;
- RTL layout is native, not manually mirrored per page;
- English structure is supported even if translation coverage is initially incomplete;
- Arabic remains a later content/localization expansion without architectural redesign;
- Material 3-based EarthCoop theme;
- system/light/dark theme support;
- semantic text scaling and accessibility-friendly controls;
- no pixel-for-pixel reproduction of Bootstrap web pages when mobile-native layout is more appropriate.

Shared visual tokens belong in the app theme/design system, not copied constants across features.

---

## 17. First vertical slice

The first production-grade Flutter slice is intentionally small:

```text
Launch
  -> Bootstrap/version gate
  -> Login
  -> Restore/validate native session
  -> Home shell
  -> My Groups
  -> Group detail
  -> Notifications
  -> Typed deep link into Group detail
```

Foundation behaviors proven alongside that journey:

- Persian RTL;
- dark mode;
- secure session persistence;
- device identity/session lifecycle;
- FCM registration on GMS Android;
- HMS registration on a non-GMS Huawei device;
- notification cursor recovery;
- offline notification-read queue + idempotent replay;
- one media upload;
- logout/revocation cleanup;
- degraded network and reconnect behavior.

The slice should run against the real `/api/v1` contract; mocks/fakes are for tests, not acceptance proof.

---

## 18. Najm Hoda boundary

M6 does not implement the full Najm Hoda mobile experience, but it establishes the boundary it will later use.

Flutter may send page/resource context and user intent to the server. It may display suggestions/proposals and collect explicit consent.

Flutter must not:

- infer or mint user authority;
- execute Hoda proposals locally;
- duplicate capability rules;
- treat model output as an executable command without the server consent/apply flow.

Future Hoda mobile work consumes the M2 authority contract.

---

## 19. Najm Bahar boundary

M6 does not implement the full Bahar UX.

When Bahar enters Flutter later:

- server integer Gol remains authoritative;
- no floating-point financial truth is introduced in Dart;
- balances and ledger effects come from `/api/v1`;
- offline financial writes are disabled unless a later dedicated design explicitly proves safety;
- formatting/display helpers must not become financial calculation authority.

Future Bahar mobile work consumes the M3 contract.

---

## 20. Error model and user-visible state

Every async feature must distinguish at least:

```text
initial
loading
content
empty
stale-content
retryable-error
non-retryable-error
auth-required
forbidden
conflict
update-required
```

Do not collapse all failures into “something went wrong.” Stable server `error.code` drives application decisions; localized server/client text drives presentation.

Request IDs should be retained in diagnostics so support can correlate a user-visible failure with backend logs without exposing secrets.

---

## 21. Security baseline

M6 security rules:

- bearer tokens only in secure storage/memory;
- never log authorization headers, push tokens, passwords or raw sensitive payloads;
- local database contains no bearer/session token;
- deep-link and push parameters are untrusted;
- WebView is not a general escape hatch for authenticated product features;
- TLS uses normal platform trust in M6; certificate pinning is deferred because operational/key-rotation risk currently outweighs benefit;
- rooted/jailbroken-device blocking is not introduced without a defined threat model;
- production debug logging is minimized/redacted;
- environment configuration contains no committed production secret.

---

## 22. Observability

M6 provides a provider-neutral diagnostics boundary. Vendor crash/analytics selection is not required to scaffold the app.

Minimum diagnostic context:

- app version/build;
- platform/OS version;
- non-secret device public ID where appropriate;
- API request ID;
- stable error code;
- network/offline queue state;
- current screen/semantic route without sensitive payload.

A future telemetry provider can consume this boundary without feature-level SDK imports.

---

## 23. Testing strategy

### 23.1 Dart/unit tests

Cover:

- API envelope/error decoding;
- semantic bootstrap decisions;
- session state transitions;
- typed deep-link validation/mapping;
- offline queue ordering/replay classification;
- DTO serialization;
- push provider selection logic;
- cache freshness state.

### 23.2 Widget tests

Cover key UI states for:

- bootstrap/update-required;
- login;
- groups list/detail;
- notification list;
- loading/empty/error/stale states;
- RTL and theme-sensitive behavior where practical.

### 23.3 Integration/contract tests

Use deterministic fake HTTP/platform adapters for most CI tests, plus a real-backend acceptance journey against a controlled EarthCoop environment for final gates.

### 23.4 Physical-device acceptance

Before M6 closes, verify at minimum:

- one ordinary GMS Android device;
- one non-GMS Huawei device;
- one iOS build/device or release-grade iOS build gate when macOS access is available.

Huawei acceptance is not optional for claiming Android push completeness.

---

## 24. CI strategy

Add a Flutter-targeted workflow triggered by `apps/mobile/**` and relevant shared API-contract changes.

Fast gate:

1. dependency resolution from lockfile;
2. `dart format --set-exit-if-changed`;
3. `flutter analyze`;
4. unit tests;
5. widget tests;
6. Android debug build.

Heavier gates run at milestones/final candidate:

- Android integration tests;
- real `/api/v1` contract acceptance;
- iOS compile gate on macOS;
- existing backend targeted/full validation when server contracts are modified.

Do not run expensive full-project/backend validation for every Dart-only UI edit unless the changed surface warrants it.

---

## 25. Delivery/release policy

M6 builds developer/test artifacts only until the foundation acceptance gate is green.

Store signing, production certificates, Play/App Store publishing, privacy-store declarations, screenshots and release operations are separate release-readiness tasks after the client foundation proves stable.

Android-first execution does not mean Android-only architecture.

---

## 26. Explicit non-goals for M6

M6 does **not** attempt:

- complete feature parity with Web/PWA;
- Marketplace/company/shop implementation;
- full Elections UX;
- full Najm Bahar UX or offline financial writes;
- full Najm Hoda companion UX;
- complex maps/background geolocation;
- replacing the current PWA;
- migrating every legacy upload flow;
- universal background sync;
- App Store/Play Store production launch;
- a second NativePHP implementation;
- client-side duplication of Laravel domain logic.

---

## 27. Definition of Done

M6 is complete only when the same final candidate proves:

1. Flutter app exists under `apps/mobile` with reproducible SDK/dependency lock;
2. bootstrap/version gate consumes the real M5 contract;
3. native login/session restore/logout uses M1 without browser cookies;
4. groups list/detail consume `/api/v1`;
5. notifications recover through the M5 cursor contract;
6. typed deep link safely opens an authorized group destination;
7. secure storage contains credentials and ordinary local DB does not;
8. Drift queue replays notification-read with the original idempotency key and no duplicate effect;
9. media upload uses opaque M5 media IDs;
10. GMS Android push registration works through FCM;
11. non-GMS Huawei push registration works through HMS after additive backend `hms` support;
12. Persian RTL and dark mode are usable in the vertical slice;
13. revoked/logout session does not continue protected delivery;
14. Flutter targeted gate is green;
15. any touched backend M1–M5 regressions are green;
16. final architecture review finds no mobile-only domain truth or cookie dependency.

---

## 28. Post-M6 direction

After M6, feature migration proceeds vertically, not by copying pages wholesale.

Recommended broad order:

1. Group feed/chat and participation flows;
2. Location/Governance read + carefully scoped edit flows;
3. Elections;
4. Najm Hoda companion;
5. Najm Bahar read experiences, then separately reviewed financial actions;
6. Projects/marketplace/company/shop according to backend readiness;
7. richer device capabilities as actual product needs justify them.

Each later mobile feature consumes existing server authority and receives a focused design only where its risk/complexity requires it.

---

## 29. Architecture gate conclusion

The Post-M5 Architecture Gate is resolved as follows:

- **Framework:** Flutter — accepted as official Native client technology.
- **Backend authority:** Laravel `/api/v1` — unchanged.
- **Repository model:** monorepo, Flutter at `apps/mobile`.
- **State:** Riverpod stable.
- **Network:** Dio shared transport.
- **Navigation:** go_router + semantic typed-link registry.
- **Local persistence:** Drift/SQLite for non-secret cache/offline queue.
- **Secrets:** platform secure storage.
- **Push:** provider abstraction with FCM + HMS; APNs-compatible iOS path retained.
- **Offline:** explicit M5 idempotent replay, not local domain execution.
- **Initial product slice:** bootstrap/login/groups/notifications/deep-link plus foundation behaviors.

This architecture is intentionally conservative: it maximizes long-term maintainability and global device compatibility while preserving the strong server-authoritative boundaries created in M0–M5.
