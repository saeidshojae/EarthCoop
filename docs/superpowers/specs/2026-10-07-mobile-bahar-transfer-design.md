# Native Bahar Transfer — Design

**Date:** 2026-10-07  
**Status:** Approved direction from repository audit; implementation not yet started  
**Scope:** Phone-free server/mobile implementation. No live transfer, production deployment, or physical UAT.

## Goal

Add a safe Native Flutter flow for immediate external Najm Bahar transfer without letting the client infer financial authority from balances or discover policy only by mutating.

The first native contract is intentionally narrower than the legacy web transfer screen.

## Product boundary

### Included

A member may send **Active Bahar** from one explicitly selected, owned, active subaccount to one explicitly resolved active destination subaccount, when the server says external transfer is currently enabled.

### Excluded

- cross-owner Dim transfer;
- main-account-to-external-account transfer;
- internal Main↔Sub redistribution;
- internal Sub↔Sub redistribution;
- scheduled transfers;
- group/legal-entity authority transfer;
- loan metadata/loan lifecycle;
- automatic/offline queued financial submission;
- production deployment or real transfer.

Those are separate financial intents.

## Why Active-only

Current canonical domain policy rejects cross-owner faded/Dim movement. The native client must not present Dim as an apparently valid choice and then rely on a 409 to explain policy. Dim may be displayed as non-transferable information, but never serialized into the external-transfer intent.

## Server read contract

Add:

### GET /api/v1/najm-bahar/transfers/capability

Response data:

```json
{
  "transfer_contract_version": 1,
  "external_transfer_enabled": false,
  "disabled_reason": "threshold_not_met",
  "sources": [
    {
      "account_id": 123,
      "account_number": "1000000007-001",
      "name": "حساب روزمره",
      "kind": "subaccount",
      "status": 1,
      "active_available_gol": 1200,
      "can_transfer_active": true
    }
  ]
}
```

Rules:
- read-only: must not create a mirror, reconcile balances, reserve funds or write policy;
- only source accounts effectively owned by the authenticated user;
- only active subaccounts with an existing canonical Account mirror are projected for external transfer;
- missing mirrors are fail-closed and are never created by this read endpoint;
- availability subtracts Active reservations;
- no aggregate balance is treated as spendable;
- capability is authoritative but not a reservation; POST rechecks everything;
- disabled_reason is a stable code, not localized text. Initial codes: `threshold_not_met`, `no_eligible_source`, `policy_disabled`.
- do not expose server authority metadata or raw reservation records.

### GET /api/v1/najm-bahar/transfers/destination?account_number=...

Exact lookup only. No fuzzy/global directory search.

Response data:

```json
{
  "account_number": "1000000011-002",
  "name": "پس‌انداز",
  "owner_type": "user",
  "owner_display_name": "نام نمایشی مجاز",
  "kind": "subaccount",
  "status": 1,
  "destination_token": "<opaque short-lived server token>"
}
```

Rules:
- accept normalized `-` or `/` account formatting, return canonical account number;
- only active destination subaccounts;
- do not expose user id, email, phone, balances, private profile data or internal account ids;
- own exact destination may be rejected for this external-transfer flow rather than silently becoming an internal transfer;
- destination token is signed/opaque and binds canonical account number + resolved destination id + effective owner + expiry + transfer_contract_version;
- short lifetime: 10 minutes;
- a missing/disabled destination is `not_found`.

The destination token prevents a confirmation screen from being based only on client-held display text.

## POST contract

Keep `POST /api/v1/najm-bahar/transfers` backward compatible for existing callers, but introduce strict native contract when `expected` is present.

Native payload:

```json
{
  "source_account_id": 123,
  "destination_account_number": "1000000011-002",
  "amount_gol": 250,
  "balance_bucket": "active",
  "description": "اختیاری",
  "expected": {
    "transfer_contract_version": 1,
    "source_account_number": "1000000007-001",
    "source_active_available_gol": 1200,
    "destination_token": "<opaque token>"
  }
}
```

Strict-native rules:
- `balance_bucket` must equal `active`;
- source must still be an owned active subaccount;
- source account number must match the selected id;
- destination token must be valid, unexpired and match the canonical destination account;
- destination must still be active and remain an external owner;
- external-transfer policy/threshold is re-evaluated;
- reservation-aware Active availability is re-evaluated;
- amount may be lower than the frozen displayed availability, but not higher;
- if displayed source availability changed before submit, fail closed with `transfer_terms_changed` rather than silently using another source or changed availability;
- zero mutation on any consent/policy/source/destination mismatch.

Stable errors:
- `transfer_terms_changed` — 409;
- `transfer_destination_changed` — 409;
- `transfer_not_allowed` — 409;
- `insufficient_available_funds` — 409;
- `not_found` — 404;
- idempotency errors remain existing middleware codes.

