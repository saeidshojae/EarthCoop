# M0 — API Constitution + Mobile Capability Inventory

**Date:** 2026-09-27  
**Baseline:** `main@b0f6e6082e3f248187a0bed66fb899dd0d7701c2`  
**Scope:** Architecture and contract definition only. No Product/UX backlog implementation.  
**Status:** Approved design for implementation planning.

## 1. Purpose

EarthCoop already has substantial domain/runtime maturity in Location/Governance, Group Chat, Elections, Projects, Najm Bahar, Najm Hoda and Notifications, but its client-facing transport layer is inconsistent: mature domain services coexist with web/session routes, API-like JSON endpoints under `web.php`, closure-based legacy APIs, and a small number of genuine Sanctum APIs.

M0 establishes one authoritative contract boundary so Web/PWA, future Native clients, Najm Hoda and future integrations consume the same application semantics without duplicating business logic.

The target architecture is:

```text
Native / Web / Najm Hoda / integrations
                 │
                 ▼
             API v1 / shared application contracts
                 │
                 ▼
       Application services / capabilities
                 │
                 ▼
       Policies + domain invariants + events
                 │
                 ▼
             Persistence / ledger
```

HTTP is one adapter. Najm Hoda may call the same application commands/queries directly in-process; it must not become a second source of business truth and need not call localhost HTTP.

## 2. Repository findings at baseline

### 2.1 API surface is mixed-generation

`routes/api.php` currently contains closure-based legacy location/profession endpoints, generic geographic routes, Najm Hoda API routes, ticket API routes, email webhooks and a legacy include of Najm Bahar routes. Presence under `/api/*` therefore does not mean a route is a stable mobile contract.

### 2.2 Native authentication is not complete

Sanctum is installed and existing APIs use `auth:sanctum`, but the current application is primarily browser/session oriented. The repository has no complete device-bound token lifecycle for native login, rotation, revocation and app/device tracking.

### 2.3 Strong reusable transport patterns already exist

Group Chat already provides useful patterns for request IDs, normalized JSON errors, idempotency-key replay, retry signaling, sequence/delta recovery and rate limiting. These patterns should be generalized rather than reinvented.

### 2.4 Canonical domains should be adapted, not rewritten

Location/Governance is canonical behind mature services and policies, but much of its JSON/UI transport is still loaded under web middleware. Elections, Projects, Notifications and Najm Bahar are similarly domain-capable but predominantly web-facing.

### 2.5 Najm Hoda is a privileged consumer, not an alternate authority

Najm Hoda already treats browser context as untrusted and has safety/capability/resource-authorization foundations. The shared API/application contract must preserve the invariant that user-provided context, AI-generated intent or client-supplied role data cannot mint server authority.

## 3. Authoritative API boundary

The only stable client-facing API namespace for new work is:

```text
/api/v1/*
```

Routes outside `/api/v1` are classified as one of:

- `legacy` — retained for compatibility/rollback;
- `web-support` — JSON/AJAX endpoint owned by the browser application;
- `internal-integration` — webhook or trusted integration surface;
- `experimental` — explicitly non-contractual.

No existing `/api/*` route is grandfathered into the stable contract merely because current frontend code consumes it.

## 4. Core architectural rule

`/api/v1` controllers are thin adapters. They may perform transport validation, authentication context resolution, request/response mapping and call application services. They must not duplicate domain rules.

Business truth remains in existing or newly extracted application/domain services:

- role and membership authority;
- canonical residence/governance identity;
- election eligibility and responsibility rules;
- financial invariants and ledger operations;
- project lifecycle;
- Najm Hoda capability/consent/authority decisions.

## 5. Authentication, session, token and device constitution

### 5.1 Web

Web/PWA continues to use cookie session + CSRF where appropriate.

### 5.2 Native

Native uses opaque bearer tokens managed by Sanctum or an equivalent first-party token service. Native clients do not emulate browser cookie sessions.

Each native mobile session is associated with both a user and a device record.

Minimum device record:

```text
id
user_id
platform           ios|android
app_version
locale
timezone           IANA name
push_capable
last_seen_at
revoked_at
created_at
updated_at
```

A device identifier is context, never authorization. Token/device ownership is validated server-side.

Tokens must support:

