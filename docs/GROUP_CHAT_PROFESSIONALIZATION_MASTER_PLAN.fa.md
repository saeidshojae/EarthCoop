# برنامه جامع بازسازی و حرفه‌ای‌سازی چت گروه‌ها

این سند منبع اصلی برنامه‌ریزی فنی و محصولی چت گروه‌ها است و چک‌لیست‌های قدیمی‌تر را تجمیع می‌کند. هدف، ارتقای تدریجی سیستم موجود بدون بازنویسی پرریسک و بدون ایجاد اختلال در نجم‌هدا، انتخابات، تفویض و سایر قابلیت‌های گروه است.

## وضعیت مبنا

- مسیر اصلی: `/groups/chat/{group}`
- قابلیت‌های موجود: پیام متنی، فایل، صوت، پاسخ، رشته گفتگو، منشن، جست‌وجو، پین، گزارش، واکنش، پست، دیدگاه و نظرسنجی
- انتقال داده: WebSocket با fallback مبتنی بر polling
- وضعیت فعلی خوانده‌نشده: پیام، فایل، صوت، پست و نظرسنجی را پوشش می‌دهد و محتوای خود کاربر را حذف می‌کند
- ویرایشگر پست: CKEditor 4 به‌صورت محلی؛ این راهکار موقت است و به‌دلیل پایان چرخه امن نسخه جاری باید مهاجرت کند
- مبنای این سند: commit `61d4db50`

## اصول غیرقابل مذاکره

- [ ] هر تغییر با feature flag و امکان rollback ارائه شود.
- [ ] هیچ بازنویسی یک‌مرحله‌ای یا Big Bang انجام نشود.
- [ ] قراردادهای نجم‌هدا، انتخابات و تفویض تا پایان مهاجرت حفظ شوند.
- [ ] عملیات HTTP حتی هنگام قطع broadcaster موفق یا ناموفق بودن خود را مستقل و صحیح اعلام کند.
- [ ] همه عملیات نوشتن دارای Policy، validation، rate limit و idempotency متناسب باشند.
- [ ] هیچ محتوای HTML بدون پاک‌سازی allowlist در خروجی خام رندر نشود.
- [ ] WebSocket مسیر اصلی و delta polling فقط مسیر بازیابی/fallback باشد.
- [ ] هر فاز پیش از ورود به production دارای تست خودکار، سنجه و برنامه rollback باشد.

## تعریف نهایی Done

- [ ] همه عملیات دو کاربر هم‌زمان بدون refresh و با ترتیب قطعی نمایش داده شوند.
- [ ] p95 ارسال پیام متنی در شبکه محلی کمتر از 500ms و نمایش در کلاینت دوم کمتر از 1s باشد.
- [ ] قطع و وصل WebSocket هیچ پیام گمشده یا تکراری ایجاد نکند.
- [ ] شمارش unread برای تمام انواع feed دقیق، race-safe و مستقل از JSON داخل رکورد محتوا باشد.
- [ ] کاربر غیرعضو نتواند هیچ محتوای گروه خصوصی را بخواند یا تغییر دهد.
- [ ] تمام مسیرهای upload، HTML و عملیات مدیریتی تست امنیتی داشته باشند.
- [ ] صفحه با 10,000 آیتم تاریخچه و گروه‌های پرترافیک بدون افت محسوس قابل استفاده باشد.
- [ ] تمام جریان‌های اصلی در عرض‌های 375، 768 و 1440 پیکسل و با صفحه‌کلید قابل انجام باشند.
- [ ] کنسول در حالت production فاقد خطای متعلق به برنامه و لاگ debug باشد.

## فاز 0 — خط مبنا، کنترل تغییر و مشاهده‌پذیری

### معماری و مالکیت

- [ ] یک ADR برای معماری هدف feed، realtime، unread و fallback ثبت شود.
- [ ] فهرست کامل endpointها، eventها، timerها و handlerهای چت استخراج شود.
- [ ] مالک هر قابلیت و مرز نجم‌هدا، انتخابات و چت مشخص شود.
- [ ] اسناد قدیمی با این سند تطبیق داده و موارد متناقض archive شوند.
- [ ] feature flagهای مستقل برای message، post، poll، comment، reaction، unread و realtime تعریف شوند.

