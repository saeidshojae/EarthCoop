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
- seller identity رسمی روی secondary Auction؛
- seller Holding reservation و جلوگیری از double-sell؛
- seller wallet UX از کیف سهام تا ایجاد listing؛
- listing idempotency با `listing_key` یکتا؛
- self-bid prevention روی secondary listing؛
- secondary cancel با release reservation؛
- Secondary close برای `single_winner`, `uniform_price`, `pay_as_bid`؛
- Active Bahar buyer -> seller به‌صورت اتمیک با Holding seller -> buyer؛
- treasury در settlement ثانویه دست‌نخورده می‌ماند؛
- bid price اصلی immutable می‌ماند و clearing price فقط در settlement metadata ثبت می‌شود؛
- scheduler `auctions:close` مسیر legacy/primary canonical/secondary canonical را dispatch می‌کند؛
- feature flag فقط ایجاد commitment جدید را می‌بندد؛ commitment موجود حتی بعد از خاموش‌شدن flag باید lifecycle خود را کامل کند؛
- Founder Ops financial findings برای blockerهای cutover؛
- `StockCanonicalReadinessService` برای گزارش launch readiness؛
- فرمان `stock:canonical-readiness --fail-on-blocker` برای release gate.

## مسیر قابل‌اعتماد فعلی

### Primary Treasury + Active Bahar

این ترکیب canonical close کامل دارد:

`issuer_type=earthcoop`

`+ market_type=primary`

`+ supply_source=treasury`

`+ settlement_channel=active_bahar`

`+ quote_unit=gol`

`+ base_price_gol > 0`

و حساب دریافت سرمایه EarthCoop در Najm Bahar باید تنظیم و فعال باشد:

`STOCK_EARTHCOOP_CAPITAL_ACCOUNT_NUMBER=<canonical account number>`

### Secondary Holder + Active Bahar

backend و UX اصلی اکنون ساخته شده‌اند:

`seller Holding -> seller reservation -> secondary Auction -> buyer Active Bahar reservation -> close -> buyer Bahar debit / seller Bahar credit + seller Holding debit / buyer Holding credit`

ولی activation عمومی هنوز عمداً خاموش است:

`STOCK_SECONDARY_MARKET_ENABLED=false`

علت باقی‌ماندن flag روی false دیگر «نبود seller-side» نیست؛ علت، نیاز به اجرای migration/test/UI/race validation و signoff روی محیط integration است.

## Blockerهای آگاهانه

### 1. External IRR/USD

External rail و reconciliation وجود دارند، اما production cutover آن عمداً خاموش است:

`STOCK_EXTERNAL_CAPITAL_ENABLED=false`

تا زمانی که provider adapter واقعی، trusted rate source، callback authentication، provider reconciliation runbook و operational/legal controls مشخص نشده باشند، UI canonical پرداخت خارجی فعال نمی‌شود.

### 2. Secondary activation

seller-side reservation/settlement و UX اصلی اکنون وجود دارند. فعال‌سازی عمومی فقط بعد از این gateها مجاز است:

- `migrate:fresh --seed` موفق؛
- Stock-specific tests موفق؛
- تست race/double-submit برای listing و bid؛
- تست two-buyer contention روی یک seller reservation؛
- تست scheduler روی expiry؛
- تست cancel بدون Bid و رد cancel با Bid فعال؛
- تست mobile/desktop کیف سهام، listing form و Auction canonical؛
- `stock:canonical-readiness --fail-on-blocker` برای scope فعال بدون blocker؛
- config/cache test برای `STOCK_SECONDARY_MARKET_ENABLED=true`.

### 3. Project primary payee mapping

Primary Active Bahar برای `issuer_type=project` هنوز destination account canonical ندارد. برای جلوگیری از واریز اشتباه به حساب سرمایه EarthCoop، این مسیر fail-closed است و `project_payee_mapping_missing` تولید می‌کند.

## Canonical close — Primary

`CanonicalAuctionCloseService`:

1. Auction را lock می‌کند؛
2. Gol pricing و settlement boundary را بررسی می‌کند؛
3. issuer را فعلاً فقط EarthCoop می‌پذیرد؛
4. وجود capital account را بررسی می‌کند؛
5. اگر active legacy/incomplete bid وجود داشته باشد کل close را رد می‌کند؛
6. Bidها را با `price_gol DESC`, سپس زمان و id مرتب می‌کند؛
7. winner plan را می‌سازد؛
8. رزرو بازندگان را release می‌کند؛
9. رزرو برندگان را به allocation/clearing price واقعی کاهش می‌دهد؛
10. هر allocation را فقط از `StockAtomicSettlementService` عبور می‌دهد؛
11. فقط بعد از موفقیت کامل Auction را `settled` می‌کند.

