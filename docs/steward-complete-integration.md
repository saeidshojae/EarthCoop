# نجم هدا — معماری منابع دانش مهماندار

## وضعیت جاری

مسیر عملیاتی مهماندار از `NajmHodaOrchestrator` به `StewardAgent::ask()` می‌رسد. پیش از فراخوانی مدل، مهماندار برای پرسش جاری از چهار خانواده منبع بازیابی انجام می‌دهد:

1. **پایگاه دانش** — `KbArticle`، فقط محتوای `published`.
2. **منابع مدیریتی مهماندار** — فایل‌های PDF / Word / TXT / Markdown و لینک‌های عمومی ثبت‌شده در تنظیمات.
3. **FAQ** — فقط پرسش‌های منتشرشده و دارای پاسخ.
4. **بلاگ عمومی EarthCoop** — `App\Modules\Blog\Models\Post` و فقط `published()`.

پست‌های داخل گروه‌ها (`App\Models\Blog`) منبع دانش سراسری مهماندار نیستند.

## ترتیب منابع

اولویت پیش‌فرض در `config/najm-hoda.php`:

- Knowledge Base: 10
- منابع بارگذاری‌شده/لینک: 8
- FAQ: 7
- Blog عمومی: 5

این ترتیب در `StewardAgent::sortBySourcePriority()` روی context بازیابی‌شده اعمال می‌شود.

## بازیابی

`StewardAgent` پرسش فارسی/انگلیسی را نرمال می‌کند، stop-wordهای رایج را کنار می‌گذارد و حداکثر هشت واژه معنادار را برای جستجو انتخاب می‌کند. نتیجه هر منبع به snippet اطراف محل تطبیق تبدیل می‌شود؛ ابتدای یک سند بلند به‌جای بخش مرتبط به مدل فرستاده نمی‌شود.

کل context بازیابی‌شده سقف دارد و متن منبع با delimiter مشخص از درخواست اصلی جدا می‌شود. مدل صریحاً موظف است متن منابع را «داده و شاهد» تلقی کند، نه دستور اجرایی.

## حریم خصوصی و ذخیره‌سازی

فایل‌های جدید مهماندار روی disk خصوصی Laravel (`local`) و زیر `storage/app/steward/knowledge` ذخیره می‌شوند؛ زیر `public/storage` قرار نمی‌گیرند.

migration مربوط به این تغییر تمام artifactهای legacy زیر `public/storage/steward/knowledge` را به storage خصوصی منتقل می‌کند. rollbackهای schema اگر باعث truncation یا ناسازگاری منبع URL شوند، fail-closed هستند.

متن بازیابی‌شده برای مدل در `AIInteraction.input` دوباره ذخیره نمی‌شود. ورودی ذخیره‌شده برای observability فقط درخواست اصلی را نگه می‌دارد؛ محاسبه تقریبی مصرف توکن همچنان از prompt واقعی استفاده می‌کند.

## منابع URL

`StewardKnowledgeUrlIngestor` فقط HTTP/HTTPS عمومی را می‌پذیرد. کنترل‌های اصلی:

- رد localhost و IPهای private/reserved
- رد URL دارای username/password
- فقط پورت‌های 80 و 443
- DNS resolution و pin کردن IP تأییدشده با cURL
- عدم دنبال‌کردن redirect
- محدودیت نوع محتوا به HTML / plain text / XHTML
- سقف 2MB هم حین دانلود و هم پس از دریافت
- سقف متن استخراجی
- حذف script/style/noscript/svg از HTML
- عدم ثبت query string یا credential لینک در log خطا

لینک در زمان ثبت ingest می‌شود و محتوای استخراج‌شده به‌عنوان snapshot منبع ذخیره می‌شود.

## فایل‌ها

آپلودهای پذیرفته‌شده: PDF، DOC، DOCX، TXT و Markdown تا 10MB.

- TXT/MD مستقیماً استخراج می‌شوند.
- PDF با `smalot/pdfparser` پردازش می‌شود.
- Word با PHPWord موجود در پروژه پردازش می‌شود.
- متن استخراج‌شده برای جلوگیری از رشد کنترل‌نشده سقف دارد.
- نام داخلی فایل UUID است تا collision روی آپلود هم‌زمان رخ ندهد.

## انتشار و cache

خلاصه منابع در `steward_content_summary` cache می‌شود. Observerهای KB، FAQ، Blog عمومی و منابع مهماندار cache را هنگام تغییر معنادار invalidate می‌کنند. تغییر `views_count` بلاگ/KB به‌تنهایی نباید cache را بی‌جهت پاک کند.

## امنیت UI

عنوان و metadata منبع خارجی در پنل ادمین untrusted تلقی می‌شود. صفحه تنظیمات قبل از قراردادن داده در HTML آن را escape می‌کند و صفحه مدیریت مستقل از attributeهای escape‌شده استفاده می‌کند؛ عنوان منبع مستقیماً داخل JavaScript inline تزریق نمی‌شود.

## تست

`tests/Feature/NajmHoda/StewardKnowledgeSourceTest.php` قراردادهای اصلی را پوشش می‌دهد، از جمله:

- ذخیره متن بزرگ
- آپلود خصوصی multipart
- اتصال retrieval به مسیر واقعی مهماندار
- حذف draft/unpublished از منابع عمومی
- snippet اطراف match در سند بلند
- جستجوی فارسی با واژگان معنادار
- افزودن URL و رد شبکه خصوصی / credential / port غیرمجاز
- عدم persistence متن بازیابی‌شده در interaction log
- XSS hardening UI
- rollback fail-closed
- محدودیت streaming دانلود URL

Full Validation مخزن باید گیت نهایی هر تغییر در این معماری باشد.
