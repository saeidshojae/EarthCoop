# چک‌لیست بازطراحی چت گروه (Realtime + بدون رفرش + توسعه‌پذیر)

## اهداف کلیدی
- حذف `location.reload()` از عملیات اصلی چت.
- حفظ کامل سازگاری با نجم‌هدا (trigger/reply/action logging).
- پشتیبانی از شرایط قطع اینترنت خارجی با fallback داخلی.
- رسیدن به معماری استاندارد، ماژولار و مقیاس‌پذیر.

## اصول معماری
- API-First: همه عملیات چت خروجی JSON استاندارد داشته باشند.
- Event-Driven: تغییرات از طریق رویدادهای قابل broadcast منتشر شوند.
- Transport Abstraction: دو حالت `websocket` و `polling` با انتخاب `auto`.
- Progressive Rollout: هر قابلیت با feature-flag فعال/غیرفعال شود.
- Backward Compatibility: تا پایان مهاجرت، مسیر قدیمی پابرجا بماند.

## قیود مهم پروژه
- نجم‌هدا نباید بشکند:
  - پیام‌های جدید همچنان `MessageCreated` و listenerهای نجم‌هدا را فعال کنند.
  - policyهای گروهی نجم‌هدا (enabled/role/action policy) حفظ شوند.
- شبکه داخلی/اینترنت محدود:
  - چت باید بدون سرویس خارجی هم کار کند.
  - اگر websocket در دسترس نبود، polling داخلی فعال بماند.

## فاز 0 (غیرمخرب - انجام شد/درحال انجام)
- [x] بکاپ کامل فایل‌های چت.
- [x] ایجاد `config/group-chat.php` برای flags و fallback.
- [x] تزریق `window.chatConfig` در `groups/chat.blade.php`.
- [x] قابل‌تنظیم کردن polling interval از config.
- [ ] افزودن مستندسازی env در `.env.example` (انجام شد).

## فاز 1 (پیام و واکنش بدون رفرش)
- [ ] حذف همه `location.reload()` مربوط به پیام/واکنش.
- [ ] یک renderer واحد برای append/patch پیام.
- [ ] normalize پاسخ API پیام/واکنش در یک schema.
- [ ] پایداری scroll و cursor بعد از update DOM.
- [ ] تست رگرسیون نجم‌هدا در پاسخ خودکار به پیام.

## فاز 2 (Broadcast داخلی پیام)
- [ ] تبدیل event پیام به broadcast قابل مصرف کلاینت.
- [ ] ایجاد channel خصوصی گروه با policy عضویت.
- [ ] اتصال listener کلاینت با `Echo` در صورت دسترسی.
- [ ] fallback خودکار به polling در خطای websocket.
- [ ] dedup پیام‌ها (با `id`) هنگام دریافت همزمان polling+socket.

## فاز 3 (پست/کامنت بدون رفرش)
- [ ] store/update/delete پست فقط JSON برگرداند.
- [ ] حذف مسیرهای GET مخرب (delete) و جایگزینی با روش امن.
- [ ] patch موضعی DOM برای پست/کامنت.
- [ ] broadcast ایجاد/ویرایش/حذف پست و کامنت.

## فاز 4 (نظرسنجی و رأی بدون رفرش)
- [ ] `PollController` برای store/update/delete خروجی JSON استاندارد.
- [ ] رأی‌دادن و نمایش نتایج به‌صورت live.
- [ ] broadcast رویدادهای poll create/vote/update.
- [ ] سازگاری با delegation و قوانین رأی‌گیری موجود.

## فاز 5 (بهینه‌سازی مقیاس)
- [ ] cursor pagination واقعی برای history.
- [ ] virtualized rendering برای پیام‌های زیاد.
- [ ] debounce/throttle برای ورودی‌ها و جستجو.
- [ ] کاهش logهای debug در production.
- [ ] idempotency key برای جلوگیری از submit تکراری.

## فاز 6 (تست و پذیرش)
- [ ] تست دستی سناریوهای چندکاربره همزمان.
- [ ] تست قطعی websocket و صحت fallback polling.
- [ ] تست نجم‌هدا: پاسخ، اکشن‌گیر، لاگ و policy.
- [ ] بررسی امنیت endpointها و مجوزها.
- [ ] چک‌لیست release + rollback plan.

## معیار پذیرش
- کاربر برای عملیات روزمره چت نیازی به refresh کامل نداشته باشد.
- سایر کاربران تغییرات را در لحظه (یا با تأخیر polling محدود) ببینند.
- در نبود سرویس خارجی websocket، چت پایدار باقی بماند.
- نجم‌هدا همان رفتار فعلی را حفظ کند یا بهتر شود.

