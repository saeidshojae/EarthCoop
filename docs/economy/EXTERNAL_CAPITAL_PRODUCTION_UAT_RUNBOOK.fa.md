# Runbook عملیاتی UAT و Production برای External Capital سهام EarthCoop

تاریخ: 2026-08-30

## 1. دامنه و اصل حاکم

این Runbook فقط برای مسیر **تأمین سرمایه خارجی عرضه اولیه/خزانه‌ای سهام خود EarthCoop** است.

این مسیر به هیچ عنوان یک سیستم پولی دوم نیست و نباید به چنین سیستمی تبدیل شود:

- قیمت canonical سهم بر حسب **Gol / Bahar** است.
- IRR فقط quote و rail تسویه خارجی است.
- پول ریالی وارد موجودی Najm Bahar نمی‌شود.
- پرداخت خارجی Bahar جدید ایجاد نمی‌کند.
- Stock wallet نباید balance پولی مستقل ایجاد کند.
- بازار ثانویه و سهام/پروژه‌های دیگر فقط با **Active Bahar** تسویه می‌شوند.
- External Capital تا پایان UAT و تأیید صریح مدیرکل باید fail-closed باقی بماند.

## 2. مسیرهای canonical

### Checkout کاربر

`POST /auctions/{auction}/external-checkout`

- نیازمند authentication است.
- ورودی معتبر کاربر فقط شامل قیمت پیشنهادی بر حسب Gol و تعداد سهم است.
- مبلغ fiat ارسالی مرورگر مرجع نیست و نباید مبنای settlement قرار گیرد.
- مبلغ IRR در سمت سرور از Gol total و quote معتبر و زمان‌دار محاسبه می‌شود.

### Callback عمومی PSP

`GET /stock/external-payment/callback`

- عمداً بدون authentication کاربر است تا PSP بتواند callback بزند.
- ZarinPal هنگام ایجاد Payment Intent باید callback را با `intent` همان Payment Intent تولید کند.
- callback پایه در تنظیمات نباید از قبل `intent` یا `intent_key` داشته باشد؛ adapter آن را برای هر intent اضافه می‌کند.

نمونه شکل نهایی:

`https://<host>/stock/external-payment/callback?intent=<url-encoded-intent-key>`

## 3. متغیرهای پیکربندی

### Feature / currency

```env
STOCK_EXTERNAL_CAPITAL_ENABLED=false
STOCK_EXTERNAL_ENABLED_CURRENCIES=
STOCK_EXTERNAL_RATE_PROVIDER=unavailable
STOCK_EXTERNAL_PAYMENT_PROVIDER=unavailable
STOCK_EXTERNAL_QUOTE_SOURCES=
STOCK_EXTERNAL_QUOTE_MAX_AGE_SECONDS=300
STOCK_EXTERNAL_QUOTE_FUTURE_TOLERANCE_SECONDS=30
```

حالت امن پیش‌فرض همین وضعیت خاموش است.

برای UAT ریالی، پس از آماده‌شدن credentials و قبل از rollout عمومی:

```env
STOCK_EXTERNAL_ENABLED_CURRENCIES=IRR
STOCK_EXTERNAL_RATE_PROVIDER=servix_gold24
STOCK_EXTERNAL_PAYMENT_PROVIDER=zarinpal
STOCK_EXTERNAL_QUOTE_SOURCES=servix:gold24:irr:v1
```

### Servix Gold 24K

```env
STOCK_SERVIX_BASE_URL=https://servix.cc/api/v1
STOCK_SERVIX_API_KEY=<secret>
STOCK_SERVIX_TIMEOUT_SECONDS=8
```

Contract:

- API key خالی ممنوع است.
- base URL باید HTTPS باشد.
- source canonical باید `servix:gold24:irr:v1` باشد.
- feed مورد قبول فقط قیمت مستقیم طلای 24K با semantics مورد انتظار adapter است.
- quote stale، future خارج از tolerance، malformed یا provider outage باید fail-closed شود.

### ZarinPal

```env
STOCK_ZARINPAL_BASE_URL=https://api.zarinpal.com/pg/v4
STOCK_ZARINPAL_GATEWAY_URL=https://www.zarinpal.com/pg/StartPay
STOCK_ZARINPAL_MERCHANT_ID=<secret>
STOCK_ZARINPAL_CALLBACK_URL=https://<earthcoop-host>/stock/external-payment/callback
STOCK_ZARINPAL_DESCRIPTION=EarthCoop primary treasury share purchase
STOCK_ZARINPAL_TIMEOUT_SECONDS=8
```

