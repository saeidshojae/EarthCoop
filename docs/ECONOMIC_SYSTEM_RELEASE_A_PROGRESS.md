# Economic System — Release A Progress

Branch: `agent/economic-system-release-a`
Baseline: `agent/najm-hoda-chat-context`

Release A is an isolated constitutional-money hardening branch. Nothing from this branch is intended to merge directly to `main` until normalization, tests, and review are complete.

## Completed foundations

- Constitutional initial membership credit: exactly 10,000 Bahar (1,000,000 Gol), always 100% dim at issuance.
- Membership issuance is ledger-backed and idempotent.
- Dim → Active activation is centralized and supply-conserving.
- Reputation conversion and scheduled auto-activation use the same monetary activation path.
- Auto-activation is idempotent per account/policy period.
- Monetary policy versioning exists with legacy Settings fallback.
- Investment visibility policy is enforced on catalogue/show/store paths.
- Historical ledger backfill is read-only by default and never changes balances.
- Treasury registry formalizes Operations/Salary, Central Insurance, Money Destruction, and Idle Tax funds.
- Treasury reserves/commitments protect interfund surplus transfers.
- Membership fee can be paid from dim or active money.
- Membership allocation is resolved through `MonetaryPolicyService` and records `policy_version_id`.
- Account semantics are canonical: every Account row stores local active+dim only; wallet totals are derived from main + child sub-accounts.
- Main ↔ Sub transfers have a ledger-backed canonical implementation that preserves money state.
- Live Main↔Sub paths are routed through safe adapters away from legacy faded→active/aggregate bugs.
- Person-to-person dim transfer is blocked.
- Referral no longer transfers the new member's dim money; invitation now creates configurable participation reputation only.
- `NajmBaharController` no longer writes financial balances directly.
- Balance normalization is deterministic and only repairs cached `balance` totals; active/dim buckets are untouched.
- Membership retirement is implemented for death, exit, and removal.
- Retirement removes exactly the constitutional 10,000-Bahar footprint while preserving estate active wealth/assets.
- Destruction order is Money Destruction Fund → Idle Tax Fund.
- Any shortage becomes a monetary-system liability, never estate/member debt.
- Retirement liabilities can be settled later, partially or fully, as treasury surplus becomes available.
- Idle-capital observation exists as a read-only policy-review layer; it never charges tax or moves funds.
- Internal Main↔Sub reshuffling does not count as economic circulation in idle observation.

## Operational commands

- `najm-bahar:audit-balances` — read-only invariant inventory.
- `najm-bahar:plan-balance-normalization` — read-only detailed normalization plan.
- `najm-bahar:normalize-balances` — dry-run by default; `--apply` only rewrites cached local totals.
- `najm-bahar:settle-retirement-liabilities` — retries outstanding retirement-system liabilities against protected treasury surplus.
- `najm-bahar:observe-idle-capital` — read-only idle-capital observation; `--record` persists snapshots and still charges no tax.

## Key tests

- `NajmBaharConstitutionTest`
- `InitialMembershipCreditTest`
- `MonetaryServiceTest`
- `MonetaryPolicyServiceTest`
- `HistoricalLedgerBackfillTest`
- `TreasuryServiceTest`
- `MembershipFeePaymentTest` including a versioned 5/4/3 allocation example
- `AccountInvariantServiceTest`
- `AccountBalanceServiceTest`
- `BalanceNormalizationServiceTest`
- `InternalAccountTransferServiceTest`
- `SafeSubAccountBindingTest`
- `SafeTransactionBindingTest`
- `DimTransferRestrictionTest`
- `MembershipRetirementServiceTest`
- `RetirementLiabilitySettlementServiceTest`
- `IdleCapitalObservationServiceTest`
- `OperationalCommandsTest`
- `InvestmentVisibilityTest`
- `NajmBaharFinancialMutationBoundaryTest`

## Remaining Release A debt

- Blade wallet/dashboard views still need a focused refactor to consume prepared canonical `walletBalance` data instead of direct legacy balance reads.
- Membership-fee UI still needs an explicit dim/active source selector even though backend support is complete.
- Legacy `TransactionService` / `SubAccountService` internals remain behind safe adapters and should be migrated incrementally so the direct-mutation allowlist can shrink further.
- True concurrent transfer/activation/settlement tests still need expansion.
- Idle-tax collection is intentionally not implemented yet; only reviewable observation exists until rate, exemptions, cadence, and exact amount×duration policy are approved.

## Next

1. Refactor wallet/dashboard Blade readers and membership source selector.
2. Shrink safe adapters by migrating remaining legacy transfer methods.
3. Expand real concurrency/failure tests.
4. Review actual idle-observation outputs before designing collection.

No merge into `main` is intended from this branch at this stage.
