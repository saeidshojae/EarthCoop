# مرز مالی Founder Operations نجم هدا

این سند قرارداد اجرایی Founder Operations با دامنه‌های `Stock` و `Najm Bahar` را ثبت می‌کند و تابع معماری رسمی `docs/economy/STOCK_BAHAR_EXTERNAL_CAPITAL_RELEASE.md` است.

## اصل مادر

نجم هدا مدیر و ناظر یکپارچهٔ عملیات مالی است، نه یک مسیر پولی یا دفترکل موازی.

- خرید سهام Bahar ایجاد نمی‌کند.
- fiat بیرونی هرگز به موجودی Najm Bahar واریز نمی‌شود.
- Stock Wallet قدیمی کیف Bahar نیست و نباید به سیستم پولی دوم تبدیل شود.
- مالکیت دارایی در Stock/Holding می‌ماند؛ مالکیت پول در Najm Bahar یا payment/reconciliation بیرونی.

## مرز settlement

External IRR/USD فقط برای ترکیب زیر مجاز است:

`issuer=earthcoop + market=primary + supply_source=treasury`

بازار ثانویه و سهام پروژه/ناشر غیر EarthCoop فقط با Active Bahar تسویه می‌شوند. مقدار ناشناخته یا legacy باید fail-closed باشد.

## وضعیت roadmap که Founder Ops باید رعایت کند

Sliceهای موجود:

1. settlement boundary و classification صریح؛
2. SettlementGateway contract و registry.

پیش‌نیازهای باقیمانده قبل از canonical auction settlement:

- Slice 2B: Active Bahar reservation با integer Gol، idempotency، locking و ledger؛
- Slice 3: external payment intent/reconciliation بدون stored-value wallet؛
- Slice 4: مهاجرت quote/price از decimal/float به integer Gol؛
- Slice 5: atomic/idempotent money settlement + Stock/Holding allocation؛
- Slice 6: gate قطعی بازار ثانویه روی Active Bahar.

## اختیار نجم هدا در وضعیت فعلی

### مجاز با delegation صریح

- خلاصه وضعیت Auction؛
- بررسی SettlementEligibilityPolicy؛
- تشخیص classification ناشناخته/نامعتبر؛
- تشخیص secondary external settlement؛
- تشخیص expired-unsettled auction؛
- تشخیص quote legacy غیر Bahar/Gol؛
- بررسی scheduled transactionهای Najm Bahar برای overdue/retry/missing transaction؛
- بررسی وجود ledger برای transaction completed؛
- ثبت FounderFinancialRiskFinding و بالا آوردن P0/P1/P2.

### approval-required ولی مشروط به وجود canonical executor صحیح

وجود Founder Approval به‌تنهایی مجوز استفاده از legacy path نیست. عملیات زیر فقط زمانی قابل وصل‌شدن به Founder execution هستند که Slice مربوط و canonical domain service تکمیل شده باشد:

- create/settle auction؛
- transfer shares؛
- execute Najm Bahar transaction از Founder Ops؛
- approve financial project؛
- monetary policy changes.

### ممنوع

- rewrite/alter Stock ownership history؛
- rewrite/alter Najm Bahar ledger history؛
- credit fiat into Najm Bahar؛
- mint Bahar as a side effect of Stock purchase؛
- استفاده از Stock Wallet قدیمی به‌عنوان Bahar wallet؛
- اتصال AuctionService به immediate `TransactionService::transfer()` به‌جای Active Bahar reservation.

## قاعدهٔ fail-closed

اگر Founder Ops نتواند issuer، market، supply source، settlement channel، quote unit یا canonical executor را با قطعیت تعیین کند، فقط Finding/Attention تولید می‌کند و هیچ عملیات مالی اجرا نمی‌شود.
