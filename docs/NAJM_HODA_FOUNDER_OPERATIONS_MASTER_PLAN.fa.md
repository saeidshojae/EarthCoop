# نقشه مادر مدیریت کل EarthCoop توسط نجم هدا

## هدف

Founder Operations یک پنل یا اتصال موردی نیست. این لایه، چارچوب مدیریت یکپارچه EarthCoop است تا همه بخش‌های فعلی و آینده سامانه بتوانند زیر مدیریت نجم هدا قرار بگیرند، بدون آن‌که برای هر ماژول معماری جداگانه ساخته شود.

اصل توسعه:

> هر دامنه باید از مسیر مشترک `observe -> summarize -> triage -> propose -> act` وارد مدیریت نجم هدا شود.

هیچ دامنه‌ای صرفاً به دلیل متصل بودن به Runtime مجاز به اقدام خودکار نیست. سطح اقدام مستقل از سطح مشاهده است و طبق Risk/Approval/Delegation کنترل می‌شود.

---

## قرارداد استاندارد هر دامنه مدیریتی

هر دامنه در `config/najm-hoda-founder-operations.php` ثبت می‌شود و حداقل این مشخصات را دارد:

- `key`: شناسه پایدار دامنه؛
- `label`: نام قابل نمایش؛
- `priority`: اولویت تکمیل اتصال از 1 تا 10؛
- `integration_stage`: یکی از `planned`, `mapped`, `observed`, `managed`؛
- `risk`: سطح ریسک مدیریتی؛
- `sources`: مدل‌ها، جداول، سرویس‌ها یا کنترلرهای منبع؛
- `event_prefixes`: خانواده رخدادهایی که نجم هدا باید ببیند؛
- `capabilities`: قابلیت‌های عملی متصل‌شده.

### مراحل اتصال

1. **planned** — دامنه در نقشه کل ثبت شده اما هنوز ورودی Runtime ندارد.
2. **mapped** — داده‌ها، رخدادها، صف‌های کاری و نقاط اقدام شناسایی شده‌اند.
3. **observed** — Runtime Event یا read-model قابل اتکا وجود دارد و نجم هدا می‌تواند وضعیت دامنه را ببیند.
4. **managed** — علاوه بر مشاهده، خلاصه‌سازی، triage، پیشنهاد اقدام و عملیات کم‌خطر مجاز تحت Safety Gate فراهم شده است.

---

## مدل اختیار

### Observe
خواندن رخداد، وضعیت، آمار و backlog. بدون تغییر داده.

### Summarize
تبدیل داده خام به گزارش مدیریتی و KPI.

### Triage
تشخیص P0/P1/P2/P3، ریسک، نیاز به اقدام و owner احتمالی.

### Propose
ساخت پیشنهاد مشخص برای مدیرکل، بدون اجرای mutation.

### Act
فقط برای عملیاتی که قرارداد Capability، Risk Policy و Delegation اجازه می‌دهند. عملیات مالی، مالکیتی، حکمرانی، ارسال انبوه، انتشار حساس و تغییرات تنظیمات کلیدی باید fail-closed باشند مگر صریحاً مجاز شوند.

---

## Founder Attention Queue

`FounderAttentionService` باید خروجی کامل سامانه را به یک صف کوتاه مدیریتی تبدیل کند:

- **P0**: بحران سیستم، فساد داده، امنیت، شکست حیاتی؛
- **P1**: موضوع مهمی که تصمیم یا دخالت مدیرکل می‌خواهد؛
- **P2**: backlog عملیاتی مانند تأیید داده پایه؛
- **P3**: اطلاع، روند، رشد، و پیشنهاد بهبود.

هدف این نیست که مدیرکل همه رخدادها را ببیند؛ هدف این است که نجم هدا همه را ببیند و فقط موارد لازم را بالا بیاورد.

---

## دامنه‌های ثبت‌شده در نقشه

### هسته عملیات
- Users & Membership
- Support & Tickets
- Occupational / Experience Reference Data
- Location Reference Data
- Runtime Health

