# Economic System — Release A Progress

Branch: `agent/economic-system-release-a`
Baseline: `agent/najm-hoda-chat-context`

## Completed P0 foundations

- Constitutional initial membership credit: exactly 10,000 Bahar (1,000,000 Gol).
- Initial membership credit is always 100% dim and 0% active.
- Initial membership issuance is ledger-backed and idempotent for new onboarding.
- Monetary activation is centralized in `MonetaryService`.
- Dim → Active activation preserves total supply and writes debit/credit bucket ledger entries.
- Reputation conversion now uses the monetary activation service.
- Automatic activation now uses the monetary activation service.
- Automatic activation is idempotent per account and policy period.
- Automatic activation is scheduled daily; policy period controls whether it applies monthly/quarterly/yearly.
- Monetary policy version storage and effective-policy resolver added, with legacy Settings fallback.
- Reputation conversion and automatic activation record monetary policy provenance.
- Investment catalogue/show/store enforce project visibility policy.
- Historical ledger backfill command added; default mode is read-only and `--apply` never changes balances.
- Treasury registry formalizes Operations/Salary, Central Insurance, Money Destruction and Idle Tax funds.
- Treasury funds now have required reserve and committed-liability fields.
- Interfund transfers are auditable, idempotent and cannot spend protected reserve/commitments.
- Annual membership fee can be paid from dim or active money.
- Membership split (operations/insurance/burn) is now resolved through `MonetaryPolicyService` and records `policy_version_id`, while legacy Settings remain a fallback only.
- Membership info now reports aggregate wallet active/dim totals via `AccountBalanceService`.
- Dim membership payment activates exactly the fee amount before distributing active Bahar to treasury funds.
- Membership payment is idempotent per member/year and preserves historical legacy split recognition.
- Read-only account invariant audit identifies local-vs-legacy-aggregate balance semantics and sub-account mirror drift.
- Canonical balance semantics are explicit in `AccountBalanceService`: each Account row is local; wallet totals are derived from main + child sub-accounts.
- Main ↔ Sub transfers have a ledger-backed canonical implementation that preserves active/dim state.
- `SafeSubAccountService` and `SafeTransactionService` route live own-account transfers away from legacy aggregate semantics and the faded→active bug.
- Person-to-person transfer of dim money is blocked at the safe transaction boundary.
- The old referral bonus no longer transfers the new member's dim money; referral now routes to configurable participation reputation only.
- Architecture test prevents new direct balance mutations outside an explicit transitional financial boundary.
- `NajmBaharController` no longer mutates financial balances directly; its legacy repair delegates to `MonetaryService`.
- Dashboard/wallet controllers now prepare canonical aggregate wallet totals for the UI.
- `BalanceNormalizationService` provides deterministic normalization of cached total fields only.
- `najm-bahar:normalize-balances` is dry-run by default and requires explicit `--apply`; it never alters active/dim buckets.
- Monetary primitives include auditable/idempotent dim cancellation and active destruction.
- Membership retirement foundation is implemented for death, exit and removal.
- Retirement cancels remaining dim only up to the constitutional 10,000-Bahar membership footprint.
- The complementary amount is destroyed first from the Money Destruction Fund, then from the Idle Tax Fund.
- Any uncovered remainder becomes `MonetaryRetirementLiability` owed by the EarthCoop monetary system; the estate is not liable.
- Retirement liabilities can now be settled later, partially or fully, as protected treasury surplus becomes available.
- Liability settlement preserves the same destruction order and updates the parent retirement atomically.
- Member active wealth is explicitly preserved for inheritance/estate handling outside the retirement monetary footprint.
- Legacy dim above the constitutional footprint is preserved rather than confiscated by retirement.

## Tests added

- `NajmBaharConstitutionTest`
- `InitialMembershipCreditTest`
- `InvestmentVisibilityTest`
- `MonetaryServiceTest`
- `MonetaryPolicyServiceTest`
- `HistoricalLedgerBackfillTest`
- `TreasuryServiceTest`
- `MembershipFeePaymentTest`
- `AccountInvariantServiceTest`
- `AccountBalanceServiceTest`
- `BalanceNormalizationServiceTest`
- `InternalAccountTransferServiceTest`
- `SafeSubAccountBindingTest`
- `SafeTransactionBindingTest`
- `MembershipRetirementServiceTest`
- `RetirementLiabilitySettlementServiceTest`
- `NajmBaharFinancialMutationBoundaryTest`

## Important bugs/legacy behavior closed in new live paths

1. The legacy `SubAccountService` credited faded money returned from a sub-account into the main account's active bucket. Live Main ↔ Sub flows now preserve money state.
2. External/person-to-person dim transfer is blocked; dim is not ordinary circulating money.
3. Referral no longer takes 10 dim Bahar from the new member and gives it to the referrer. Invitation is participation reputation; conversion later activates the referrer's own dim balance.
4. Controller-level historical balance repair no longer writes balances directly.

## Normalization status

Canonical semantics are defined and new live internal transfers preserve them. Historical rows can be normalized safely because the normalization operation only rewrites cached `balance` totals to local `balance_active + balance_faded`; it does not move, create, activate, cancel or destroy money.

Commands:

- `najm-bahar:audit-balances` — read-only invariant inventory.
- `najm-bahar:plan-balance-normalization` — read-only detailed plan.
- `najm-bahar:normalize-balances` — dry-run by default; `--apply` explicitly writes cached totals only.

Before production migration, remaining aggregate-reading UI/services must move to `AccountBalanceService` so displays do not assume old aggregate `main.balance` semantics.

## Retirement model

For a member retirement:

1. Determine canonical wallet dim total from main + sub-accounts.
2. Cancel at most 10,000 Bahar of dim membership footprint.
3. Compute `10,000 Bahar - dim_cancelled`.
4. Destroy that amount from Money Destruction Fund available surplus.
5. If needed, destroy the remainder from Idle Tax Fund available surplus.
6. Record any remaining shortage as a system monetary retirement liability.
7. Never debit the member's active wealth or estate assets.
8. If treasury liquidity arrives later, settle the outstanding system liability in the same fund order without touching the estate.

Retirement is idempotent per member.

## Known transitional debt

- Blade wallet/dashboard views still need a focused refactor to consume the prepared canonical `walletBalance` data everywhere instead of directly reading legacy account totals.
- Wallet membership UI still needs an explicit dim/active source selector even though the backend already supports it.
- `TransactionService` and `SubAccountService` still contain legacy direct balance mutation internals behind safe adapters; the allowlist must continue shrinking.
- Idle-money classification is intentionally not charging tax yet. The next step is a reviewable assessment layer before any policy-driven collection exists.

## Next Release A work

1. Refactor wallet/dashboard Blade readers to canonical aggregate balance data.
2. Build reviewable idle-money assessment/classification (no automatic tax collection initially).
3. Add policy-controlled idle-tax collection only after assessment rules and exemptions are approved.
4. Add a batch/command path for settling outstanding retirement liabilities from future treasury liquidity.
5. Shrink safe adapters by migrating remaining legacy transfer methods.
6. Expand true-concurrency tests.
7. Add explicit UI source selection for annual membership fee.

No merge into `main` is intended from this branch at this stage.
