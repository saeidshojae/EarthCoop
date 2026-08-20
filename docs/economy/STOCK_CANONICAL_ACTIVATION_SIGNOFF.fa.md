# Stock Canonical Activation Signoff

## وضعیت

این سند checkpoint فعال‌سازی مسیر canonical سهام است و تا زمانی که همه gateها سبز نشده‌اند، مجوز روشن‌کردن feature flag بازار ثانویه یا ادغام در `main` محسوب نمی‌شود.

## مسیرهای پیاده‌شده

- Active Bahar reservation / release / settlement / refund
- Gol-based canonical pricing
- Primary treasury canonical settlement
- External payment intent/reconciliation boundary (provider-neutral, runtime disabled)
- Secondary seller Holding reservation
- Secondary listing UX from user Stock wallet
- Secondary canonical bid + Active Bahar reservation
- Secondary seller-to-buyer Holding transfer and buyer-to-seller Active Bahar settlement
- Canonical auction scheduler dispatch
- Project primary payee-account mapping
- Founder Ops financial-risk visibility
- `stock:canonical-readiness --fail-on-blocker`

## UI موجود

- کیف سهام کاربر با امکان ورود به فرم عرضه ثانویه
- فرم ایجاد عرضه ثانویه با quantity، Gol price، auction type و duration
- صفحه canonical Auction با Order Book بر مبنای Gol
- فرم Bid با Active Bahar
- نمایش موجودی Active/available Active
- لغو Bid canonical و آزادسازی reservation پول
- لغو Listing توسط فروشنده در صورت نداشتن Bid فعال و آزادسازی reservation سهم
- self-bid guard

## Gateهای فعال‌سازی

1. migration chain باید روی دیتابیس خالی بدون خطا اجرا شود.
2. `tests/Feature/Stock` و `tests/Unit/Stock` باید سبز باشند.
3. full integration validation باید سبز باشد.
4. `php artisan stock:canonical-readiness --fail-on-blocker` باید exit code صفر بدهد.
5. orphan Active Bahar reservation = 0.
6. orphan seller Holding reservation = 0.
7. reconciliation_required = 0.
8. canonical Auction نباید legacy Bid داشته باشد.
9. seller باید حساب فعال Najm Bahar داشته باشد.
10. project primary Stock باید payee mapping معتبر داشته باشد.
11. External rail تا provider/rate-source واقعی خاموش می‌ماند.

## وضعیت checkpoint فعلی

- Draft validation PR: #73 — validation only, DO NOT MERGE.
- Production deploy روی PR اجرا نمی‌شود؛ deploy workflow فقط push به `main` را deploy می‌کند.
- نخستین Full Validation در مرحله migration failure گزارش کرده است؛ علت باید رفع و validation تکرار شود.
- `STOCK_SECONDARY_MARKET_ENABLED` تا عبور همه gateها false می‌ماند.
- `STOCK_EXTERNAL_CAPITAL_ENABLED` false می‌ماند.

## قاعده تصمیم

وجود backend و UI به‌تنهایی معادل production readiness نیست. بازار ثانویه فقط پس از migration + tests + readiness + smoke signoff فعال می‌شود.