### جامعه و حکمرانی
- Groups & Community Operations
- Governance & Elections
- Invitations & Growth
- Reports / Moderation / Reputation

### ارتباطات و محتوا
- Secretariat & Correspondence
- Email & System Mail Configuration
- Blog & Editorial Operations
- Pages / Knowledge Base / Published Content
- Notifications & Announcements

### مالی و مالکیت
- Najm Bahar Finance
- Stock / Auctions / Settlement

### زیرساخت مدیریتی
- System & Admin Configuration
- Roles / Permissions
- Group Settings
- Realtime Settings

این فهرست بسته نیست. هر ماژول جدید EarthCoop باید پیش از کامل شدن، به همین رجیستری اضافه شود.

---

## وضعیت فعلی Founder Operations

### Observed / Managed
- Runtime Health — managed
- Users — observed + summary/triage پایه
- Support — observed + summary/triage پایه
- Reference approvals — observed + summary/triage پایه
- Location approvals — observed + summary/triage پایه
- Email templates — observed
- Blog posts — observed
- Stock lifecycle events — observed

### Already instrumented elsewhere and scheduled for Founder read-model completion
- Groups
- Elections / polls
- Secretariat
- Najm Bahar
- Generic content events

### Planned
- Notifications / announcements
- Reports / moderation / reputation
- Invitations / growth
- Admin/system configuration

---

## ترتیب توسعه پیشنهادی

### Wave F1 — Unified Attention Layer
تکمیل Founder Attention Queue و یک خروجی واحد برای داشبورد/چت نجم هدا.

### Wave F2 — Complete existing observed domains
برای Users, Support, Email, Blog, Stock و approval queues، read-model و پیشنهاد اقدام تکمیل شود.

### Wave F3 — Governance and community
Groups، Elections، reports و moderation وارد snapshot و attention شوند. اعمال حکمرانی فقط با approval صریح.

### Wave F4 — Finance and ownership
Najm Bahar و Stock به KPI، anomaly detection، settlement visibility و proposal layer متصل شوند. عملیات مالی/مالکیتی به‌صورت پیش‌فرض بدون اختیار اجرا باقی بمانند.

### Wave F5 — Communications and content operations
Secretariat، Email، Blog، Notifications و Knowledge Base به workflow مدیریتی مشترک متصل شوند؛ شامل drafting، review queue و publication/send approval.

### Wave F6 — Admin and configuration
تنظیمات سیستم، نقش‌ها، دسترسی‌ها، realtime، group settings و operational configuration به inventory و drift detection وصل شوند. تغییرات حساس نیازمند approval باشند.

### Wave F7 — Self-expanding management coverage
برای هر ماژول جدید، CI/test باید نبودن domain registration یا contract پایه را گزارش کند تا هیچ بخش جدیدی بیرون از دایره مدیریت نجم هدا رشد نکند.

---

## معیار پایان هر دامنه

یک دامنه زمانی `managed` محسوب می‌شود که:

1. منابع داده‌اش مشخص باشند؛
2. رخدادهای مهمش در Runtime قابل مشاهده باشند؛
3. read-model مدیریتی داشته باشد؛
4. backlog و anomalyهایش قابل تشخیص باشند؛
5. P0/P1/P2/P3 تولید کند؛
6. پیشنهاد اقدام قابل توضیح بسازد؛
7. mutationهای آن capability contract داشته باشند؛
8. risk و approval policy مشخص باشد؛
9. تست instrumentation و safety وجود داشته باشد؛
10. در Founder Snapshot و coverage report دیده شود.

---

## اصل نهایی

نجم هدا باید به نقطه‌ای برسد که «پنل ادمین» فقط یکی از رابط‌های مدیریت باشد، نه مرکز واقعی مدیریت. مرکز واقعی مدیریت، لایه یکپارچه نجم هدا خواهد بود؛ پنل ادمین، چت مدیرکل، داشبورد، n8n و سایر رابط‌ها همگی مصرف‌کننده همان وضعیت، قراردادها و تصمیم‌های مدیریتی مشترک خواهند بود.