### خط مبنای قابل اندازه‌گیری

- [ ] تعداد query، زمان پاسخ، حجم HTML اولیه و زمان interactive ثبت شود.
- [ ] p50/p95/p99 برای send، edit، delete، vote، react و upload ثبت شود.
- [ ] نرخ قطع WebSocket، فعال‌شدن fallback، duplicate و retry ثبت شود.
- [ ] سناریوی بار پایه با 2، 20 و 100 کاربر هم‌زمان تعریف شود.
- [ ] بودجه عملکردی برای bundle، initial payload، تعداد request و مصرف حافظه تصویب شود.

### پاک‌سازی کنسول

- [x] CKEditor و دارایی‌های runtime موردنیاز آن محلی شده‌اند.
- [x] 404های `scayt.css`، `dialog.css` و `tableselection.css` رفع شده‌اند.
- [ ] CKEditor 4.22.1 با ویرایشگر نگهداری‌شده جایگزین شود؛ هشدار امنیتی پنهان نشود.
- [ ] Font Awesome، jQuery و Select2 از CDN به Vite/دارایی محلی منتقل شوند.
- [ ] فونت‌های Vazirmatn از نظر preload، cache headers، تعداد weight و حجم بررسی شوند.
- [ ] لاگ‌های debug مانند پیام‌های init و `console.log` در production حذف یا پشت flag قرار گیرند.
- [ ] تست کنسول در پروفایل مرورگر بدون افزونه انجام شود؛ خطاهای `chrome-extension://` جزو برنامه نیستند.

**معیار پذیرش فاز 0:** داشبورد خط مبنا، ADR تصویب‌شده و کنسول production بدون خطای متعلق به برنامه.

## فاز 1 — امنیت، مجوزها و صحت داده

### Policy و عضویت گروه

- [ ] `GroupPolicy` برای view، participate، moderate و manage تعریف شود.
- [ ] `MessagePolicy`، `PostPolicy`، `PollPolicy` و `CommentPolicy` ایجاد شوند.
- [ ] ایجاد پست، نظرسنجی، دیدگاه و واکنش فقط برای عضو مجاز باشد.
- [ ] ویرایش/حذف فقط برای مالک یا مدیر مجاز باشد.
- [ ] scoped binding ارتباط `{group}` با `{post|poll|message}` را تضمین کند.
- [ ] channel authorization همان Policyهای HTTP را استفاده کند.
- [ ] endpointهای feed، unread، search، mention و فایل نیز عضویت را بررسی کنند.

### Validation و یکپارچگی دامنه

- [ ] Form Request مستقل برای هر عملیات ساخته شود.
- [ ] گزینه‌های نظرسنجی حداقل دو مورد، غیرخالی، محدود و غیرتکراری باشند.
- [ ] `option_id` حتماً متعلق به همان poll باشد.
- [ ] انقضا و فعال‌بودن poll پیش از vote کنترل شود.
- [ ] محدودیت تغییر رأی و نوع نظرسنجی به‌صورت صریح تعریف شود.
- [ ] `parent_id` دیدگاه و پیام متعلق به همان post/group باشد.
- [ ] ایجاد poll و options داخل transaction انجام شود.
- [ ] تغییر رأی داخل transaction و با constraint صحیح انجام شود.
- [ ] constraint یکتا برای واکنش، رأی و کلید idempotency بازبینی شود.

### امنیت محتوا و آپلود

- [ ] HTML پست با sanitizer سمت سرور و allowlist پاک‌سازی شود.
- [ ] CSP متناسب با ویرایشگر و media اعمال شود.
- [ ] MIME واقعی، extension، حجم و signature فایل بررسی شود.
- [ ] نام فایل در storage با UUID/hash تولید و نام اصلی فقط metadata باشد.
- [ ] فایل‌های خصوصی از disk خصوصی و controller مجاز stream شوند.
- [ ] اسکن بدافزار و محدودیت تصویر/EXIF برای محیط production طراحی شود.
- [ ] rate limit مجزا برای message، upload، post، poll، vote، comment و reaction اعمال شود.
- [ ] خطاهای 403/404/422 اطلاعات داخلی یا وجود منابع خصوصی را افشا نکنند.

