# فاز ۵ — بازسازی فرانت‌اند چت گروهی

## وضعیت

برنامهٔ بازسازی ساختاری تکمیل شده؛ پذیرش نهایی فاز هنوز باز است — چهار معیار معماری و آزمون مرورگری باقی مانده‌اند.

Checkpoint دوم: مسیرهای ایجاد پیام در delta، WebSocket قدیمی، polling، optimistic submit، پاسخ HTTP و voice همگی از `renderMessageThroughPipeline` و Feed/Renderer مشترک عبور می‌کنند. renderer موجود در دورهٔ dual-run به‌عنوان adapter ثبت می‌شود تا markup یا رفتار موازی ایجاد نشود.

Checkpoint سوم: mutationهای پیام (`edit`، `delete`، `reaction` و `mark-read`) از WebSocket و polling به event نرمال و `Feed.mutate` منتقل شدند. inline handlerهای تولیدشده در سه فایل runtime اصلی حذف و با delegation جایگزین شدند.

Checkpoint چهارم: تمام inline click/mouse handlerهای `chat.blade.php` و partialهای اصلی گروه (`message`، `post`، `poll`، `comment`، `header`، `group_info_panel` و `election`) حذف شدند. actionهای toolbar، timeline و modalها از delegated bridge مشترک استفاده می‌کنند و backdrop فقط روی کلیک مستقیم بسته می‌شود.

Checkpoint پنجم: پنج inline handler دو صفحهٔ مستقل comment نیز با delegation محلی حذف شدند. Feedback API ماژولار شامل toast، confirm و prompt غیرمسدودکننده اضافه شد و تمام call siteهای blocking در `group-chat.js` به آن منتقل شدند. عملیات مخرب حذف poll/post/chat/history اکنون Promise-based هستند.

Checkpoint ششم: تمام alert/confirm/promptهای `chat-features.js`، `voice-recorder.js` و scriptهای inline `chat.blade.php` به Feedback API منتقل شدند. تغییر نقش، بررسی گزارش، لغو voice، حذف/گزارش پیام و pin/unpin اکنون async و غیرمسدودکننده‌اند. وابستگی اشتباه تغییر نقش به `window.event` نیز حذف شد.

Checkpoint هفتم: صفحهٔ مستقل legacy `groups/comment.blade.php` و اسکریپت `group_info_panel` نیز از dialogهای blocking پاک شدند. Feedback به‌صورت lazy برای تمام صفحات Vite initialize می‌شود و edit/delete/report comment از prompt/confirm Promise-based استفاده می‌کنند.

Checkpoint هشتم: bootstrap تنظیمات و assetهای runtime از `chat.blade.php` به `groups/partials/chat_runtime.blade.php` استخراج شد. APIهای مالکیت freeze‌شدهٔ `GroupChatFeatures` و `GroupVoiceRecorder` اضافه شدند و چهار تست قرارداد منبع، inline handler، blocking dialog، بارگذاری partial و ownership API را در CI کنترل می‌کنند.

Checkpoint نهم: optimistic voice از Blade به `voice-recorder.js` منتقل شد. MutationObserver یک‌بارمصرف با یک delegated capture listener دارای cleanup جایگزین شد و API voice متد `destroy` دارد. modalهای مدیریت اعضا/گزارش‌ها نیز به `groups/partials/management_modals.blade.php` منتقل شدند. حجم Blade اصلی از ۴۹۲۲ به ۴۷۷۵ خط کاهش یافت.

Checkpoint دهم: create/update/delete/reaction/read مربوط به post و poll از شاخه‌های مستقیم `applyFeedEvent` به Feed/Renderer مشترک منتقل شدند. adapterهای legacy فقط پیاده‌ساز مرز مشترک‌اند و تست مستقل اثبات می‌کند post creation و poll update از همان pipeline عبور می‌کنند. تعداد تست‌های JS به ۱۳ رسید.

