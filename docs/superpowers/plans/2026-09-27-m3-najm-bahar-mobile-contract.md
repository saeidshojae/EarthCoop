# M3 Najm Bahar Stable Mobile Contract Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Expose Najm Bahar's mature account, balance, ledger/history, transfer, activation, membership-fee and launch-scope scheduled-operation capabilities through safe `/api/v1` adapters without duplicating or weakening existing monetary invariants.

**Architecture:** M3 treats existing Najm Bahar services as financial authority and adds a thin mobile application/API layer. All authoritative amounts are integer Gol; controllers never mutate balances directly. Transport idempotency from M1 is combined with existing domain idempotency, reservation/commitment rules, effective-owner transfer policy, ledger/event recording and account invariants. Old API Najm Bahar controllers remain legacy and are not used as the basis for v1.

**Tech Stack:** PHP 8.2+, Laravel 12, Laravel Sanctum 4, PHPUnit 11, M1 `/api/v1` transport/device/idempotency contracts, existing Najm Bahar models/services/policies.

**Spec:** `docs/superpowers/specs/2026-09-27-m0-api-constitution-mobile-readiness-design.md`

## Global Constraints

- M1 validated implementation boundary is `24f0e09c22c2caebee0affbe72b06037cd8533ac`; do not weaken bearer/device, envelope, pagination or idempotency contracts.
- All authoritative money inputs/outputs are integer Gol. Bahar formatting is display-only; no float is accepted as monetary authority.
- `AccountBalanceService::local/aggregate` defines the public bucket projection: Active, available Dim, committed Dim and total.
- Dim is not transferable between independent economic actors; activation and explicit constitutional operations are separate domain actions.
- Transfers go through the application's bound canonical transaction service (`TransactionService` / `SafeTransactionService` / stricter binding), never controller-side balance edits.
- Activation goes through `MonetaryService::activateDim(...)`; membership issuance/activation/event semantics are not reimplemented in API code.
- Existing reservation/commitment and effective-owner policies remain authoritative.
- Every financial mutation requires M1 `Idempotency-Key`; the same key must also be propagated/derived into the domain idempotency seam where supported.
- Never infer account ownership from a client-provided user ID. Resolve current member and owned account server-side.
- Old `API\NajmBaharController` and old web response shapes are legacy compatibility surfaces, not the v1 schema source.
- Idle-tax completion, broad payment-provider UAT, wallet top-up providers, broad project investment expansion and future normalized schema are not M3 blockers unless launch scope is explicitly changed.
- No Product/UX frozen-backlog work and no C14 retirement.

## Review Focus

1. A client must never transfer/activate/pay from another user's account by submitting an account ID/number it does not own or control.
2. Same `Idempotency-Key` replay must not duplicate transaction, activation, fee payment, reservation or scheduled operation; changed payload with same key must fail.
3. Available Active must honor reservations; nominal balance must never be treated as spendable when a reservation reduces availability.
4. Dim must not become transferable merely because a client labels it `faded`; prohibited cross-owner Dim movement must keep the existing domain failure.
5. Every API amount must round-trip as integer Gol without floating-point conversion, including large values and zero/negative rejection.

---

## File Structure

### Shared M3 application/query boundary

- Create: `app/Modules/NajmBahar/Services/Api/NajmBaharAccountQueryService.php` — current user's canonical account, bucket balances, subaccounts and ownership-safe projections.
- Create: `app/Modules/NajmBahar/Services/Api/NajmBaharLedgerQueryService.php` — owner-scoped cursor/page transaction and ledger history.
- Create: `app/Modules/NajmBahar/Services/Api/NajmBaharTransferApplicationService.php` — ownership validation + canonical transaction-service call + idempotency propagation.
- Create: `app/Modules/NajmBahar/Services/Api/NajmBaharActivationApplicationService.php` — launch-scope activation orchestration over existing eligibility/conversion services and `MonetaryService`.
- Create: `app/Modules/NajmBahar/Services/Api/NajmBaharMembershipFeeApplicationService.php` — shared membership-fee operation extracted from existing controller/domain logic where necessary.
- Create only for already-supported scheduled operations: `app/Modules/NajmBahar/Services/Api/NajmBaharScheduledOperationService.php`.

### v1 transport

- Create: `app/Http/Controllers/API/V1/NajmBaharAccountController.php`
- Create: `app/Http/Controllers/API/V1/NajmBaharTransactionController.php`
- Create: `app/Http/Controllers/API/V1/NajmBaharActivationController.php`
- Create: `app/Http/Controllers/API/V1/NajmBaharMembershipFeeController.php`
- Create only if scheduled operation is in launch scope: `app/Http/Controllers/API/V1/NajmBaharScheduledOperationController.php`
- Create: `app/Http/Resources/API/V1/NajmBaharAccountResource.php`
- Create: `app/Http/Resources/API/V1/NajmBaharTransactionResource.php`
- Modify: `routes/api-v1.php`

