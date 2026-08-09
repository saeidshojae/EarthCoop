# Economic System — Release A Progress

Branch: `agent/economic-system-release-a`
Baseline: `agent/najm-hoda-chat-context`

## Completed P0 foundations

- Constitutional initial membership credit: exactly 10,000 Bahar (1,000,000 Gol).
- Initial membership credit is always 100% dim and 0% active.
- Initial membership issuance is now ledger-backed and idempotent for new onboarding.
- Monetary activation is centralized in `MonetaryService`.
- Dim → Active activation preserves total supply and writes debit/credit bucket ledger entries.
- Reputation conversion now uses the monetary activation service.
- Automatic activation now uses the monetary activation service.
- Automatic activation is idempotent per account and policy period.
- Automatic activation is scheduled daily; policy period controls whether it applies monthly/quarterly/yearly.
- Monetary policy version storage and effective-policy resolver added, with legacy Settings fallback.
- Reputation conversion and automatic activation record monetary policy provenance.
- Investment catalogue/show/store enforce project visibility policy.

## Tests added

- `NajmBaharConstitutionTest`
- `InitialMembershipCreditTest`
- `InvestmentVisibilityTest`
- `MonetaryServiceTest`
- `MonetaryPolicyServiceTest`

## Next Release A work

1. Backfill/normalize historical issuance ledger records without re-minting balances.
2. Replace remaining direct financial balance mutations with monetary/transaction services.
3. Formalize system funds and treasury transfers.
4. Add membership payment source selection (dim or active).
5. Add retirement/burn foundation.
6. Add idle-money classification/tax foundation.
7. Expand financial invariant and concurrency tests.

No merge into `main` is intended from this branch at this stage.
