# طراحی تجربه یکپارچه گروه و Group Control Center

**تاریخ:** 2026-08-27  
**Branch:** `agent/pre-main-ui-polish`  
**وضعیت:** Design approved in conversation; implementation not started  

## 1. هدف

هدف این بازطراحی، تبدیل تجربه گروه در EarthCoop به یک مسیر روشن، حرفه‌ای و استاندارد است؛ به‌گونه‌ای که کاربر برای استفاده روزمره مستقیماً وارد گفت‌وگو شود، Hero چت شلوغ نباشد، همه قابلیت‌های گروه در یک Control Center قابل فهم و دسته‌بندی باشند، و صفحه `groups/{id}` به داشبورد تحلیلی/گزارشی واقعی گروه تبدیل شود.

اصل کلیدی این مرحله: **هیچ capability، route، policy یا دسترسی فعلی نباید در بازطراحی گم یا بی‌اجازه گسترده شود.** تغییرات تا حد ممکن UI/UX و view-level هستند و backend عمیق فقط در صورت نیاز اثبات‌شده لمس می‌شود.

## 2. تصمیم‌های محصولی قطعی

1. مسیر پرتکرار از «گروه‌های من» مستقیماً به `groups/chat/{id}` می‌رود.
2. `groups/{id}` دیگر lobby قبل از Chat نیست؛ داشبورد وضعیت، شاخص‌ها و گزارش‌های گروه است.
3. Chat شخصیت app-like مستقل خود را حفظ می‌کند.
4. Hero چت سبک و عملیاتی می‌شود؛ کنترل‌های مدیریتی پراکنده از آن جمع می‌شوند.
5. یک **Group Control Center** واحد، مرکز دسترسی به قابلیت‌های گروه در محیط Chat است.
6. Control Center در موبایل به شکل Bottom Sheet حدود 85–90٪ ارتفاع و در دسکتاپ به شکل پنل عریض/Modal حرفه‌ای نمایش داده می‌شود؛ محتوا و منطق هر دو یکی است.
7. Control Center حتی‌المقدور تب‌بندی می‌شود، نه مجموعه‌ای طولانی از سکشن‌های زیر هم.
8. تب‌های اصلی: **محتوا، اعضا، حکمرانی، ابزارها**.
9. دبیرخانه گروه برای همه اعضای گروه discoverable است؛ محتوای قابل مشاهده داخل دبیرخانه تابع Policy موجود است.
10. پنل نجم هدا فقط برای مدیر و بازرس قابل مشاهده است.
11. مدیریت حساب و امور مالی گروه در نجم بهار یک ابزار مستقل و مهم است و نباید به یک لینک مبهم یا حذف‌شده تقلیل یابد.
12. هیچ merge به `main` در این مرحله انجام نمی‌شود.

## 3. معماری تجربه کاربری

### 3.1 مسیر اصلی کاربر

```text
گروه‌های من
    ↓
گفت‌وگوی گروه (Chat)
    ↓
Hero سبک + دکمه «پنل گروه»
    ↓
Group Control Center
    ├── محتوا
    ├── اعضا
    ├── حکمرانی
    └── ابزارها
           ├── داشبورد و گزارش‌های گروه
           ├── دبیرخانه گروه
           └── حساب و امور مالی گروه — نجم بهار
```

برای مدیر/بازرس، در تب حکمرانی «نجم هدا» نیز اضافه می‌شود.

### 3.2 Hero چت

Hero باید در بالاترین سطح، فقط اطلاعات و actionهای ضروری را نشان دهد:

- آواتار و نام گروه
- نقش کاربر
- وضعیت عضویت
- تعداد اعضا و در صورت نیاز مهمان‌ها
- اطلاعات کوتاه contextual مانند سطح/مکان
- CTA اصلی و واضح: **پنل گروه**

Hero نباید تبدیل به داشبورد مدیریتی شود. actionهای پرتکرار بسیار محدود می‌توانند باقی بمانند، اما اصل بر انتقال actionهای مدیریتی و کم‌تکرار به Control Center است.

### 3.3 Group Control Center

یک component واحد با adaptive presentation:

- **Mobile:** Bottom Sheet با ارتفاع حدود 85–90vh، handle واضح، header ثابت، tabs قابل لمس، scroll داخلی.
- **Desktop:** پنل یا Modal عریض، center-aligned، با همان tabs و همان محتوای داخلی.

الزامات UX:

- tab bar واضح و همیشه در دسترس باشد.
- وضعیت active tab مشخص باشد.
- focus management و keyboard navigation در دسکتاپ رعایت شود.
- بستن با دکمه close، Escape و interaction استاندارد overlay انجام شود.
- scroll داخل پنل از scroll Chat مستقل باشد.
- باز و بسته‌شدن پنل نباید state یا scroll Chat را خراب کند.
- accessibility labels برای tabها و icon-only controls لحاظ شود.

## 4. مدل تب‌ها

### تب «محتوا»

مناسب برای کارهایی که مستقیماً به تولید و مرور محتوای گروه مربوط‌اند:

- ایجاد پست
- مرور/دسترسی به پست‌ها
- ساخت نظرسنجی
- مرور نظرسنجی‌ها
- انتخابات جاری/شرکت در انتخابات در صورتی که از نظر UX به جریان محتوایی مربوط باشد
- پیام‌ها یا موارد سنجاق‌شده در صورت نیاز به shortcut

اصل: actionهای فوری محتوا در Hero فقط در صورت اثبات پرتکراربودن باقی می‌مانند؛ منبع canonical کنترل‌ها Control Center است.

### تب «اعضا»

- فهرست اعضا
- مدیران
- بازرسان
- نقش‌ها و وضعیت‌ها
- مدیریت اعضا برای نقش مجاز
- افزودن مهمان برای نقش مجاز
- درخواست/چت مدیران در صورت وجود capability فعلی
- profile navigation اعضا

هیچ role یا permission جدیدی در UI ساخته نمی‌شود؛ visibility باید از authorization موجود تبعیت کند.

### تب «حکمرانی»

- انتخابات جاری
- ایجاد/مدیریت انتخابات برای نقش‌های مجاز
- نشست گروه و فعال/غیرفعال‌کردن آن برای نقش مجاز
- مدیریت مشارکت نشست
- گزارش‌ها و moderation tools
- تنظیمات گروه
- ویرایش گروه
- سایر کنترل‌های governance موجود
- **پنل نجم هدا** فقط برای مدیر/بازرس

### تب «ابزارها»

- **داشبورد و گزارش‌های گروه** → `groups/{id}`
- **دبیرخانه گروه** → برای همه اعضا discoverable؛ visibility اسناد تابع Policy دبیرخانه
- **حساب و امور مالی گروه — نجم بهار** → داشبورد مالی گروه
- هر ابزار مستقل دیگر فقط در صورت وجود capability فعلی و نیاز روشن

نجم بهار باید کاربر را به dashboard مالی واقعی گروه ببرد، نه اینکه عملیات مالی دوباره در Chat بازسازی شوند. dashboard فعلی شامل نمای کلی حساب، کیف پول، انتقال وجه، حساب‌های فرعی و سوابق تراکنش‌هاست.

## 5. نقش صفحه `groups/{id}`

این صفحه به **Group Dashboard** تبدیل می‌شود و باید روی «فهم وضعیت گروه» تمرکز کند، نه اجرای عملیات روزمره.

محتوای مناسب:

- هویت و توضیح گروه
- شاخص‌های عضویت و ساختار
- مدیران و بازرسان
- وضعیت و فعالیت‌های اخیر
- شاخص‌های مشارکت، پست، نظرسنجی و انتخابات
- اطلاعات و شاخص‌های مالی در حد summary، بدون تکرار dashboard نجم بهار
- shortcut به دبیرخانه
- shortcut به نجم بهار
- shortcut به نجم هدا برای مدیر/بازرس
- CTA اصلی: **گفت‌وگوی گروه / بازگشت به گفت‌وگو**

از این صفحه actionهای تکراری که Control Center محل طبیعی آن‌هاست حذف یا به shortcut استاندارد تبدیل می‌شوند.

## 6. Capability Preservation Matrix

قبل از جابه‌جایی هر کنترل، implementation باید inventory کامل از Hero فعلی، `group_info_panel`, `groups/{id}`, routeها و policyها بسازد. هر capability باید یک destination نهایی و permission source مشخص داشته باشد.

ماتریس پایه‌ی تأییدشده تا این لحظه:

