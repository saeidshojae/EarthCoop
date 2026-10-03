# M6 — Normative Architecture Clarifications

**Date:** 2026-09-29  
**Applies to:** `2026-09-29-m6-flutter-native-client-foundation-design.md`  
**Status:** Normative supplement produced by design self-review.

These clarifications resolve the only material ambiguities found during the M6 design self-review. They are part of the M6 design and must be carried into the implementation plan.

## 1. Degraded/offline bootstrap policy

The bootstrap failure behavior is fixed as follows:

1. **No successful bootstrap has ever completed on this installation:** if `/api/v1/bootstrap` cannot be reached, the app does not enter an authenticated product shell. It shows an explicit retry/offline-unavailable state. There is no trustworthy prior compatibility decision or useful authenticated cache to restore.
2. **A successful bootstrap and authenticated session have previously existed:** if the app starts offline, it may enter a clearly degraded offline shell using only already-owned cached data.
3. In degraded offline mode, cached reads may be shown as stale/offline. The client may record only explicitly allowlisted low-risk local intent; in M6 that means the notification-read queue defined by the main spec.
4. No queued mutation is replayed and no protected network mutation is attempted after connectivity returns until a **fresh bootstrap succeeds**.
5. After reconnect, bootstrap runs before session refresh/replay. If the server returns `update_required=true`, replay and protected navigation remain blocked until the app is updated.
6. Bootstrap failure is never silently interpreted as compatibility success.

This policy preserves offline usability without allowing an unknown/outdated client to resume server mutations before compatibility is re-established.

## 2. Push delivery completeness

M6 push acceptance means actual provider delivery, not token registration alone.

Required server/client shape:

```text
Flutter PushTokenSource
  ├── FCM adapter (GMS Android; iOS initially through FCM/APNs)
  └── HMS adapter (Huawei/non-GMS Android)
          │
          ▼
PUT /api/v1/devices/{device}/push
          │
          ▼
Existing PushDeliveryGateway boundary
  ├── FCM delivery adapter
  └── HMS delivery adapter
```

Rules:

- extend the current server provider allowlist additively from `fcm|apns` to `fcm|apns|hms`;
- real FCM and HMS delivery implementations remain behind the existing provider-neutral gateway/fan-out boundary;
- CI uses deterministic fake provider adapters and never requires production credentials;
- provider credentials/configuration are environment secrets and are never committed;
- physical-device acceptance must prove at least one delivered notification on a GMS Android device and one delivered notification on a non-GMS Huawei device before M6 claims Android push completeness;
- iOS initially uses the FCM-backed APNs path to reduce operational surface; direct APNs remains an additive future option, not a separate architecture.

## 3. Media proof scope

The M6 requirement to prove one ordinary image upload is a transport/integration acceptance requirement. It does not require inventing a new user-facing media feature solely to exercise `/api/v1/media`.

The implementation may prove upload through the first real feature that naturally needs media or through an integration acceptance harness. No throwaway production screen is added only for testing.

## 4. Self-review conclusion

After these clarifications:

- no unresolved framework-choice ambiguity remains;
- no M0–M5 authority invariant is weakened;
- offline startup/replay behavior is deterministic;
- Huawei support is a real delivery requirement rather than a token-registration placeholder;
- M6 remains scoped to client foundation + one vertical slice rather than feature parity.
