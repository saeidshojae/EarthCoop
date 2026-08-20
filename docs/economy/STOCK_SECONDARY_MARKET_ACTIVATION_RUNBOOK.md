# Stock Secondary Market Activation Runbook

## Scope

This runbook governs activation of the canonical EarthCoop Stock secondary market.

Canonical secondary settlement is:

`buyer Active Bahar -> seller Najm Bahar account`

at the same time as:

`seller Holding -> buyer Holding`

Treasury `available_shares` must not change in a secondary settlement.

## Implemented backend and UX

The branch contains:

- seller identity on canonical secondary auctions;
- seller Holding reservation and anti-double-sell accounting;
- seller listing creation from the Stock holding wallet;
- canonical `listing_key` idempotency;
- Active Bahar buyer reservation before bid acceptance;
- seller self-bid rejection at bid acceptance;
- canonical price floor in integer Gol;
- seller listing cancellation when there are no active bids;
- release of seller Holding reservation on valid cancellation;
- secondary winner selection and settlement;
- seller Active Bahar proceeds;
- seller Holding debit + buyer Holding credit;
- idempotent settlement retry;
- release of losing buyer reservations and unsold seller reservation remainder;
- canonical close dispatch from `auctions:close`;
- Stock canonical readiness audit and Founder Ops risk visibility.

## Feature-flag semantics

`STOCK_SECONDARY_MARKET_ENABLED` controls acceptance of **new** secondary listings.

It must not strand commitments already accepted before the flag is disabled. Existing canonical auctions, Active Bahar reservations and seller Holding reservations must continue through their authoritative close/release/reconciliation lifecycle.

## Activation prerequisites

Do not set `STOCK_SECONDARY_MARKET_ENABLED=true` until all of the following are true in the target environment:

1. migrations for canonical Gol pricing, Active Bahar reservations, Holding reservations, secondary seller identity and listing key have completed;
2. `php artisan stock:canonical-readiness --fail-on-blocker` exits successfully;
3. there are no orphan Active Bahar Stock-bid reservations;
4. there are no orphan seller Holding reservations;
5. there are no canonical auctions containing legacy bids;
6. there are no `reconciliation_required` Stock allocations;
7. target users have active main Najm Bahar accounts for buyer payments and seller proceeds;
8. scheduler is running `auctions:close` every minute;
9. feature tests for listing, bid reservation, cancellation, close and settlement pass in the target environment;
10. a manual smoke test with non-production-value test accounts confirms that treasury shares remain unchanged in a secondary trade.

## Activation sequence

1. Keep `STOCK_SECONDARY_MARKET_ENABLED=false` during deploy/migration.
2. Run migrations.
3. Run the canonical Stock test suite.
4. Run `php artisan stock:canonical-readiness --fail-on-blocker`.
5. Resolve all blockers.
6. Perform a controlled seller-listing smoke test.
7. Enable `STOCK_SECONDARY_MARKET_ENABLED=true`.
8. Re-run readiness immediately.
9. Monitor Founder Ops financial findings and `auctions:close` logs.

## Rollback / kill switch

If new secondary listings must be stopped, set:

`STOCK_SECONDARY_MARKET_ENABLED=false`

This stops new seller listings. It must not delete auctions, reservations, bids, ledger entries or Holding history.

Already accepted canonical commitments continue to close or reconcile. If a financial inconsistency is detected, stop accepting new listings and resolve the affected allocation through the reconciliation workflow; never rewrite ledger or Holding history.

## Fail-closed invariants

- Secondary settlement channel is Active Bahar only.
- Seller cannot bid on their own listing.
- Bid price cannot be below canonical `base_price_gol` even when `min_bid_gol` is null.
- Seller cannot list more than unreserved Holding quantity.
- Buyer cannot reserve another user's Najm Bahar account.
- One listing idempotency key cannot describe different seller/stock/quantity/price data.
- Canonical auctions cannot be settled through the legacy Stock Wallet engine.
- Unknown or incomplete seller/reservation identity blocks settlement.
- Ledger and ownership history are append-preserving and must not be rewritten as a recovery mechanism.