**معیار پذیرش فاز 1:** تست نفوذ سطح endpoint، تست XSS/upload و تست منفی کاربر غیرعضو برای تمام عملیات‌ها موفق باشد.

## فاز 2 — قرارداد API و قابلیت اطمینان عملیات

- [ ] envelope واحد پاسخ تعریف شود: `status`, `data`, `error`, `meta`, `request_id`.
- [ ] کدهای HTTP و خطاهای validation در تمام endpointها یکسان شوند.
- [ ] کلید idempotency برای message، file، voice، post، poll، vote، comment و reaction اضافه شود.
- [ ] optimistic update فقط همراه rollback قطعی اجرا شود.
- [ ] stateهای `pending`, `sent`, `delivered`, `read`, `failed` تعریف شوند.
- [ ] retry فقط برای خطاهای قابل تکرار و با همان idempotency key باشد.
- [ ] timeout و AbortController برای درخواست‌های منقضی پیاده شود.
- [ ] عملیات delete به tombstone تبدیل شود تا ترتیب feed و sync حفظ شود.
- [ ] تاریخچه edit و اطلاعات `edited_at/edited_by` نگهداری شود.
- [ ] رندر HTML داخل JSON به‌تدریج با DTO نسخه‌دار جایگزین شود.

**معیار پذیرش فاز 2:** دوبار کلیک، retry شبکه و refresh وسط ارسال هیچ رکورد تکراری یا وضعیت مبهم ایجاد نکند.

## فاز 3 — Feed یکپارچه و مدل unread مقیاس‌پذیر

### مدل feed

- [ ] جدول `group_feed_items` با `group_id`, `sequence`, `type`, `content_id`, `actor_id`, timestamps طراحی شود.
- [ ] sequence برای هر گروه یکتا و افزایشی باشد.
- [ ] indexهای `group_id + sequence` و `type + content_id` اضافه شوند.
- [ ] migration/backfill برای پیام، فایل، صوت، پست و poll طراحی شود.
- [ ] دوره dual-write و کنترل تطابق داده تعریف شود.
- [ ] eventهای edit/delete/reaction/vote به نسخه همان feed item متصل شوند.

### خوانده‌نشده

- [ ] `last_read_feed_sequence` به عضویت گروه اضافه شود.
- [ ] unread بر اساس cursor محاسبه و محتوای خود کاربر حذف شود.
- [ ] mark-as-read بر اساس visibility واقعی و dwell کوتاه انجام شود.
- [ ] `Mark all as read` اتمیک پیاده شود.
- [ ] اولین آیتم unread و divider پایدار داخل timeline نمایش داده شود.
- [ ] unread منشن و reply به‌صورت شمارنده فرعی قابل استخراج باشد.
- [ ] JSONهای `read_by` پس از دوره سازگاری deprecate شوند.
- [ ] در صورت نیاز به رسید جزئی، جدول normalized مجزای `content_reads` ایجاد شود.

**معیار پذیرش فاز 3:** شمارنده میان دو دستگاه یک کاربر و چند کاربر هم‌زمان دقیق بماند و query آن با رشد تعداد اعضا خطی نشود.

## فاز 4 — Realtime، ترتیب رویداد و fallback واحد

- [ ] envelope نسخه‌دار event شامل `event_id`, `group_id`, `sequence`, `type`, `actor_id`, `occurred_at`, `payload` تعریف شود.
- [ ] همه writeها از outbox تراکنشی event تولید کنند.
- [ ] worker رویدادها را به channel خصوصی گروه broadcast کند.
- [ ] client رویدادها را بر اساس `event_id` deduplicate کند.
- [ ] client رویدادهای خارج از ترتیب را با sequence مرتب یا gap را بازیابی کند.
- [ ] endpoint واحد delta با `after_sequence` تمام انواع feed را بازگرداند.
- [ ] fallback فقط هنگام قطع/ناسالم‌بودن WebSocket فعال شود.
- [ ] پس از reconnect ابتدا gap sync و سپس polling متوقف شود.
- [ ] backoff تصاعدی، jitter و سقف retry اعمال شود.
- [ ] polling هنگام hidden بودن tab یا offline بودن دستگاه متوقف شود.
- [ ] typing و presence به‌صورت ephemeral، throttled و بدون ذخیره دائمی باشند.
- [ ] وضعیت اتصال `آنلاین/در حال اتصال/آفلاین` در UI نمایش داده شود.