Checkpoint یازدهم: رویداد comment به‌جای ساخت timeline card مستقل، از مرز Feed/Renderer مشترک به‌عنوان invalidation نخ پست عبور می‌کند و شمارندهٔ کامنت را با مقدار canonical سمت‌سرور به‌روز می‌کند. قرارداد delta اکنون `comments_count` را با eager loading و aggregate برمی‌گرداند و یک Feature test مقدار دو کامنت را اثبات می‌کند. Renderer نیز نتیجهٔ boolean adapterهای legacy را بدون تلاش برای append کردن به DOM می‌پذیرد.

Checkpoint دوازدهم: حذف و گزارش پیام برای markup اولیهٔ Blade و markup پویای JS به action bridge واحد منتقل شد. handlerهای مستقیم قدیمی فقط برای markup سازگاریِ فاقد `data-group-chat-action` قابل فعال‌شدن‌اند و listenerهای آزمایشی/تکراری لینک پروفایل حذف شدند. تست قرارداد منبع این مالکیت را تثبیت می‌کند. حجم `chat.blade.php` از ۴۹۲۲ خط اولیه به ۴۱۶۵ خط رسیده است.

Checkpoint سیزدهم: یک Lifecycle سراسری صفحه مستقل از روشن‌بودن پرچم frontend ساخته شد و هنگام `pagehide` تمام منابع ثبت‌شده را آزاد می‌کند. polling پانزده‌ثانیه‌ای unread، timeoutهای بازیابی/رندر، listenerهای scroll و read-state و `MutationObserver` مدیر اسکرول همگی به این Lifecycle منتقل شدند. timeout اجراشده نیز cleanup خود را از registry حذف می‌کند تا debounce پرتکرار موجب رشد Set نشود. تست‌های JS توقف timeout/interval پس از destroy و مالکیت observer/polling را اثبات می‌کنند.

Checkpoint چهاردهم: retry نمایی delta، listenerهای online/offline/visibility، polling یک‌ثانیه‌ای پیام، polling سه‌ثانیه‌ای پست، reconcile ده‌ثانیه‌ای و monitor پنج‌ثانیه‌ای fallback همگی تحت Lifecycle صفحه قرار گرفتند. انتظار موقت برای restore اسکرول از interval به timeout بازگشتی تبدیل شد و monitor fallback بلافاصله پس از شروع polling متوقف می‌شود. تست قرارداد منبع مانع بازگشت timer/listener خام به این خوشه می‌شود.

Checkpoint پانزدهم: Lifecycle قابلیت `clearInterval` ثبت‌شده پیدا کرد. countdown نظرسنجی هنگام انقضا، تاریخ نامعتبر یا حذف node از DOM متوقف و از registry خارج می‌شود. timer ضبط صوت نیز از Lifecycle استفاده می‌کند و `GroupVoiceRecorder.destroy` اکنون به‌صورت idempotent timer، MediaRecorder، trackهای stream، AudioContext، blob و optimistic bridge را آزاد می‌کند. listenerهای click/init/unload این sidecar نیز تحت Lifecycle قرار گرفتند. تست‌های JS لغو مستقل interval و قرارداد cleanup صوت/countdown را پوشش می‌دهند.

Checkpoint شانزدهم: stateهای خصوصی mention debounce و Voice Recorder از `window` حذف و به closureهای مالک منتقل شدند؛ دسترسی خواندنی blob فقط از API محدود `GroupVoiceRecorder.getBlob` ممکن است. ارزیابی TypeScript در `GROUP_CHAT_PHASE_5_TYPESCRIPT_DECISION.fa.md` ثبت شد: مهاجرت contract-first تأیید، تبدیل یک‌بارهٔ legacy رد، و ترتیب تبدیل api/realtime سپس feed/renderer/store تعیین شد. نصب TypeScript تا تثبیت E2E و جداسازی Blade به تعویق افتاد.