### Existing financial authority to reuse

- `app/Modules/NajmBahar/Services/AccountService.php`
- `app/Modules/NajmBahar/Services/AccountBalanceService.php`
- `app/Modules/NajmBahar/Services/AccountInvariantService.php`
- `app/Modules/NajmBahar/Services/ActiveBaharReservationService.php`
- `app/Modules/NajmBahar/Services/DimCommitmentService.php`
- `app/Modules/NajmBahar/Services/MonetaryService.php`
- `app/Modules/NajmBahar/Services/MonetaryEventRecorder.php`
- `app/Modules/NajmBahar/Services/TransactionService.php`
- `app/Modules/NajmBahar/Services/SafeTransactionService.php`
- `app/Modules/NajmBahar/Services/StrictTransactionService.php`
- `app/Modules/NajmBahar/Services/EffectiveOwnerTransferPolicyService.php`
- `app/Modules/NajmBahar/Services/FinancialIdempotencyReplayService.php`
- `app/Modules/NajmBahar/Services/FeeService.php`
- Existing membership fee controller/service flow and reputation/participation conversion service where activation policy originates.
- `ScheduledSubAccountTransferExecutor.php` only for already-supported launch-scope scheduled execution.

### Tests

- Create: `tests/Feature/Api/V1/NajmBaharAccountContractTest.php`
- Create: `tests/Feature/Api/V1/NajmBaharTransactionContractTest.php`
- Create: `tests/Feature/Api/V1/NajmBaharActivationContractTest.php`
- Create: `tests/Feature/Api/V1/NajmBaharMembershipFeeContractTest.php`
- Create if applicable: `tests/Feature/Api/V1/NajmBaharScheduledOperationContractTest.php`
- Create: `tests/Feature/Api/V1/NajmBaharMobileJourneyTest.php`
- Reuse impacted `tests/Feature/NajmBahar`, `tests/Unit/NajmBahar`, and `tests/Architecture/NajmBaharFinancialMutationBoundaryTest.php` as regression gates.

---

### Task 1: Freeze integer-Gol account and balance read contract

**Files:**
- Create: `app/Modules/NajmBahar/Services/Api/NajmBaharAccountQueryService.php`
- Create: `app/Http/Controllers/API/V1/NajmBaharAccountController.php`
- Create: `app/Http/Resources/API/V1/NajmBaharAccountResource.php`
- Modify: `routes/api-v1.php`
- Test: `tests/Feature/Api/V1/NajmBaharAccountContractTest.php`

**Interfaces:**
- `GET /api/v1/najm-bahar/account`
- `GET /api/v1/najm-bahar/accounts/{account}/balance` only if the account is owned/authorized; prefer current-member account discovery over arbitrary ID input.
- `NajmBaharAccountQueryService::mainFor(User $user): Account`
- `NajmBaharAccountQueryService::balance(Account $account): array` delegates to `AccountBalanceService::local/aggregate`.
- Stable money object fields are integers: `active_gol`, `dim_available_gol`, `dim_committed_gol`, `dim_total_gol`, `total_gol`; optional display strings may be additive only.

- [ ] **Step 1: Write RED tests** for current-user ownership, no cross-user account disclosure, integer JSON values, aggregate/local consistency and reserved/committed bucket visibility.
- [ ] **Step 2: Confirm RED because v1 Bahar account routes do not exist.**
- [ ] **Step 3: Implement the query/resource/controller boundary; do not calculate balances independently of `AccountBalanceService`.**
- [ ] **Step 4: Run new tests plus account invariant/balance regressions; confirm GREEN.**
- [ ] **Step 5: Commit** `feat(api): expose Najm Bahar account balances`.

### Task 2: Add owner-scoped transaction and ledger history

**Files:**
- Create: `app/Modules/NajmBahar/Services/Api/NajmBaharLedgerQueryService.php`
- Create: `app/Http/Controllers/API/V1/NajmBaharTransactionController.php`
- Create: `app/Http/Resources/API/V1/NajmBaharTransactionResource.php`
- Modify: `routes/api-v1.php`
- Test: `tests/Feature/Api/V1/NajmBaharTransactionContractTest.php`

