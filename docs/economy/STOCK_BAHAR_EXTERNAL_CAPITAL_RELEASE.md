# Stock × Najm Bahar × External Capital

## Release boundary

This release connects the existing Stock domain to Najm Bahar without creating a second monetary system.

Canonical flow:

`EarthCoop valuation -> Bahar-denominated share value -> Bahar-denominated auction -> fiat quote -> IRR/USD settlement -> EarthCoop capital -> Stock ownership`

## Constitutional invariants

1. Stock purchase never creates Bahar.
2. External fiat never credits a Najm Bahar account or balance.
3. The legacy Stock `Wallet` is not a Bahar wallet and must not become a parallel money ledger.
4. External IRR/USD settlement is allowed only when all three conditions are true:
   - issuer = EarthCoop;
   - market = primary;
   - supply source = EarthCoop treasury.
5. Secondary-market settlement is Active Bahar only.
6. Project/non-EarthCoop stock settlement is Active Bahar only.
7. Unknown/legacy issuer or auction classifications fail closed for canonical settlement.
8. Asset ownership remains in Stock/Holding (and later the generic Asset Ledger); money ownership remains in Najm Bahar or the external payment provider/reconciliation domain.

## Current audit findings

### Stock wallet

The legacy `WalletService` stores fiat-like balances using decimal/float semantics. It therefore cannot be reused as a Najm Bahar balance.

A critical legacy settlement defect was also found: the old `settle()` path reduced `held_amount` but did not consume `balance`. This release patches that defect defensively while the wallet remains legacy code. The canonical settlement path will replace it with explicit gateways.

### Auction

`AuctionService` currently assumes one wallet-based settlement path and contains hard-coded تومان presentation. Price and payment rail are coupled.

The target design separates them:

- quote/value unit: Bahar;
- settlement channel: Active Bahar, external IRR, or external USD;
- eligibility policy: independent, fail-closed domain rule;
- asset transfer: Stock/Holding only after confirmed settlement.

### Najm Bahar

Najm Bahar already has the correct primitive for internal money movement: integer amounts, account locking, idempotency, ledger-backed transactions, and an explicit `active` balance type through `TransactionService`.

The Stock integration must call that primitive; it must not mutate Najm Bahar balances directly.

## Slice 1 implemented

- `SettlementChannel` defines canonical settlement channels.
- `SettlementEligibilityPolicy` enforces the external-capital boundary.
- Stock issuer metadata is explicit (`issuer_type`, `issuer_id`).
- Auction settlement metadata is explicit (`market_type`, `supply_source`, `settlement_channel`, `quote_unit`).
- Legacy rows receive no permissive classification defaults.
- Unit tests cover allowed and forbidden settlement combinations.
- Legacy wallet settlement now consumes both held amount and underlying balance under a row lock.

## Next slices

### Slice 2 — SettlementGateway abstraction

Introduce a common contract for reserve/release/settle/refund and route auction execution through it.

Implement `NajmBaharSettlementGateway` using Active Bahar only. The gateway will operate in integer Gol and use idempotency keys derived from auction/bid/allocation identity.

### Slice 3 — External capital rail

Implement external payment intent/reconciliation records for IRR/USD. These records represent provider/payment state, not a stored-value wallet. No fiat balance is credited to users and no Bahar is minted.

### Slice 4 — Bahar-denominated price migration

Replace float/decimal auction arithmetic with integer Gol quote fields and deterministic fiat quote snapshots for external settlement.

### Slice 5 — Atomic asset settlement

Make successful money settlement and Stock/Holding allocation an idempotent state machine with compensating/reconciliation handling for external payment failures.

### Slice 6 — Secondary market gate

Enforce `Active Bahar` as the only secondary-market channel and reject external settlement before bid reservation/order acceptance.

## Out of scope for this release

Securities offering eligibility, KYC/AML, investor eligibility, disclosures, payment-provider licensing/compliance, and jurisdiction-specific trading restrictions are not solved by this software boundary and require the separate legal adapter/compliance workstream.