**معیار پذیرش فاز 4:** تمام عملیات message/post/poll/comment/reaction/edit/delete در تست دوکلاینتی زیر یک ثانیه دیده شوند و قطع شبکه باعث فقدان یا تکرار نشود.

## فاز 5 — بازسازی فرانت‌اند و کاهش پیچیدگی

- [ ] تمام inline handlerهای `onclick` حذف و event delegation جایگزین شود.
- [ ] `alert()` و `confirm()` با toast/dialog غیرمسدودکننده جایگزین شوند.
- [ ] `chat.blade.php` به layout و partial/componentهای کوچک تقسیم شود.
- [ ] `group-chat.js` به ماژول‌های Composer، Feed، Realtime، Unread و Actions تقسیم شود.
- [ ] `chat-features.js` و `voice-recorder.js` مالکیت مشخص و API کوچک داشته باشند.
- [ ] ApiClient مرکزی CSRF، JSON parsing، timeout، error mapping و retry را مدیریت کند.
- [ ] state store واحد منبع حقیقت UI باشد.
- [ ] renderer واحد برای initial load، optimistic item، polling و WebSocket استفاده شود.
- [ ] lifecycle برای ثبت/حذف listener و timer در navigation تعریف شود.
- [ ] تمام timerهای تکراری، listenerهای چندباره و globalهای غیرضروری حذف شوند.
- [ ] migration تدریجی به TypeScript برای قراردادهای feed و API ارزیابی شود.
- [ ] تست واحد برای reducer/store و event reconciliation نوشته شود.

**معیار پذیرش فاز 5:** هر عملیات فقط یک handler، یک مسیر API و یک مسیر render داشته باشد و ورود مجدد به صفحه listener یا timer تکراری نسازد.

## فاز 6 — تجربه کاربری حرفه‌ای عملیات‌ها

### Composer و ارسال

- [ ] composer واحد با اقدامات متن، فایل، صوت، پست و poll طراحی شود.
- [ ] ابزارهای ثانویه در موبایل داخل bottom sheet قرار گیرند.
- [ ] draft مجزا برای هر گروه و هر نوع محتوا ذخیره شود.
- [ ] Enter/Shift+Enter مطابق زبان و انتظار کاربر عمل کند.
- [ ] reply context، cancel reply و jump-to-original واضح باشند.
- [ ] وضعیت ارسال، خطا، retry و cancel روی همان آیتم نمایش داده شود.
- [ ] ارسال تکراری هنگام double-click یا کندی شبکه ناممکن باشد.

### فایل و صوت

- [ ] progress واقعی، cancel و retry برای upload اضافه شود.
- [ ] نوع، حجم، نام امن و thumbnail فایل پیش از ارسال نمایش داده شود.
- [ ] lightbox تصویر و دانلود مجاز فایل پیاده شود.
- [ ] ضبط صوت دارای pause/resume، timer، preview، حذف و ضبط مجدد باشد.
- [ ] waveform، scrubber، duration و playback speed به پلیر افزوده شود.
- [ ] پخش هم‌زمان چند صوت مدیریت شود.

### پست، دیدگاه و poll

- [ ] پست در timeline ابتدا به‌صورت کارت خلاصه و قابل گسترش نمایش داده شود.
- [ ] autosave فرم پست و بازیابی پس از خطا اضافه شود.
- [ ] ویرایشگر قدیمی با راهکار نگهداری‌شده و سبک جایگزین شود.
- [ ] دیدگاه‌ها به thread قابل پیمایش با شمارنده و آخرین فعالیت تبدیل شوند.
- [ ] poll دارای افزودن، حذف و مرتب‌سازی گزینه‌ها باشد.
- [ ] نوع رأی، ناشناس‌بودن، امکان تغییر و زمان پایان شفاف باشد.
- [ ] نتایج poll زنده، قابل فهم و بدون layout shift نمایش داده شوند.