- explicit creation only after successful native authentication;
- finite expiration;
- independent revocation per device/session;
- rotation without revoking unrelated devices;
- abilities/scopes only as coarse transport capabilities, never as resource authorization;
- server-side audit metadata without storing plaintext tokens.

M5 extends the device model with push-token delivery state; M1 must not couple auth validity to a push provider.

## 6. Response and error envelope

All `/api/v1` JSON responses use a shared envelope.

Success:

```json
{
  "status": "success",
  "data": {},
  "error": null,
  "meta": {
    "api_version": "v1"
  },
  "request_id": "uuid"
}
```

Error:

```json
{
  "status": "error",
  "data": null,
  "error": {
    "code": "validation_failed",
    "message": "localized human message",
    "details": {},
    "retryable": false
  },
  "meta": {
    "api_version": "v1",
    "http_status": 422
  },
  "request_id": "uuid"
}
```

Every response carries `X-Request-ID`. A supplied valid request ID may be preserved; otherwise the server generates one.

`error.code` is stable and locale-independent. Production responses never expose stack traces, SQL, exception classes or secrets.

## 7. HTTP semantics

Canonical meanings:

- `200` successful read/update;
- `201` resource created;
- `202` accepted for asynchronous/continued processing;
- `204` successful no-body operation;
- `400` malformed request;
- `401` unauthenticated/invalid token;
- `403` authenticated but forbidden;
- `404` missing resource or intentionally concealed inaccessible resource;
- `409` state/idempotency/concurrency conflict;
- `422` valid JSON/HTTP request with validation/domain-input failure;
- `429` rate limited;
- `5xx` server/transient failure.

## 8. Pagination, filtering and sorting

### 8.1 Cursor pagination

Mutable ordered streams use cursor pagination by default:

- group feed/chat;
- notifications;
- transaction/audit history;
- activity/event feeds.

Canonical request:

```text
?page[cursor]=...&page[limit]=50
```

Canonical response metadata:

```json
{
  "pagination": {
    "next_cursor": "...",
    "has_more": true
  }
}
```

### 8.2 Page-number pagination

Bounded/search/catalog lists may use:

```text
?page[number]=2&page[size]=25
```

Server-defined maximums are mandatory.

### 8.3 Filtering and sorting

Canonical syntax:

```text
?filter[status]=active
?filter[group_id]=123
?sort=-created_at,name
```

Only explicit server-side whitelists are allowed. Database column names are not a public query language. Unsupported filters/sorts return a stable `422` error code.

## 9. Idempotency and retry semantics

Mutations with meaningful side effects require or strongly enforce `Idempotency-Key`, including financial operations, votes/delegations, project submission, content creation, invitations and Najm Hoda apply actions.

Contract:

- same key + same request => replay original non-5xx result;
- same key + different request => `409 idempotency_key_reused`;
- original request still processing => `409 request_in_progress` + `Retry-After`;
- replay response includes `Idempotency-Replayed: true`;
- transport replay retention is at least 24 hours unless a stricter domain rule applies.

Transport idempotency never replaces domain idempotency. Financial issuance/fees/transfers and other constitutional invariants remain protected by their domain-level uniqueness/replay rules.

Native retry policy:

- reads may retry with exponential backoff + jitter;
- mutations may retry only with the same idempotency key;
- `Retry-After` is authoritative when present;
- `retryable=false` is never automatically retried.

## 10. Authorization and resource-policy boundary

The authoritative order is:

```text
Authentication
  → token/device validity
  → resource authorization/policy
  → domain permission
  → business invariant
  → mutation
```

The following are always untrusted context and never sufficient authority:

- `user_id` supplied by client;
- role names or `is_admin` supplied by client;
- group IDs supplied as proof of membership;
- page/context JSON;
- device ID;
- Najm Hoda prompt or generated action intent.

Resource authorization is resolved against server state and policies. Sensitive resource existence may be concealed with `404` where enumeration would create leakage.

## 11. Locale, timezone, dates and numbers

Supported initial API locales: `fa`, `en`, `ar`.

Native locale is negotiated through `Accept-Language`; response may include `Content-Language`. Session locale is a web concern and is not the native API authority.

