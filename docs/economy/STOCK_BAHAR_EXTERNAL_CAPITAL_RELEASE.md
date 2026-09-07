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

The legacy `AuctionService` still uses decimal/float prices, the legacy Stock wallet, and تومان presentation. Canonical Gol auctions are blocked from creating bids through that path.

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

- Active Bahar money + Holding allocation are performed inside one database transaction;
- Holding settlement has a unique idempotency key;
- retry cannot consume the same money or shares twice;
- confirmed external money followed by local asset failure becomes `reconciliation_required` rather than fake success;
- `reconciliation_required` is P0 in Founder Operations.

## Slice 6 implemented — secondary-market gate and canonical bid acceptance

Canonical bids now have explicit acceptance/payment references:

- `acceptance_key` — unique idempotent bid-acceptance identity;
- `reservation_key` — Active Bahar reservation identity;
- `external_payment_intent_id` — reserved for eligible primary/external canonical flows.

### Active Bahar bid acceptance

`StockBidAcceptanceService` provides the canonical Active-Bahar path:

1. bidder identity and payer account are validated;
2. the payer must currently be the bidder's own main Najm Bahar user account;
3. Auction must be active, settlement-eligible, and canonical Gol priced;
4. settlement channel must be `active_bahar`;
5. integer `price_gol`, quantity, min/max and lot constraints are validated;
6. exact `total_gol` is calculated with checked integer arithmetic;
7. Active Bahar is reserved before the Bid is accepted;
8. reservation and Bid creation run in the same database transaction;
9. acceptance is idempotent; conflicting reuse of an acceptance key fails closed.

### Secondary-market constitutional gate

A secondary-market order cannot be accepted on an external IRR/USD rail. `SettlementEligibilityPolicy` rejects that classification before any reservation or Bid creation. The canonical Active-Bahar acceptance service adds a second explicit guard.

### Canonical cancellation

Cancelling a canonical active bid releases its Active Bahar reservation and then marks the Bid cancelled. It never touches the legacy Stock wallet.

### Legacy-route isolation

The old user bid controllers remain available only for legacy decimal auctions during migration. They explicitly reject canonical Gol auctions.

In addition, the `Bid` model itself fails closed when a canonical Gol Auction attempts to create a Bid without the canonical acceptance identity and, for Active Bahar, without a reservation key. This protects against forgotten legacy controllers or future accidental direct `Bid::create()` calls.

The legacy decimal `price` column remains populated only for schema compatibility in the canonical Bid row and has no canonical economic meaning. New calculations and settlement must use `price_gol` exclusively.

## Remaining migration/launch work

The six economic slices establish the canonical backend boundary, but the legacy UI/controller/reporting surfaces still require a deliberate cutover before production Stock launch:

1. build/update canonical Gol bid UI and cancellation UI;
2. route eligible EarthCoop primary external purchases through quote snapshot + external intent;
3. route canonical winner/allocation processing through `StockAtomicSettlementService`;
4. update order-book/reporting screens to display and sort `price_gol` for canonical auctions;
5. retire legacy Stock Wallet participation in canonical auctions;
6. add reconciliation operations for `reconciliation_required` external settlements;
7. run migration/readiness audit over legacy Stock/Auction/Bid rows before enabling canonical trading.

## Out of scope

Securities offering eligibility, KYC/AML, investor eligibility, disclosures, payment-provider licensing/compliance, and jurisdiction-specific trading restrictions require the separate legal/compliance workstream.
