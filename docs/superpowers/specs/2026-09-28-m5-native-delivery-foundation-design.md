# M5 — Native Delivery Foundation

**Date:** 2026-09-28  
**Baseline:** `main@38ef89def3077f4280c7dd704f4c2b055ac95931`  
**Parent design:** `docs/superpowers/specs/2026-09-27-m0-api-constitution-mobile-readiness-design.md`  
**Status:** Dedicated M5 design derived from the approved M0 mobile-readiness constitution.  
**Scope:** Device delivery state, typed notifications/deep links, shared media, realtime recovery, offline-safe replay, and app compatibility bootstrap. No Native UI implementation.

---

## 1. Purpose

M5 closes the last shared backend/client-contract gaps that must exist before EarthCoop can make a serious Native Mobile proof-of-concept without inventing mobile-only business logic.

M1 established `/api/v1`, native authentication/session/device identity and the core mobile contract. M2 established Najm Hoda mobile authority boundaries. M3 established Najm Bahar mobile-safe contracts. M4 established a future-proof actor/organization boundary so User and Group ownership semantics do not become hard-coded mobile assumptions.

M5 adds the delivery and resilience layer around those contracts:

```text
Native / Web / Najm Hoda
        │
        ▼
     /api/v1
        │
        ├── Device delivery state
        ├── Typed notifications + deep links
        ├── Shared media resources
        ├── Realtime optimization
        ├── Cursor/delta recovery
        ├── Offline-safe mutation replay
        └── App compatibility bootstrap
        │
        ▼
Existing application services / policies / domain invariants
```

M5 must not become a second source of truth. Push, realtime, offline queues and media references are transport/delivery infrastructure around canonical domain services.

---

## 2. Repository baseline findings

### 2.1 Native device identity already exists

`NativeDevice` and the M1 native session layer already provide the stable device identity needed by M5. The current device record includes:

- `public_id`
- `user_id`
- `platform`
- `app_version`
- `locale`
- `timezone`
- `push_capable`
- `last_seen_at`
- `revoked_at`

M5 must extend this model for delivery state instead of introducing a parallel device registry.

### 2.2 Notification infrastructure already exists but is web/database-oriented

`NotificationService` currently routes through Laravel notifications and `GenericNotification`, respects `NotificationSetting`, and accepts a raw URL plus free-form context.

M5 must preserve that service boundary and evolve notification payload semantics to typed destinations and delivery channels. Existing web callers must remain compatible during migration.

### 2.3 Existing realtime/delta patterns should be reused

Group Chat already contains mature sequence/delta/retry patterns. M5 generalizes the principle:

> Realtime is an optimization. Authoritative state is recovered from API cursors/deltas.

No subsystem may require uninterrupted WebSocket/broadcast delivery for correctness.

### 2.4 Existing idempotency contract is the offline replay foundation

The M0/M1 `/api/v1` contract already defines request IDs, idempotency keys, replay behavior and stable conflict errors. M5 must build offline replay on top of that contract rather than adding a second queue-specific idempotency mechanism.

---

## 3. Non-negotiable invariants

1. **Push tokens are delivery addresses, never authentication credentials.**
2. **Device identifiers are context, never authorization.**
3. **A revoked device/session must not receive protected push content after revocation becomes effective.**
4. **Provider tokens must not be exposed through public API responses.**
5. **Realtime event loss must be recoverable through an authoritative API read/delta path.**
6. **Offline replay may never bypass current authentication, authorization, policy, domain state or consent.**
7. **Offline replay of the same mutation must not create duplicate side effects.**
8. **Media IDs, not filesystem paths or filenames, are public resource identities.**
9. **Deep links do not grant access; resource authorization always runs after navigation.**
10. **App version blocking is deterministic, explicit and independent from ordinary API deprecation.**
11. **M5 does not duplicate domain rules into mobile-specific services.**
12. **Web/PWA behavior remains compatible unless an explicit migration step changes a caller.**

---

## 4. Track A — Device Delivery State

### 4.1 Extend `NativeDevice`, do not replace it

M5 adds push delivery state to the existing device record or a tightly-owned child table when normalization is preferable.

Required semantics:

- zero or one current active push token per provider/device installation;
- token rotation without creating a second logical device;
- explicit revoke/unregister;
- provider-independent application API;
- server-side last-success / last-failure metadata;
- invalid/unregistered provider token can be disabled without revoking the authenticated device session;
- revoking the native session disables protected delivery for that device.

