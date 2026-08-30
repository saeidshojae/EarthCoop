# ماتریس آمادگی UAT نجم بهار

Baseline: `fb7f3441347b43e7f845c915e63f0e8e955baccb`

UAT branch: `agent/najm-bahar-uat`

## قواعد وضعیت

- `IMPLEMENTED`: مسیر runtime + قرارداد/تست قابل ردیابی موجود است.
- `PARTIAL`: بخشی عملیاتی است اما قرارداد مقصد یا سطح UAT کامل نشده است.
- `ROADMAP_ONLY`: در سند مقصد تعریف شده ولی runtime production-grade متناظر هنوز کامل نیست.
- `DEFERRED_EXTERNAL`: فقط به علت وابستگی خارجی واقعی deferred است و نباید مصنوعی سبز شود.

| حوزه | وضعیت | شواهد runtime/test | UAT بعدی |
|---|---|---|---|
| Agreement / account creation | IMPLEMENTED/PENDING-CI | `MembershipEligibilityService`, `NajmBaharController::processAgreement`, `MembershipEligibilityServiceTest`, `MembershipIssuanceEligibilityTest` | fresh CI + manual onboarding replay |
| Initial issuance | IMPLEMENTED/PENDING-CI | `NajmBaharConstitution`, `MonetaryService::issueMembershipCredit`, `InitialMembershipCreditTest`, ownership guard | concurrency/replay + fresh CI |
| 10,000 Bahar = 1,000,000 Gol, 100% Dim | IMPLEMENTED | constitutional constant, zero initial active, integer Gol | DB/UAT fixture confirmation |
| Money ledger/events | IMPLEMENTED | `MonetaryEventRecorder`, `Transaction`, `LedgerEntry` | conservation/reconciliation scenarios |
| Active / Dim | IMPLEMENTED | `balance_active`, `balance_faded`, `AccountBalanceService` | display/runtime reconciliation |
| Committed Dim | IMPLEMENTED | `committed_dim`, `DimCommitmentService` | commit/release/activation lifecycle |
| Reserved Active | PARTIAL/RED-UAT | `ActiveBaharReservationService`; internal account transfer is reservation-aware on UAT branch; generic `TransactionService::transfer` still has a RED regression contract | close `TransactionReservationProtectionTest` without weakening reservation semantics |
| Activation | IMPLEMENTED | `MonetaryService::activateDim`, `MonetaryPolicyService`, scheduler `najm-bahar:activate-faded` | policy-period UAT and replay |
| Transfers | PARTIAL/RED-UAT | canonical transaction/internal transfer services + invariant tests; `TransactionReservationProtectionTest` exposes generic-transfer double-spend path against reserved Active | make generic Active debit use spendable Active after reservations |
| Scheduled transfers | PARTIAL -> FIX IN UAT BRANCH | `NajmBaharProcessScheduled`, `ScheduledSubAccountTransferExecutor`; baseline lacked scheduler wiring | validate scheduler contract/CI |
| Membership fee | IMPLEMENTED | explicit Dim/Active source, activation-on-Dim, treasury split, idempotency tests | manual UI UAT after core gates |
| Treasury/system funds | IMPLEMENTED | Operations/Salary, Central Insurance, Money Destruction, Idle Tax via `TreasuryService` | reserves/surplus/interfund UAT |
| Retirement/liability | IMPLEMENTED/PARTIAL-UAT | services + dedicated tests | end-to-end estate/retirement UAT |
| Idle capital | PARTIAL | observation service/command exists; charging/tax execution intentionally not inferred from observation | policy and actual tax-collection audit |
| Jobs/retry | IMPLEMENTED/PARTIAL-UAT | activation scheduler + bounded failed-operation retry; scheduled transaction wiring added in UAT branch | scheduler list + retry/dead-letter UAT |
| User views | IMPLEMENTED/PARTIAL-UAT | agreement, dashboard, wallet, transfer, projects, investments, subaccounts, reports/audit views | manual role-based navigation/UAT |
| Admin views | PARTIAL-UAT | Najm Bahar admin controllers/settings/project management are present | route/permission/UI click matrix |
| Stock × Najm Bahar × External Capital | FROZEN | baseline checkpoint `fb7f344...` | no change in this UAT track |
| Servix/ZarinPal real provider flow | DEFERRED_EXTERNAL | credential + HTTPS staging required | keep flags non-green until real provider UAT |
| Full normalized target account schema (`available_active`, `reserved_active`, `available_dim`, `committed_dim`) | ROADMAP_ONLY/PARTIAL | current runtime remains compatibility model plus service-backed reservation/commitment | do not claim full schema migration |
| Idle tax redistribution engine | ROADMAP_ONLY/PARTIAL | idle observation/fund foundation exists | separate implementation/UAT gate |

## UAT Gate 1 — Membership issuance

Contract:

1. exactly one membership-originated issuance per member;
2. exactly 10,000 Bahar = 1,000,000 Gol;
3. initial Active = 0;
4. initial Dim = 1,000,000 Gol;
5. transaction + ledger backed;
6. idempotent replay;
7. zero-balance precondition;
8. membership credit may only be issued to that member's own user account;
9. activation must conserve total monetary ownership;
10. initial membership credit is only reachable for a valid member: non-system identity, active status, Terms accepted, verified email, and completed canonical profile.

Baseline already contained explicit tests for 1–7 and 9. During UAT, item 8 was found missing inside `MonetaryService` and a regression contract plus ownership guard were added on the UAT branch. Item 10 was then reconciled with the real registration lifecycle and centralized in `MembershipEligibilityService`; the agreement GET/POST and lazy `ensureInitialFunding()` issuance path now use that policy. These changes are not considered verified until fresh CI evidence exists for the resulting head.

## UAT Gate 2 — Reservation / spendability invariant

Contract:

1. reservation reduces spendable Active without changing monetary ownership or nominal Active;
2. release restores spendability without mint/burn;
3. settlement consumes reserved Active exactly once and records double-entry ledger entries;
4. no other transfer path may spend Active already reserved by `ActiveBaharReservationService`.

Items 1–3 are represented by existing reservation service tests. UAT found item 4 violated in two paths: internal main/sub transfer and generic `TransactionService::transfer`. Internal transfer was hardened to use reservation-aware spendable Active. The generic transfer defect remains intentionally RED under `TransactionReservationProtectionTest` until its debit check is made reservation-aware and fresh validation is available.

## Validation state

Current UAT head has no GitHub Actions workflow run or combined status evidence yet. Therefore no new UAT change on this branch is described as green/validated solely from code inspection.

## Safety

- no direct change or merge to `main`;
- Stock freeze remains untouched;
- external payment-provider UAT remains explicitly deferred, never fake-green;
- new financial changes belong only to `agent/najm-bahar-uat` until independently validated.
