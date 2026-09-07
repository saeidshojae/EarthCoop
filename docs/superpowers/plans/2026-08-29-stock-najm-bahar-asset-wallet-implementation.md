# Stock + Najm Bahar Asset Wallet Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make Stock valuation Bahar-facing/Gol-canonical, expose existing Stock holdings as the single-source Najm Bahar Asset Wallet, and retire the legacy Stock fiat wallet from user-facing flows without destructive data deletion.

**Architecture:** Keep `holdings` / `holding_transactions` as the canonical ownership ledger and add a Najm Bahar application/UI boundary over them. Stock continues to own issuance, auctions, settlement and ownership writes. Human-facing monetary input is Bahar, but persistence/calculation is integer Gol; legacy decimal Stock fields remain compatibility-only because the original schema makes them non-null, and must never drive canonical behavior.

**Tech Stack:** Laravel 9/PHP, Eloquent, Blade, PHPUnit, existing Stock Pricing/Settlement services, existing Najm Bahar module.

**Spec:** `docs/superpowers/specs/2026-08-29-stock-najm-bahar-asset-wallet-design.md`

## Global Constraints

- 1 Bahar = 100 Gol.
- 1,000 Gol = 1 gram of pure 24K gold.
- Canonical money values use integer Gol; no float arithmetic in canonical pricing.
- Fiat is only an external settlement rail and is never a Najm Bahar or Stock balance.
- Existing `Holding` / `HoldingTransaction` records remain the only share-ownership source of truth.
- Do not create a duplicate Najm Bahar asset-ownership table.
- Do not delete legacy Stock wallet tables/models in this batch.
- External-capital and secondary-market rollout flags remain disabled.
- Do not merge to `main`.
- No implicit historical Rial-to-Bahar conversion.

---

### Task 1: Exact Bahar-to-Gol Stock valuation boundary

**Files:**
- Create: `app/Modules/Stock/Pricing/BaharGolConverter.php`
- Create: `app/Modules/Stock/Services/StockValuationService.php`
- Test: `tests/Feature/Stock/StockBaharValuationTest.php`

**Interfaces:**
- Produces: `BaharGolConverter::toGol(string $bahar): int`
- Produces: `BaharGolConverter::toBaharString(int $gol): string`
- Produces: `StockValuationService::configure(Stock $stock, string $valuationBahar, int $totalShares, ?int $availableShares, ?string $info): Stock`
- Canonical per-share value must be exact integer Gol; if `valuationGol % totalShares !== 0`, reject rather than silently round.
- Because `stocks.startup_valuation` and `stocks.base_share_price` are legacy non-null columns, new canonical writes may populate them with deterministic Bahar-denominated compatibility mirrors only to satisfy schema compatibility; all authoritative reads/tests use `_gol` fields.

- [ ] **Step 1: Write failing tests**

Test exact cases including `12500000 Bahar -> 1250000000 Gol`, `12.34 Bahar -> 1234 Gol`, rejecting more than two Bahar decimal places, rejecting non-positive input, exact share division, and proving legacy decimal fields cannot change canonical valuation.

- [ ] **Step 2: Run the targeted test and verify RED**

Run: `php artisan test tests/Feature/Stock/StockBaharValuationTest.php`
Expected: FAIL because converter/service do not yet exist.

- [ ] **Step 3: Implement minimal converter and valuation service**

Parse the Bahar string manually with integer/string operations. Never cast the input to float. Validate total shares and integer overflow before multiplication/division. Write `startup_valuation_gol` and `base_share_price_gol` as authoritative fields.

- [ ] **Step 4: Run targeted test and verify GREEN**