### واکنش، جست‌وجو و ناوبری

- [ ] reaction picker لمسی/صفحه‌کلیدی و optimistic با rollback باشد.
- [ ] جزئیات کاربران واکنش‌دهنده قابل مشاهده باشد.
- [ ] فیلترهای همه، unread، منشن، پیام، فایل، پست، poll و pinned اضافه شوند.
- [ ] جست‌وجو بر اساس متن، فرستنده، نوع و بازه تاریخ انجام شود.
- [ ] jump به نتیجه و حفظ scroll anchor پیاده شود.
- [ ] پنل pinned و history edit/delete در دسترس باشد.

**معیار پذیرش فاز 6:** کاربر در موبایل و دسکتاپ تمام عملیات‌ها را بدون refresh، پیام مبهم یا از دست‌دادن draft انجام دهد.

## فاز 7 — عملکرد، رسانه و مقیاس

- [ ] cursor pagination واحد بر پایه sequence جای paginationهای پراکنده را بگیرد.
- [ ] prepend تاریخچه scroll anchor را حفظ کند.
- [ ] virtualization/windowing برای timelineهای بزرگ اجرا شود.
- [ ] تصاویر lazy-load و thumbnailهای چنداندازه تولید شوند.
- [ ] audio/video metadata و waveform خارج از request اصلی پردازش شوند.
- [ ] queryهای داخل Blade حذف و eager loading/aggregateهای لازم انجام شود.
- [ ] N+1 پست، دیدگاه، reaction و poll با query budget کنترل شود.
- [ ] cache فقط برای داده قابل بازسازی و با invalidation روشن استفاده شود.
- [ ] bundle چت code-split و فقط در صفحات مرتبط بارگذاری شود.
- [ ] فونت فارسی subset و فقط weightهای واقعاً مصرف‌شده بارگذاری شود.
- [ ] cache-control بلندمدت و fingerprint برای assetها اعمال شود.
- [ ] pollingهای جداگانه حذف و delta sync واحد جایگزین شود.

**معیار پذیرش فاز 7:** بودجه مصوب query، request، bundle، memory و p95 در آزمون بار رعایت شود.

## فاز 8 — دسترس‌پذیری، RTL و سازگاری

- [ ] تمام دکمه‌های icon-only نام قابل دسترس داشته باشند.
- [ ] modalها focus trap، Escape، restore focus و عنوان مرتبط داشته باشند.
- [ ] وضعیت ارسال، خطا و رویداد جدید با `aria-live` مناسب اعلام شود.
- [ ] ترتیب tab، shortcutها و reaction picker با صفحه‌کلید تست شوند.
- [ ] اندازه هدف لمسی حداقل 44×44 پیکسل باشد.
- [ ] کنتراست light/dark و focus indicator بررسی شود.
- [ ] `prefers-reduced-motion` برای animationها رعایت شود.
- [ ] متن فارسی، اعداد، URL و محتوای LTR داخل RTL صحیح نمایش داده شوند.
- [ ] safe-area، صفحه‌کلید موبایل و عدم هم‌پوشانی composer با دکمه‌های شناور کنترل شود.
- [ ] Chrome، Edge، Firefox و Safari/WebKit تست شوند.

**معیار پذیرش فاز 8:** مسیرهای اصلی با صفحه‌کلید و screen reader قابل انجام باشند و ممیزی خودکار accessibility خطای بحرانی نداشته باشد.

## فاز 9 — مدیریت، حریم خصوصی و کنترل سوءاستفاده

