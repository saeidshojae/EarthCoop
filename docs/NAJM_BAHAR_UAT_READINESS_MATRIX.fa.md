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
| Agreement / account creation | PARTIAL | `NajmBaharController::processAgreement`, `AccountService` | eligibility/profile enforcement و replay |
| Initial issuance | IMPLEMENTED | `NajmBaharConstitution`, `MonetaryService::issueMembershipCredit`, `InitialMembershipCreditTest`, `MonetaryServiceTest` | ownership invariant + concurrency/replay |
| 10,000 Bahar = 1,000,000 Gol, 100% Dim | IMPLEMENTED | constitutional constant, zero initial active, integer Gol | DB/UAT fixture confirmation |
| Money ledger/events | IMPLEMENTED | `MonetaryEventRecorder`, `Transaction`, `LedgerEntry` | conservation/reconciliation scenarios |
| Active / Dim | IMPLEMENTED | `balance_active`, `balance_faded`, `AccountBalanceService` | display/runtime reconciliation |
| Committed Dim | IMPLEMENTED | `committed_dim`, `DimCommitmentService` | commit/release/activation lifecycle |
| Reserved Active | PARTIAL | `ActiveBaharReservationService` | reserve/release/settle UI + aggregate visibility |
| Activation | IMPLEMENTED | `MonetaryService::activateDim`, `MonetaryPolicyService`, scheduler `najm-bahar:activate-faded` | policy-period UAT and replay |
| Transfers | IMPLEMENTED | canonical transaction/internal transfer services + invariant tests | user-to-user, subaccount, cross-owner UAT |
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
9. activation must conserve total monetary ownership.

Baseline already contained explicit tests for 1–7 and 9. During UAT, item 8 was found missing inside `MonetaryService` and a regression contract plus ownership guard were added on the UAT branch. This fix is not considered verified until fresh CI evidence exists for the resulting head.

## Known UAT contract gap — membership eligibility

The agreement GET computes profile-completeness signals, while the POST account-creation path itself currently relies on authentication + agreement acceptance (and rejects system identities) rather than enforcing the same full profile eligibility server-side. Treat this as an open UAT contract gap until the exact canonical definition of a valid/verified member is reconciled with registration state and enforced by a dedicated test.

## Safety

- no direct change or merge to `main`;
- Stock freeze remains untouched;
- external payment-provider UAT remains explicitly deferred, never fake-green;
- new financial changes belong only to `agent/najm-bahar-uat` until independently validated.
