# Stock + Najm Bahar Asset Wallet Unification Design

Date: 2026-08-29
Branch: `agent/economic-system-current-integration`
Status: Design for founder review — no production rollout implied

## 1. Goal

Reconcile the legacy Stock module with the canonical EarthCoop economic architecture so that:

- Stock valuation and auction pricing are Bahar/Gol-denominated, not Rial-denominated.
- Admins enter human-facing valuation values in Bahar.
- All canonical monetary computation and persistence uses integer Gol.
- Fiat (initially IRR) is only an external settlement rail for eligible EarthCoop primary/treasury issuance.
- Stock does not own or expose a second fiat wallet.
- User-owned shares are exposed as part of Najm Bahar's Asset Wallet, using one canonical ownership ledger.
- Existing Stock `Holding` records remain the initial canonical asset-ownership source rather than creating a duplicate Najm Bahar share ledger.

## 2. Canonical units

The canonical unit contract remains:

- 1 Bahar = 100 Gol.
- 1,000 Gol = 1 gram of pure 24K gold.
- Monetary persistence and calculations use integer Gol.
- Human-facing admin valuation inputs use Bahar and are deterministically converted to Gol.
- No floating-point conversion is allowed in canonical pricing logic.

For example, if an admin enters `12,500,000 Bahar` as EarthCoop valuation, canonical storage is `1,250,000,000 Gol`.

## 3. Admin Stock definition

The current admin form still describes `startup_valuation` and `base_share_price` as Rial values. This is legacy behavior and must be replaced.

New UX:

- `EarthCoop valuation (Bahar)` — required human-facing input.
- `Total shares` — required integer.
- `Treasury shares available for offering` — integer, constrained by the primary-offering policy.
- `Base value per share` — calculated canonically from valuation and total shares where exact; otherwise the UI must disclose the integer-Gol rounding policy rather than silently using decimals.
- The form may display an informational Gol equivalent, but Gol remains the persisted canonical amount.
- IRR must not appear in Stock definition. IRR appears only when a live external settlement quote is requested for an eligible purchase.

Existing legacy decimal fields (`startup_valuation`, `base_share_price`) are transitional compatibility fields. They are not authoritative after this change. Canonical fields (`startup_valuation_gol`, `base_share_price_gol`) are authoritative.

## 4. Ownership architecture

### 4.1 Najm Bahar Money Wallet

Purpose: monetary balances only.

- Dim / Active Bahar and Gol accounting.
- Membership fee, invitation reward, activation flows, transfers, project circulation and related banking activity.
- It must never contain external fiat received for Stock settlement.

### 4.2 Najm Bahar Asset Wallet

Purpose: non-cash assets owned by a user.

Initial asset class:

- EarthCoop shares represented by the existing Stock `Holding` + `HoldingTransaction` ledger.

Future extensibility:

- Shares of approved projects/issuers.
- Other explicitly modeled investment assets if later authorized.

The first implementation must NOT create a duplicate table that copies Stock holdings into Najm Bahar. Instead, Najm Bahar Asset Wallet is an application/service/UI boundary over the canonical ownership ledger. This preserves one source of truth.

### 4.3 Stock module

Stock remains responsible for:

- issuer/share definition;
- primary/treasury offering;
- auctions and bids;
- secondary-market behavior when explicitly enabled;
- external-capital quote/payment orchestration for eligible EarthCoop primary issuance;
- settlement and allocation;
- writing final ownership changes to the canonical asset ledger.

Stock is not responsible for maintaining a user fiat balance.

## 5. Legacy Stock Wallet retirement

The existing Stock `Wallet` / `WalletTransaction` pair represents a legacy second-money-system concept. Its user-facing semantics (`balance`, `held_amount`, fiat currency) conflict with the canonical architecture.

Retirement strategy must be staged and non-destructive:

1. Stop presenting the legacy Stock wallet as a user financial account.
2. Remove the Stock Book card that shows withdrawable/reserved Rial balances.
3. Replace it with a settlement-information surface when a primary purchase actually needs external payment.
4. Audit all code dependencies on `Wallet` / `WalletTransaction` before removing any backend table/model.
5. Preserve historical records until a dedicated migration/reconciliation proves they can be archived safely.

No destructive database migration is allowed in the first implementation batch.

## 6. Stock Book UX

The Stock Book should communicate four separate concepts clearly:

1. **EarthCoop Share Information** — valuation in Bahar, total shares, treasury availability, canonical per-share value in Gol/Bahar.
2. **Primary Offerings / Auctions** — currently available EarthCoop treasury offerings.
3. **Your Assets** — summary of share ownership, linking to Najm Bahar Asset Wallet.
4. **Secondary Market** — visibly disabled until its feature flag and Active-Bahar settlement requirements are ready.

The old Rial wallet card is removed from the product experience.

## 7. Settlement flow

Eligible EarthCoop primary/treasury purchase:

`Gol-denominated winning allocation -> authoritative direct 24K-gold quote -> deterministic IRR quote -> external payment provider -> verified payment -> Stock allocation -> Asset Wallet ownership`

Invariants:

- No Bahar is minted by external settlement.
- No IRR is credited to Najm Bahar.
- No fiat balance is credited to Stock Wallet.
- Callback-supplied amount is never authoritative.
- Provider verification uses the canonical EarthCoop payment intent amount/currency.
- Refund/reversal remains an explicit append-only lifecycle.

Secondary market and non-EarthCoop issuers do not use this external-fiat path; they remain Active-Bahar-only unless a later approved design changes that rule.

## 8. Data compatibility and migration

Current `Stock` already contains both legacy decimal fields and canonical Gol fields. Migration should therefore be evolutionary:

- canonical Gol fields become the only fields written by new valuation/pricing workflows;
- legacy decimal accessors/fields are treated as compatibility-only and must not drive canonical calculations;
- existing records lacking canonical Gol values must fail closed for canonical offering/payment flows until explicitly reconciled;
- no implicit Rial-to-Bahar conversion of historical Stock values is allowed, because the original business meaning and historical rate basis may be ambiguous;
- any legacy primary-sale history must be reconciled before production readiness so it counts toward the 10% primary allocation cap.

## 9. Holding valuation

The current `Holding::getTotalValueAttribute()` multiplies quantity by legacy `base_share_price` and returns a float. This is not canonical.

The replacement valuation path must:

- use integer `base_share_price_gol` or a dedicated canonical pricing service;
- return Gol as the authoritative amount;
- render Bahar only as a presentation conversion;
- never use float for canonical asset valuation.

## 10. Safety and rollout

All existing rollout safeguards remain in force:

- external capital disabled by default;
- provider selectors default unavailable;
- Servix and ZarinPal credentials absent by default;
- readiness attestations false by default;
- secondary market disabled by default;
- no merge to `main` without explicit founder decision.

The Asset Wallet unification does not itself authorize real-money Stock sales.

## 11. Test strategy

Implementation must be TDD / contract-first.

Required tests include:

- admin Bahar input converts exactly to integer Gol;
- canonical Stock values do not depend on legacy Rial fields;
- invalid/non-integral canonical conversions fail clearly rather than silently drifting;
- Stock Book no longer exposes a fiat wallet balance;
- Asset Wallet displays ownership from the existing Holding ledger without copying records;
- settled primary allocation updates the same canonical Holding source seen by Asset Wallet;
- Holding valuation uses canonical Gol pricing;
- secondary market remains disabled and Active-Bahar-only by policy;
- external IRR settlement does not mutate Najm Bahar money balances;
- legacy Stock records without reconciled Gol values cannot enter canonical external-capital flow;
- existing Stock, Najm Bahar and full-project regression suites remain green.

## 12. Implementation sequence after approval

1. Add RED contracts for Bahar-admin input and canonical Gol persistence.
2. Reconcile Stock create/update controller/service and validation.
3. Update admin Stock UI from Rial to Bahar and canonical informational display.
4. Add Asset Wallet application boundary backed by existing `Holding` records.
5. Replace Stock Book `Stock Wallet` UI with `Your Assets` / Asset Wallet link and settlement-specific presentation.
6. Replace legacy float Holding valuation with canonical Gol valuation.
7. Audit legacy `Wallet` / `WalletTransaction` dependencies and mark retirement status without destructive deletion.
8. Run targeted Stock + Najm Bahar tests, Full Validation and responsive contract validation.
9. Resume local UAT with a controlled EarthCoop primary offering.

## 13. Explicit non-goals for this batch

- Enabling real ZarinPal payments.
- Enabling Servix live production credentials.
- Enabling secondary-market trading.
- Deleting historical legacy Stock wallet tables.
- Inventing historical Rial-to-Bahar conversions.
- Creating a second asset-ownership ledger inside Najm Bahar.