Checkpoint هفدهم: منطق سراسری بستن action-menu از Blade اصلی به `groups/partials/action_menu_dismissal.blade.php` منتقل شد. دو click listener موازی به یک handler ادغام و click/keydown تحت Lifecycle ثبت شدند؛ guard ورود مجدد از نصب دوباره جلوگیری می‌کند. تست قرارداد include و مالکیت Lifecycle را تثبیت می‌کند و حجم Blade اصلی به ۴۱۵۱ خط کاهش یافت.

Checkpoint هجدهم: runtime کامل جست‌وجوی چت شامل باز/بسته‌شدن پنل، debounce، pagination، رندر نتیجه و ناوبری صفحه‌کلید از Blade اصلی به `groups/partials/chat_search_runtime.blade.php` منتقل شد. رفتار runtime در این checkpoint عمداً تغییر نکرد تا refactor ساختاری diff کوچکی داشته باشد. تست قرارداد include و مالکیت کد جست‌وجو را تثبیت می‌کند و Blade اصلی به ۳۹۳۱ خط کاهش یافت.

Checkpoint نوزدهم: partial جست‌وجو دارای guard ورود مجدد شد و تمام listenerهای ثابت آن به Lifecycle منتقل شدند. stateهای `__setSearching` و `__ensureSearchOpen` از `window` حذف و محلی شدند؛ listener جداگانهٔ هر نتیجه نیز با یک handler delegated روی لیست جایگزین شد تا pagination تعداد handlerها را افزایش ندهد.

Checkpoint بیستم: runtime سنجاق/برداشتن سنجاق پیام از Blade اصلی به `groups/partials/pin_runtime.blade.php` منتقل شد. هر دو مسیر pin و unpin که به‌اشتباه از متغیر تعریف‌نشدهٔ `id` برای DOM lookup استفاده می‌کردند به `messageId` اصلاح شدند و تست قرارداد این شناسه را برای هر دو مسیر کنترل می‌کند.

Checkpoint بیست‌ویکم: مدیر یکپارچهٔ اسکرول، بازیابی موقعیت و نشانگرهای خوانده‌نشده از Blade اصلی به `groups/partials/scroll_unread_runtime.blade.php` منتقل شد. رفتار runtime در این checkpoint عمداً ثابت ماند؛ قراردادهای source اکنون include مستقل، خروج کامل پیاده‌سازی از Blade اصلی و مالکیت polling و MutationObserver توسط lifecycle صفحه را کنترل می‌کنند. Blade اصلی با این استخراج از ۴۳۶۶ به ۳۹۶۳ خط کاهش یافت.

Checkpoint بیست‌ودوم: runtime تعاملات composer شامل auto-resize، منوی ساخت محتوا، انتخاب فایل صوتی و بازکردن فرم‌های پست و نظرسنجی از Blade اصلی به `groups/partials/composer_actions_runtime.blade.php` منتقل شد. رفتار legacy در این checkpoint عمداً تغییر نکرد و تست قرارداد، include مستقل و مالکیت کد توسط partial جدید را تثبیت می‌کند. شمارش واقعی Blade اصلی در ابتدای checkpoint برابر ۳۹۶۵ خط بود و پس از استخراج به ۳۸۴۲ خط کاهش یافت.

Checkpoint بیست‌وسوم: listenerهای textarea، منوی composer، document، انتخاب فایل و دکمه‌های ساخت پست/نظرسنجی و همچنین دو timeout تأخیری به مالکیت `GroupChatLifecycle` منتقل شدند. guard راه‌اندازی تکراری و cleanup برای آزادسازی وضعیت و کلاس textarea اضافه شد. تنها listener مستقیم باقی‌مانده bootstrap یک‌بارهٔ `DOMContentLoaded` است، چون lifecycle توسط ماژول Vite پس از parse ساخته می‌شود؛ قرارداد تست این استثنا را دقیقاً به یک listener با `{ once: true }` محدود می‌کند.