کل Active-Bahar primary close داخل transaction اجرا می‌شود.

## Canonical close — Secondary

`SecondaryAuctionCloseService`:

1. فقط `secondary + holder + active_bahar` را می‌پذیرد؛
2. seller identity و seller Holding reservation را الزام می‌کند؛
3. active legacy/incomplete bid را blocker می‌داند؛
4. Bidها را بر اساس `price_gol DESC`, سپس زمان و id مرتب می‌کند؛
5. بازندگان را release می‌کند؛
6. برای uniform-price reservation پولی برنده را به clearing price کاهش می‌دهد؛
7. `bid.price_gol` اصلی را تغییر نمی‌دهد؛
8. پول خریدار را به حساب Active Bahar فروشنده انتقال می‌دهد؛
9. Holding فروشنده را debit و Holding خریدار را credit می‌کند؛
10. seller reservation را consume می‌کند؛
11. مانده سهم فروش‌نرفته را release می‌کند؛
12. `Stock.available_shares` خزانه را تغییر نمی‌دهد؛
13. بدون Bid، کل seller reservation را آزاد و Auction را settle می‌کند.

## Seller Listing UX

از `holding_index` برای Stock دارای Gol valuation، مسیر «عرضه برای فروش» وجود دارد.

فرم listing:

- quantity را حداکثر به available Holding محدود می‌کند؛
- قیمت را با integer Gol می‌گیرد؛
- نوع Auction را انتخاب می‌کند؛
- duration محدود دارد؛
- `listing_key` UUID از زمان نمایش فرم تولید و روی retry حفظ می‌شود؛
- ایجاد listing هنگام feature flag خاموش fail-closed است؛
- ایجاد موفق بلافاصله seller Holding reservation می‌سازد؛
- فروشنده نمی‌تواند روی listing خودش Bid ثبت کند؛
- listing بدون Bid فعال قابل cancel است و reservation آزاد می‌شود.

## Readiness Audit

`StockCanonicalReadinessService` این موارد را blocker می‌داند:

- capital account لازم تنظیم/فعال نیست؛
- canonical auction settlement boundary نامعتبر دارد؛
- Gol pricing ناقص است؛
- legacy bid داخل canonical auction وجود دارد؛
- external auction وجود دارد ولی external cutover خاموش است؛
- secondary auction در زمان cutover flag خاموش است؛
- seller identity/share reservation ناقص یا ناسازگار است؛
- seller حساب فعال Najm Bahar ندارد؛
- project primary payee mapping وجود ندارد؛
- `reconciliation_required` وجود دارد؛
- Active Bahar stock-bid reservation یتیم وجود دارد؛
- seller share reservation یتیم وجود دارد.

Expired-running canonical auctions warning هستند و باید توسط scheduler بسته شوند.

## Founder Ops

`FounderStockRiskService` blockerهای سطح Auction را به `FounderFinancialRiskFinding` تبدیل می‌کند:

- critical -> P0؛
- high -> P1؛
- medium -> P2.

`reconciliation_required` همچنان P0 است.

## Legacy boundary

مسیر legacy حذف نشده تا migration کنترل‌شده باقی بماند، ولی canonical Auction حق عبور از Stock Wallet/legacy settlement را ندارد.

دفاع‌ها:

- canonical-aware route/view؛
- canonical Bid model guard؛
- CanonicalAwareAuctionService binding؛
- canonical close engineهای مجزا؛
- scheduler dispatcher؛
- readiness detection برای legacy bid contamination.

## قبل از ادغام نهایی

این شاخه نباید مستقیماً به `main` ادغام شود. قبل از تصمیم نهایی:

1. با آخرین main روی integration branch reconcile شود؛
2. `migrate:fresh --seed` اجرا شود؛
3. PHPUnit کل پروژه و Stock-specific tests اجرا شوند؛
4. `route:list` برای duplicate/overrideهای Auction/Holding بررسی شود؛
5. UI canonical روی desktop/mobile تست شود؛
6. race/retry tests برای listing, bid acceptance, reservation و close اجرا شوند؛
7. scheduler `auctions:close` با legacy/primary/secondary تست شود؛
8. config cache با env جدید تست شود؛
9. `stock:canonical-readiness --fail-on-blocker` روی scope فعال موفق شود؛
10. external تا تکمیل provider workstream خاموش بماند؛
11. secondary فقط پس از signoff integration از false به true تغییر کند.