Run: `php artisan test tests/Feature/Stock/StockBaharValuationTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

Commit message: `feat(stock): make valuation Bahar-facing and Gol-canonical`

### Task 2: Admin Stock definition uses Bahar and calculated per-share Gol

**Files:**
- Modify: `app/Modules/Stock/Controllers/StockController.php`
- Modify: `app/Modules/Stock/Views/admin_stock_create.blade.php`
- Test: `tests/Feature/Stock/StockAdminCanonicalValuationTest.php`

**Interfaces:**
- Admin field name: `startup_valuation_bahar`.
- Admin no longer supplies a free-form Rial `base_share_price`; it is derived by `StockValuationService`.
- Existing canonical stock values render back into Bahar without reading legacy Rial-era fields.

- [ ] **Step 1: Write failing HTTP/source contract tests**

Assert the admin form contains `startup_valuation_bahar`, Bahar/Gol explanatory copy, no `(ریال)` valuation/base-price labels, and POSTing a valid Bahar valuation persists exact canonical Gol values. Assert non-exact per-share Gol division returns validation error instead of rounding.

- [ ] **Step 2: Run targeted test and verify RED**

Run: `php artisan test tests/Feature/Stock/StockAdminCanonicalValuationTest.php`
Expected: FAIL against the current Rial form/controller.

- [ ] **Step 3: Implement controller and Blade changes**

Inject/use `StockValuationService`; preserve issuer metadata and structural fields. Present calculated base share value in Gol and Bahar. Explain that aggregate primary/treasury issuance is capped by policy and do not falsely constrain treasury inventory itself to 10%.

- [ ] **Step 4: Run targeted tests and verify GREEN**

Run: `php artisan test tests/Feature/Stock/StockAdminCanonicalValuationTest.php tests/Feature/Stock/StockBaharValuationTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

Commit message: `feat(stock): replace admin Rial valuation with Bahar input`

### Task 3: Canonical Holding valuation

**Files:**
- Modify: `app/Modules/Stock/Models/Holding.php`
- Test: `tests/Feature/Stock/HoldingCanonicalValuationTest.php`

**Interfaces:**
- Produces: `Holding::total_value_gol` as `?int`.
- `total_value_gol` is `quantity * stock.base_share_price_gol` with overflow protection.
- Legacy `total_value` must no longer be a float/Rial-derived authoritative value; it should delegate to the Gol value or be deprecated safely.

- [ ] **Step 1: Write failing tests**

Create a Stock where legacy `base_share_price` deliberately disagrees with `base_share_price_gol`; assert Holding valuation follows Gol only. Assert unreconciled Stock without canonical price yields no canonical valuation rather than falling back to legacy decimal data.

- [ ] **Step 2: Verify RED**

Run: `php artisan test tests/Feature/Stock/HoldingCanonicalValuationTest.php`
Expected: FAIL because current accessor multiplies legacy `base_share_price` and returns float.

- [ ] **Step 3: Implement canonical accessors**

Use integer arithmetic and fail closed on overflow/unreconciled pricing.

- [ ] **Step 4: Verify GREEN**

Run targeted Stock tests.

- [ ] **Step 5: Commit**

Commit message: `refactor(stock): value holdings from canonical Gol`

### Task 4: Najm Bahar Asset Wallet boundary over existing Holdings

**Files:**
- Create: `app/Modules/NajmBahar/Services/AssetWalletService.php`
- Create: `app/Http/Controllers/NajmBaharAssetWalletController.php`
- Create: `resources/views/najm-bahar/assets.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/NajmBahar/AssetWalletTest.php`

**Interfaces:**
- Produces: route `GET /najm-bahar/assets`, name `najm-bahar.assets`.
- `AssetWalletService::forUser(int $userId)` reads the existing positive Stock Holdings with Stock relation; it creates no asset copy.
- The view renders asset quantity, canonical Gol value and Bahar presentation.

- [ ] **Step 1: Write failing tests**

Assert the new route/service exists, a pre-existing Stock Holding appears in the Asset Wallet, no duplicate asset table/model record is created, and canonical values are used.

- [ ] **Step 2: Verify RED**

Run: `php artisan test tests/Feature/NajmBahar/AssetWalletTest.php`
Expected: FAIL because boundary does not yet exist.

- [ ] **Step 3: Implement service/controller/route/view**

Keep the controller read-only in this batch. Use Unified layout. Make clear that the monetary wallet and asset wallet are separate.

- [ ] **Step 4: Verify GREEN**

Run Asset Wallet + Holding tests.

- [ ] **Step 5: Commit**

Commit message: `feat(najm-bahar): expose canonical asset wallet`

### Task 5: Stock Book retires fiat wallet and links to Asset Wallet