Checkpoint بیست‌وچهارم: runtime ارسال AJAX پست از Blade اصلی به `groups/partials/post_submission_runtime.blade.php` منتقل و listener فرم تحت مالکیت lifecycle قرار گرفت. از آنجا که modal و `postForm` پیش از runtime به‌صورت ثابت render می‌شوند، monkey-patch مبتنی بر `Object.defineProperty(window, 'openBlogBox', ...)`، فلگ DOM خصوصی `_ajaxIntercepted` و retryهای زمانی ۵۰/۵۰۰ میلی‌ثانیه حذف شدند و یک guard صریح با cleanup جایگزین آن‌ها شد. Blade اصلی از ۳۸۴۲ به ۳۷۵۶ خط کاهش یافت.

Checkpoint بیست‌وپنجم: مسیر موفق ارسال پست به bridge محدود و immutable با نام `GroupChatFeedBridge` متصل شد. این bridge همان `applyFeedItemThroughPipeline` را در حالت modular یا legacy فراخوانی می‌کند و cursor داخلی polling را به‌روز نگه می‌دارد. در نتیجه runtime فرم دیگر `appendChild`، `_initPostMenus`، `_initReactionButtons` یا `_lastKnownPostId` را مستقیماً استفاده نمی‌کند و ایجاد پست مانند websocket/delta از boundary واحد feed/renderer عبور می‌کند.

Checkpoint بیست‌وششم: شاخه‌های create، update و delete در fallback polling پست‌ها نیز به `GroupChatFeedBridge` منتقل شدند. ساختن element موقت، `appendChild`/`replaceWith` و راه‌اندازی مستقیم menu/reaction از حلقهٔ polling حذف شد؛ global موازی `_lastKnownPostId` نیز کامل کنار رفت و cursor فقط از create موفق pipeline یا `latest_post_id` سرور به‌روزرسانی می‌شود. قرارداد source هر سه عملیات polling و حذف global قدیمی را تثبیت می‌کند.

Checkpoint بیست‌وهفتم: حذف‌های reconcile، حذف محلی پس از پاسخ موفق و جایگزینی/patch پس از ویرایش پست به `GroupChatFeedBridge.mutate` منتقل شدند. adapter مرکزی پست اکنون در حضور HTML جایگزینی canonical انجام می‌دهد و در پاسخ legacy بدون HTML، patch محدود فیلدها را از داخل همان boundary اجرا می‌کند. در نتیجه callerهای reconcile/delete/edit دیگر element پست را مستقیماً حذف یا جایگزین نمی‌کنند و قرارداد source بازگشت این مسیرهای موازی را ممنوع می‌کند.

Checkpoint بیست‌وهشتم: initialization عنصربه‌عنصر منو و reaction پست با event delegation زیر مالکیت `GroupChatLifecycle` جایگزین شد. globalهای `_initPostMenus` و `_initReactionButtons`، فلگ‌های خصوصی `_menuInit`/`_reactionInit` و listenerهای تکراری `DOMContentLoaded` حذف شدند؛ بنابراین پست‌ها و نظرسنجی‌های تازه‌رندرشده بدون initialization دستی قابل تعامل‌اند. delegation عمداً `.message-action` را به handler فعلی پیام واگذار می‌کند و click بیرون، Escape و repositionهای resize/scroll را با cleanup lifecycle مدیریت می‌کند.

Checkpoint بیست‌ونهم: delegation مرکزی action menu به `.message-action` نیز گسترش یافت و دو بلوک initializer پیام در `public/js/group-chat.js` و Blade اصلی حذف شدند. toggle، aria، position، بستن سایر menuها و بستن actionهای معمولی اکنون برای message/post/poll یک مسیر مشترک دارند؛ `.btn-reaction` همچنان باز می‌ماند تا handler تخصصی reaction رفتار قبلی را حفظ کند. قرارداد source نبود lookup و listener per-message را در هر دو منبع کنترل می‌کند.

