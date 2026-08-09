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

Najm Bahar already has the correct primitive for immediate internal money movement: integer amounts, account locking, idempotency, ledger-backed transactions, and an explicit `active` balance type through `TransactionService`.

The Stock integration must call Najm Bahar primitives; it must not mutate Najm Bahar balances directly.

The audit also confirmed an important missing primitive: there is currently no canonical `reserved_active` balance or equivalent Active-Bahar reservation service. Auctions require reservation at bid time. Therefore Stock must not be wired directly to immediate `transfer()` as a substitute for reservation.

## Slice 1 implemented — settlement boundary

- `SettlementChannel` defines canonical settlement channels.
- `SettlementEligibilityPolicy` enforces the external-capital boundary.
- Stock issuer metadata is explicit (`issuer_type`, `issuer_id`).
- Auction settlement metadata is explicit (`market_type`, `supply_source`, `settlement_channel`, `quote_unit`).
- Legacy rows receive no permissive classification defaults.
- Unit tests cover allowed and forbidden settlement combinations.
- Legacy wallet settlement now consumes both held amount and underlying balance under a row lock.

## Slice 2 implemented — gateway contract

The canonical settlement abstraction is now explicit:

- `SettlementGateway` defines `reserve`, `release`, `settle`, and `refund`.
- `SettlementRequest` requires a positive integer amount and non-empty idempotency/reference identity.
- `SettlementReceipt` reports the canonical result state without exposing a stored-value wallet model.
- `SettlementGatewayRegistry` resolves gateways by settlement channel and fails closed when a channel is unknown, unregistered, or duplicated.
- Unit coverage verifies request invariants and fail-closed registry behavior.

The abstraction is intentionally not yet injected into `AuctionService`: doing so before Active Bahar has a real reservation primitive would either weaken auction guarantees or silently keep the legacy wallet as the monetary authority.

## Required Najm Bahar dependency before Active-Bahar auction wiring

Add a canonical Active-Bahar reservation primitive with all of these properties:

1. amounts are integer Gol;
2. only Active Bahar can be reserved;
3. reservation reduces spendable Active Bahar without changing total money supply;
4. release restores spendable Active Bahar;
5. settlement consumes a reservation and credits the destination atomically;
6. refund is idempotent and cannot exceed the settled amount;
7. all operations are ledger-backed and use unique idempotency keys;
8. account/sub-account invariants remain valid under retries and concurrency.

The preferred account model is explicit `available_active` + `reserved_active` semantics (or an equivalent reservation ledger that produces the same invariants). This is a Najm Bahar capability, not a Stock wallet capability.

## Next slices

### Slice 2B — Active Bahar reservation + gateway

Implement the Najm Bahar Active-Bahar reservation primitive, then implement `NajmBaharSettlementGateway` against it. Auction/bid/allocation identities will produce deterministic idempotency keys.

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
