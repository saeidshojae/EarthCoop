# برنامه اجرایی Sprint 1 نجم هدا

## بازه
- 23 فوریه 2026 تا 8 مارس 2026

## هدف Sprint
- تکمیل مقدمات فاز 0: کنترل مرکزی، ایمنی پایه، و آماده سازی فنی.

## خروجی های اجباری
- Enforce `NAJM_HODA_ENABLED` در entrypointهای اصلی.
- مسیرهای حساس نجم هدا رفتار fail-safe داشته باشند.
- backlog فاز 0 به تسک های ریز، قابل تست، و قابل تحویل شکسته شود.

## تسک های دقیق

### Epic A: Feature Flag Enforcement
1. افزودن guard در `app/Http/Controllers/API/NajmHodaController.php`
2. افزودن guard در `app/Listeners/HandleNajmHodaGroupMessage.php`
3. افزودن guard در دستورات:
   - `app/Console/Commands/NajmHodaChat.php`
   - `app/Console/Commands/NajmHodaBootstrapGroups.php`
   - `app/Console/Commands/NajmHodaModerationSweep.php`
   - `app/Console/Commands/NajmHodaCreateAgent.php`
4. بررسی مسیرهای admin chat/control برای رفتار سازگار با `enabled`

### Epic B: Safety Defaults
1. تعریف پاسخ استاندارد disabled برای API
2. تعریف خروجی استاندارد disabled برای commandها
3. ثبت لاگ استاندارد برای blocked execution به علت disabled

### Epic C: Task Refinement برای ادامه فاز 0
1. شکستن فاز 0 به تسک های 0.5 تا 1.5 روزه
2. تعریف معیار پذیرش هر تسک
3. تعریف وابستگی های دقیق بین تسک ها
4. تعیین ترتیب اجرا برای Sprint 2

## معیار پذیرش
- وقتی `NAJM_HODA_ENABLED=false`:
  - API نجم هدا پاسخ disabled بدهد.
  - listener گروهی کار نکند.
  - commandهای نجم هدا اجرا را متوقف کنند.
- وقتی `NAJM_HODA_ENABLED=true`:
  - رفتار فعلی حفظ شود و regression ایجاد نشود.

## ریسک ها
- قطع ناخواسته برخی قابلیت های فعلی در زمان enforce.
- پراکندگی منطق فعال/غیرفعال در چند مسیر.

## کاهش ریسک
- Guard سبک و متمرکز.
- تست syntax و تست مسیرهای کلیدی بعد از هر تغییر.
- فعال سازی تدریجی روی مسیرهای پرریسک.

## وضعیت اجرا
- `in_progress`: Epic A
- `pending`: Epic B, Epic C
