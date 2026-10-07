# ممیزی فعال‌سازی نیتیو بهار — ۷ اکتبر ۲۰۲۶

## نتیجه

Backend فعال‌سازی مشارکت از قبل بخش بزرگی از invariantهای مالی را دارد: eligibility سرورمحور، منبع participation-only، idempotency، مصرف atomic امتیاز، Dim→Active بدون minting و rollback در کمبود امتیاز/Dim.

برای Native mutation هنوز چهار شکاف اصلی وجود دارد:

1. POST فعلی snapshot شرایط دیده‌شده توسط کاربر را bind نمی‌کند.
2. رفتار legacy امتیاز نامضرب را floor می‌کند؛ برای تأیید مالی نیتیو باید مقدار مصرف/فعال‌سازی دقیق باشد.
3. نتیجهٔ مبهم transport endpoint اختصاصی reconciliation ندارد.
4. eligibility از `MonetaryPolicyService::current()` استفاده می‌کند؛ fallback legacy از `Setting::firstNajmBaharSettings()` عبور می‌کند که می‌تواند migration یک‌بارهٔ مبلغ‌های قدیمی را ذخیره کند. برای read-contract نیتیو بهتر است policy projection فعال‌سازی بدون write باشد.

## قرارداد موجود

`GET /api/v1/najm-bahar/activation/eligibility` داده‌های زیر را سرورمحور برمی‌گرداند:
- remaining_convertible_points
- conversion_ratio_points_per_gol
- max_convertible_points
- max_activation_gol
- dim_available_gol
- active_gol
- policy_version / policy_source

`POST /api/v1/najm-bahar/activation`:
- فقط `source=participation` و `points` صحیح مثبت را می‌پذیرد؛
- Idempotency-Key اجباری است؛
- client authority fields را رد می‌کند؛
- امتیاز را قفل و مصرف می‌کند؛
- مقدار activation را از ratio سرور محاسبه می‌کند؛
- Dim را به Active تبدیل می‌کند و total را mint نمی‌کند؛
- replay همان کلید اثر دوم ندارد؛
- same key/different payload conflict است.

## تصمیم Native

نسخهٔ اول Native Activation فقط تبدیل امتیاز مشارکت به Active Bahar در **حساب اصلی خود کاربر** است.

- کاربر مقدار فعال‌سازی را به‌صورت مقدار دقیق Bahar/Gol انتخاب می‌کند.
- intent authoritative در wire همچنان `points` است، اما points دقیقاً `activation_gol × ratio` است.
- Native strict contract فقط requested points را می‌پذیرد که مضرب ratio و دقیقاً قابل نگاشت به مقدار Gol تأییدشده باشد.
- هیچ rounding/floor پنهانی در strict native path وجود ندارد.
- legacy callers بدون `expected` رفتار فعلی را حفظ می‌کنند.
- eligibility/read path برای activation باید zero-write باشد؛ legacy settings فقط خوانده شوند، نه migrate/save.
- snapshot تأییدشده شامل ratio، policy identity/source، convertible points، max activation و Dim available است.
- POST همهٔ شرایط را دوباره محاسبه و در mismatch بدون mutation با `activation_terms_changed` رد می‌کند.
- نتیجهٔ نامعلوم با GET اختصاصی by-idempotency reconcile می‌شود.
- automatic retry مالی خاموش، no offline queue، no POST on restart، same-intent retry با همان key/body و عمر 23 ساعت.

## خارج از دامنه

- auto activation دوره‌ای؛
- activation از سرمایه‌گذاری/حق عضویت؛
- admin equal monthly activation؛
- group/legal entity activation؛
- تغییر policy؛
- production deploy و انتقال مالی واقعی.
