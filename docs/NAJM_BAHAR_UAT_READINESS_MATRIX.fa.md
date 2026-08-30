# ماتریس آمادگی UAT نجم بهار

Baseline Stock Freeze: `fb7f3441347b43e7f845c915e63f0e8e955baccb`

UAT branch: `agent/najm-bahar-uat`

Last validated code checkpoint: `16117d32e0ab0445a89b9a52b9955b74a6d68a5b`

Validation evidence: EarthCoop Integration Full Validation `#1891` — success, including Najm Bahar regression, Stock regression, Full Project PHPUnit and enforce gate.

## قواعد وضعیت

- `IMPLEMENTED/VALIDATED`: مسیر runtime + contract/test موجود و روی checkpoint بالا در Full Validation سبز است.
- `IMPLEMENTED/PARTIAL-UAT`: implementation و تست خودکار موجود است اما UAT دستی/نقشی هنوز کامل نشده است.
- `PARTIAL`: بخشی عملیاتی است اما قرارداد مقصد یا سطح UAT کامل نشده است.
- `ROADMAP_ONLY`: در سند مقصد تعریف شده ولی runtime production-grade متناظر هنوز کامل نیست.
- `DEFERRED_EXTERNAL`: فقط به علت وابستگی خارجی واقعی deferred است و نباید مصنوعی سبز شود.

| حوزه | وضعیت | شواهد runtime/test | UAT بعدی |
|---|---|---|---|
| Agreement / account creation | IMPLEMENTED/VALIDATED | `MembershipEligibilityService`, `NajmBaharController::processAgreement`, eligibility/issuance tests | manual onboarding replay |
| Initial issuance | IMPLEMENTED/VALIDATED | `NajmBaharConstitution`, `MonetaryService::issueMembershipCredit`, issuance + ownership + eligibility contracts | DB/UAT fixture confirmation |
| 10,000 Bahar = 1,000,000 Gol, 100% Dim | IMPLEMENTED/VALIDATED | constitutional constant, zero initial Active, integer Gol | manual account display confirmation |
| Money ledger/events | IMPLEMENTED/VALIDATED | `MonetaryEventRecorder`, `Transaction`, `LedgerEntry`, double-entry tests | reconciliation scenarios |
| Active / available Dim | IMPLEMENTED/VALIDATED | `balance_active`, `balance_faded`, `AccountBalanceService` | UI/display reconciliation |
| Committed Dim | IMPLEMENTED/VALIDATED | `committed_dim`, `DimCommitmentService`, committed-dim invariants | end-to-end commitment lifecycle |
| Reserved Active | IMPLEMENTED/VALIDATED | `ActiveBaharReservationService`, reservation lifecycle, reservation-aware spend checks | manual auction/reservation UAT when applicable |
| Activation | IMPLEMENTED/VALIDATED | `MonetaryService::activateDim`, `MonetaryPolicyService`, `najm-bahar:activate-faded`, command replay contract | manual policy/config UAT |
| Transfers | IMPLEMENTED/VALIDATED | `SafeTransactionService`, canonical main/system + sub/system + internal services, reservation protection, committed-dim internal-transfer contract | manual user transfer UAT |
| Scheduled transfers | IMPLEMENTED/VALIDATED / PARTIAL-UAT | `NajmBaharProcessScheduled`, `ScheduledSubAccountTransferExecutor`, scheduler contract; command wired every minute without overlap | live scheduler execution/retry UAT |
| Membership fee | IMPLEMENTED/VALIDATED | explicit Dim/Active source, activation-on-Dim, treasury split, annual idempotency, fail-closed policy-integrity contract | manual UI payment UAT |
| Treasury/system funds | IMPLEMENTED/PARTIAL-UAT | Operations/Salary, Central Insurance, Money Destruction, Idle Tax via `TreasuryService`; reserve/liability protection | interfund + retirement UAT |
| Retirement/liability | IMPLEMENTED/PARTIAL-UAT | `MembershipRetirementService`, `RetirementLiabilitySettlementService`, dedicated tests | end-to-end exit/death/removal scenario |
| Idle capital | PARTIAL | observation service/command exists; charging/tax execution is not inferred from observation | policy + collection/redistribution implementation audit |
| Jobs/retry | IMPLEMENTED/PARTIAL-UAT | activation scheduler, scheduled-transfer scheduler, bounded failed-operation retry | scheduler list + retry/dead-letter UAT |
| User views | IMPLEMENTED/PARTIAL-UAT | agreement, dashboard, wallet, transfer, projects, investments, subaccounts, reports/audit views | manual role-based navigation/UAT |
| Admin views | IMPLEMENTED/PARTIAL-UAT | Najm Bahar admin dashboard/controllers/settings/system accounts/fees/projects are present | route/permission/UI click matrix |
| Stock × Najm Bahar × External Capital | FROZEN | Stock Freeze baseline `fb7f344...`; Stock regression remains green in #1891 | no change in this UAT track |
| Servix/ZarinPal real provider flow | DEFERRED_EXTERNAL | credential + HTTPS staging required | keep provider UAT flags non-green until real UAT |
| Full normalized target account schema (`available_active`, `reserved_active`, `available_dim`, `committed_dim`) | ROADMAP_ONLY/PARTIAL | runtime remains compatibility fields plus service-backed reservation/commitment | do not claim full normalized schema migration |
| Idle tax redistribution engine | ROADMAP_ONLY/PARTIAL | idle observation + treasury fund foundation exist | separate implementation/UAT gate |