**Interfaces:**
- `GET /api/v1/najm-bahar/transactions`
- Cursor pagination for mutable transaction history using M1 pagination helper; bounded `page[limit]`.
- Filters are explicitly whitelisted, e.g. `filter[type]`, `filter[status]`, optional account scope that must belong to current user.
- Stable transaction projection: `id`, `type`, `status`, `amount_gol`, `balance_bucket`, `direction`, counterparty-safe summary, `description`, `created_at`, audit/evidence identifiers when present.

- [ ] **Step 1: Write RED tests** for owner scoping, cursor order/no duplicates, filters, integer amounts and no leakage of internal/system metadata secrets.
- [ ] **Step 2: Confirm RED.**
- [ ] **Step 3: Implement query service against canonical transaction/ledger models; no legacy API controller reuse.**
- [ ] **Step 4: Run new history tests plus ledger/event-recorder regressions; confirm GREEN.**
- [ ] **Step 5: Commit** `feat(api): add Najm Bahar transaction history`.

### Task 3: Add safe transfer application boundary

**Files:**
- Create: `app/Modules/NajmBahar/Services/Api/NajmBaharTransferApplicationService.php`
- Extend: `app/Http/Controllers/API/V1/NajmBaharTransactionController.php`
- Modify: `routes/api-v1.php`
- Test: `tests/Feature/Api/V1/NajmBaharTransactionContractTest.php`

**Interfaces:**
- `POST /api/v1/najm-bahar/transfers` with required M1 `Idempotency-Key`.
- Request uses destination account number/reference and integer `amount_gol`; source is resolved from authenticated actor/owned account, not a trusted `user_id`.
- Application service ultimately calls the container-bound `TransactionService::transfer(...)`, which must remain the safe/strict canonical binding, and passes the transport key into the domain idempotency seam.
- Public response returns transaction resource + updated balance projection; it never returns internal mutation metadata as authority.

- [ ] **Step 1: Write RED tests** for positive integer Gol, zero/negative/float rejection, source ownership, cross-user threshold/policy behavior, Dim non-transferability, reserved Active protection, replay same key/same payload, and `409` same key/different payload.
- [ ] **Step 2: Confirm RED.**
- [ ] **Step 3: Implement ownership resolution/application service and call canonical transaction binding; never assign balance columns directly.**
- [ ] **Step 4: Run `SafeTransactionServiceInvariantTest`, `SafeTransactionBindingTest`, reservation and effective-owner transfer tests; confirm GREEN.**
- [ ] **Step 5: Commit** `feat(api): add safe Najm Bahar transfer contract`.

### Task 4: Expose only policy-backed Dim activation

**Files:**
- Audit first: `app/Http/Controllers/ReputationConversionController.php`, participation/reputation services and activation rules.
- Create: `app/Modules/NajmBahar/Services/Api/NajmBaharActivationApplicationService.php`
- Create: `app/Http/Controllers/API/V1/NajmBaharActivationController.php`
- Modify: `routes/api-v1.php`
- Test: `tests/Feature/Api/V1/NajmBaharActivationContractTest.php`

**Interfaces:**
- `GET /api/v1/najm-bahar/activation/eligibility` — reports server-derived convertible source/limits and current Dim availability; no client-calculated conversion rate is authoritative.
- `POST /api/v1/najm-bahar/activation` — required idempotency key; accepts the launch-scope policy source and requested eligible quantity, not arbitrary reason/metadata capable of minting authority.
- Application service verifies eligibility/participation source, records source consumption atomically, and invokes `MonetaryService::activateDim(Account $account, int $requestedAmount, string $reason, array $metadata, string $idempotencyKey, bool $allowPartial = false): array`.

- [ ] **Step 1: Audit current conversion source and write RED tests** proving no activation without eligible source, no minting, Dim decreases exactly as Active increases, insufficient Dim fails/partials only where policy explicitly allows, and replay is single-effect.
- [ ] **Step 2: Confirm RED on missing v1 application seam.**
- [ ] **Step 3: Extract/reuse the existing reputation/participation conversion transaction rather than accepting arbitrary activation from the client.**
- [ ] **Step 4: Run `MonetaryServiceTest` plus reputation/participation conversion regressions; confirm GREEN.**
- [ ] **Step 5: Commit** `feat(api): expose policy-backed Bahar activation`.

### Task 5: Share the membership-fee operation and add v1 payment

**Files:**
- Audit/extract from: `app/Http/Controllers/NajmBaharMembershipFeeController.php`
- Create: `app/Modules/NajmBahar/Services/Api/NajmBaharMembershipFeeApplicationService.php`
- Create: `app/Http/Controllers/API/V1/NajmBaharMembershipFeeController.php`
- Modify minimally: existing web membership-fee controller to consume the shared application service if business logic currently lives there.
- Modify: `routes/api-v1.php`
- Test: `tests/Feature/Api/V1/NajmBaharMembershipFeeContractTest.php`
- Regression: `tests/Feature/NajmBahar/MembershipFeePaymentTest.php`.

