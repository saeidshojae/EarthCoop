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

## Current audit findings

The legacy Stock wallet remains transitional and must never become a Bahar wallet. `AuctionService` still assumes the legacy wallet path, float/decimal price semantics and تومان presentation. It is intentionally not wired to the new gateway yet.

Najm Bahar already provides integer money state, account locking, idempotent transactions and double-entry ledger semantics. Stock must use Najm Bahar primitives and must not mutate its balances directly.

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

A canonical Najm Bahar reservation ledger now exists in `najm_active_bahar_reservations`.

Properties:

1. reservation amounts are positive integer Gol;
2. reservations are backed only by `balance_active`;
3. reserving does not mutate account balances or total money supply; spendable Active Bahar is `balance_active - open reservations`;
4. release closes the reservation and restores spendability without minting or transferring money;
5. settlement atomically locks accounts, debits payer Active Bahar, credits payee Active Bahar, creates a canonical Najm transaction and double-entry ledger entries, then marks the reservation settled;
6. refunds are transaction- and ledger-backed, idempotent by refund key and capped by settled amount;
7. reservation, release and settlement keys are unique/idempotent;
8. account locks use deterministic ordering for payer/payee settlement/refund;
9. `NajmBaharSettlementGateway` implements the Stock settlement contract for `active_bahar` without using the legacy Stock wallet.

### Deliberate boundary after Slice 2B

`AuctionService` is still **not** connected to the gateway. That wiring is postponed until price migration and the auction allocation state machine can guarantee that the amount passed to the gateway is integer Gol and that asset allocation cannot diverge from money settlement.

The reservation primitive is therefore available and test-covered, but legacy bid settlement remains isolated rather than being partially migrated.

## Next slices

### Slice 3 — External capital rail

Implement external payment intent/reconciliation records for IRR/USD. These records represent provider/payment state, not a stored-value wallet. No fiat balance is credited to users and no Bahar is minted.

### Slice 4 — Bahar-denominated price migration

Replace float/decimal auction arithmetic with integer Gol quote fields and deterministic fiat quote snapshots for external settlement.

### Slice 5 — Atomic asset settlement

Connect auction reservation/settlement only after successful money settlement and Stock/Holding allocation form an idempotent state machine with compensating/reconciliation handling.

### Slice 6 — Secondary market gate

Enforce Active Bahar as the only secondary-market channel before bid reservation/order acceptance.

## Out of scope

Securities offering eligibility, KYC/AML, investor eligibility, disclosures, payment-provider licensing/compliance, and jurisdiction-specific trading restrictions require the separate legal/compliance workstream.