Contract:

- merchant ID خالی ممنوع است.
- base/gateway/callback باید HTTPS باشند.
- path callback باید دقیقاً `/stock/external-payment/callback` باشد.
- callback پایه نباید `intent` یا `intent_key` از قبل داشته باشد.
- secretها نباید در readiness evidence، log عمومی یا provider payload ذخیره شوند.

## 4. Readiness attestations

تمام موارد زیر در شروع باید `false` بمانند:

```env
STOCK_EXTERNAL_RATE_PROVIDER_UAT_PASSED=false
STOCK_EXTERNAL_PAYMENT_PROVIDER_UAT_PASSED=false
STOCK_EXTERNAL_REFUND_REVERSAL_GAMEDAY_PASSED=false
STOCK_EXTERNAL_OFFERING_POLICY_VALIDATED=false
STOCK_EXTERNAL_STOCK_REGRESSION_PASSED=false
STOCK_EXTERNAL_NAJM_BAHAR_REGRESSION_PASSED=false
STOCK_EXTERNAL_FULL_VALIDATION_PASSED=false
STOCK_EXTERNAL_FOUNDER_ROLLOUT_APPROVED=false
```

ReadinessGate فقط زمانی `ready=true` می‌شود که همه contractها و attestations لازم سبز باشند.

Blockerهای مهم شامل این موارد است:

- `external_capital_disabled`
- `authoritative_rate_provider_unavailable`
- `authoritative_rate_provider_configuration_invalid`
- `external_payment_provider_unavailable`
- `external_payment_provider_configuration_invalid`
- `primary_offering_configuration_invalid`
- `rate_provider_uat_missing`
- `payment_provider_uat_missing`
- `refund_reversal_gameday_missing`
- `offering_policy_validation_missing`
- `stock_regression_missing`
- `najm_bahar_regression_missing`
- `full_validation_missing`
- `founder_rollout_approval_missing`

وجود حتی یک blocker به معنی ممنوع بودن checkout واقعی است.

## 5. ترتیب UAT

### UAT-0 — بدون پول واقعی

1. `STOCK_EXTERNAL_CAPITAL_ENABLED=false` باقی بماند.
2. routeها، config و readiness report بررسی شوند.
3. Stock tests و Full Validation سبز باشند.
4. تأیید شود Secondary Market همچنان خاموش است.

### UAT-1 — Rate provider

1. credential واقعی Servix فقط در secret store محیط UAT قرار گیرد.
2. یک quote برای Gol مشخص درخواست شود.
3. source، currency، numerator/denominator و timestamp ثبت و با semantics provider تطبیق داده شود.
4. سناریوهای stale/future/invalid/outage اجرا شوند و همگی fail-closed باشند.
5. تنها بعد از ثبت evidence، `STOCK_EXTERNAL_RATE_PROVIDER_UAT_PASSED=true` شود.

### UAT-2 — Payment intent / redirect

1. credential ZarinPal در UAT تنظیم شود.
2. یک عرضه اولیه خزانه EarthCoop با `external_irr` استفاده شود.
3. checkout با price Gol و quantity آغاز شود.
4. بررسی شود مبلغ IRR از quote server-side آمده و ورودی fiat مرورگر نادیده گرفته شده است.
5. بررسی شود request به ZarinPal callback اختصاصی همان intent را دارد.
6. بررسی شود Bid پیش از پرداخت `awaiting_funding` است.

### UAT-3 — Callback موفق

1. payment در gateway موفق شود.
2. callback بدون session کاربر دریافت شود.
3. Authority callback با `provider_intent_id` ذخیره‌شده برابر باشد.
4. verify با **amount ذخیره‌شده در Payment Intent** انجام شود، نه amount callback/browser.
5. فقط verify معتبر intent را `confirmed` کند.
6. فقط intent تأییدشده Bid مربوط به خودش را `active` کند.
7. provider payment reference ثبت شود.

### UAT-4 — Cancellation

1. پرداخت توسط کاربر لغو شود یا callback non-OK معتبر دریافت شود.
2. Payment Intent باید `cancelled` شود.
3. Bid باید `awaiting_funding` بماند و هرگز `active` نشود.
4. برای cancellation نباید verify-confirmation جعلی ساخته شود.
5. cancellation نباید به علت exception بعدی rollback شود.