| Capability | محل فعلی نمونه | محل نهایی | visibility / permission |
|---|---|---|---|
| ایجاد پست | Hero | محتوا | همان شرط فعلی |
| ساخت نظرسنجی | Hero | محتوا | همان شرط فعلی |
| شرکت در انتخابات | Hero | محتوا یا حکمرانی | همان election policy فعلی |
| افزودن/مدیریت انتخابات | Hero/Panel | حکمرانی | مدیر/بازرس طبق شرط فعلی |
| مدیریت اعضا | Hero/Panel | اعضا | مدیر طبق شرط فعلی |
| گزارش‌ها/moderation | Hero | حکمرانی | همان شرط فعلی |
| تنظیمات گروه | Hero | حکمرانی | همان policy فعلی |
| خروج از گروه | Hero/Panel | Control Center footer یا منوی contextual | عضو مجاز |
| ویرایش گروه | Panel | حکمرانی | همان شرط فعلی |
| افزودن مهمان | Panel | اعضا | همان شرط فعلی |
| درخواست چت مدیران | Panel | اعضا/حکمرانی | همان شرط فعلی |
| فعال/غیرفعال‌کردن نشست | Panel | حکمرانی | مدیر/بازرس طبق شرط فعلی |
| مدیریت مشارکت نشست | Panel | حکمرانی | مدیر/بازرس طبق شرط فعلی |
| فهرست اعضا | Panel | اعضا | اعضای مجاز |
| مدیران/بازرسان | Panel | اعضا | اعضای مجاز |
| پست‌ها | Panel | محتوا | اعضای مجاز |
| نظرسنجی‌ها | Panel | محتوا | اعضای مجاز |
| آمار و گزارش‌گیری | Panel | داشبورد یا حکمرانی | مدیر طبق شرط فعلی |
| داشبورد گروه | `groups/{id}` | ابزارها + route مستقل | عضو مجاز |
| دبیرخانه گروه | route مستقل | ابزارها + shortcut dashboard | همه اعضا discoverable؛ content policy-controlled |
| نجم هدا گروه | route مستقل / dashboard | حکمرانی + shortcut dashboard | مدیر/بازرس |
| نجم بهار گروه | route مستقل / dashboard | ابزارها + shortcut dashboard | authorization فعلی نجم بهار |
| کیف پول گروه | نجم بهار | داخل نجم بهار | authorization فعلی |
| انتقال وجه گروه | نجم بهار | داخل نجم بهار | authorization فعلی |
| حساب‌های فرعی | نجم بهار | داخل نجم بهار | authorization فعلی |
| سوابق تراکنش‌ها | نجم بهار | داخل نجم بهار | authorization فعلی |

این ماتریس **حداقل** است؛ implementation قبل از حذف یا جابه‌جایی UI باید هر کنترل دیگری را هم کشف و ثبت کند. هیچ کنترل بدون destination نهایی حذف نمی‌شود.

## 7. مسیر «گروه‌های من»

هر لینک گروه فعال و قابل ورود از صفحه «گروه‌های من» باید مستقیم به `groups.chat` برود. مسیرهای pending/disabled/restoration باید رفتار فعلی خود را حفظ کنند و صرفاً با هدف direct-to-chat دستکاری نشوند.

اگر جایی دیگر در سایت لینک «ورود به گروه» با semantics متفاوت وجود دارد، باید جداگانه بررسی شود؛ این تصمیم به‌طور خودکار همه لینک‌های گروه در کل سایت را بازنویسی نمی‌کند.

## 8. Authorization و امنیت

- UI هرگز جای backend authorization را نمی‌گیرد.
- routeها و controllerها باید همان policy/gate فعلی را enforce کنند.
- visibility جدید فقط باید بر اساس permission source موجود محاسبه شود.
- دبیرخانه: entry visible برای همه members؛ داده‌های داخل ماژول تابع policy خود دبیرخانه.
- نجم هدا: entry فقط مدیر/بازرس.
- نجم بهار: entry و operations مطابق authorization موجود Najm Bahar.
- هیچ لینک مخفی‌شده‌ای نباید به معنای دسترسی backend جدید باشد.

## 9. معماری فنی پیشنهادی

اصل YAGNI: بازطراحی با کمترین refactor ممکن انجام شود.

ترجیح:

1. `group_info_panel.blade.php` به‌جای ساخت component کاملاً جدید، به shell جدید Control Center تبدیل شود یا به partialهای کوچک‌تر تقسیم شود اگر حجم فایل مانع تست‌پذیری باشد.
2. presentation موبایل/دسکتاپ با CSS responsive روی یک DOM/semantic structure مشترک انجام شود؛ دو implementation جدا ساخته نشود.
3. Hero فعلی فقط slim شود؛ controllerهای working Chat و lifecycle موجود دست‌نخورده بمانند مگر جایی که action relocation الزام ایجاد کند.
4. action identifiers فعلی (`data-chat-page-action`) در صورت امکان حفظ شوند تا JavaScript behavior نشکند.
5. routeهای سالم فعلی برای Najm Hoda، Secretariat، Najm Bahar و Group Dashboard reuse شوند.
6. backend data loading داخل Blade، در صورتی که صرفاً برای tabهای منتقل‌شده لازم است، در این مرحله فقط به اندازه ضرورت تغییر کند. refactor عمیق query/controller خارج از scope است مگر performance/regression واقعی اثبات شود.

