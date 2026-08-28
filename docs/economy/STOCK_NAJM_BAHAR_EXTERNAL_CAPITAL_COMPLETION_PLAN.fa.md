# برنامه تکمیل Stock × Najm Bahar × External Capital

## وضعیت سند

- branch اجرا: `agent/economic-system-current-integration`
- هدف: تکمیل مرز سرمایه خارجی بدون ایجاد سیستم پولی دوم و بدون نقض نجم بهار
- ادغام به `main`: **ممنوع تا تصمیم صریح مدیرکل**
- فعال‌سازی External Capital: **فعلاً ممنوع**
- فعال‌سازی Secondary Market: **فعلاً ممنوع**

این سند ادامه مستقیم دو handoff زیر است:

- `ECONOMIC_SYSTEM_CURRENT_INTEGRATION_STATUS.fa.md`
- `STOCK_CANONICAL_RECONCILIATION_HANDOFF.fa.md`

## Invariantهای غیرقابل نقض

1. قیمت‌گذاری canonical سهام بر حسب integer Gol باقی می‌ماند.
2. settlement خارجی فقط برای عرضه اولیه/خزانه‌ای خود EarthCoop مجاز است.
3. IRR/USD هرگز به balance نجم بهار وارد نمی‌شود و هیچ Bahar جدیدی خلق نمی‌کند.
4. بازار ثانویه و دارایی/پروژه‌های دیگر فقط با Active Bahar تسویه می‌شوند.
5. Money Ledger و Asset Ledger مستقل‌اند و settlement باید مرز اتمیک/قابل reconciliation داشته باشد.
6. refund/reversal تاریخچه را بازنویسی نمی‌کند؛ event جدید append-only ایجاد می‌شود.
7. هر مسیر ناقص production باید fail-closed بماند.

## Batch 1 — Quote Authority & Freshness

وضعیت: **پیاده‌سازی شده، در انتظار/تحت validation**

- allowlist منبع نرخ authoritative اضافه شد.
- allowlist پیش‌فرض خالی است؛ در نتیجه production بدون پیکربندی صریح fail-closed است.
- quote دارای سقف عمر است.
- timestamp بیش از tolerance مجاز در آینده رد می‌شود.
- deterministic integer quote و snapshot موجود حفظ شد.

این batch هنوز «منبع نرخ واقعی production» ایجاد نکرده است؛ فقط مرز پذیرش نرخ را سخت کرده است.

## Batch 2 — Payment Provider Identity Integrity

وضعیت: **پیاده‌سازی شده، در انتظار/تحت validation**

- provider یک ExternalPaymentIntent بعد از bind شدن قابل تعویض نیست.
- replay همان intent key با provider متفاوت conflict است.
- pending transition و reconciliation نمی‌توانند provider را silently عوض کنند.
- intent بدون provider می‌تواند در اولین تعامل معتبر provider را bind کند؛ پس از آن ثابت است.

این batch هنوز PSP واقعی یا webhook adapter تولید نکرده است.

## Batch 3 — Explicit Refund / Reversal Lifecycle

وضعیت: **پیاده‌سازی شده، در انتظار/تحت validation**

- `refunded` و `reversed` به‌عنوان state/event صریح خارجی اضافه شده‌اند.
- فقط payment intent تأییدشده می‌تواند refund/reversal شود.
- در این مرحله فقط full refund/full reversal مجاز است؛ partial refund عمداً fail-closed است.
- refund/reversal پس از asset settlement ممنوع است و نیازمند مسیر صریح asset reversal خواهد بود.
- allocationهای تسویه‌نشده مرتبط cancel و money-state آن‌ها صریحاً ثبت می‌شود.
- reconciliation history همچنان append-only است.

## Batch 4 — Authoritative Rate Adapter

وضعیت: **انجام‌نشده**

قبل از فعال‌سازی واقعی باید یک port/adapter رسمی برای نرخ ایجاد شود که حداقل این شواهد را تولید کند:

- source identifier ثابت و allowlisted
- نرخ numerator/denominator integer
- timestamp معتبر
- currency pair / quote semantics روشن
- raw provider reference یا signed/auditable reference در صورت وجود
- timeout/retry/circuit-breaker policy
- تست stale/future/invalid/provider outage

هیچ feed واقعی تا زمان انتخاب و اعتبارسنجی منبع مناسب production authoritative تلقی نمی‌شود.

## Batch 5 — Real External Payment Provider Adapter

وضعیت: **انجام‌نشده**

نیازها:

- create payment/deposit intent
- provider intent ID binding
- webhook authenticity verification
- event idempotency
- amount/currency exact reconciliation
- failed/cancelled/confirmed/refunded/reversed events
- sensitive-payload redaction
- retry/out-of-order event handling
- provider outage behavior
- reconciliation tooling برای اپراتور

تا پیش از این batch، ExternalCapitalPaymentService فقط domain/lifecycle boundary است، نه PSP production.

## Batch 6 — EarthCoop Primary Offering Policy

وضعیت: **نیازمند تکمیل و UAT**

باید صریحاً بسته شود:

- issuer = EarthCoop
- market = primary
- source = treasury
- سقف عرضه اولیه مجاز طبق policy نهایی
- عرضه فقط از treasury reserve واقعی
- جلوگیری از oversubscription در سطح offering/auction
- disclosure/versioning
- eligibility حقوقی/jurisdiction adapter در صورت نیاز

## Batch 7 — Feature Flags & Readiness Gate

وضعیت: **نباید هنوز فعال شود**

External Capital فقط وقتی می‌تواند enable شود که همگی سبز باشند:

1. authoritative rate adapter واقعی و UAT شده؛
2. PSP adapter واقعی و webhook verification UAT شده؛
3. refund/reversal + reconciliation GameDay سبز؛
4. offering cap/supply invariant سبز؛
5. Stock regression سبز؛
6. Najm Bahar regression سبز؛
7. Full Validation سبز؛
8. تأیید صریح مدیرکل برای rollout.

Secondary Market نیز مستقل از این مسیر و تا UAT/تصمیم صریح باید خاموش بماند.

## Validation Contract

برای هر batch:

- ابتدا contract test RED
- سپس production implementation حداقلی
- `tests/Feature/Stock`
- تست‌های مرتبط Najm Bahar
- Full Validation
- ثبت نتیجه و SHA

هیچ موفقیتی صرفاً از روی inspection کد «سبز» اعلام نمی‌شود؛ نتیجه CI یا اجرای تست واقعی باید ثبت شود.

## نقطه ادامه پس از این checkpoint

پس از سبز شدن batchهای 1 تا 3، گام بعدی **ساخت portهای Rate Provider و Payment Provider** است، نه روشن‌کردن feature flag و نه ساخت UI فروش واقعی. Adapter واقعی باید بعد از انتخاب provider/feed قابل استفاده در jurisdiction عملیاتی EarthCoop تکمیل شود.
