# برنامه تکمیل Stock × Najm Bahar × External Capital

## وضعیت سند

- branch اجرا: `agent/economic-system-current-integration`
- هدف: تکمیل مرز سرمایه خارجی بدون ایجاد سیستم پولی دوم و بدون نقض نجم بهار
- ادغام به `main`: **ممنوع تا تصمیم صریح مدیرکل**
- فعال‌سازی External Capital: **فعلاً ممنوع / fail-closed**
- فعال‌سازی Secondary Market: **فعلاً ممنوع / fail-closed**
- آخرین checkpoint کدیِ Full Validation سبز پیش از این reconcile سند: `7cbad63fccdc1a7610b0368b63ba23ef704bd3b7`
- Full Validation متناظر: `#1835` — success
- Responsive Contract Validation متناظر: `#201` — success

این سند ادامه مستقیم handoffهای زیر است:

- `ECONOMIC_SYSTEM_CURRENT_INTEGRATION_STATUS.fa.md`
- `STOCK_CANONICAL_RECONCILIATION_HANDOFF.fa.md`
- `EXTERNAL_CAPITAL_PRODUCTION_UAT_RUNBOOK.fa.md`

## Invariantهای غیرقابل نقض

1. قیمت‌گذاری canonical سهام بر حسب integer Gol باقی می‌ماند.
2. settlement خارجی فقط برای عرضه اولیه/خزانه‌ای خود EarthCoop مجاز است.
3. IRR/USD هرگز به balance نجم بهار وارد نمی‌شود و هیچ Bahar جدیدی خلق نمی‌کند.
4. بازار ثانویه و دارایی/پروژه‌های دیگر فقط با Active Bahar تسویه می‌شوند.
5. Money Ledger و Asset Ledger مستقل‌اند و settlement باید مرز اتمیک/قابل reconciliation داشته باشد.
6. refund/reversal تاریخچه را بازنویسی نمی‌کند؛ event جدید append-only ایجاد می‌شود.
7. هر مسیر ناقص production باید fail-closed بماند.
8. مبلغ fiat ارسالی مرورگر هرگز authority تسویه نیست؛ amount معتبر فقط از quote و Payment Intent سمت سرور می‌آید.

## Batch 1 — Quote Authority & Freshness

وضعیت: **پیاده‌سازی و validation شده در کد؛ rollout واقعی وابسته به UAT provider است**

- allowlist منبع نرخ authoritative اضافه شده است.
- allowlist پیش‌فرض خالی است؛ production بدون پیکربندی صریح fail-closed است.
- quote دارای سقف عمر است.
- timestamp بیش از tolerance مجاز در آینده رد می‌شود.
- deterministic integer quote و snapshot حفظ شده است.
- amount نهایی از Gol canonical و quote ذخیره‌شده در سمت سرور مشتق می‌شود.

باقی‌مانده عملیاتی: UAT واقعی feed و ثبت evidence طبق Runbook.

## Batch 2 — Payment Provider Identity Integrity

وضعیت: **پیاده‌سازی و validation شده**

- provider یک ExternalPaymentIntent بعد از bind شدن قابل تعویض نیست.
- replay همان intent key با provider متفاوت conflict است.
- pending transition و reconciliation نمی‌توانند provider را silently عوض کنند.
- intent بدون provider می‌تواند در اولین تعامل معتبر provider را bind کند؛ پس از آن ثابت است.
- callback به Payment Intent مشخص با `intent_key` متصل می‌شود.
- Authority callback باید با provider intent ذخیره‌شده تطبیق داشته باشد.

## Batch 3 — Explicit Refund / Reversal Lifecycle

وضعیت: **domain lifecycle پیاده‌سازی و validation شده؛ GameDay واقعی باقی مانده است**