Checkpoint سی‌ام: delete/report پیام در هر سه تولیدکنندهٔ DOM به قرارداد یکسان `data-group-chat-action` و `data-message-id` منتقل شد. handler اولیهٔ `DOMContentLoaded` در Blade، تعریف `initializeMessageActions` و فراخوانی‌های per-message آن در هر دو renderer حذف شدند؛ edit همچنان از delegation سند و reply/pin از bridge داده‌ای موجود عبور می‌کنند. این تغییر ۲۴۷ خط fallback تکراری را حذف کرد و Blade اصلی از ۳۷۱۲ به ۳۴۷۲ خط کاهش یافت.

Checkpoint سی‌ویکم: runtime ویرایش پیام از Blade اصلی به partial مستقل `message_edit_runtime` منتقل شد. بازکردن modal، submit ویرایش، بستن با دکمه/backdrop و Escape همگی زیر مالکیت `GroupChatLifecycle` قرار گرفتند و cleanup، وضعیت modal و guard راه‌اندازی را بازنشانی می‌کند. قرارداد source از بازگشت listenerهای مستقیم جلوگیری می‌کند و Blade اصلی به ۳۱۷۵ خط کاهش یافت.

Checkpoint سی‌ودوم: تنظیم و راه‌اندازی CKEditor پست از Blade اصلی به partial مستقل `ckeditor_runtime` منتقل شد. polling انتظار برای بارگذاری CKEditor اکنون تحت مالکیت Lifecycle است، پس از موفقیت صریحاً متوقف می‌شود و راه‌اندازی editor با بررسی instance موجود idempotent است. cleanup نمونهٔ متعلق به صفحه و guard را آزاد می‌کند و قرارداد source از بازگشت interval خام جلوگیری می‌کند. Blade اصلی به ۳۰۸۲ خط کاهش یافت.

Checkpoint سی‌وسوم: بلوک legacy پیام شامل قراردادهای صفحه، read-state، debounce ثبت پیام خوانده‌شده، renderer قدیمی پیام، patch محتوای ویرایش‌شده و helperهای escape/text به partial مستقل `legacy_message_runtime` منتقل شد. ترتیب اجرای classic script و APIهای مورد استفادهٔ runtimeهای موجود بدون تغییر حفظ شده‌اند تا این استخراج صرفاً ساختاری باشد؛ مالکیت lifecycle این بلوک در checkpoint جداگانه انجام می‌شود. Blade اصلی از ۳۰۸۲ به ۲۵۶۰ خط کاهش یافت.

Checkpoint سی‌وچهارم: Lifecycle قابلیت لغو صریح timeout ثبت‌شده را دریافت کرد و debounce ثبت آخرین پیام خوانده‌شده از timeout خام به timeout تحت مالکیت صفحه منتقل شد. listener عنصربه‌عنصر لینک پروفایل پیام‌های تازه‌رندرشده نیز با delegation واحد document جایگزین شد؛ cleanup هم timeout معلق و guard راه‌اندازی را آزاد می‌کند. تست واحد `clearTimeout` و قرارداد source از بازگشت این دو الگوی نشت‌پذیر جلوگیری می‌کنند.

Checkpoint سی‌وپنجم: listenerهای jQuery سراسری و listener عنصربه‌عنصر triggerهای modal پست‌های دسته‌بندی به click/keydown delegation زیر مالکیت Lifecycle منتقل شدند. درخواست AJAX فعال پیش از درخواست بعدی و هنگام cleanup لغو می‌شود؛ modal، قفل scroll بدنه و guard نیز در teardown بازنشانی می‌شوند. قرارداد source بازگشت `$(document).on` و listenerهای per-trigger را ممنوع می‌کند.