**Files:**
- Modify: `app/Modules/Stock/Controllers/StockController.php`
- Modify: `app/Modules/Stock/Views/stock_dashboard.blade.php`
- Modify: `app/Modules/Stock/Views/holding_index.blade.php`
- Modify: `app/Modules/Stock/Views/holding_show.blade.php` only where canonical summary data is available; do not invent historical conversions.
- Test: `tests/Feature/Stock/StockBookCanonicalUxTest.php`

**Interfaces:**
- Stock Book shows canonical EarthCoop valuation in Bahar and share price in Gol/Bahar.
- It no longer shows withdrawable/reserved Rial Stock wallet balances or links to `/wallet` as a financial account.
- `Your Assets` links to `najm-bahar.assets`.
- Existing `/holdings` remains compatibility-safe and should direct users to the same asset-wallet concept rather than create another ledger.
- Stop calling legacy `Stock::recalculateMarketData()` from the canonical Stock Book because it only mutates legacy decimal pricing.

- [ ] **Step 1: Write failing source/feature tests**

Assert Stock Book has no legacy fiat-wallet copy, no Rial valuation labels, contains Asset Wallet link, and renders canonical Gol/Bahar values even when legacy decimal values disagree.

- [ ] **Step 2: Verify RED**

Run: `php artisan test tests/Feature/Stock/StockBookCanonicalUxTest.php`
Expected: FAIL against current dashboard.

- [ ] **Step 3: Implement minimal UX/controller changes**

Remove legacy wallet data dependency from `book()`. Keep secondary market visibly disabled. Replace generic buy/sell hero copy with primary-offering/ownership wording appropriate to the current rollout state.

- [ ] **Step 4: Verify GREEN**

Run Stock Book, Asset Wallet and Holding tests.

- [ ] **Step 5: Commit**

Commit message: `feat(stock): unify user holdings with Najm Bahar assets`

### Task 6: Legacy Stock wallet dependency audit and safety contract

**Files:**
- Create: `docs/economy/STOCK_LEGACY_WALLET_RETIREMENT_STATUS.fa.md`
- Test: `tests/Feature/Stock/LegacyStockWalletRetirementTest.php`

**Interfaces:**
- Legacy wallet tables/models remain present for historical compatibility.
- New canonical Stock/Asset Wallet user flows must not write fiat balances to them.
- External capital settlement remains isolated from both Stock legacy wallet and Najm Bahar money balances.

- [ ] **Step 1: Write failing safety contract**

Assert canonical user-facing Stock Book does not depend on `WalletService`; Asset Wallet does not touch legacy wallet; existing external-capital settlement tests remain the authority for no-money-wallet mutation.

- [ ] **Step 2: Verify RED where a legacy dependency still exists**

Run the new test and observe the expected dependency failure before removal.

- [ ] **Step 3: Remove only user-flow dependencies and document remaining backend references**

Do not delete tables/models/services. Record each remaining legacy dependency and why it is retained.

- [ ] **Step 4: Verify GREEN**

Run targeted Stock and Najm Bahar suites.

- [ ] **Step 5: Commit**

Commit message: `docs(stock): stage legacy fiat wallet retirement`

### Task 7: Regression, responsive validation, and local UAT checkpoint

**Files:**
- Update design/status docs only if validation uncovers implementation facts that need recording.

- [ ] **Step 1: Run Stock regression**

Run all `tests/Feature/Stock` tests.

- [ ] **Step 2: Run Najm Bahar regression**

Run all `tests/Feature/NajmBahar` tests.

- [ ] **Step 3: Run full project validation through the repository's established CI workflow**

Require fresh green Full Validation for the final head; do not reuse an older green run.

- [ ] **Step 4: Run responsive contract validation**

Require fresh green responsive validation for the final head.

- [ ] **Step 5: Confirm rollout remains fail-closed**

Verify external capital disabled by default, provider credentials/selectors unavailable by default, readiness attestations false, and secondary market disabled.

- [ ] **Step 6: Publish checkpoint for local UAT**

Provide the user the final commit SHA and `git pull --ff-only origin agent/economic-system-current-integration`, then UAT `/admin/stock/create`, `/stock-book`, and `/najm-bahar/assets` with controlled test data.