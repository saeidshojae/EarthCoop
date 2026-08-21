# S6 — مرز امن تهیه پیش‌نویس توسط نجم هدا

این مرحله مسیر read-only نجم هدا را یک گام به سمت مباشرت کنترل‌شده توسعه می‌دهد، بدون اینکه اختیار ثبت رسمی یا انتشار خودکار ایجاد کند.

## قرارداد

1. مرورگر فقط route/resource hint می‌فرستد؛ Page Context توسط سرور دوباره resolve و authorize می‌شود.
2. درخواست تهیه پیش‌نویس فقط در صفحه معتبر دبیرخانه intercept می‌شود.
3. پاسخ اول صرفاً Preview است و هیچ رکوردی در دیتابیس ایجاد نمی‌کند.
4. payload ساخت‌یافته همان Preview برای حداکثر ۱۵ دقیقه و با کلید actor + conversation + office نگه‌داری می‌شود.
5. تنها فرمان صریح «ذخیره پیش‌نویس» اجازه persistence می‌دهد.
6. درست پیش از persistence، `SecretariatRecordPolicy::create` دوباره ارزیابی می‌شود.
7. نتیجه فقط `status=draft` دارد؛ submit، approval، registration، dispatch و publish خارج از این قابلیت‌اند.
8. «لغو» pending preview را حذف می‌کند و هیچ اثر دامنه‌ای باقی نمی‌گذارد.
9. title/body دلخواه browser page context هرگز به سند تبدیل نمی‌شود؛ محتوای سند فقط از فرمان صریح کاربر می‌آید.
10. همه flowهای غیرمرتبط نجم هدا باید بدون تغییر به مسیر موجود ادامه دهند.

## Runtime

API chat (`POST /api/najm-hoda/chat`) پس از احراز هویت و resolve مجدد Page Context، `NajmHodaSecretariatDraftAssistant` را قبل از سایر مسیرهای اجرایی فراخوانی می‌کند. اگر interceptor پاسخی ندهد، جریان قبلی نجم هدا بدون تغییر ادامه پیدا می‌کند.

## Gate مورد انتظار

- syntax controller/service/test
- `migrate:fresh` روی MySQL 8
- کل تست‌های S1-S6
- preview با zero persistence
- save فقط Draft پس از confirmation
- cancel با zero persistence
- authorization recheck در زمان save
- end-to-end API chat preview → confirmation → Draft
- browser payload non-leakage
- legacy `ExecutionBoundaryTest`
- 3 × 12-process registry numbering concurrency
- group authorization regressions