- [ ] سطح دسترسی مدیر، ناظر، عضو و کاربر محدود مستند شود.
- [ ] گزارش محتوا دارای دلیل، وضعیت، SLA و audit trail باشد.
- [ ] حذف مدیریتی به‌صورت tombstone و با actor/reason ثبت شود.
- [ ] تنظیم mute برای گروه، منشن، thread و انواع محتوا فراهم شود.
- [ ] retention و پاک‌سازی فایل‌ها/پیام‌های حذف‌شده تعریف شود.
- [ ] export/delete داده کاربر و نیازهای حریم خصوصی بررسی شود.
- [ ] ضداسپم شامل rate limit تطبیقی، duplicate detection و محدودیت upload باشد.
- [ ] audit log عملیات حساس غیرقابل تغییر و قابل جست‌وجو باشد.

**معیار پذیرش فاز 9:** عملیات مدیریتی قابل ممیزی، قابل توضیح و مطابق سیاست نگهداری داده باشند.

## فاز 10 — تست، انتشار و بهره‌برداری

### هرم تست

- [ ] تست واحد Policy، validator، sanitizer، reducer و formatter تکمیل شود.
- [ ] تست Feature برای تمام endpointها و نقش‌ها نوشته شود.
- [ ] تست concurrency برای vote، reaction، idempotency و read cursor اضافه شود.
- [ ] تست E2E دوکاربره برای همه عملیات‌های feed نوشته شود.
- [ ] تست offline/reconnect/gap/duplicate/out-of-order اضافه شود.
- [ ] تست upload نامعتبر، XSS، CSRF و دسترسی افقی اجرا شود.
- [ ] تست بصری موبایل/دسکتاپ و accessibility در CI اضافه شود.
- [ ] load test با سناریوی نزدیک به production اجرا شود.

### انتشار کنترل‌شده

- [ ] migrationها forward/backward compatible باشند.
- [ ] dual-read/dual-write دارای ابزار بررسی تطابق باشد.
- [ ] rollout ابتدا برای گروه آزمایشی و سپس درصدی انجام شود.
- [ ] alert برای error rate، latency، queue lag و WebSocket disconnect تعریف شود.
- [ ] runbook قطع broadcaster، رشد queue، خطای migration و rollback نوشته شود.
- [ ] پس از پایداری، مسیرهای قدیمی، JSON `read_by` و pollingهای legacy حذف شوند.

**معیار پذیرش فاز 10:** release آزمایشی بدون regression، با داشبورد سلامت و rollback آزموده‌شده انجام شود.

## بسته‌های اجرایی پیشنهادی

### بسته A — تثبیت فوری

فازهای 0، 1 و بخش‌های ضروری 2. خروجی: امنیت endpointها، حذف خطاهای واقعی کنسول، قرارداد پاسخ و جلوگیری از عملیات تکراری.

### بسته B — هسته یکپارچه

فازهای 3 و 4. خروجی: feed ترتیبی، unread مقیاس‌پذیر، WebSocket قابل بازیابی و fallback واحد.

### بسته C — بازسازی فرانت

فاز 5. خروجی: ماژول‌های مستقل، renderer/store واحد و حذف کدهای inline و alertها.

### بسته D — تجربه محصول

فازهای 6 و 8. خروجی: composer حرفه‌ای، upload/voice کامل، timeline قابل جست‌وجو و تجربه موبایل/دسترس‌پذیر.

### بسته E — آمادگی مقیاس و انتشار

فازهای 7، 9 و 10. خروجی: عملکرد قابل سنجش، مدیریت و حریم خصوصی، تست بار و انتشار کنترل‌شده.

## ترتیب شروع پیشنهادی

1. Policy و scoped binding نظرسنجی، پست، دیدگاه و واکنش
2. validation و transaction رأی/گزینه‌های نظرسنجی
3. sanitizer HTML و برنامه مهاجرت CKEditor
4. قرارداد API و idempotency همه عملیات‌ها
5. طراحی ADR و schema مربوط به feed sequence و read cursor
6. delta endpoint و event envelope واحد
7. شکستن frontend و ایجاد ApiClient/Store/Renderer واحد
8. بازطراحی composer و عملیات فایل/صوت
9. بهینه‌سازی timeline و assetها
10. تکمیل ماتریس E2E، بار، امنیت و انتشار مرحله‌ای
