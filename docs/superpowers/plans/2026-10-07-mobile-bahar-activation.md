# Native Participation Activation — Implementation Plan

**Date:** 2026-10-07
**Base:** DraftPR165 after native external transfer checkpoint
**No main merge/deploy/live financial action without separate approval.**

## Task 1 — Zero-write eligibility v1

RED:
- eligibility lacks activation_contract_version and policy_version_id;
- legacy fallback may write Setting through firstNajmBaharSettings.

GREEN:
- dedicated read-only activation policy projection;
- eligibility contract v1;
- no writes on GET for versioned or legacy policy.

Focused activation contracts only.

## Task 2 — Consent-bound strict POST

RED:
- stale ratio/points/Dim/policy snapshot can still mutate;
- strict native non-multiple requested points can floor silently.

GREEN:
- optional exact `expected` contract;
- legacy no-expected unchanged;
- strict native exact multiple requirement;
- recompute eligibility immediately before mutation;
- mismatch => activation_terms_changed, zero mutation.

Run activation contract + financial architecture.

## Task 3 — Read-only reconciliation

RED:
- ambiguous result cannot be resolved without replay.

GREEN:
- GET by-idempotency;
- current user + applied conversion only;
- canonical transaction/balance receipt;
- zero writes.

## Task 4 — Flutter wire types/repository

- typed eligibility/intent/receipt;
- exact integer Gol/points;
- POST retry disabled;
- GET eligibility/reconcile;
- receipt matching.

Focused repository tests.

## Task 5 — Controller state machine

- review/freeze/confirm;
- coalesced POST;
- ambiguity handling;
- same-intent retry;
- 23h expiry;
- GET-only reconciliation;
- session/logout/no restart replay.

Focused controller tests.

## Task 6 — Native UI/runtime

- policy/eligibility state;
- exact activation amount input;
- exact points-consumed preview;
- Persian confirmation;
- known/unknown result;
- no action when ineligible.

Widget regressions, RTL/narrow/large integer coverage.

## Task 7 — Final evidence/review

Once stable:
1. activation focused contracts + financial architecture;
2. complete Flutter formatter/analyzer/tests;
3. full server PHPUnit once;
4. whole-change review;
5. regression-first fixes for Critical/Important findings;
6. checkpoint + DraftPR165 update.

No new signed APK merely for this feature while physical device testing is unavailable.
