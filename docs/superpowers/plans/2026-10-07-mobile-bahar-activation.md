# Native Bahar Participation Activation — Implementation Plan

**Date:** 2026-10-07
**Branch rule:** isolated branch; no main merge/deploy/live activation without explicit later approval.

## Task 1 — Harden eligibility contract
RED:
- missing activation_contract_version/policy_version_id/max_activation_points;
- inconsistent integer projection.

GREEN:
- version 1 projection;
- exact policy identity;
- exact max activation points;
- zero-write eligibility.

Focused activation contract tests only.

## Task 2 — Strict consent-bound server activation
RED:
- stale policy/ratio/points/Dim accepted after review;
- strict Native request silently floors non-multiple points.

GREEN:
- optional exact `expected`;
- strict Native multiple-only points;
- exact snapshot comparison;
- `activation_terms_changed`;
- legacy no-expected behavior preserved.

Run activation contracts + financial architecture check.

## Task 3 — Read-only reconciliation
RED:
- ambiguous transport result cannot be resolved without replay/history guessing.

GREEN:
- GET by idempotency key;
- actor + activation route scope;
- successful completed receipt only;
- no writes/leaks.

## Task 4 — Flutter typed wire contract
Add activation intent/receipt DTOs and repository methods.
- POST retry disabled;
- GET reconcile;
- exact receipt matcher;
- session/bootstrap guards.

## Task 5 — Controller state machine
States:
loading, ready, reviewing, submitting, confirmed, definiteRejected, outcomeUnknown.

Test:
- no POST on prepare;
- exact multiple validation;
- immutable intent;
- coalesced confirm;
- same-intent retry;
- 23h expiry;
- reconcile GET-only;
- session/logout suppression;
- restart no replay.

## Task 6 — Native UI/runtime
Add activation section to Najm Bahar screen.
- points input;
- exact conversion preview;
- Dim→Active before/after;
- explicit confirmation;
- ambiguity recovery;
- RTL/narrow/large-value widget tests.

## Task 7 — Final evidence
Once stable:
- focused activation server/mobile;
- financial architecture;
- complete Flutter suite/analyzer/formatter;
- one full server suite;
- whole-change review;
- docs/checkpoint.

Do not package a new APK merely because software completes while phone remains unavailable.
