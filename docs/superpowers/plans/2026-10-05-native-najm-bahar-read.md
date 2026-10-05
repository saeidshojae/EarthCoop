# Native Najm Bahar Read Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task after plan review. Steps use checkbox syntax for tracking.

**Goal:** Add an authenticated native wallet and paginated transaction history for the current user's Najm Bahar accounts.

**Architecture:** Reuse API v1 and captured account/device/session guards. Keep financial data in page memory, with independent account and history states. Add one protected route and Home entry without changing server business logic.

**Tech Stack:** Existing Flutter/Dart, Dio, ChangeNotifier, go_router and flutter_test; no new dependencies.

**Spec:** docs/superpowers/specs/2026-10-05-mobile-module-expansion-design.fa.md (first wallet/history delivery only).

## Global Constraints

- Base: a3c41b321dfd93bbb4fddce4e012cc41e8523682, including verified +15 receipt.
- API amounts remain integer Gol; 100 Gol equals one Bahar. Never use floating point for monetary formatting.
- No money mutation, account provisioning, financial persistent cache or offline write queue.
- Preserve distinct local/aggregate balances and available/committed Dim values.
- Phone acceptance remains open; no FTP publication, main merge, provider activation or public-release claim.
- Google/network resilience stays deferred. Hoda and financial writes are later independent work.

## Review Focus

- A response arriving after logout/account/device/token change must not expose the previous account (Task 1/2 tests).
- Refresh overtaking pagination must not mix old pages into the refreshed list (Task 2 test).
- Missing account and an empty valid history must not be presented as fabricated zero balances (Task 1/2/3 tests).
- Large integer money values, negative display amounts and malformed payloads must retain precision or fail explicitly (Task 1 tests).
- Direct route entry and Home taps during logout must obey live session/bootstrap authorization (Task 3 tests).

### Task 1: Typed read repository and money presentation

**Files:** Create apps/mobile/lib/features/najm_bahar/najm_bahar_dto.dart and najm_bahar_repository.dart. Test apps/mobile/test/features/najm_bahar/najm_bahar_repository_test.dart and najm_bahar_dto_test.dart.

**Interfaces:** NajmBaharBalance.fromJson(Object?) with five integer fields; NajmBaharAccount.fromJson(Object?) with id/accountNumber/name/type/status/local/aggregate; NajmBaharTransaction.fromJson(Object?) with resource fields. NajmBaharHistoryPage holds items, nextCursor and hasMore. NajmBaharRepository({required ApiClient apiClient, required bool Function() isCurrentSession}) exposes Future<NajmBaharAccount> account() and Future<NajmBaharHistoryPage> history({String? cursor}). String formatGol(int amount) returns exact Persian Bahar/Gol display using integer division/remainder.

- [ ] Write tests against canonical Resource fixtures. Assert formatGol(101) is 1 Bahar + 1 Gol, zero and negative amounts are exact, and 3_900_000_309 retains all digits. Reject fractional/noninteger financial fields and malformed balance/meta shapes.
- [ ] Test GET account and GET transactions with limit=20, optional opaque cursor, bearer/device headers, and next_cursor/has_more from envelope meta. Verify 404 propagates and no POST occurs. Assert captured session change before or during a response rejects that response.
- [ ] Run focused tests in a lightweight CI branch and observe missing implementation failures; local Dart runtime is unavailable. Do not build Android for this RED gate.
- [ ] Implement interfaces with strict decoding, current-session checks before/after awaits, and no implicit account creation/cache. API failures retain their existing categorical codes.
- [ ] Run focused tests; proceed only when they pass. Commit the repository deliverable.

### Task 2: Independent read states and race-safe pagination

**Files:** Create apps/mobile/lib/features/najm_bahar/najm_bahar_controller.dart. Test apps/mobile/test/features/najm_bahar/najm_bahar_controller_test.dart.

