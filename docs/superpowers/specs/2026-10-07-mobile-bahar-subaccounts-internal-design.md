# Native Najm Bahar Subaccounts & Internal Redistribution — Design

**Date:** 2026-10-07  
**Scope:** phone-free implementation only; no main merge/deploy/live movement.

## Goal

Make Native external transfer actually usable end-to-end by letting a member create/manage personal subaccounts and redistribute their own Active/Dim between Main and owned subaccounts without changing aggregate wealth.

## Read model

Add:

`GET /api/v1/najm-bahar/subaccounts`

Response:
```json
{
  "internal_transfer_contract_version": 1,
  "main": {
    "account_id": 7,
    "account_number": "1000000007",
    "name": "حساب اصلی",
    "active_gol": 800,
    "active_available_gol": 500,
    "dim_available_gol": 900,
    "dim_committed_gol": 100
  },
  "subaccounts": [
    {
      "sub_account_id": 11,
      "account_id": 22,
      "account_number": "1000000007-001",
      "name": "روزمره",
      "status": 1,
      "active_gol": 300,
      "active_available_gol": 250,
      "dim_available_gol": 400
    }
  ]
}
```

Rules:
- current-user ownership only;
- read-only, never creates mirrors;
- only canonical mirrored subaccounts appear in Native v1;
- Active availability is reservation-aware;
- Main Dim available excludes committed Dim by using canonical balance semantics;
- all money integer Gol;
- no raw reservation/ledger/user ids beyond owned account ids needed for mutation.

## Subaccount creation

`POST /api/v1/najm-bahar/subaccounts`

Payload:
```json
{"name":"روزمره"}
```

Rules:
- authenticated user's main account only;
- name nullable/trimmed/max 80;
- server generates account number;
- creates SubAccount + canonical mirror atomically;
- idempotency key required to prevent duplicate account creation on ambiguous transport;
- no client-supplied account number/status/owner/balance;
- response returns the created projected subaccount.

Creation is not a monetary transfer but still uses idempotency because duplicate financial containers are harmful.

## Rename

`PATCH /api/v1/najm-bahar/subaccounts/{id}`

Only name can change; ownership enforced; max 80; no balance/status mutation.

Rename is not part of financial same-intent recovery and may use standard idempotency middleware.

## Internal transfer capability

The read model itself is the capability snapshot. Each source projection exposes exact spendable amounts.

Buckets:
- `active`: reservation-aware;
- `dim`: available Dim only, never committed Dim.

Directions:
- `main_to_sub`
- `sub_to_main`
- `sub_to_sub`

The server derives canonical main/sub entities from ids; no arbitrary account number transfer.

## Strict internal transfer POST

`POST /api/v1/najm-bahar/internal-transfers`

Payload:
```json
{
  "direction": "main_to_sub",
  "source_sub_account_id": null,
  "destination_sub_account_id": 11,
  "amount_gol": 200,
  "balance_bucket": "active",
  "description": "اختیاری",
  "expected": {
    "internal_transfer_contract_version": 1,
    "source_account_number": "1000000007",
    "source_available_gol": 500,
    "destination_account_number": "1000000007-001"
  }
}
```

For `sub_to_main`, source_sub_account_id required and destination null.
For `sub_to_sub`, both required and must differ.

Rules:
- bucket only `active|dim`;
- amount positive integer Gol;
- exact ownership/status/canonical mirror required;
- current source availability must exactly equal frozen `source_available_gol`; if changed, `internal_transfer_terms_changed` 409;
- exact destination account number must still match selected id;
- Active current availability rechecks reservations;
- Dim current availability excludes committed Dim by canonical source semantics;
- no fallback to another source/destination/bucket;
- no aggregate mixing;
- zero mutation on mismatch;
- aggregate wealth and aggregate Active/Dim must remain invariant.

Receipt must be matchable:
- completed transaction;
- direction `internal`;
- amount exact;
- balance bucket exact;
- source/destination canonical account projections after transfer.

## Idempotency/reconciliation

POST requires `Idempotency-Key`.
Native repository sets `allowAutomaticRetry=false`.

Add:
`GET /api/v1/najm-bahar/internal-transfers/by-idempotency/{key}`

Current-user scoped, read-only, exact successful transaction or 404.

Client:
- one frozen intent + key;
- repeated confirm coalesces;
- network/malformed/request_in_progress/retryable => outcomeUnknown;
- explicit same-intent retry only;
- 23h lifetime;
- restart never POSTs;
- no financial offline queue.

## UI

Najm Bahar screen gets «حساب‌های فرعی من».

Capabilities:
- list main and subaccounts with Active/Dim;
- create account;
- rename account;
- action «انتقال داخلی»;
- choose direction from concrete source/destination cards, not raw account numbers;
- choose Active or Dim;
- show exact spendable source amount;
- amount entry;
- review exact source/destination/bucket/amount;
- confirm once;
- unknown-result reconcile/retry.

Wording explains internal redistribution does not send money to another person and does not change total ownership.

## Safety tests

Server:
- GET zero-write and ownership scoped;
- missing mirror excluded, never auto-created by GET;
- create idempotent and server-numbered;
- rename ownership/name-only;
- main/sub/sub projections reservation-aware;
- committed Dim not spendable;
- each direction Active + Dim preserves aggregate totals;
- stale expected => zero mutation;
- cross-owner ids rejected;
- same source/destination rejected;
- exact receipt/reconcile scoped to current user;
- idempotent replay single effect.

Mobile:
- strict DTO decode;
- create/list/rename repository;
- financial POST retry disabled;
- state machine ambiguity/session rules;
- no POST open/cancel;
- exact source/destination/bucket confirmation;
- large integer/RTL/narrow layout;
- no offline replay.

## Final gate

Focused RED/GREEN during tasks. Once stable:
- subaccount/internal-transfer contracts;
- canonical internal service regressions;
- financial architecture;
- full Flutter suite/analyzer/formatter;
- one full server suite;
- whole-change review.

Do not package another APK while phone is unavailable; bundle into later consolidated candidate.
