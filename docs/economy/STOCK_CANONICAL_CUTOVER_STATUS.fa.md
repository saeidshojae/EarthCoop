# وضعیت Canonical Cutover ماژول Stock

## هدف

این سند وضعیت اجرایی گذار Stock از مسیر legacy (`decimal/float + Stock Wallet`) به مسیر canonical (`integer Gol + Najm Bahar / external reconciliation + atomic Stock ownership`) را ثبت می‌کند.

## وضعیت فعلی

### آماده در backend و مسیر کاربر

- Settlement eligibility fail-closed؛
- Active Bahar reservation؛
- External payment intent/reconciliation بدون fiat wallet؛
- integer Gol pricing؛
- deterministic fiat quote snapshot؛
- atomic Stock/Holding allocation؛
- canonical Bid acceptance با رزرو Active Bahar قبل از پذیرش؛
- canonical cancellation با release رزرو؛
- canonical-aware UI برای Auctionهای Gol؛
- canonical order book بر اساس `price_gol`؛
- legacy Auctionها همچنان از view قدیمی استفاده می‌کنند؛
- legacy AuctionService برای Auctionهای canonical fail-closed است؛
- Primary Treasury + Active Bahar canonical close برای `single_winner`, `uniform_price`, `pay_as_bid`؛
- uniform-price با کاهش idempotent reservation به clearing price؛
- Founder Ops financial findings برای blockerهای cutover؛
- StockCanonicalReadinessService برای گزارش launch readiness.

## مسیر production-ready فعلی

در این شاخه فقط این ترکیب قابلیت عبور از canonical close را دارد:

`market_type=primary`

`+ supply_source=treasury`

`+ settlement_channel=active_bahar`

`+ quote_unit=gol`

`+ base_price_gol > 0`

و باید حساب دریافت سرمایه EarthCoop در Najm Bahar تنظیم و فعال باشد:

`STOCK_EARTHCOOP_CAPITAL_ACCOUNT_NUMBER=<canonical account number>`

اگر این حساب تنظیم نشده باشد، settlement fail-closed می‌شود.

## Blockerهای آگاهانه

### 1. External IRR/USD

External rail و reconciliation وجود دارند، اما production cutover آن عمداً خاموش است:

`STOCK_EXTERNAL_CAPITAL_ENABLED=false`

تا زمانی که provider adapter واقعی، trusted rate source، callback authentication، provider reconciliation runbook و operational/legal controls مشخص نشده باشند، UI canonical پرداخت خارجی آن را قابل انجام نشان نمی‌دهد.

### 2. Secondary market

دروازه اقتصادی Secondary قبلاً Active Bahar-only شده است؛ اما settlement واقعی فروشنده هنوز production-ready نیست، چون seller-side asset supply باید قبل از معامله reserve شود و هنگام settlement از Holding فروشنده به خریدار منتقل گردد.

بنابراین:

`STOCK_SECONDARY_MARKET_ENABLED=false`

باقی می‌ماند تا موارد زیر ساخته شوند:

- seller identity روی listing/auction؛
- seller Holding reservation؛
- جلوگیری از double-sell؛
- atomic seller Holding debit + buyer Holding credit؛
- Active Bahar buyer debit + seller credit؛
- cancel/expiry release برای seller supply reservation؛
- reconciliation و Founder Ops coverage.

## Canonical close

`CanonicalAuctionCloseService`:

1. Auction را lock می‌کند؛
2. وجود Gol pricing و settlement boundary را بررسی می‌کند؛
3. وجود capital account را بررسی می‌کند؛
4. اگر حتی یک active legacy/incomplete bid وجود داشته باشد کل close را رد می‌کند؛
5. Bidها را با `price_gol DESC`, سپس زمان و id مرتب می‌کند؛
6. winner plan را بر اساس نوع Auction می‌سازد؛
7. رزرو بازندگان را release می‌کند؛
8. رزرو برندگان را در allocation/clearing price به مقدار دقیق کاهش می‌دهد؛
9. هر allocation را فقط از `StockAtomicSettlementService` عبور می‌دهد؛
10. فقط بعد از موفقیت کامل Auction را `settled` می‌کند.

کل Active-Bahar close در یک DB transaction بیرونی اجرا می‌شود؛ بنابراین failure در allocationهای بعدی، mutationهای پول و دارایی قبلی همان close را نیز rollback می‌کند.

## Readiness Audit

`StockCanonicalReadinessService` این موارد را blocker می‌داند:

- capital account تنظیم/فعال نیست؛
- canonical auction settlement boundary نامعتبر دارد؛
- Gol pricing ناقص است؛
- legacy bid داخل canonical auction وجود دارد؛
- external auction وجود دارد ولی external cutover خاموش است؛
- secondary auction وجود دارد ولی seller-side cutover خاموش است؛
- `reconciliation_required` وجود دارد؛
- Active Bahar stock-bid reservation یتیم وجود دارد.

Expired-running canonical auctions به‌عنوان warning گزارش می‌شوند.

## Founder Ops

`FounderStockRiskService` blockerهای سطح Auction را به `FounderFinancialRiskFinding` تبدیل می‌کند. در نتیجه:

- critical -> P0؛
- high -> P1؛
- medium -> P2؛

از مسیر موجود Founder Attention دیده می‌شوند.

`reconciliation_required` critical/P0 است.

## Legacy boundary

مسیر legacy حذف نشده است تا migration کنترل‌شده باقی بماند. اما canonical Auction نباید بتواند از legacy wallet/settlement عبور کند.

دفاع‌ها:

- canonical-aware route/view؛
- canonical Bid model guard؛
- CanonicalAwareAuctionService binding؛
- CanonicalAuctionCloseService مستقل؛
- readiness detection برای legacy bid contamination.

## قبل از ادغام نهایی

این شاخه نباید مستقیماً به `main` ادغام شود. قبل از تصمیم نهایی باید:

1. روی integration branch با آخرین main reconcile شود؛
2. `migrate:fresh --seed` اجرا شود؛
3. PHPUnit کل پروژه و Stock-specific tests اجرا شوند؛
4. route:list برای duplicate/overrideهای Auction بررسی شود؛
5. UI حراج canonical روی desktop/mobile تست شود؛
6. race/retry tests برای Bid acceptance و Auction close اجرا شوند؛
7. config cache با env جدید تست شود؛
8. readiness audit باید برای scope فعال صفر blocker نشان دهد؛
9. external و secondary تا تکمیل workstream خودشان disabled بمانند.
