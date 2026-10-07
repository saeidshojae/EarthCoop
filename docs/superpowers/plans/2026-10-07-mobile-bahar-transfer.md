# Native Bahar Transfer — Implementation Plan

**Date:** 2026-10-07  
**Depends on:** signed membership-payment +18 and its financial transport primitives  
**Rule:** test-first, isolated branch, no main merge/deploy/live transfer without later explicit approval.

## Task 1 — Server capability and source projection

Files:
- routes/api-v1.php
- NajmBaharTransactionController.php
- new `NajmBaharTransferCapabilityService.php`
- focused API tests

RED:
- missing capability endpoint;
- source projection leaks/omits owned subaccounts;
- nominal Active ignores reservations;
- read creates missing mirrors or writes state;
- threshold closed not represented.

GREEN:
- `GET /najm-bahar/transfers/capability`;
- contract version 1;
- external policy state;
- owned active subaccount sources only;
- integer reservation-aware availability;
- zero-write projection.

Checkpoint: focused contract tests only.

## Task 2 — Exact destination preview

Files:
- routes/api-v1.php
- new destination resolver/service
- controller
- tests

RED:
- no native v1 destination resolution;
- privacy fields not bounded;
- self/internal destination ambiguity.

GREEN:
- exact-only GET preview;
- slash/dash normalization;
- active subaccount only;
- minimal safe owner display;
- opaque signed 10-minute destination token;
- external-owner requirement.

Checkpoint: focused destination contract tests.

## Task 3 — Strict consent-bound POST

Files:
- NajmBaharTransactionController.php
- NajmBaharTransferApplicationService.php
- transfer capability/destination services
- tests

RED:
- source availability/policy/destination can change after review;
- POST accepts stale native confirmation;
- native client could request Dim;
- receipt not fully matchable.

GREEN:
- optional `expected` strict contract for new native caller;
- legacy no-expected behavior preserved;
- exact source number, frozen availability and destination token validation;
- active-only native external transfer;
- zero mutation on mismatch;
- stable error codes;
- matchable receipt.

Checkpoint: existing transaction contracts + new strict consent tests + financial architecture check.

## Task 4 — Read-only idempotency reconciliation

Files:
- routes/api-v1.php
- controller/application query service
- tests

RED:
- ambiguous client cannot determine if transfer completed without replaying POST/history guessing.

GREEN:
- authenticated `GET /najm-bahar/transfers/by-idempotency/{key}`;
- user + route scoped;
- completed exact receipt or not_found;
- no metadata/domain-key leakage;
- zero writes.

Checkpoint: focused reconciliation tests.

## Task 5 — Flutter wire contract

Files:
- new transfer DTO/intent source;
- NajmBaharRepository;
- API tests

RED:
- missing typed capability/destination/intent/receipt;
- financial POST would use default retry semantics.

GREEN:
- strict integer DTOs;
- exact JSON intent;
- POST with `allowAutomaticRetry=false`;
- GET capability/destination/reconcile;
- receipt match validation.

Checkpoint: repository/ApiClient focused tests.

## Task 6 — Transfer controller state machine

Files:
- new `najm_bahar_transfer_controller.dart`
- controller tests

States:
- loadingCapability
- ready
- resolvingDestination
- reviewing
- submitting
- confirmed
- definiteRejected
- outcomeUnknown

RED/GREEN:
- no POST on open/preview/cancel;
- immutable frozen intent after first submit;
- coalesced confirm;
- same-intent retry;
- 23-hour monotonic expiry;
- GET-only reconcile;
- session/logout invalidation;
- delayed publication suppression;
- no restart replay.

Checkpoint: controller tests only.

## Task 7 — Native UI/runtime integration

Files:
- transfer section/screen;
- NajmBaharScreen/runtime wiring;
- widget tests

UI:
- capability lock/readiness;
- source subaccount selector;
- Active-only wording;
- destination exact number + explicit preview;
- amount in Bahar/Gol with exact integer serialization;
- optional description;
- exact confirmation screen;
- unknown-outcome recovery.

Tests include RTL/narrow/large amount and no Dim affordance.

Checkpoint: focused widgets + existing Najm Bahar mobile regressions.

## Task 8 — Final evidence

Only after stable Task 1–7:
1. formatter/analyzer + complete Flutter suite;
2. financial architecture + transfer contract tests;
3. full server PHPUnit once;
4. whole-change review;
5. fix Critical/Important findings regression-first;
6. update checkpoints;
7. package one next signed UAT candidate only if useful for the consolidated future phone run.

Do not merge to main, deploy server, publish APK, activate provider, or perform a real transfer in this task without a separate explicit user approval.