Recommended persistent fields/domain concepts:

```text
provider              fcm|apns
push_token_hash       server-side lookup/audit identity
push_token_ciphertext encrypted/plaintext-at-rest according to deployment secret policy
push_token_updated_at
push_enabled_at
push_disabled_at
last_push_success_at
last_push_failure_at
last_push_failure_code
```

The public API never returns the stored provider token after registration.

### 4.2 API contract

```text
PUT    /api/v1/devices/{device_public_id}/push
DELETE /api/v1/devices/{device_public_id}/push
```

Registration request:

```json
{
  "provider": "fcm",
  "token": "provider-token",
  "push_capable": true
}
```

The authenticated session may mutate push state only for its own current device unless a later explicit account-management contract authorizes otherwise.

Rotation is represented by PUT of a new provider token on the same device.

### 4.3 Provider abstraction

Create a provider-neutral boundary such as:

```text
PushDeliveryGateway
  send(PushEnvelope): PushDeliveryResult
```

Implementations may later target FCM and APNs. M5 acceptance must not require production credentials. A fake/null transport is sufficient for deterministic tests.

Business callers do not call FCM/APNs directly.

---

## 5. Track B — Typed Notifications and Deep Links

### 5.1 Notification payload becomes typed

Existing URL-only semantics are retained as compatibility input, but canonical M5 notification data uses a typed link:

```json
{
  "type": "project.status_changed",
  "title": "...",
  "message": "...",
  "link": {
    "version": 1,
    "route": "project.detail",
    "params": {
      "project_id": "123"
    },
    "fallback_url": "https://earthcoop.ir/..."
  },
  "context": {}
}
```

Typed routes are an allowlisted application contract, not arbitrary controller/action names.

### 5.2 Delivery fan-out

A canonical notification may be delivered through multiple channels:

```text
Notification intent
   ├── database/in-app
   ├── realtime/broadcast optimization
   └── push delivery (eligible active devices only)
```

Channel failure must not roll back the canonical notification record unless the domain explicitly requires atomic delivery, which M5 does not introduce.

### 5.3 Preferences

Existing `NotificationSetting` remains authoritative for user preference categories.

M5 may add channel-specific preference resolution, but it must preserve these rules:

- security/integrity-required notices may define non-optional delivery policy separately;
- user preference is evaluated server-side;
- disabling push does not necessarily disable the in-app notification record;
- a device that is revoked/disabled is excluded regardless of preference.

### 5.4 API projection

`/api/v1/notifications` should expose typed link data when present while preserving enough compatibility for older clients during v1 additive migration.

Unknown optional notification fields must be safely ignored by clients.

---

## 6. Track C — Shared Media Resource

### 6.1 Canonical endpoint

```text
POST /api/v1/media
```

Multipart input:

```text
purpose=<allowlisted enum>
file=<binary>
```

Return at minimum:

```json
{
  "id": "server-generated-public-id",
  "purpose": "group.post",
  "mime_type": "image/jpeg",
  "size": 12345,
  "sha256": "...",
  "status": "ready",
  "width": 1200,
  "height": 900
}
```

### 6.2 Security/privacy policy

Centralize:

- MIME allowlist;
- extension vs MIME verification;
- size limits by purpose;
- private/public access classification;
- EXIF/privacy stripping hooks for images;
- malware/scanning hook state;
- content hash;
- ownership/uploader audit;
- future object-storage/signed-upload migration.

Raw storage paths are never API identities.

### 6.3 Compatibility

Existing ticket/chat upload paths remain compatibility surfaces. M5 does not require deleting them.

New native-facing mutations should reference `media_id` where media attachment is supported.

---

## 7. Track D — Realtime Recovery and Delta Sync

### 7.1 Principle

Realtime transport is best-effort acceleration.

Correctness requires:

```text
Realtime event received
    → apply if new

Realtime gap / reconnect / app resume
    → call authoritative sync/delta API
    → reconcile from server cursor
```

### 7.2 Event envelope

Canonical realtime events should carry a stable identity and enough metadata for deduplication:

```json
{
  "event_id": "uuid-or-stable-id",
  "stream": "notifications",
  "cursor": "opaque-cursor",
  "type": "notification.created",
  "occurred_at": "2026-09-28T17:00:00Z",
  "data": {}
}
```

