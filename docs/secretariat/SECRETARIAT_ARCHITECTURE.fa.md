# معماری ماژول مستقل دبیرخانه EarthCoop

## 1. تصمیم معماری
دبیرخانه باید به‌عنوان یک bounded module مستقل با نام مفهومی **Secretariat / Registry** طراحی شود، نه به‌عنوان «مدیریت فایل‌های گروه» و نه به‌عنوان چند جدول کمکی در ماژول Groups.

دلیل: دبیرخانه مرجع رسمی ثبت، اعتبار، تاریخچه، ارجاع، نسخه‌بندی و بایگانی رکوردهای سازمانی است. فایل فقط یک پیوست به رکورد ثبتی است.

## 2. مأموریت ماژول
ماژول دبیرخانه حافظه رسمی و قابل ممیزی هر واحد سازمانی EarthCoop است و باید بتواند ثبت و پیگیری موارد زیر را پوشش دهد:
- نامه وارده
- نامه صادره
- مکاتبه داخلی
- صورتجلسه
- مصوبه
- تصمیم رسمی
- قرارداد
- تفاهم‌نامه
- توافق
- آیین‌نامه/دستورالعمل
- گزارش رسمی
- ابلاغ
- یادداشت رسمی
- اسناد مالی یا اجرایی لازم‌الثبت
- پرونده/دوسیه مرکب از چند رکورد
- پیوست‌ها و نسخه‌های مرتبط

## 3. اصل بنیادی: Record First, File Second
واحد اصلی سیستم «رکورد ثبتی» است، نه فایل.

هر رکورد می‌تواند:
- متن ساختاریافته داشته باشد؛
- صفر یا چند فایل پیوست داشته باشد؛
- به یک یا چند رکورد دیگر ارجاع دهد؛
- از یک رویداد بیرونی مانند جلسه یا پیام ایجاد شده باشد؛
- مسئول/مالک/طرف‌های مرتبط داشته باشد؛
- سطح دسترسی و محرمانگی داشته باشد؛
- وضعیت lifecycle مستقل داشته باشد؛
- audit trail غیرقابل حذف داشته باشد.

## 4. مرز دامنه
### داخل Secretariat
- شماره ثبت و numbering policy
- نوع رکورد
- عنوان و موضوع
- خلاصه/متن رسمی
- status و lifecycle
- سطح اعتبار
- محرمانگی و visibility
- origin/source reference
- روابط بین رکوردها
- نسخه‌بندی
- پیوست‌ها
- ارجاع/ابلاغ
- audit trail
- جست‌وجو و بایگانی
- retention/archival policy در آینده

### خارج از Secretariat
- خود چت و پیام‌رسانی
- اجرای جلسه
- موتور انتخابات
- اجرای Action Item
- تراکنش مالی
- storage عمومی فایل‌های غیرثبتی

این ماژول به این حوزه‌ها لینک می‌شود ولی مالک منطق آن‌ها نیست.

## 5. مدل مالکیت
دبیرخانه نباید فقط به Group محدود شود. طراحی پیشنهادی owner polymorphic است:

- owner_type
- owner_id

در MVP، owner اصلی Group خواهد بود؛ اما معماری باید از ابتدا امکان مالکیت برای Project، Company/LegalEntity، Committee و سایر واحدهای آینده را داشته باشد.

## 6. موجودیت‌های پیشنهادی
### SecretariatRecord
هسته اصلی ثبت.
فیلدهای مفهومی:
- id
- owner_type / owner_id
- registry_number
- record_type
- title
- subject
- summary
- body/content
- status
- confidentiality
- effective_at
- registered_at
- registered_by
- approved_by
- source_type / source_id یا relation polymorphic
- metadata
- created_at / updated_at

### SecretariatRecordVersion
نسخه‌های محتوایی رکورد؛ نسخه رسمی نباید با overwrite خام از بین برود.

### SecretariatAttachment
پیوست‌های رکورد با metadata، checksum، mime/type، uploader و version relation.

### SecretariatRelation
روابط معنایی بین رکوردها، برای مثال:
- derived_from
- refers_to
- supersedes
- amends
- implements
- responds_to
- attached_to_case
- decision_of
- action_of

