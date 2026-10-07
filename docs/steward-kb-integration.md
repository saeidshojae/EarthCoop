# Steward Agent — Knowledge Base Integration (Legacy Note)

این سند مربوط به نسخه قدیمی اتصال صرفاً Knowledge Base است و جزئیات اجرایی آن دیگر مرجع جاری نیست.

معماری فعلی مهماندار چهار خانواده منبع را در مسیر واقعی `StewardAgent::ask()` بازیابی می‌کند: Knowledge Base، منابع فایل/URL مهماندار، FAQ و بلاگ عمومی EarthCoop. برای جزئیات به `docs/steward-complete-integration.md` مراجعه کنید.

نکات مهم نسبت به نسخه قدیمی:

- retrieval دیگر به پنج واژه اول پرسش محدود نیست.
- شرط‌های انتشار داخل query گروه‌بندی شده‌اند تا draftها با `orWhere` نشت نکنند.
- Blog سراسری از `App\Modules\Blog\Models\Post` می‌آید، نه پست‌های خصوصی گروه‌ها.
- فایل‌های مهماندار private هستند و URL نیز به‌عنوان source پشتیبانی می‌شود.
- snippet مرتبط اطراف match به prompt می‌رود.
- مسیر `NajmHodaOrchestrator → StewardAgent::ask()` مسیر مرجع runtime است.

این فایل فقط برای حفظ تاریخچه نگه داشته شده است.