Clients must treat duplicated realtime events as normal.

### 7.3 Authoritative recovery endpoints

M5 does not require one universal event store if existing domain streams are already authoritative.

Required coverage is pragmatic:

- notifications: cursor-based sync;
- group chat/feed: reuse current sequence/delta semantics;
- other pilot journeys: either provide delta/cursor or re-fetch canonical resource/list safely after reconnect.

A future generic activity/event stream may be added later if justified by actual client needs.

---

## 8. Track E — Offline Cache and Mutation Replay Contract

M5 is a server contract for safe offline-capable clients. It does not implement the Native client queue itself.

### 8.1 Queueable mutation metadata

Client-side queued mutation records must preserve:

```text
idempotency_key
created_at
resource
operation
payload_hash
client_sequence
```

### 8.2 Replay rules

On reconnect:

1. re-authenticate/confirm token validity;
2. replay mutations in client order where ordering matters;
3. use the original idempotency key;
4. re-run current server authorization/policy/domain validation;
5. accept idempotent replay response as success;
6. stop/branch on explicit non-retryable conflict;
7. never silently rewrite user intent to force success.

### 8.3 Conflict strategy

M5 defines stable classes, not a universal merge algorithm:

- `idempotent_replay` — original result returned;
- `authorization_changed` — no longer allowed;
- `resource_changed` — optimistic state conflict;
- `validation_changed` — current rules reject payload;
- `expired_offline_action` — replay window elapsed;
- `not_found` — resource removed/concealed;
- transient `retryable` failures.

Sensitive operations may be explicitly marked not suitable for long-lived offline replay.

### 8.4 Required proof

At least one representative side-effecting API mutation must be exercised end-to-end as an offline replay acceptance test and prove that repeating the queued request does not duplicate the mutation.

---

## 9. Track F — App Compatibility Bootstrap

### 9.1 Endpoint

Provide an unauthenticated or minimally-authenticated bootstrap endpoint safe to call before normal app navigation, for example:

```text
GET /api/v1/bootstrap
```

Canonical response includes:

```json
{
  "api": {
    "version": "v1"
  },
  "client": {
    "platform": "android",
    "minimum_version": "1.2.0",
    "latest_version": "1.4.0",
    "update_required": false,
    "update_recommended": true
  }
}
```

### 9.2 Version policy

Policy is server-owned and separately configurable for iOS/Android.

Comparison must use deterministic semantic-version rules; lexical string comparison is forbidden.

Required behaviors:

- below minimum → `update_required=true`;
- minimum <= current < latest → update may be recommended but API remains usable;
- current >= latest → no recommendation;
- malformed/unknown version → stable documented behavior;
- emergency minimum-version policy may be changed without redeploying the client.

App compatibility blocking is independent from normal `/api/v1` deprecation headers.

---

## 10. Cross-cutting security rules

### 10.1 Protected push content

Push payloads should be minimal. Sensitive/private full content should be avoided where notification preview could leak on a lock screen.

Preferred pattern:

```text
Push = event hint + typed destination + safe preview
Open app = authenticated API fetch for authoritative protected data
```

### 10.2 Token secrecy

Provider push tokens:

- never logged in plaintext;
- never returned by list endpoints;
- never included in exception telemetry;
- masked or hashed for diagnostics.

### 10.3 Device/session revocation

Push eligibility is checked at delivery time, not only enqueue time. A device/session revoked before job execution must be filtered out.

### 10.4 Deep-link safety

Deep-link params are untrusted transport input. Normal API resource authorization remains mandatory.

### 10.5 Media ownership

Possessing a `media_id` is not permission to attach/read it. The server validates uploader/visibility/purpose/resource ownership policy.

---

## 11. Data model guidance

M5 should prefer additive schema changes.

Expected new/extended persistence concepts:

```text
native_devices                    existing, extended only where needed
push_delivery_attempts            optional audited delivery history
media                             canonical uploaded-media identity
client_version_policies           per platform minimum/latest policy
```

A separate generic offline queue table is not required because the queue is client-side and API idempotency remains the server replay authority.

A generic realtime event log is also not required unless implementation proves current domain cursors insufficient.

---

## 12. Suggested implementation decomposition

The implementation plan should remain dependency ordered and test-first.

### Task 1 — Device push state

- additive schema/model changes;
- ownership policy;
- register/rotate/revoke API;
- protected token handling;
- session revocation interaction.