### SecretariatParty
فرستنده/گیرنده/طرف قرارداد/طرف تفاهم/ذی‌نفع، با امکان user/group/external party.

### SecretariatDispatch / Referral
ارجاع، ابلاغ و گردش رسمی رکورد میان مسئولان یا واحدها.

### SecretariatAuditEvent
تاریخچه append-only از ایجاد، ویرایش، ثبت، تأیید، ابلاغ، ارجاع، ابطال، بایگانی و دسترسی‌های حساس.

### SecretariatCase
پرونده اختیاری برای تجمیع چند رکورد پیرامون یک موضوع یا فرایند.

## 7. Lifecycle پیشنهادی
حداقل چرخه عمومی:

`draft → pending_approval → registered → dispatched/active → closed → archived`

و مسیرهای استثنا:
- rejected
- cancelled
- superseded
- voided

ثبت رسمی و تأیید باید از ویرایش روزمره جدا باشد. رکورد registered نباید بی‌ردپا rewrite شود؛ تغییرات بعدی باید version/amendment تولید کنند.

## 8. شماره ثبت
شماره ثبت یک concern مستقل است و نباید از ID دیتابیس استفاده شود.

الگوی نهایی باید configurable باشد، مثلاً بر اساس:
- owner/group
- year
- record type
- sequence

مثال مفهومی:
`SEC-G123-2026-RES-000042`

فرمت قطعی باید بعداً با نیازهای فارسی/بین‌المللی و قواعد حقوقی تصمیم‌گیری شود.

## 9. سطح دسترسی
حداقل سطوح مفهومی:
- public_group: قابل مشاهده برای اعضای مجاز گروه
- leadership: مدیر/بازرس
- restricted: افراد/نقش‌های مشخص
- confidential: دسترسی ویژه ثبت‌شده

هر دسترسی حساس باید قابل audit باشد. RBAC موجود EarthCoop مرجع احراز نقش باقی می‌ماند و Secretariat policy دامنه‌ای خود را روی آن اعمال می‌کند.

## 10. یکپارچگی با نجم هدا
نجم هدا «دبیر هوشمند» است، نه مرجع نهایی اعتبار.

نجم هدا می‌تواند:
- موارد لازم‌الثبت را پیشنهاد کند؛
- از صورتجلسه، تصمیم و Action Item پیش‌نویس رکورد بسازد؛
- نوع و موضوع سند را پیشنهاد دهد؛
- سوابق و رکوردهای مرتبط را پیدا کند؛
- نامه یا پاسخ پیشنهادی تولید کند؛
- تعهدات، سررسیدها و ارجاعات باز را هشدار دهد؛
- خلاصه پرونده و timeline بسازد؛
- نقص اطلاعات یا تعارض نسخه را گوشزد کند.

اما عملیات دارای اثر رسمی مانند registration/approval/dispatch باید با policy و در صورت لازم تأیید انسانی انجام شود.

## 11. اتصال به سیستم جلسه موجود
زنجیره هدف:

`GroupSession → evidence → meeting minute draft → confirmed decisions → approved minute → Secretariat registration`

پس از تأیید صورتجلسه، نجم هدا می‌تواند پیشنهاد کند:
- ثبت صورتجلسه در دبیرخانه؛
- تبدیل تصمیمات منتخب به «مصوبه» مستقل؛
- لینک Action Itemهای اجرایی به مصوبه مرجع.

زنجیره قابل ممیزی:
`جلسه → صورتجلسه ثبت‌شده → مصوبه → اقدام → نامه/ابلاغ → گزارش انجام`

## 12. اتصال به Action Items
Action Item مالک اجرای کار است و Secretariat مالک سند رسمی نیست.

رابط پیشنهادی:
- action item می‌تواند secretariat_record_id یا relation به مصوبه/تصمیم مرجع داشته باشد؛
- پایان اقدام می‌تواند «گزارش انجام» پیشنهادی برای دبیرخانه تولید کند؛
- status اقدام نباید متن تاریخی مصوبه را تغییر دهد.