## UAT Gate 1 — Membership issuance

Validated contract:

1. exactly one membership-originated issuance per valid member;
2. exactly 10,000 Bahar = 1,000,000 Gol;
3. initial Active = 0;
4. initial Dim = 1,000,000 Gol;
5. transaction + ledger backed;
6. idempotent replay;
7. zero-balance precondition;
8. issuance only to that member's own user account;
9. activation conserves monetary ownership;
10. issuance reachable only for valid membership: non-system identity, active status, Terms accepted, verified email and completed canonical profile.

## UAT Gate 2 — Reservation / spendability

Validated contract:

1. reservation reduces spendable Active without changing nominal Active or monetary ownership;
2. release restores spendability without mint/burn;
3. settlement consumes reserved Active exactly once and records double-entry ledger entries;
4. refund is bounded/idempotent;
5. settlement/refund preserve `committed_dim` in canonical account total;
6. internal and generic/canonical transfer paths may not double-spend reserved Active.

## UAT Gate 3 — Automatic activation

Validated contract:

1. activation moves available Dim to Active without minting;
2. partial activation is capped by available Dim;
3. committed Dim is preserved;
4. same policy period/replay is idempotent;
5. scheduled command wiring uses the versioned/effective policy contract.

## UAT Gate 4 — Membership fee

Validated contract:

1. member explicitly chooses Dim or Active payment source;
2. Dim source activates exactly the fee before transfer;
3. no silent fallback to another source;
4. canonical treasury allocation is versioned/auditable;
5. a policy whose split total does not equal the membership fee fails closed and rolls back all monetary mutations;
6. annual replay does not charge twice.

## UAT Gate 5 — Internal transfer canonical total

Validated contract:

1. Main ↔ Sub internal movement preserves money state;
2. reserved Active cannot be double-spent into a child account;
3. main-account `committed_dim` remains untouched;
4. stored main local total remains `active + available_dim + committed_dim`;
5. aggregate ownership remains conserved across Main + children;
6. replay remains idempotent.

The committed-Dim transfer gap was first exposed as RED by Full Validation `#1889`, then corrected in `InternalAccountTransferService`, and the same Najm Bahar regression passed in Full Validation `#1891`.

## Validation state

`16117d32e0ab0445a89b9a52b9955b74a6d68a5b` is the latest code checkpoint with complete green evidence from Full Validation `#1891`.

This documentation update does not alter runtime financial behavior. Any later code change requires its own fresh validation before being called green.

## Safety

- no direct change or merge to `main`;
- Stock Freeze remains untouched;
- Elections, Secretariat, Najm Hoda and Group Chat regressions remain protected by Full Validation;
- external payment-provider UAT remains explicitly deferred, never fake-green;
- new financial changes remain isolated on `agent/najm-bahar-uat` until explicit integration approval.