### Task 2 — Push provider abstraction

- `PushDeliveryGateway`;
- fake/null provider;
- deterministic failure taxonomy;
- delivery eligibility resolver.

### Task 3 — Typed notification contract

- typed `link` value object/resource projection;
- backward compatibility from legacy URL callers;
- notification API serialization;
- preference behavior.

### Task 4 — Push notification fan-out

- current active device resolution;
- queued delivery;
- revoked/disabled device exclusion;
- provider-failure disable behavior where appropriate.

### Task 5 — Shared media resource

- media schema/model/service;
- `/api/v1/media` upload;
- centralized purpose/MIME/size/privacy policy;
- one pilot consumer reference path.

### Task 6 — Realtime recovery contract

- notification cursor/delta sync;
- event identity/dedup fields;
- prove group-chat existing delta contract remains compatible;
- reconnect gap acceptance.

### Task 7 — Offline replay hardening

- classify replay-safe representative mutations;
- stable conflict/error codes where missing;
- end-to-end repeated idempotency acceptance;
- authorization change on replay acceptance.

### Task 8 — App compatibility bootstrap

- version-policy storage/configuration;
- semantic-version comparison;
- bootstrap endpoint;
- forced/recommended update acceptance.

### Task 9 — M5 native-delivery acceptance journey

End-to-end acceptance must prove at minimum:

1. native device registers a push token;
2. a canonical notification is created with a typed deep link;
3. delivery targets the active eligible device;
4. after device/session revocation the same protected delivery is excluded;
5. realtime interruption is recoverable through authoritative API state/delta;
6. one offline mutation replay returns the original idempotent result without duplicate side effect;
7. an incompatible old app deterministically receives `update_required=true`.

### Task 10 — Regression and architecture gate preparation

Run targeted M1–M5 regressions and verify:

- no cookie dependency for pilot native flows;
- no mobile-only domain logic;
- no provider-specific push calls from business services;
- no raw media path identities in new APIs;
- no offline bypass of policy;
- M2 Hoda authority and M3 Bahar money invariants remain green;
- M4 actor ownership/representation remains green.

---

## 13. Explicit non-goals

M5 does **not** include:

- building Android/iOS UI;
- selecting the final Native framework;
- integrating production APNs/FCM credentials;
- replacing Laravel Notifications wholesale;
- deleting legacy upload endpoints;
- implementing a universal event-sourcing system;
- implementing client-side SQLite/cache code;
- implementing background geolocation;
- adding a new business notification taxonomy for every EarthCoop domain;
- Company/Shop/Marketplace domain work beyond the M4 actor boundary;
- changing Najm Bahar financial rules;
- changing election/governance laws;
- C14 legacy retirement.

---

## 14. M5 Definition of Done

M5 is complete when the same fixed candidate SHA proves all of the following:

- push registration/rotation/revocation is first-party, provider-neutral and device-bound;
- revoked devices cannot receive protected push delivery;
- canonical notifications expose typed deep links and remain compatible with existing web notification creation;
- shared media uses server-generated media identity and centralized validation;
- realtime delivery loss is recoverable from authoritative API state/cursor/delta;
- at least one queued side-effecting mutation can be retried offline with the same idempotency key without duplicate side effects;
- current authorization/policy is re-evaluated during replay;
- bootstrap returns deterministic minimum/latest/update-required state per platform;
- M1 authentication/session contracts, M2 Hoda authority, M3 Bahar, and M4 actor/project boundaries remain green;
- repository Full Validation is green on the exact final M5 candidate.

After that, the project may enter the **Post-M5 Architecture Gate** defined by the mobile-readiness roadmap before beginning the Native PoC and final technology selection.

---

## 15. Post-M5 Architecture Gate

Before Native PoC, explicitly confirm:

1. pilot native journeys can operate cookie-free through `/api/v1`;
2. Native, Web and Najm Hoda reuse the same application/domain truth;
3. retries/offline replay are side-effect safe;
4. push/realtime loss is recoverable;
5. Hoda actions still require server authority/consent;
6. Bahar money remains integer-Gol and ledger/domain guarded;
7. actor ownership is not hard-coded to User;
8. no unresolved P0 backend transport blocker remains for the selected pilot journeys.

Only after this gate should the Native PoC be used to compare technologies and make the final mobile framework choice.