## 13. مکاتبات
برای نامه وارده/صادره باید حداقل این داده‌ها پشتیبانی شود:
- direction: incoming/outgoing/internal
- sender/recipients
- subject
- body
- attachments
- received/sent date
- external reference number
- internal registry number
- reply_to / responds_to
- dispatch/referral trail
- delivery status در آینده

ارسال فیزیکی/ایمیل/API یک concern integration است و نباید هسته Registry را به provider خاص قفل کند.

## 14. جست‌وجو و بازیابی
MVP باید جست‌وجوی حداقل روی این ابعاد را هدف بگیرد:
- شماره ثبت
- عنوان/موضوع
- نوع رکورد
- بازه تاریخ
- ثبت‌کننده/طرف‌ها
- وضعیت
- برچسب/metadata
- رکورد مرجع یا پرونده

Full-text search و semantic retrieval نجم هدا می‌تواند در مرحله بعد روی همین مدل استاندارد سوار شود.

## 15. قواعد ایمنی و اعتبار
- ثبت رسمی بدون actor معتبر ممنوع.
- authorization همیشه server-side.
- حذف فیزیکی رکورد registered در مسیر عادی ممنوع؛ void/archive استفاده شود.
- audit eventها append-only باشند.
- فایل‌های حساس checksum و metadata داشته باشند.
- تغییرات محتوای ثبت‌شده از طریق version/amendment انجام شود.
- هر خودکارسازی نجم هدا باید source/evidence و actor/confirmation را ثبت کند.

## 16. UI/UX پیشنهادی
دبیرخانه باید یک ماژول کامل داشته باشد، اما از کنسول نجم هدا نیز surface شود.

صفحه اصلی دبیرخانه:
- inbox/وارده
- outbox/صادره
- ثبت‌های داخلی
- صورتجلسات و مصوبات
- قراردادها و تفاهمات
- پرونده‌ها
- نیازمند اقدام/تأیید
- جست‌وجوی سریع

در کنسول نجم هدا:
- ثبت سند جدید
- ثبت صورتجلسه/مصوبه
- نامه وارده
- تهیه نامه صادره
- جست‌وجوی دبیرخانه
- موارد منتظر تأیید/ارجاع
- پرونده‌های باز

فرم‌ها باید guided باشند و برای عملیات رسمی preview + confirm داشته باشند.

## 17. فازبندی پیشنهادی
### Phase S0 — قرارداد معماری
- تثبیت terminology
- enum/type taxonomy اولیه
- lifecycle
- permission matrix
- relation model
- numbering strategy

### Phase S1 — Registry Core MVP
- records
- versions
- attachments
- relations
- audit
- registration/approval
- group owner

### Phase S2 — Meetings Integration
- ثبت صورتجلسه تأییدشده
- ثبت مصوبات منتخب
- لینک action item ↔ resolution

### Phase S3 — Correspondence
- incoming/outgoing/internal
- parties
- referral/dispatch

### Phase S4 — Case & Search
- case files
- advanced filters
- timeline

### Phase S5 — Najm Hoda Secretariat Agent
- guided create
- semantic retrieval
- auto-draft
- proactive missing/overdue registry attention

## 18. مواردی که فعلاً نباید انجام شوند
- تبدیل دبیرخانه به یک file browser ساده
- coupling مستقیم به UI یک گروه
- وابستگی core به LLM
- overwrite اسناد ثبت‌شده
- ادغام انتخابات سیستمی با دبیرخانه
- طراحی workflow حقوقی خاص یک کشور پیش از تعیین حوزه قضایی

## 19. معیار موفقیت
ماژول زمانی درست طراحی شده که بتوان برای هر تصمیم مهم پاسخ دقیق داد:
«چه چیزی ثبت شد، چه کسی ثبت/تأیید کرد، از کجا آمده، نسخه معتبر کدام است، به چه اسنادی مرتبط است، چه اقدامی از آن ناشی شده، چه ابلاغی انجام شده و اکنون وضعیت آن چیست؟»

این سند North Star فنی دبیرخانه است و قبل از migration یا implementation باید Phase S0 بر اساس آن نهایی شود.
