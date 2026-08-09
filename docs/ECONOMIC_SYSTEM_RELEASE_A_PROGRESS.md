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
- Dim membership payment activates exactly the fee amount before distributing active Bahar to treasury funds.
- Membership payment is idempotent per member/year and preserves historical legacy split recognition.
- Read-only account invariant audit identifies local-vs-legacy-aggregate balance semantics and sub-account mirror drift.
- Canonical balance semantics are explicit in `AccountBalanceService`: each Account row is local; wallet totals are derived from main + child sub-accounts.
- Main ↔ Sub transfers have a ledger-backed canonical implementation that preserves active/dim state.
- `SafeSubAccountService` and `SafeTransactionService` route live own-account transfers away from legacy aggregate semantics and the faded→active bug.
- Architecture test prevents new direct balance mutations outside an explicit transitional financial boundary.
- `BalanceNormalizationService` provides deterministic normalization of cached total fields only.
- `najm-bahar:normalize-balances` is dry-run by default and requires explicit `--apply`; it never alters active/dim buckets.
- Monetary primitives now include auditable/idempotent dim cancellation and active destruction.
- Membership retirement foundation is implemented for death, exit and removal.
- Retirement cancels remaining dim only up to the constitutional 10,000-Bahar membership footprint.
- The complementary amount is destroyed first from the Money Destruction Fund, then from the Idle Tax Fund.
- Any uncovered remainder becomes `MonetaryRetirementLiability` owed by the EarthCoop monetary system; the estate is not liable.
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
- `NajmBaharFinancialMutationBoundaryTest`

## Important bug closed in new live paths

The legacy `SubAccountService` credited faded money returned from a sub-account into the main account's active bucket. Live Main ↔ Sub flows now go through `InternalAccountTransferService`, so moving dim money between a member's own accounts cannot activate it.

## Normalization status

Canonical semantics are now defined and new live internal transfers preserve them. Historical rows can be normalized safely because the normalization operation only rewrites cached `balance` totals to local `balance_active + balance_faded`; it does not move, create, activate, cancel or destroy money.

Commands:

- `najm-bahar:audit-balances` — read-only invariant inventory.
- `najm-bahar:plan-balance-normalization` — read-only detailed plan.
- `najm-bahar:normalize-balances` — dry-run by default; `--apply` explicitly writes cached totals only.

Before production migration, aggregate-reading UI/services still need to be moved to `AccountBalanceService` so displays do not assume old aggregate `main.balance` semantics.

## Retirement model now implemented

For a member retirement:

1. Determine canonical wallet dim total from main + sub-accounts.
2. Cancel at most 10,000 Bahar of dim membership footprint.
3. Compute `10,000 Bahar - dim_cancelled`.
4. Destroy that amount from Money Destruction Fund available surplus.
5. If needed, destroy the remainder from Idle Tax Fund available surplus.
6. Record any remaining shortage as a system monetary retirement liability.
7. Never debit the member's active wealth or estate assets.

Retirement is idempotent per member.

## Known transitional debt

`NajmBaharController` still contains one inline historical unbucketed-balance repair assignment. The equivalent method exists in `MonetaryService`; the controller is temporarily named in the architecture allowlist until that legacy controller can be safely patched/refactored. No new controller-level financial mutations are permitted.

The wallet membership UI still needs a focused refactor to expose the backend's explicit `dim` / `active` source choice cleanly.

Some aggregate readers still use legacy `Account.balance` and must move to `AccountBalanceService` before normalization is run against production data.

## Next Release A work

1. Migrate aggregate balance readers/UI to `AccountBalanceService`.
2. Remove the temporary controller mutation allowlist entry.
3. Add retirement-liability settlement flow when treasury liquidity later becomes available.
4. Add idle-money classification/tax foundation.
5. Add policy-versioned membership fee allocation instead of legacy mutable settings.
6. Refactor wallet/dashboard UI for explicit dim/active membership source selection.
7. Restrict external transfers of dim money to constitutional allowed uses/internal account movement.
8. Shrink safe adapters by migrating remaining legacy transfer methods.
9. Expand true-concurrency tests.

No merge into `main` is intended from this branch at this stage.
