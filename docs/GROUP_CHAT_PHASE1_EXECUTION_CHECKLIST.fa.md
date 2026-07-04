# چک‌لیست اجرایی فاز ۱ چت گروهی (پایداری ریلتایم)

## هدف
- فعال‌سازی ریلتایم واقعی با Redis + Soketi
- جلوگیری از تداخل websocket و polling
- رسیدن به SLA: تحویل رویداد بین دو کلاینت کمتر از 1 ثانیه

## تعریف Done برای فاز ۱
- رویداد پیام/ویرایش/حذف/ری‌اکشن روی کلاینت دوم بدون refresh نمایش داده شود.
- در حالت اتصال websocket، polling درخواست غیرضروری تولید نکند.
- در حالت قطع websocket، fallback به polling فعال و پایدار باشد.
- صف‌ها async باشند و مسیر ارسال پیام API بلوکه نشود.

## مرحله ۱: هم‌ترازسازی پیکربندی runtime
- [ ] تنظیم env برای broadcast روی پروتکل Pusher (Soketi):
  - `BROADCAST_CONNECTION=pusher`
  - `QUEUE_CONNECTION=redis`
  - `GROUP_CHAT_TRANSPORT=auto`
  - `GROUP_CHAT_FALLBACK_TO_POLLING=true`
- [ ] بررسی فایل‌های تنظیم:
  - [config/broadcasting.php](config/broadcasting.php)
  - [config/queue.php](config/queue.php)
  - [config/group-chat.php](config/group-chat.php)
- [ ] پاکسازی کش تنظیم:
  - `php artisan config:clear`
  - `php artisan cache:clear`

## مرحله ۲: تثبیت Bootstrap فرانت (websocket -> fallback)
- [ ] init realtime listeners یک‌بار اجرا شود.
- [ ] شروع polling فقط وقتی fallback لازم است انجام شود.
- [ ] health-check دوره‌ای برای فعال‌سازی polling در صورت قطع realtime وجود داشته باشد.
- [ ] state ریلتایم برای debug قابل مشاهده باشد.

## مرحله ۳: اجرای سرویس‌ها در محیط توسعه/استیجینگ
- [ ] اجرای Soketi
- [ ] اجرای queue worker
- [ ] اجرای اپلیکیشن

## مرحله ۴: سناریوهای پذیرش
- [ ] سناریو A: دو کلاینت، ارسال پیام متنی، نمایش زیر 1 ثانیه در کلاینت دوم
- [ ] سناریو B: edit/delete/reaction روی کلاینت اول، نمایش تغییر در کلاینت دوم
- [ ] سناریو C: قطع websocket، ادامه دریافت پیام با polling
- [ ] سناریو D: وصل مجدد websocket، کاهش polling و بازگشت به realtime

## مرحله ۵: معیارهای مشاهده‌پذیری
- [ ] بررسی `X-Chat-Server-Time-Ms` برای APIهای چت
- [ ] بررسی لاگ front برای وضعیت realtime (`window.getGroupRealtimeState()`)
- [ ] ثبت p95 latency برای دریافت رویداد بین دو کلاینت

## ریسک‌های مسدودکننده فاز ۱
- اگر Redis/Soketi در محیط فعال نباشد، ریلتایم واقعی محقق نمی‌شود.
- اگر queue همچنان sync باشد، زیر بار تاخیر API بالا می‌رود.
- اگر fallback همزمان با websocket فعال بماند، فشار بیهوده به API وارد می‌شود.

## خروجی‌های این فاز
- پایداری مسیر realtime + fallback
- چک‌لیست تایید قابل تکرار برای QA
- مبنا برای فاز ۲ (مقیاس‌پذیری دیتابیس و کاهش N+1)
