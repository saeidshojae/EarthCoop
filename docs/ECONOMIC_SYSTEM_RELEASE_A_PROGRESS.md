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
- Canonical balance semantics are now explicit in `AccountBalanceService`: each Account row is local; wallet totals are derived from main + child sub-accounts.
- Main ↔ Sub transfers have a ledger-backed canonical implementation that preserves active/dim state.
- A transitional `SafeSubAccountService` binding routes live Main ↔ Sub flows away from the legacy faded→active bug.
- A transitional `SafeTransactionService` binding intercepts own Main ↔ Sub transfers while leaving other mature transaction flows on the legacy core.
- Read-only `najm-bahar:plan-balance-normalization` reports exactly which stored balances would change before any normalization write is allowed.
- Architecture test prevents new direct balance mutations outside an explicit transitional financial boundary.

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
- `InternalAccountTransferServiceTest`
- `SafeSubAccountBindingTest`
- `SafeTransactionBindingTest`
- `NajmBaharFinancialMutationBoundaryTest`

## Important bug closed in new live paths

The legacy `SubAccountService` credited faded money returned from a sub-account into the main account's active bucket. The safe adapter now routes Main ↔ Sub flows through `InternalAccountTransferService`, so moving dim money between a member's own accounts cannot activate it.

## Current blocker before retirement/burn

Historical account rows are still not normalized. Main account `balance` has represented either:

- the local balance (`balance_active + balance_faded`), or
- an aggregate balance that also includes child sub-accounts.

New live Main ↔ Sub paths now use canonical local semantics, but existing stored rows and aggregate-reading UI/services must be migrated before a data-changing normalization command is introduced.

Retirement/burn must wait until this normalization is complete; otherwise a member's remaining dim entitlement can be double-counted or under-counted.

The following commands are deliberately read-only safeguards:

- `najm-bahar:audit-balances`
- `najm-bahar:plan-balance-normalization`

## Known transitional debt

`NajmBaharController` still contains one inline historical unbucketed-balance repair assignment. The equivalent method now exists in `MonetaryService`; the controller is temporarily named in the architecture allowlist until that large legacy controller can be safely patched/refactored. No new controller-level financial mutations are permitted.

The wallet membership UI also still needs a focused refactor to expose the backend's explicit `dim` / `active` source choice cleanly.

## Next Release A work

1. Migrate all aggregate balance readers to `AccountBalanceService`.
2. Add a reviewed normalization apply-command only after readers are canonical.
3. Synchronize historical SubAccount ↔ Account mirrors and local totals.
4. Remove the temporary controller mutation allowlist entry.
5. Shrink the safe adapters by migrating remaining legacy transfer methods.
6. Implement retirement/cancellation/burn once account semantics are canonical.
7. Add idle-money classification/tax foundation.
8. Add policy-versioned membership fee allocation instead of legacy mutable settings.
9. Refactor wallet/dashboard UI for explicit dim/active membership source selection.
10. Expand true-concurrency tests.

No merge into `main` is intended from this branch at this stage.