Checkpoint سی‌وششم: کنترل فرم ویرایش گروه از globalهای آزاد `openGroupEdit`/`cancelGroupEdit` به API محدود و immutable با نام `GroupChatPageChrome` منتقل شد و دکمهٔ لغو باقی‌مانده از `onclick` به action داده‌محور تغییر کرد. اعلان موفقیت session، اسکرول اولیهٔ pinned messages و cleanup فرم نیز تحت Lifecycle قرار گرفتند؛ تعریف تکراری و ناقص `closeAllModals` از Blade حذف و پیاده‌سازی اصلی runtime حفظ شد. Blade اصلی به ۲۵۳۳ خط کاهش یافت.

Checkpoint سی‌وهفتم: کنترل نمایش فرم ویرایش poll از global آزاد `showEditPollBox` به `GroupChatPageChrome` منتقل و action bridge به API محدود متصل شد؛ cleanup تمام فرم‌های باز را می‌بندد. دو helper مردهٔ `togglePollMenu` و `confirmDelete` که هیچ call-site در چت نداشتند همراه سه بلوک script inline حذف شدند. Blade اصلی به ۲۵۰۶ خط کاهش یافت و قرارداد source نبود دوبارهٔ این globalها را تثبیت می‌کند.

Checkpoint سی‌وهشتم: هر سه بلوک CSS باقی‌ماندهٔ صفحه به partialهای مستقل `base_styles`، `message_edit_styles` و `auxiliary_styles` منتقل شدند. includeها دقیقاً در نقاط قبلی باقی مانده‌اند تا ترتیب cascade تغییر نکند و قرارداد source ترتیب آن‌ها و نبود `<style>` در Blade اصلی را کنترل می‌کند. این استخراج ۱۸۵۴ خط CSS را از فایل هماهنگ‌کننده خارج کرد و `chat.blade.php` از ۲۵۰۶ به ۶۵۲ خط کاهش یافت.

Checkpoint سی‌ونهم: hero و خلاصهٔ اطلاعات گروه در نسخه‌های موبایل و دسکتاپ، آمار و actionهای مدیریتی/محتوا از Blade اصلی به partial مستقل `group_hero` منتقل شدند. این checkpoint صرفاً مرزبندی markup است و ترتیب DOM و رفتار موجود را تغییر نمی‌دهد؛ قرارداد source حضور actionهای داده‌محور در partial و نبود card اصلی در فایل هماهنگ‌کننده را کنترل می‌کند. Blade اصلی از ۶۵۲ به ۳۳۸ خط کاهش یافت.

Checkpoint چهلم: آخرین expressionهای تعاملی inline در hero (`@click` و state نمایشی Alpine) با action داده‌محور `toggle-group-hero` و API lifecycle-owned صفحه جایگزین شدند. وضعیت باز/بسته با `aria-expanded`، `hidden` و کلاس CSS همگام است و cleanup آن را به حالت بسته برمی‌گرداند. ممیزی master plan هشت معیار انجام‌شده و چهار معیار باز را ثبت کرد؛ بنابراین برنامهٔ ساختاری ۴۰‌چک‌پوینتی تمام شده اما پذیرش فاز تا رفع شکاف‌های معماری و E2E بسته نشده است.

## اجزای اضافه‌شده

- `ApiClient`: مدیریت CSRF، request id، idempotency key، timeout، retry، JSON و نگاشت خطا.
- `Store`: منبع state قابل اشتراک با subscribe/unsubscribe.
- `Lifecycle`: مالکیت و cleanup متمرکز listener و timer.
- `Realtime reconciler`: تشخیص duplicate و gap در sequence.
- مرزهای دامنه‌ای `Composer`، `Feed`، `Renderer`، `Unread` و `Actions`.
- event delegation مرکزی بر پایهٔ `data-group-chat-action`.

## انتشار تدریجی

پرچم `GROUP_CHAT_FEATURE_MODULAR_FRONTEND_V1` به‌صورت پیش‌فرض خاموش است. در حالت خاموش، رفتار legacy بدون تغییر ادامه دارد. در حالت روشن، `groupChatFetch` درخواست‌ها را به ApiClient مرکزی واگذار می‌کند و `window.GroupChat` API کوچک ماژولار را ارائه می‌دهد.