Server/canonical timestamps are UTC RFC 3339/ISO-8601, e.g. `2026-09-27T13:52:31Z`.

Device/user timezone is an IANA identifier such as `Asia/Tehran`, not a raw offset.

Date-only values use `YYYY-MM-DD`. Jalali conversion is presentation-level and does not replace canonical transport dates.

Machine identifiers, enum values and error codes are not localized.

## 12. Money conventions

Najm Bahar uses integer Gol as transport and calculation truth. `100 Gol = 1 Bahar`.

No floating-point Bahar amount may be used for authoritative calculations or persisted money transfer input. Human-formatted money may be supplied as an optional display field only.

Future fiat money uses integer minor units + ISO currency code.

## 13. Upload and media conventions

M5 introduces a shared media resource rather than allowing every feature to define an incompatible upload contract.

Target shape:

```text
POST /api/v1/media
purpose=<enum>
file=<binary>
```

Returned media identity is server-generated and includes at minimum:

```text
id
mime_type
size
sha256
status
width? / height?
```

Resource mutations reference `media_id`; filenames/public filesystem paths are never resource identities.

The shared media boundary must support centralized MIME/size policy, private/public authorization, EXIF/privacy handling, malware/scanning hooks, and future signed object-storage upload without a breaking client contract.

Existing ticket/chat uploads remain compatibility surfaces until migrated.

## 14. Deep-link conventions

A notification/action destination is typed data, not only a raw URL.

Canonical form:

```json
{
  "link": {
    "version": 1,
    "route": "group.thread",
    "params": {
      "group_id": "123",
      "item_id": "456"
    },
    "fallback_url": "https://earthcoop.ir/..."
  }
}
```

HTTPS App/Universal Links are the preferred external entry form. A deep link never grants permission; opening it triggers normal authentication/resource authorization.

## 15. Realtime and recovery

Realtime delivery is an optimization, not source of truth. A lost WebSocket/broadcast event must be recoverable from an authoritative API cursor/delta endpoint.

Group Chat sequence/delta semantics are the reference pattern for M5. Realtime event IDs are deduplicated by clients; server sync endpoints remain authoritative after reconnect/gaps.

## 16. Offline policy

Queued offline mutations must store at least:

```text
idempotency_key
created_at
resource
operation
payload_hash
client_sequence
```

Replay after reconnect follows normal auth, policy, idempotency and domain validation. Sensitive operations may define replay-expiry windows. Offline state never bypasses a newer server-side authorization or policy decision.

## 17. Versioning and deprecation

Major API version is encoded in the URI: `/api/v1`.

Additive changes are allowed inside v1 when clients are required to ignore unknown optional fields. Breaking changes require `/api/v2` or a specifically versioned sub-contract.

Normal breaking deprecation provides at least 180 days compatibility and advertises:

```text
Deprecation: true
Sunset: <date>
Link: <migration-document>; rel="deprecation"
```

Security, legal or integrity emergencies may require faster retirement and must use the minimum-supported-app-version mechanism plus explicit operator communication.

## 18. App compatibility bootstrap

M5 provides client compatibility metadata:

```json
{
  "api": {"version": "v1"},
  "client": {
    "minimum_version": "...",
    "latest_version": "...",
    "update_required": false
  }
}
```

This is independent from normal API deprecation and exists to block known-insecure or incompatible native builds when required.

## 19. Najm Hoda authority constitution

Three concepts are strictly separate:

```text
User intent
AI proposal
Server authority
```

Najm Hoda may answer, propose, prepare and explain. Apply/execution requires server-minted authority after actor authentication, resource authorization, capability policy and required consent.

Client context, browser context and model output cannot mint authority. The same application commands/queries used by `/api/v1` should be callable directly by trusted in-process Najm Hoda services so business rules are shared without HTTP self-calls.

## 20. Legacy migration rule

An old API/AJAX route is migrated by:

1. identify its actual semantic/domain truth;
2. locate or extract the correct application service/query;
3. add a thin `/api/v1` adapter;
4. add contract/security tests;
5. optionally migrate web consumers;
6. retire old transport later under an explicit retirement task.

M1–M5 do not authorize C14 legacy deletion.

## 21. Mobile Capability Inventory

Status vocabulary:

- `existing` — domain and usable transport/service foundation exists;
- `partial` — substantial foundation exists but mobile contract is incomplete;
- `web-only` — real capability exists but is browser/session/view owned;
- `legacy` — exists but must not become the new stable contract;
- `missing` — no adequate subsystem exists yet.

| Capability | Status at baseline | Target milestone |
|---|---|---|
| Native login/logout/token issue | missing | M1 |
| Device-bound session inventory | missing | M1 |
| Token revoke/rotate | missing | M1 |
| Current user/profile | web-only/partial | M1 |
| Registration/verification/recovery | web-only | M1 |
| Canonical location tree | partial | M1 |
| Residence/governance read/write | web-only/partial | M1 |
| Location proposal/structural claim journey | partial | M1 |
| My Groups | web-only | M1 |
| Canonical group search | partial | M1 |
| Group detail/membership | web-only/partial | M1 |
| Group feed/chat | partial/strong | M1 |
| Chat delta/sync/unread cursor | partial/strong | M1 |
| Posts/comments/reactions | partial | M1 |
| Polls/voting/delegation | partial | M1 |
| Systemic election portal/history | web-only | M1 |
| Responsibility offers | web-only | M1 |
| Election feedback/review | web-only | M1 |
| Project list/detail/create/edit/submit | web-only | M1 |
| Project canonical target scope | partial/strong | M1 |
| Notification list/read/preferences | web-only | M1 |
| Support tickets | partial/genuine Sanctum API | M1 compatibility |
| Najm Hoda chat/history | partial/genuine API | M2 |
| Najm Hoda page/group context | existing internal | M2 |
| Najm Hoda Capability Registry | existing internal | M2 |
| Najm Hoda server authority factory | partial/missing formal mint boundary | M2 |
| Najm Hoda consent/action/evidence schema | partial | M2 |
| Anonymous→authenticated Hoda continuity | missing | M2 |
| Najm Bahar account/ledger core | existing domain | M3 |
| Najm Bahar wallet/balance API | web-only | M3 |
| Najm Bahar transaction history | web-only | M3 |
| Najm Bahar transfer | web-only/domain-green | M3 |
| Najm Bahar activation | web-only/domain-green | M3 |
| Membership fee | web-only/domain-green | M3 |
| Old API Najm Bahar controllers | legacy | ignore/retire later |
| Organization/legal actor abstraction | partial/missing | M4 |
| Broad Company subsystem | missing/roadmap | non-blocker |
| Broad Marketplace/Shop subsystem | missing/legacy fragments | non-blocker |
| Push APNs/FCM | missing | M5 |
| Native notification delivery | missing | M5 |
| Typed notification deep links | partial | M5 |
| Realtime broadcast | partial | M5 |
| Generic media/upload API | missing | M5 |
| Offline replay/sync | partial (group chat only) | M5 |
| App-wide sync cursor | missing | M5 |
| Minimum native app version gate | missing | M5 |
| Reverse geocoder provider | missing/deferred | non-blocker |

## 22. Dependency map M1–M5

```text
M0 API Constitution + inventory
              │
              ▼
M1 API v1 foundation + native auth/device identity + core journeys
              │
       ┌──────┴──────┐
       ▼             ▼
M2 Najm Hoda     M3 Najm Bahar
mobile contract  mobile contract
       └──────┬──────┘
              ▼
M4 actor/organization boundary freeze
              │
              ▼
M5 device delivery: push/media/realtime/offline/compatibility
              │
              ▼
Architecture Gate
              │
              ▼
Native PoC / technology finalization
```

M2 and M3 may execute in parallel after the M1 foundations they consume are stable.

## 23. Milestone definitions and checkpoints

### M1 — API v1 Core Foundation

Deliver:

- `/api/v1` route boundary;
- request context + request ID;
- unified success/error envelope;
- API-wide exception mapping;
- explicit API rate limiting;
- native auth/token/device lifecycle;
- locale/timezone headers;
- shared pagination/filter/sort helpers;
- generalized idempotency;
- resource-policy conventions;
- adapters for pilot/core journeys: auth, profile, location/residence/governance, groups/chat, polls/elections, projects and notifications.

Checkpoints:

- **M1-A Transport Contract:** envelope/request-ID/rate-limit/pagination/idempotency tests green.
- **M1-B Auth & Device Security:** issue/revoke/rotate/device ownership/expired token tests green.
- **M1-C Core Journey:** a non-browser test client completes the pilot journey without cookie session/Blade dependency.

### M2 — Najm Hoda Stable Mobile Contract

Deliver:

- stable conversation/context/capability/proposal/consent/apply/evidence/audit schema;
- server-only authority minting factories/services;
- resource authorization for executable launch-scope capabilities;
- idempotent apply semantics;
- anonymous→authenticated conversation continuity design/implementation where included in launch scope.

Checkpoints:

- **M2-A Authority Boundary:** forged client/model authority cannot execute.
- **M2-B Resource Safety:** group/content/financial launch-scope resources are policy checked.
- **M2-C Mobile Contract:** Native can converse, inspect proposals, consent and receive auditable outcomes through stable schemas.

Full autonomy/self-extension is not a blocker.

### M3 — Najm Bahar Stable Mobile Contract

Deliver API adapters around existing canonical financial services for:

- account and state balances;
- ledger/transaction history;
- transfer;
- activation;
- membership fee;
- launch-scope scheduled operations/audit evidence.

Checkpoints:

- **M3-A Money Representation:** integer Gol only; no float authority.
- **M3-B Mutation Safety:** transport idempotency + domain idempotency + reservation/commitment invariants remain green.
- **M3-C Mobile Journey:** user can view account/history and execute launch-scope allowed actions without web forms.

Idle-tax completion, broad payment providers and future normalized schema are not blockers unless launch scope changes.

### M4 — Actor / Organization Boundary

Deliver only the minimum stable ownership/actor abstraction needed to avoid breaking future Company/Shop/Marketplace work.

Minimum actor concepts:

- user actor;
- group/governance actor;
- organization actor placeholder/interface;
- system actor.

Checkpoint:

- **M4-A Ownership Compatibility:** API resources/actions use actor references/authorization boundaries that do not hard-code every resource as permanently user-owned.

Broad Marketplace/Company implementation is explicitly out of scope.

### M5 — Native Delivery Foundation

Deliver:

- push device token registration/rotation/revocation;
- provider abstraction for APNs/FCM;
- typed notification/deep-link contract;
- shared media/upload resource;
- realtime authorization/reconnect/delta recovery;
- offline read-cache and mutation replay policy;
- minimum/latest app-version compatibility bootstrap.

Checkpoints:

- **M5-A Device Delivery:** revoked device receives no protected delivery; token rotation is safe.
- **M5-B Realtime Recovery:** dropped realtime delivery is recoverable from API sync/delta.
- **M5-C Offline Replay:** duplicate replay cannot duplicate supported mutations.
- **M5-D Compatibility:** unsupported native build receives deterministic update-required response.

## 24. Architecture Gate before Native PoC

Native PoC may begin only when all are true:

1. Native can authenticate without cookie/session dependency.
2. Pilot journeys execute through `/api/v1` and shared application services.
3. Najm Hoda and Najm Bahar do not maintain duplicate mobile business logic.
4. Retried/offline mutations cannot produce duplicate protected side effects.
5. Push/deep-link/realtime loss is recoverable through authoritative APIs.

Technology selection for the production Native client should be finalized after this gate, not used to shape or weaken the backend contract prematurely.

## 25. Explicit non-blockers

The following are intentionally outside the critical path unless launch scope changes:

- C14 legacy retirement;
- broad Marketplace implementation;
- full Company ecosystem;
- full Najm Hoda autonomy/self-extension;
- reverse geocoder provider;
- nationwide settlement classification/promotion;
- final idle-tax redistribution engine;
- external payment-provider UAT;
- frozen Product/UX backlog.

## 26. Safety and delivery rules

- No direct implementation changes on `main`.
- M0 design/plan work occurs on an isolated branch.
- Product/UX frozen backlog remains untouched.
- Tests are written before implementation for every M1–M5 mutation/contract change.
- Use targeted local/test commands during tasks; Full Validation is a checkpoint/final-gate tool, not an iterative debugging loop.
- GitHub Actions must not be used as trial-and-error feedback.
- No production data-destructive operation is authorized by this design.
- C14 retirement requires a separate audit and explicit approval.
