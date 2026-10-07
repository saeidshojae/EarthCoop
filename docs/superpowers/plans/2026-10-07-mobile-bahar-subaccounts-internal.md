# Native Najm Bahar Subaccounts & Internal Redistribution — Implementation Plan

**Date:** 2026-10-07  
**Branch:** isolated from completed activation source.  
**Safety:** no main merge/deploy/live movement.

## Task 1 — Owned subaccount read model
RED:
- no API list endpoint;
- reservation/committed semantics absent;
- unmirrored source ambiguity.

GREEN:
- GET /subaccounts;
- contract v1;
- main + canonical owned subaccounts;
- Active available reservation-aware;
- Dim available canonical;
- zero writes.

## Task 2 — Safe create/rename
RED:
- Native cannot create/rename;
- duplicate create ambiguity.

GREEN:
- idempotent POST create, server-generated code/mirror;
- ownership-safe PATCH rename;
- no client financial/status authority.

## Task 3 — Strict internal transfer API
RED:
- no Native Main↔Sub/Sub↔Sub contract;
- stale source availability can mutate;
- source/destination/bucket fallback ambiguity.

GREEN:
- direction-specific exact consent;
- Active/Dim;
- canonical internal services only;
- aggregate invariants;
- stable errors;
- legacy web behavior untouched.

## Task 4 — GET-only reconciliation
Add current-user scoped lookup by idempotency key for successful internal transfer receipt.

## Task 5 — Flutter wire contract
Typed read model, subaccount DTO, internal transfer intent/receipt.
- financial POST retry disabled;
- strict receipt matcher;
- session/bootstrap guards.

## Task 6 — Controller state machine
prepare/list/create/rename separate from financial intent.
Internal transfer states: ready/reviewing/submitting/confirmed/definiteRejected/outcomeUnknown.
Test same-intent retry, 23h expiry, reconcile, logout, no restart replay.

## Task 7 — Native UI
Subaccount list/create/rename + internal transfer review/recovery.
RTL/narrow/large values.

## Task 8 — Final evidence
Focused contracts + canonical service regressions, financial architecture, complete Flutter, one full server suite, review, checkpoint.
No new APK merely for this feature while phone is unavailable.