**Interfaces:** NajmBaharController(NajmBaharRepository repository) extends ChangeNotifier. Exposes account, accountFailure, accountLoading, transactions, historyFailure, historyLoading, nextCursor, hasMore, receivedAt; Future<void> load(), refreshAccount(), refreshHistory(), loadMore(). Dispose invalidates all pending updates. Generation counters invalidate old refresh/page responses; page cursor consumption is serialized.

- [ ] Write tests for independent account/history success and failure, account 404 plus valid history, history empty, and page failure preserving existing rows.
- [ ] Test duplicate loadMore calls make one request, repeated IDs are deduplicated in response order, refresh overtakes a pending old page, and stale responses never update receivedAt. Test disposal and account change with Completer-controlled responses.
- [ ] Observe focused RED; implement controller using separate account/history generations and mounted/disposed checks. On failed account refresh retained data is visibly dated, never labeled fresh or replaced by zero.
- [ ] Run focused tests and commit the controller deliverable.

### Task 3: Wallet page, Home entry and protected runtime integration

**Files:** Create apps/mobile/lib/features/najm_bahar/najm_bahar_screen.dart. Modify apps/mobile/lib/features/home/home_screen.dart, app/router/app_router.dart, app/runtime/mobile_app_runtime.dart and app/runtime/production_runtime.dart. Add screen tests in apps/mobile/test/features/najm_bahar/najm_bahar_screen_test.dart; extend existing Home/router/runtime tests. Add a scoped module-checkpoint CI workflow; update device checklist and receipts after verification.

**Interfaces:** NajmBaharScreen({required NajmBaharController controller}) listens to injected controller; runtime view owns creation/load/disposal. Add HomeScreen.onOpenNajmBahar, NajmBaharRouteBuilder = Widget Function(BuildContext), and optional najmBaharBuilder through MobileAppRuntime/AppRouter. Route /najm-bahar invokes builder only with live valid session and allowed protected-network bootstrap; otherwise redirects/shows the existing login/unavailable flow. Runtime constructs captured-token/device ApiClient and a live epoch/user/token/device/bootstrap guard. Preserve existing router behavior and semantic-link allowlist.

- [ ] Write widget tests for loading, local and aggregate balance labels, all five balances, no account, empty history, account/history independent error/retry, next page and dated retained amounts after refresh failure. Verify text and behavior, not private implementation.
- [ ] Write Home/router/runtime tests for entry navigation, unauthenticated/direct entry, required-update/network gate, logout in progress disabling entry, and account-switch response rejection. Observe focused RED before runtime integration.
- [ ] Implement the page with RTL/accessibility and existing layout conventions. Add a runtime-owned controller/view. No transfer/activation/payment button is advertised until those flows exist.
- [ ] Run dart format, flutter analyze and the complete mobile test suite in CI. Preserve dependency lock and platform configuration. Correct any failure by evidence before proceeding.
- [ ] Obtain independent whole-change review after tests pass, covering the five Review Focus cases. Record findings and deferred limits explicitly.
- [ ] After successful tests/review only, bump to 1.0.0+16 and build one stable-UAT Release using the verified +15 signing/configuration pipeline without FTP. Verify actual APK package/version/permission/nondebuggable/certificate, retain APK and build metadata, and record source SHA/run/artifact digest.
- [ ] Extend consolidated physical checklist with wallet/history compared to the same site account, missing account, pagination and account switch. Keep all earlier phone gates open. Commit receipt with skip-ci; avoid repeating the completed full server suite unless server files change.

## Self-review and handoff

The plan covers the first-delivery spec: DTO units, server-authoritative balances, independent read failures, account guards, pagination races, UI wiring, software verification and deferred device acceptance. Later module rows are roadmap scope, not hidden work in this plan. No production code is changed by this document.

Recommended execution: direct implementation in this session, then one independent whole-change review. Task interfaces are tightly coupled to the existing runtime; a separate implementer per task adds avoidable handoff cost. Plan review/choice remains pending; implementation has not started.