## تست checkpoint اول

- `npm run test:group-chat`: بیست‌وچهار تست پاس؛ شامل dual-read sequence، renderer مشترک، mutation pipeline، قرارداد delegated action، cleanup lifecycle و قرارداد partialهای منو، جست‌وجو و pin/unpin.
- `npm run build`: build production موفق.
- `node --check public/js/group-chat.js`: موفق.
- `php artisan test tests/Feature/GroupChat tests/Unit/GroupChat`: بیست‌ودو تست پاس؛ شامل شمارندهٔ canonical کامنت در delta.
- `php artisan route:list --name=groups.mark-all-read`: route مورد استفادهٔ ماژول Unread موجود است.
- `git diff --check`: بدون خطای whitespace.

## کار باقی‌مانده تا پایان فاز

1. شکستن کامل مسیر legacy باقی‌مانده در `public/js/group-chat.js` و انتقال ownership به ماژول‌های Composer/Feed/Realtime/Unread/Actions.
2. تبدیل Store به منبع حقیقت تمام stateهای UI و حذف state موازی DOM/global.
3. عبور initial load و همهٔ optimistic/polling/WebSocket mutationها از renderer واحد بدون adapter موازی legacy.
4. ممیزی و حذف globalها، timerها و listenerهای legacy باقی‌مانده و اثبات re-entry بدون تکثیر.
5. آزمون مرورگری ورود مجدد/دوکلاینتی؛ آزمون مرورگر فعلاً به‌علت خطای runtime ابزار blocked است.

## وضعیت آزمون مرورگری

سرور محلی روی `127.0.0.1:8000` پاسخ ۲۰۰ می‌دهد، اما اتصال ابزار Browser به‌دلیل خطای داخلی `failed to write kernel assets` قابل راه‌اندازی نبود. این آزمون «blocked/اجرا نشده» ثبت می‌شود و به‌عنوان pass گزارش نشده است. تست‌های source contract جایگزین موقت‌اند و معادل E2E تعاملی نیستند.

## ارزیابی فعلی معیارهای فاز ۵

- کامل: حذف inline handler، حذف dialog blocking، ApiClient، تست store/reconciliation، مرز اولیه ماژول‌ها و API مالکیت sidecarها.
- ناقص: `group-chat.js` legacy هنوز کامل به ماژول‌های دامنه‌ای شکسته نشده؛ store هنوز منبع حقیقت تمام UI نیست؛ renderer واحد initial load و تمام post/poll/comment را کامل مالک نیست؛ lifecycle هنوز همه timer/listener/globalهای legacy را جمع نکرده است.
- انجام‌شده: شکستن Blade اصلی به فایل هماهنگ‌کنندهٔ ۳۳۸ خطی و partialهای مستقل.
- انجام‌نشده: تست مرورگری ورود مجدد/two-client.
- تصمیم‌گیری‌شده: مهاجرت تدریجی TypeScript به‌صورت contract-first؛ اجرای تبدیل پس از دروازه‌های ثبت‌شده در سند تصمیم.
4. شکستن Blade بزرگ به partial/componentهای کوچک.
5. تعیین و اعمال API مالکیت محدود برای voice recorder و chat features.
6. تکمیل تست‌های DOM و آزمون مرورگری دوکلاینتی.

## محدودیت شناخته‌شده

mutationهای outbox نسخهٔ feed item را افزایش می‌دهند اما sequence ایجاد اولیه را حفظ می‌کنند. endpoint فعلی delta فقط `sequence > cursor` را برمی‌گرداند؛ بنابراین بازیابی mutation از دست‌رفته صرفاً با cursor عددی ممکن نیست. تا اصلاح قرارداد cursor به `(sequence, version)` یا افزودن mutation log، مسیر WebSocket قدیمی و polling مسئول بازیابی mutation باقی می‌ماند و پرچم modular frontend پیش‌فرض خاموش است.