The server receipt must return the canonical transaction projection and exact source balance projection already used by API v1. It must be enough for the client to verify:
- transaction is completed;
- amount equals frozen intent;
- bucket is active;
- direction is outgoing;
- counterparty account number equals frozen canonical destination.

## Client intent

Create a dedicated `BaharTransferIntent`, not a generic mutation map.

Frozen fields:
- source id/number/name;
- frozen source available Active;
- destination canonical account number/display;
- destination token;
- amount Gol;
- normalized description;
- transfer_contract_version;
- idempotency key.

Once confirmation starts, source/destination/amount/description/key are immutable until definite rejection or explicit cancel before first submit.

## Retry and ambiguous result

Reuse the membership-payment safety pattern:
- `allowAutomaticRetry=false` for POST;
- one explicit idempotency key generated at confirmation;
- repeated confirm coalesces one pending POST;
- network error, malformed success, request_in_progress, retryable transport failure => `outcomeUnknown`;
- no automatic POST on restart;
- no financial offline queue;
- explicit same-intent retry only with identical body and key;
- 23-hour client retry lifetime, below the server idempotency record's 24-hour expiry;
- after expiry, do not retry the old mutation.

### Reconciliation

A transfer needs stronger reconciliation than membership payment because a generic history item could be ambiguous.

Add:

`GET /api/v1/najm-bahar/transfers/by-idempotency/{key}`

It is authenticated and user-scoped, read-only, and returns:
- confirmed transaction receipt if the transport/domain idempotency key belongs to the current user and transfer route;
- `not_found` when no completed result exists.

Do not expose the domain hashed key or allow cross-user lookup.

Client `reconcile()` is GET-only. A matching confirmed receipt resolves `outcomeUnknown`; no matching receipt leaves the outcome unknown.

## UI

Entry point: Najm Bahar screen, separate card/action «انتقال بهار».

Flow:
1. GET capability.
2. If locked, show policy status and no submit control.
3. Select one eligible source subaccount and show available Active.
4. Enter exact destination account number.
5. Explicit «بررسی مقصد» GET resolves canonical destination.
6. Enter integer/decimal user amount but convert locally to exact Gol with at most two decimal Bahar digits; serialized amount is integer Gol only.
7. Optional description.
8. Review screen shows source, destination name + canonical number, amount, Active label and description.
9. «تأیید و انتقال» freezes intent/key and posts once.
10. Known success shows tracking number/receipt and refreshes wallet/history best-effort.
11. Definite rejection reloads capability/destination as appropriate.
12. Unknown result freezes all fields and shows «بررسی نتیجه» plus «تلاش دوباره با همان درخواست».

Never show a Dim transfer toggle. Never fall back from selected source to another account.

## Session and lifecycle

- account/session change immediately invalidates capability, destination preview, frozen intent and receipt publication;
- delayed old-session replies cannot repopulate UI;
- logout while POST is in flight prevents publication to next session;
- app restart does not persist/replay a pending transfer intent;
- secure idempotency keys must not be logged with bearer/device secrets.

## Server tests

Contract tests must prove:
- capability read is zero-write and reservation-aware;
- only owned active subaccounts are listed;
- threshold closed/open projection;
- exact destination preview normalization/privacy;
- disabled/nonexistent destination is not_found;
- self/internal destination excluded from external contract;
- strict native source/destination/availability snapshot mismatch is zero-mutation;
- explicit source never falls back;
- cross-owner Dim rejected;
- threshold rechecked at POST;
- reservation race rejected;
- replay is single effect;
- same key/different body conflicts;
- by-idempotency lookup is current-user/route scoped;
- receipt matches exact source/destination/amount;
- large integer Gol remains integer.

Existing legacy API tests remain green.

## Mobile tests

Repository/DTO:
- strict decode of capability/source/destination/receipt;
- exact POST body;
- no automatic retry on financial POST;
- GET-only destination/reconciliation;
- malformed receipt => unknown result;
- session/bootstrap guards.

Controller:
- opening form never POSTs;
- confirm sends exactly once;
- same-intent retry is byte-equivalent intent + same key;
- 23h expiry;
- restart has no replay;
- ambiguous result reconciliation;
- session/logout clears state;
- delayed reply suppression.

Widget:
- locked capability has no submit;
- only Active external-transfer path;
- source subaccount selection;
- exact destination preview required before review;
- no POST on preview/cancel;
- exact amount/source/destination confirmation;
- large values + RTL/narrow layout;
- unknown result freezes fields;
- confirmed receipt displays tracking;
- no Dim transfer affordance.

## Validation strategy

Use focused RED/GREEN suites while implementing. Only after stable changes:
- financial mutation architecture gate;
- transfer API contract suite;
- complete Flutter formatter/analyzer/tests;
- one final server full suite because canonical financial paths/read contracts changed;
- one whole-change review;
- do not build another APK merely because this design exists. Package the next signed candidate only after the transfer implementation itself is final.

No production data or live transfer is used.
