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

## Current blocker before retirement/burn

Legacy account semantics are not yet uniform. Main account `balance` has historically represented either:

- the local balance (`balance_active + balance_faded`), or
- an aggregate balance that also includes child sub-accounts.

Retirement/burn must not be implemented until these semantics are normalized, otherwise a member's remaining dim entitlement can be double-counted or under-counted.

The `najm-bahar:audit-balances` command is intentionally read-only and exists to inventory this state before a data-changing normalization migration is approved.

## Next Release A work

1. Inventory all reads/writes of `Account.balance` and define one canonical meaning.
2. Normalize main/sub-account balance semantics and remove mirror drift safely.
3. Refactor remaining direct financial mutations behind transaction/monetary services.
4. Add retirement/cancellation/burn foundation after account semantics are canonical.
5. Add idle-money classification/tax foundation.
6. Add policy-versioned membership fee allocation instead of legacy mutable settings.
7. Refactor wallet/dashboard UI for explicit dim/active membership source selection.
8. Expand financial invariant and true-concurrency tests.

No merge into `main` is intended from this branch at this stage.
