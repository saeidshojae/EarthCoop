# Stock × Najm Bahar × External Capital

## Release boundary

This release connects the existing Stock domain to Najm Bahar without creating a second monetary system.

Canonical flow:

`EarthCoop valuation -> Bahar-denominated share value -> Bahar-denominated auction -> fiat quote -> IRR/USD settlement -> EarthCoop capital -> Stock ownership`

## Constitutional invariants

1. Stock purchase never creates Bahar.
2. External fiat never credits a Najm Bahar account or balance.
3. The legacy Stock `Wallet` is not a Bahar wallet and must not become a parallel money ledger.
4. External IRR/USD settlement is allowed only for EarthCoop + primary market + EarthCoop treasury supply.
5. Secondary-market settlement is Active Bahar only.
6. Project/non-EarthCoop stock settlement is Active Bahar only.
7. Unknown/legacy issuer or auction classifications fail closed for canonical settlement.
8. Asset ownership remains in Stock/Holding; money ownership remains in Najm Bahar or the external payment/reconciliation domain.

## Transitional code warning

The legacy `AuctionService` still uses decimal/float prices, the legacy Stock wallet, and تومان presentation. It remains isolated from the canonical settlement path.

No automatic conversion from legacy decimal/toman values to Gol is performed. Existing legacy rows keep nullable Gol columns until explicitly migrated with known economic meaning.

## Slice 1 implemented — settlement boundary

- canonical settlement channels;
- fail-closed settlement eligibility policy;
- explicit issuer, market, supply, channel and quote metadata;
- no permissive legacy classification defaults.

## Slice 2 implemented — gateway contract

- `SettlementGateway`: reserve/release/settle/refund;
- positive integer `SettlementRequest` amounts;
- canonical `SettlementReceipt`;
- fail-closed `SettlementGatewayRegistry`.

## Slice 2B implemented — Active Bahar reservation + gateway

A canonical Najm Bahar reservation ledger exists in `najm_active_bahar_reservations`.

- integer Gol reservation;
- Active Bahar only;
- reserve reduces spendable without changing total supply;
- release restores spendability;
- settle/refund are transaction- and double-entry-ledger-backed;
- unique idempotency keys and deterministic account locking;
- `NajmBaharSettlementGateway` implements internal settlement without the Stock wallet.

## Slice 3 implemented — external capital rail

A provider-neutral IRR/USD payment-intent and append-only reconciliation rail exists.

- no fiat wallet/balance;
- no Najm Bahar credit or minting;
- external intents only after `SettlementEligibilityPolicy` passes;
- external settlement restricted to EarthCoop + primary + treasury;
- exact amount/currency reconciliation;
- expired intents cannot confirm;
- provider secrets are redacted before persistence;
- confirmation is payment evidence only, not Stock/Holding allocation.

## Slice 4 implemented — integer Gol pricing + deterministic fiat quote snapshot

Canonical nullable integer Gol fields coexist with legacy decimal fields. Legacy values are never silently treated as Gol.

`FiatQuoteSnapshot` records Gol amount, IRR/USD minor-unit amount, integer rate numerator/denominator, deterministic half-up integer rounding, source and timestamp. New canonical external payment intents require a reproducible quote snapshot and a canonical Gol auction.

## Slice 5 implemented — atomic asset settlement state machine

Canonical settlement allocations are represented by `stock_settlement_allocations` and keyed by a unique `allocation_key`.

Each allocation binds:

- auction and bid identity;
- user and Stock identity;
- settlement channel;
- integer quantity, `price_gol` and `total_gol`;
- money state and asset state;
- Active-Bahar reservation or external-payment intent;
- idempotent Holding transaction;
- attempts, errors, settlement time and reconciliation-required state.

### Idempotent asset leg

`holding_transactions` now has a unique nullable `idempotency_key`. Canonical Holding settlement locks the holding row and credits quantity only once. Reusing the same key with different user/stock/quantity fails closed.

### Active Bahar atomicity

For Active Bahar, `StockAtomicSettlementService` performs the canonical money and asset legs inside the same database transaction:

1. lock settlement allocation and Stock;
2. verify treasury share availability;
3. consume the exact Active-Bahar reservation through `ActiveBaharReservationService`;
4. create the Najm transaction and double-entry money ledger;
5. create the idempotent Holding settlement;
6. decrement `available_shares`;
7. mark the bid won;
8. mark the allocation settled.

If any later local step fails, the surrounding transaction rolls the money and asset mutations back together. A retry returns the same settled allocation rather than consuming money/shares again.

### External-payment atomicity boundary

External money cannot be transactionally rolled back by the EarthCoop database because provider confirmation is an external fact. Therefore the canonical rule is intentionally different:

1. external intent must already be `confirmed`;
2. its immutable quote snapshot must match the allocation's exact `total_gol`;
3. Stock/Holding allocation is attempted transactionally inside EarthCoop;
4. if asset allocation succeeds, the allocation becomes `settled`;
5. if provider money is confirmed but local asset allocation fails, the system never pretends settlement succeeded: the allocation becomes `reconciliation_required`, with `money_state=confirmed_external` and `asset_state=failed`.

`reconciliation_required` is a P0 Founder Operations condition because real external money exists while the corresponding asset allocation is incomplete.

### Deliberate legacy boundary

The canonical path does not fabricate a decimal `StockTransaction.price` simply to satisfy the old transaction schema. Canonical audit history is represented by the settlement allocation, Holding transaction, Najm Bahar ledger or external reconciliation evidence. Legacy `StockTransaction` can be retired or migrated separately after the canonical path is complete.

## Next slice

### Slice 6 — Secondary market gate

Enforce Active Bahar as the only secondary-market settlement channel before bid/order acceptance, and ensure canonical bid acceptance reserves Active Bahar before an order is considered accepted.

## Out of scope

Securities offering eligibility, KYC/AML, investor eligibility, disclosures, payment-provider licensing/compliance, and jurisdiction-specific trading restrictions require the separate legal/compliance workstream.
