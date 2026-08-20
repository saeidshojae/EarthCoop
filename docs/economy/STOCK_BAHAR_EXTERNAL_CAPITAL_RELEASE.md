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

The legacy `AuctionService` still uses decimal/float prices, the legacy Stock wallet, and تومان presentation. It remains isolated until the atomic settlement state machine replaces its money/asset mutation path.

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
- `NajmBaharSettlementGateway` implements the internal settlement contract without the Stock wallet.

## Slice 3 implemented — external capital rail

A provider-neutral IRR/USD payment-intent and append-only reconciliation rail exists.

- no fiat wallet/balance;
- no Najm Bahar credit or minting;
- external intents only after `SettlementEligibilityPolicy` passes;
- external settlement restricted to EarthCoop + primary + treasury;
- currency/channel match enforced;
- exact amount/currency reconciliation;
- expired intents cannot confirm;
- provider secrets are redacted before persistence;
- confirmation is payment evidence only, not Stock/Holding allocation.

## Slice 4 implemented — integer Gol pricing + deterministic fiat quote snapshot

Canonical pricing fields now exist alongside the legacy decimal fields:

- Stock: `base_share_price_gol`, `startup_valuation_gol`;
- Auction: `base_price_gol`, `min_bid_gol`, `max_bid_gol`;
- Bid: `price_gol`;
- StockTransaction: `price_gol`.

### Canonical pricing rules

1. all canonical Stock/Auction/Bid arithmetic is positive integer Gol;
2. bid totals use checked integer multiplication only;
3. legacy decimal values are not automatically converted or treated as Gol;
4. canonical auctions require `quote_unit=gol` and `base_price_gol > 0`;
5. legacy ordering/helpers remain isolated until the old `AuctionService` is retired;
6. Founder Ops reports auctions/stocks that are missing canonical Gol configuration.

### Deterministic external quote snapshots

`FiatQuoteSnapshot` stores:

- Gol amount;
- IRR/USD currency;
- fiat amount in integer minor units;
- integer rate numerator/denominator;
- deterministic `half_up_integer` rounding;
- quote source;
- quote timestamp.

The snapshot validates that the stored fiat amount can be reproduced exactly from the Gol amount and integer ratio and rejects integer overflow.

From Slice 4 forward, new external payment intents require a valid `FiatQuoteSnapshot`. A legacy decimal auction cannot create a new canonical external payment intent.

## Deliberate boundary after Slice 4

The legacy `AuctionService` is still not wired to the new settlement rails. The remaining dangerous boundary is atomicity between:

1. bid/payment reservation or external confirmation;
2. winner/allocation decision;
3. Stock/Holding mutation;
4. money settlement/release/refund;
5. retry/reconciliation after partial failure.

That is Slice 5 and must be solved as one idempotent state machine rather than by injecting a gateway into the existing legacy settlement methods.

## Next slices

### Slice 5 — Atomic asset settlement

Connect bid reservation/payment confirmation and Stock/Holding allocation through one idempotent settlement state machine with reconciliation/compensation handling.

### Slice 6 — Secondary market gate

Enforce Active Bahar as the only secondary-market channel before bid reservation/order acceptance.

## Out of scope

Securities offering eligibility, KYC/AML, investor eligibility, disclosures, payment-provider licensing/compliance, and jurisdiction-specific trading restrictions require the separate legal/compliance workstream.
