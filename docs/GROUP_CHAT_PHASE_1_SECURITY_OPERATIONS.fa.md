# عملیات امنیتی فاز ۱ چت گروهی

این سند رویهٔ production برای کنترل فایل‌های چت گروهی را تعریف می‌کند. فایل‌های جدید با UUID روی disk خصوصی ذخیره و فقط پس از Policy check از controller تحویل می‌شوند.

## خط لولهٔ پیشنهادی بدافزار

1. upload ابتدا در مسیر قرنطینهٔ خصوصی ذخیره شود و وضعیت metadata آن `pending_scan` باشد.
2. job صف، فایل را با ClamAV یا سرویس اسکن سازمانی بررسی کند؛ تا قبل از نتیجه، download مجاز نباشد.
3. نتیجهٔ سالم با hash SHA-256، نسخهٔ scanner و زمان اسکن ثبت و فایل به مسیر نهایی منتقل شود.
4. فایل آلوده حذف منطقی، رویداد امنیتی و audit log ایجاد کند؛ متن خطا وجود یا جزئیات scanner را افشا نکند.
5. در صورت unavailable بودن scanner، production باید fail-closed باشد و retry با backoff انجام شود.

## تصویر و EXIF

- decode واقعی تصویر و بازتولید آن با encoder سمت سرور انجام شود؛ صرف extension/MIME کافی نیست.
- ابعاد و تعداد پیکسل سقف داشته باشد تا decompression bomb رد شود.
- metadata شامل EXIF/GPS/ICC غیرضروری هنگام بازتولید حذف شود.
- thumbnail خارج از request اصلی تولید و فایل اصلی هرگز از public disk سرو نشود.

## Rollback و مشاهده‌پذیری

- مسیرهای قدیمی public فقط برای خواندن رکوردهای legacy باقی می‌مانند؛ upload جدید همیشه خصوصی است.
- سنجه‌های `upload_rejected`, `scan_pending`, `scan_failed`, `download_forbidden` و latency اسکن ثبت شوند.
- rollback کد نباید فایل خصوصی را public کند؛ در حالت اضطراری upload جدید غیرفعال و download مجاز موجود ادامه یابد.