**Interfaces:**
- `GET /api/v1/najm-bahar/membership-fee` returns current configured integer Gol total and payment status/split summary.
- `POST /api/v1/najm-bahar/membership-fee/pay` requires idempotency key and an allowed payment source (`active` or policy-authorized Dim path).
- Fee amount comes from `FeeService::getMembershipFee(): int`; API does not hard-code 12 Bahar or fee splits.

- [ ] **Step 1: Write RED parity tests** for configured fee, active payment, Dim activation+payment path, insufficient funds, payment replay and web/v1 use of one shared application operation.
- [ ] **Step 2: Confirm RED.**
- [ ] **Step 3: Extract minimal shared application service and make both v1 and web call it; preserve existing constitutional/event behavior.**
- [ ] **Step 4: Run membership-fee, monetary, treasury/event regressions; confirm GREEN.**
- [ ] **Step 5: Commit** `feat(api): add Najm Bahar membership fee contract`.

### Task 6: Add launch-scope scheduled operations only after current behavior audit

**Files:**
- Audit: existing scheduled-transfer models/controllers/jobs and `ScheduledSubAccountTransferExecutor.php`.
- Create only if supported for members today: `app/Modules/NajmBahar/Services/Api/NajmBaharScheduledOperationService.php`
- Create: `app/Http/Controllers/API/V1/NajmBaharScheduledOperationController.php`
- Modify: `routes/api-v1.php`
- Test: `tests/Feature/Api/V1/NajmBaharScheduledOperationContractTest.php`

**Interfaces:**
- At minimum, read current member's scheduled operations and cancel where existing domain policy permits.
- Creation is included only if the repository already has a mature member-facing canonical creation path; do not invent a new scheduler in M3.
- Execution remains job/domain owned; mobile API never directly marks a schedule executed.

- [ ] **Step 1: Audit and write decision/RED tests for the exact currently-supported scheduled journey.**
- [ ] **Step 2: Implement the smallest owner-scoped adapter around the existing scheduler/executor.**
- [ ] **Step 3: Pin cancel/create idempotency and cross-owner denial where applicable.**
- [ ] **Step 4: Run scheduled-transfer executor/job regressions; confirm GREEN.**
- [ ] **Step 5: Commit only the supported launch-scope surface.**

### Task 7: M3-C non-browser financial acceptance and checkpoint

**Files:**
- Create: `tests/Feature/Api/V1/NajmBaharMobileJourneyTest.php`
- Modify only defects required by the accepted M3 contract.

**Interfaces:**
- Bearer client: view account/buckets → page transaction history → perform one policy-allowed transfer → inspect resulting transaction/balance → perform policy-backed activation if eligible → inspect/pay membership fee → inspect supported scheduled operation evidence if included.

- [ ] **Step 1: Write end-to-end acceptance before fixing integration gaps.**
- [ ] **Step 2: Assert every mutation uses idempotency and every amount is integer Gol.**
- [ ] **Step 3: Assert duplicate replay does not duplicate ledger/event side effects, reservations remain protected and account invariant reconciliation stays green.**
- [ ] **Step 4: Fix only M3 integration gaps; do not implement idle-tax engine, provider top-ups, broad investment/mobile marketplace or M4 actor abstraction.**
- [ ] **Step 5: Run `tests/Feature/Api/V1/NajmBahar*`, impacted `tests/Feature/NajmBahar`, `tests/Unit/NajmBahar`, and `tests/Architecture/NajmBaharFinancialMutationBoundaryTest.php`.**
- [ ] **Step 6: Run Full Validation once on one fixed SHA.**
- [ ] **Step 7: Record M3-A/M3-B/M3-C evidence and commit** `docs(bahar): record M3 mobile contract gate`.

## M3 Definition of Done

- **M3-A Money Representation:** all v1 financial authority is integer Gol and API controllers do no floating-point monetary mutation.
- **M3-B Mutation Safety:** M1 transport idempotency and existing domain idempotency/reservation/commitment/effective-owner/account invariants all remain green.
- **M3-C Mobile Journey:** authenticated bearer client can inspect account/history and execute launch-scope allowed financial actions without web forms.
- Old API Najm Bahar controllers remain legacy compatibility only.
- Idle-tax completion, payment providers, broad Marketplace/Company and future schema normalization remain outside this blocker set.