- `refunded` و `reversed` state/eventهای صریح خارجی هستند.
- فقط Payment Intent تأییدشده می‌تواند refund/reversal شود.
- در این مرحله فقط full refund/full reversal مجاز است؛ partial refund عمداً fail-closed است.
- refund/reversal پس از asset settlement ممنوع است و نیازمند مسیر صریح asset reversal می‌ماند.
- allocationهای تسویه‌نشده مرتبط cancel و money-state آن‌ها صریح ثبت می‌شود.
- reconciliation history append-only باقی می‌ماند.

باقی‌مانده عملیاتی: Refund/Reversal GameDay با provider واقعی و ثبت evidence.

## Batch 4 — Authoritative Rate Adapter

وضعیت: **adapter واقعی Servix Gold 24K پیاده‌سازی و تست شده؛ UAT production هنوز انجام نشده است**

پیاده‌سازی فعلی:

- adapter: `ServixGold24AuthoritativeRateProvider`
- source identifier: `servix:gold24:irr:v1`
- rail فعلی: IRR only؛ سایر currencyها fail-closed
- نرخ به‌صورت integer numerator/denominator وارد `FiatQuoteSnapshot` می‌شود.
- timestamp provider الزامی است.
- payload باید semantics مستقیم طلای 24K در IRR/RLS مورد انتظار را داشته باشد.
- timeout و provider failure fail-closed هستند.
- API key و base URL اکنون بخشی از readiness configuration contract نیز هستند.

باقی‌مانده:

- UAT با credential واقعی؛
- اجرای سناریوهای outage/stale/future/invalid طبق Runbook؛
- اگر در UAT عملیاتی نیاز اثبات شد، retry/circuit-breaker policy مستقل اضافه شود؛ در وضعیت فعلی failure به‌صورت fail-closed مدیریت می‌شود و retry خودکار به‌عنوان رفتار اثبات‌نشده اعلام نمی‌شود.

## Batch 5 — Real External Payment Provider Adapter

وضعیت: **ZarinPal adapter و callback lifecycle پیاده‌سازی و تست شده؛ live UAT و عملیات production باقی مانده است**

پیاده‌سازی فعلی:

- provider: `ZarinpalExternalPaymentProvider`
- IRR only؛ USD روی این provider fail-closed است.
- create intent و provider Authority binding وجود دارد.
- callback URL برای هر Payment Intent با `intent_key` همان intent ساخته می‌شود.
- callback عمومی canonical: `/stock/external-payment/callback`
- checkout کاربر authenticated است؛ callback provider به session کاربر وابسته نیست.
- callback Authority با provider intent canonical مقایسه می‌شود.
- callback موفق فقط پس از server-to-server verify با amount ذخیره‌شده در Payment Intent تأیید می‌شود.
- browser/callback amount authority نیست.
- cancellation به event صریح `payment_cancelled / cancelled` تبدیل می‌شود و rollback ناخواسته نمی‌شود.
- cancellation هیچ Bidی را active نمی‌کند.
- event/reconciliation idempotency در domain boundary وجود دارد.
- sensitive provider payload redaction در domain وجود دارد.
- callback پایه باید HTTPS و روی path canonical باشد و نباید از قبل `intent`/`intent_key` داشته باشد.
- merchant/base/gateway/callback/description اکنون در readiness configuration contract کنترل می‌شوند.

باقی‌مانده:

- live UAT با merchant واقعی؛
- success/cancel/replay/tamper GameDay روی محیط UAT؛
- refund/reversal provider-operational exercise؛
- tooling اپراتوری تکمیلی برای reconciliation در صورت نیاز UAT.

نکته: این callback مدل «اعتماد به داده callback» نیست؛ confirmation تنها بعد از verify سمت سرور با ZarinPal انجام می‌شود.

## Batch 6 — EarthCoop Primary Offering Policy

وضعیت: **policy نرم‌افزاری پیاده‌سازی و regression-test شده؛ UAT/حقوقی حوزه هدف باقی مانده است**

بسته شده در کد:

- issuer = EarthCoop
- market = primary
- source = treasury
- سقف allocation از طریق basis points قابل تنظیم است؛ مقدار canonical فعلی 1000 bps = 10%
- عرضه نمی‌تواند از treasury available shares بیشتر شود.
- open offeringها نمی‌توانند cap را oversubscribe کنند.
- allocationهای settled قبلی cap را مصرف می‌کنند.
- policy version و disclosure version بخشی از evidence هستند.

باقی‌مانده خارج از صرف کد:

- validation حقوقی/jurisdiction در حوزه عملیاتی عرضه؛
- disclosure نهایی قابل ارائه به خریدار؛
- UAT فرآیند واقعی عرضه.

## Batch 7 — Feature Flags & Readiness Gate

وضعیت: **پیاده‌سازی، hardening و Full Validation شده؛ rollout همچنان ممنوع است**

ReadinessGate اکنون علاوه بر flagها و attestations، configuration واقعی adapterهای production را نیز کنترل می‌کند.

برای Servix واقعی:

- API key الزامی؛
- base URL معتبر HTTPS الزامی.

برای ZarinPal واقعی:

- merchant ID الزامی؛
- base URL، gateway URL و callback URL معتبر HTTPS؛
- callback path دقیقاً `/stock/external-payment/callback`؛
- callback پایه بدون `intent`/`intent_key`؛
- description غیرخالی.

Secretها وارد readiness evidence نمی‌شوند.

External Capital فقط وقتی می‌تواند ready شود که همگی سبز باشند:

1. feature flag؛
2. authoritative provider معتبر و allowlisted؛
3. configuration واقعی rate provider؛
4. payment provider معتبر؛
5. configuration واقعی payment provider؛
6. primary offering configuration؛
7. Rate Provider UAT؛
8. Payment Provider UAT؛
9. Refund/Reversal GameDay؛
10. Offering Policy validation؛
11. Stock regression؛
12. Najm Bahar regression؛
13. Full Validation؛
14. تأیید صریح مدیرکل برای rollout.

Secondary Market مستقل از این مسیر و تا UAT/تصمیم صریح باید خاموش بماند.

## Validation Contract

برای هر batch:

- ابتدا contract test RED؛
- سپس production implementation حداقلی؛
- `tests/Feature/Stock`؛
- تست‌های مرتبط Najm Bahar؛
- Full Validation؛
- ثبت نتیجه و SHA.

هیچ موفقیتی صرفاً از روی inspection کد «سبز» اعلام نمی‌شود؛ نتیجه CI یا اجرای تست واقعی باید ثبت شود.

### Evidence ثبت‌شده برای checkpoint کدی فعلی

روی SHA:

`7cbad63fccdc1a7610b0368b63ba23ef704bd3b7`

نتایج GitHub Actions:

- Responsive Contract Validation `#201`: **success**
- Integration Full Validation `#1835`: **success**
- در Full Validation همه gateهای migration/boot، Group Chat، Admin/Identity، Najm Hoda، Governance، Najm Bahar، Stock، JavaScript و Full Project PHPUnit سبز شدند.

## نقطه ادامه پس از این checkpoint

مرحله بعد **روشن‌کردن feature flag production نیست**.

ترتیب درست ادامه:

1. محیط UAT و secretهای واقعی Servix/ZarinPal آماده شود.
2. Runbook `EXTERNAL_CAPITAL_PRODUCTION_UAT_RUNBOOK.fa.md` مرحله‌به‌مرحله اجرا شود.
3. Rate Provider UAT evidence ثبت شود.
4. ZarinPal success/cancel/replay/tamper UAT ثبت شود.
5. Refund/Reversal GameDay اجرا شود.
6. disclosure و الزامات حقوقی عرضه در jurisdiction هدف بررسی شود.
7. regressions و Full Validation مجدداً روی checkpoint UAT اجرا شوند.
8. فقط در پایان، تصمیم rollout و `STOCK_EXTERNAL_FOUNDER_ROLLOUT_APPROVED` با تصمیم صریح مدیرکل تعیین شود.

تا آن زمان External Capital و Secondary Market باید fail-closed باقی بمانند.