### UAT-5 — Replay / idempotency

1. callback موفق همان payment دوباره ارسال شود.
2. هیچ Bid دوم، settlement دوم یا asset allocation مضاعف ایجاد نشود.
3. callback cancellation تکراری نیز نباید state مالی جدید یا Bid فعال بسازد.
4. callback با intent key درست ولی Authority غلط باید fail-closed شود.
5. callback با intent key ناشناخته باید fail-closed شود.

### UAT-6 — Tamper tests

برای یک checkout معتبر عمداً موارد زیر از browser/callback دستکاری شوند:

- amount fiat
- currency
- Authority
- intent key
- auction target

هیچ‌یک نباید بتوانند amount/currency/reference canonical ذخیره‌شده را تغییر دهند یا Bid دیگری را فعال کنند.

### UAT-7 — Refund / reversal GameDay

1. refund/reversal فقط برای intent تأییدشده تست شود.
2. amount و currency باید با payment اصلی exact match داشته باشند.
3. event جدید append-only ثبت شود.
4. history قبلی بازنویسی یا حذف نشود.
5. پس از asset settlement، reversal ضمنی ممنوع باشد و explicit asset reversal لازم بماند.
6. پس از ثبت evidence، `STOCK_EXTERNAL_REFUND_REVERSAL_GAMEDAY_PASSED=true` شود.

## 6. Validation gates قبل از rollout

به‌ترتیب evidence واقعی ثبت شود و فقط همان flag مربوط روشن شود:

1. Rate Provider UAT
2. Payment Provider UAT
3. Refund/Reversal GameDay
4. Primary Offering Policy validation
5. Stock regression
6. Najm Bahar regression
7. Full Project Validation
8. تأیید صریح مدیرکل EarthCoop

`STOCK_EXTERNAL_FOUNDER_ROLLOUT_APPROVED=true` آخرین کلید است، نه اولین کلید.

## 7. ترتیب فعال‌سازی کنترل‌شده

حتی بعد از سبزشدن تمام evidenceها:

1. ابتدا محیط UAT/staging.
2. فقط IRR فعال شود؛ USD مستقل و fail-closed باقی بماند.
3. فقط یک عرضه اولیه/خزانه‌ای کنترل‌شده EarthCoop انتخاب شود.
4. transactionهای کم‌مقدار و قابل reconciliation اجرا شوند.
5. logهای Payment Intent، reconciliation و Bid بررسی شوند.
6. پس از تأیید نتایج، rollout محدود production انجام شود.

هیچ تغییر این Runbook به‌تنهایی مجوز روشن‌کردن feature flag production نیست.

## 8. Rollback / Kill switch

در هر anomaly:

```env
STOCK_EXTERNAL_CAPITAL_ENABLED=false
```

و در صورت نیاز:

```env
STOCK_EXTERNAL_ENABLED_CURRENCIES=
STOCK_EXTERNAL_PAYMENT_PROVIDER=unavailable
STOCK_EXTERNAL_RATE_PROVIDER=unavailable
```

سپس:

1. checkout جدید متوقف شود.
2. intentهای `pending` فهرست و reconcile شوند؛ حذف نشوند.
3. payment/referenceهای provider با ledger داخلی تطبیق داده شوند.
4. هیچ history یا reconciliation event پاک یا rewrite نشود.
5. asset allocationهای settle‌شده خودکار reverse نشوند.
6. root cause، affected intents و remediation ثبت شود.

## 9. چیزهایی که هرگز نباید در log/evidence قرار گیرند

- Servix API key
- ZarinPal merchant secret/credential
- access token / Authorization header
- card number / PAN / CVV/CVC
- password
- email/phone در provider payload مگر با ضرورت و سیاست مستقل

Readiness evidence باید فقط identifierها، statusها، currencyهای فعال، policy version و callback path canonical را نشان دهد.

## 10. وضعیت فعلی rollout

در زمان ثبت این Runbook:

- External Capital production rollout: **غیرفعال**
- Secondary Market rollout: **غیرفعال**
- IRR rail: در مرحله validation/UAT، نه مجاز برای rollout عمومی
- USD rail: **fail-closed / غیرفعال**
- merge به `main`: خارج از دامنه این branch و ممنوع تا تصمیم صریح مدیرکل