## 10. Accessibility و responsive standards

- Bottom Sheet دارای dialog semantics و label روشن باشد.
- tabs با `role=tablist`, `role=tab`, `aria-selected`, `aria-controls` یا معادل semantic استاندارد ساخته شوند.
- focus هنگام بازشدن وارد پنل شود و هنگام بستن به trigger برگردد.
- Escape در دسکتاپ پنل را ببندد.
- targetهای لمسی حداقل حدود 44px باشند.
- tab bar روی موبایل در صورت کمبود عرض horizontal-scroll یا layout مناسب داشته باشد، نه shrink ناخوانا.
- dark mode و RTL فعلی حفظ شوند.
- هیچ تغییر ناخواسته در Hero sticky/header reveal behavior اخیر ایجاد نشود.

## 11. Performance

- بازکردن Control Center نباید request سنگین جدید یا reload صفحه ایجاد کند مگر capability مقصد ذاتاً route مستقل باشد.
- tab switching client-side و فوری باشد.
- queryهای موجود `group_info_panel` باید در implementation بررسی شوند؛ اگر تمام تب‌ها upfront داده سنگین می‌کشند، فقط در صورت نیاز و بدون توسعه backend عمیق، loading سبک‌تر پیشنهاد شود.
- CKEditor و assets سنگین نباید به Control Center منتقل یا eager-load شوند مگر tab مربوط واقعاً آن‌ها را لازم داشته باشد.

## 12. Testing و UAT

### Regression contracts

حداقل باید پوشش داده شود:

- لینک گروه فعال از «گروه‌های من» به Chat می‌رود.
- Hero فقط actionهای نهایی تأییدشده را دارد.
- هیچ capability inventory شده بدون مقصد نهایی حذف نشده است.
- tabs بر اساس role درست render می‌شوند.
- نجم هدا برای نقش غیرمجاز دیده نمی‌شود.
- دبیرخانه برای member قابل کشف است.
- نجم بهار route صحیح گروه را باز می‌کند.
- dashboard route از Control Center قابل دسترسی است.
- actionهای موجود `data-chat-page-action` بعد از relocation همچنان کار می‌کنند.

### UAT موبایل

- ورود مستقیم از groups list به Chat
- بازکردن Bottom Sheet
- جابه‌جایی بین چهار tab
- scroll داخلی پنل بدون حرکت Chat پشت آن
- بستن پنل و حفظ موقعیت Chat
- role-based visibility
- دسترسی به Dashboard, Secretariat, Najm Bahar و Najm Hoda برای نقش مناسب

### UAT دسکتاپ

- پنل عریض بدون overflow
- keyboard/escape/focus
- tabs و actionها
- route navigation صحیح
- Hero slim و بدون ازدحام

### Final gates

- Group Chat JS tests
- Group Chat PHP/regression tests
- relevant role/policy tests
- frontend build
- Full Validation
- manual UAT mobile + desktop

## 13. Non-goals

این مرحله شامل موارد زیر نیست:

- بازنویسی backend انتخابات
- بازنویسی دبیرخانه
- توسعه قابلیت جدید نجم هدا
- بازنویسی حسابداری نجم بهار
- تغییر policyهای حقوقی/دسترسی موجود بدون نیاز مستقل
- merge به `main`

## 14. معیار اتمام

این بازطراحی زمانی complete تلقی می‌شود که:

1. کاربر از «گروه‌های من» مستقیم وارد Chat شود.
2. Hero چت سبک، واضح و غیرشلوغ باشد.
3. Control Center موبایل/دسکتاپ با چهار تب نهایی کار کند.
4. تمام قابلیت‌های inventory شده محل روشن داشته باشند.
5. Dashboard، Secretariat و Najm Bahar برای اعضای مجاز قابل کشف باشند.
6. Najm Hoda فقط برای مدیر/بازرس قابل کشف باشد.
7. هیچ regression در Chat، انتخابات، دبیرخانه، نجم هدا یا نجم بهار ایجاد نشده باشد.
8. Full Validation و UAT نهایی سبز باشند.
9. هیچ merge به `main` بدون تصمیم صریح کاربر انجام نشده باشد.
