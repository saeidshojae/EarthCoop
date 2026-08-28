# ممیزی مخزن برای ماژول دبیرخانه EarthCoop

## هدف
این سند نتیجه ممیزی سورس واقعی `main` پس از Release integration است و مشخص می‌کند برای ساخت ماژول مستقل `Secretariat / Registry` چه زیرساخت‌هایی اکنون وجود دارند، چه چیزهایی قابل استفاده مجددند، کدام اجزا فقط الگوی طراحی هستند و چه شکاف‌هایی باید ساخته شوند.

اصل تصمیم: **دبیرخانه سیستم فایل نیست؛ مرجع رسمی ثبت، نسخه، اعتبار، ارجاع، بایگانی و ممیزی اسناد کل EarthCoop است.**

---

## 1. نتیجه کلان ممیزی

### موجود و قابل استفاده مستقیم از طریق Integration
1. **نشست گروه** — `App\Models\GroupSession`
   - عنوان، موضوع، دستور جلسه، زمان شروع/پایان و actorهای ایجاد/پایان را دارد.
   - مالک اجرای جلسه باقی می‌ماند.
   - دبیرخانه فقط بعد از تأیید، رکورد رسمی صورتجلسه را به آن لینک می‌کند.

2. **صورتجلسه نجم هدا** — `App\Models\NajmHodaGroupMeetingMinute`
   - `summary`, `minutes`, `evidence_snapshot`, `decision_candidates`, `action_candidates`, `approved_by`, `approved_at` دارد.
   - بهترین source برای تولید `meeting_minute` در Registry است.
   - نباید جدول آن به جدول دبیرخانه تبدیل شود؛ منبع عملیاتی جلسه است.

3. **Action Item گروه** — `App\Models\NajmHodaGroupActionItem`
   - مسئول، موعد، اولویت، وضعیت، source message و metadata دارد.
   - اجرای کار باید در همین دامنه بماند.
   - دبیرخانه فقط مرجع رسمی اقدام، مصوبه مرتبط، گزارش انجام و ابلاغ را ثبت می‌کند.

4. **Governance Proposal / Resolution** — `App\Modules\Governance\Models\Proposal` و `Resolution`
   - Proposal و Resolution رسمیِ دامنه Governance از قبل وجود دارند.
   - Registry نباید منطق رأی، quorum، adoption یا effect را کپی کند.
   - Resolution می‌تواند source رکورد `resolution/formal_decision` باشد و Registry شماره ثبت/نسخه/بایگانی و ارتباطات ثبتی را اضافه کند.

5. **Policy و نقش‌های گروه** — `App\Policies\GroupPolicy`
   - `view`, `participate`, `manageSession`, `moderate`, `manage` دارد.
   - مرجع موجود برای membership و نقش مدیر/بازرس است.
   - `SecretariatPolicy` باید روی این لایه سوار شود، نه اینکه role engine دیگری بسازد.

6. **کنسول مدیریت نجم هدا**
   - نشست، صورتجلسه، تصمیم، اقدام، حکمرانی، محتوا، attention و dashboard عملیاتی دارد.
   - مسیر مناسب برای surface کردن «دبیرخانه» در context مدیریتی است.
   - خود دبیرخانه باید UI مستقل نیز داشته باشد.

7. **Knowledge Graph نجم هدا** — `NajmHodaUnifiedDomainKnowledgeGraphService`
   - pattern مناسبی برای query چنددامنه‌ای و scope/RBAC دارد.
   - در مراحل بعد می‌توان Secretariat را به graph اضافه کرد.
   - نباید جای search قطعی و permission-aware خود Registry را بگیرد.

---

## 2. زیرساخت‌های موجود که فقط الگو هستند، نه Core دبیرخانه

### Ticket / TicketAttachment / TicketActivity
مسیرها:
- `App\Models\Ticket`
- `App\Models\TicketAttachment`
- `App\Models\TicketActivity`

قابلیت‌های مفید به عنوان pattern:
- tracking code
- assignment/status/priority
- attachment metadata: file name/path/type/size/mime/uploader
- activity history با old/new value

تصمیم:
- جدول‌های Ticket نباید برای دبیرخانه reuse شوند.
- Attachment دبیرخانه باید generic، version-aware و دارای checksum باشد.
- Audit دبیرخانه باید append-only و قوی‌تر از TicketActivity باشد.

### AdminActionLog
`App\Models\AdminActionLog` دارای actor/action/target/metadata/IP است.

تصمیم:
- الگوی مفیدی برای audit metadata است.
- اما admin-specific است و قرارداد immutability دبیرخانه را ندارد.
- `SecretariatAuditEvent` مستقل لازم است.

### Message attachments
`App\Models\Message` فیلدهای `file_path/file_type/file_name/voice_message` دارد.

تصمیم:
- فایل چت، سند رسمی نیست.
- پیام/پست/نظرسنجی فقط می‌توانند source یک رکورد ثبتی باشند.
- promotion به دبیرخانه باید صریح و policy-controlled باشد.

### Pages CMS
`App\Models\Page` محتوا، ترجمه، template و publication state دارد.

تصمیم:
- Page برای محتوای عمومی وب است، نه immutable official record.
- انتشار عمومی یک سند ثبت‌شده می‌تواند بعداً به Page یا publication surface لینک شود؛ جدول pages نباید Registry شود.

### SystemEmail
`App\Models\SystemEmail` آدرس‌های ایمیل سیستمی فعال/default را نگه می‌دارد.

تصمیم:
- می‌تواند provider/identity مکاتبات صادره باشد.
- خود correspondence history و Registry نیست.

---

## 3. زیرساختی که نباید روی آن ساخته شود

### جدول `files`
`database/migrations/2025_03_14_174047_create_files_table.php` فقط `id` و timestamps می‌سازد، در حالی که مدل `App\Models\File` فیلدهای `group_id`, `user_id`, `filename` را fillable اعلام می‌کند.

نتیجه:
- این زیرساخت legacy/incomplete است.
- نباید migrate/patch شود تا به SecretariatAttachment تبدیل شود.
- Core دبیرخانه باید جدول attachment استاندارد خودش را داشته باشد.
- بعداً می‌توان داده legacy واقعی را در صورت وجود به‌صورت migration/import کنترل‌شده منتقل کرد.

---

## 4. شکاف‌های واقعی که در مخزن وجود ندارند

### Core Registry
- Office/Registry scope رسمی
- رکورد ثبتی عمومی
- شماره ثبت race-safe و مستقل از DB id
- current version و نسخه‌های immutable
- amendment/supersede contract
- relation graph بین رکوردها
- پرونده/Case
- parties استاندارد برای اشخاص/واحدها/طرف‌های خارجی

### Files / Integrity
- attachment عمومی و version-aware
- checksum
- integrity verification
- revoke/supersede فایل رسمی بدون hard delete

### Workflow
- submit for approval
- register
- dispatch/referral
- close/archive/void
- transition matrix مرکزی

### Access Control
- confidentiality model
- restricted ACL
- audit دسترسی به اسناد حساس
- policy مستقل دبیرخانه

### Correspondence
- incoming/outgoing/internal record model
- sender/recipient parties
- external reference number
- reply chain
- referral/dispatch trail
- delivery state

### Audit / Retention
- append-only registry audit
- retention policy
- legal hold در آینده
- immutable access/event history

### Search
- فیلتر قطعی بر registry number/type/status/date/party/source/case
- full-text index
- semantic retrieval permission-aware در مرحله بعد

---

## 5. اصلاح معماری پس از Audit: Office First

برای اینکه دبیرخانه «برای کل EarthCoop» باشد، یک لایه بالاتر از Record لازم است:

### `SecretariatOffice`
نماینده یک دفتر ثبت در همان ماژول واحد است.

نمونه‌ها:
- دبیرخانه مرکزی EarthCoop
- دبیرخانه یک Group
- دبیرخانه یک Project
- دبیرخانه یک Company/LegalEntity در آینده
- دبیرخانه یک Board/Committee در آینده

Office باید حداقل این concernها را مالک باشد:
- scope/owner
- office code
- status
- numbering policy
- default confidentiality
- timezone/locale در صورت نیاز
- permission context

اصل معماری:
**یک bounded module دبیرخانه، چند Office؛ نه چند سیستم دبیرخانه مستقل.**

`SecretariatRecord` همچنان Aggregate Root سند است، اما هر record به یک Office تعلق دارد.

---

## 6. Source-of-Truth Matrix

| داده/رویداد | مرجع حقیقت | نقش دبیرخانه |
|---|---|---|
| اجرای نشست | GroupSession | لینک provenance و ثبت صورتجلسه |
| متن/تصمیمات جلسه | NajmHodaGroupMeetingMinute | تبدیل کنترل‌شده به record رسمی |
| اجرای وظیفه | NajmHodaGroupActionItem | ثبت مرجع مصوبه/گزارش انجام |
| پیشنهاد حکمرانی | Governance Proposal | source/link، نه کپی workflow |
| مصوبه حکمرانی | Governance Resolution | ثبت و بایگانی ثبتی، نه ownership رأی |
| پیام/پست/نظرسنجی | Group Chat | source اختیاری برای promotion |
| تیکت پشتیبانی | Ticket | فقط در صورت policy به مکاتبه رسمی تبدیل شود |
| پروژه | Najm Bahar Project | owner/source اسناد پروژه |
| تراکنش مالی | Najm Bahar ledger | فقط reference برای سند مالی؛ دفترکل کپی نشود |
| صفحات عمومی | Page CMS | publication target، نه canonical document |

---

## 7. قواعد جلوگیری از دوباره‌کاری
1. Registry هیچ workflow دامنه‌ای را دوباره پیاده نمی‌کند.
2. `GovernanceResolution` با `SecretariatRecord(type=resolution)` یکی نیست: اولی حقیقت حکمرانی، دومی ثبت رسمی/بایگانی آن است.
3. `MeetingMinute` عملیاتی با `SecretariatRecord(type=meeting_minute)` یکی نیست: نسخه ثبتی snapshot رسمی و immutable است.
4. Action Item در Registry status اجرایی ندارد.
5. فایل چت/TicketAttachment مستقیماً attachment رسمی نمی‌شود مگر با import/copy کنترل‌شده و checksum.
6. هیچ داده رسمی ثبت‌شده hard-delete نمی‌شود.

---

## 8. ریسک‌های اصلی پیش از Implementation
- ایجاد دو source of truth برای Resolution/Meeting/Action.
- leakage اسناد restricted/confidential در search یا نجم هدا.
- race در شماره ثبت.
- overwrite شدن رکورد registered.
- reliance بر path فایل بدون checksum/integrity.
- class-name polymorphism بدون morph map پایدار.
- اتوماتیک ثبت کردن تمام chat/ticket و تبدیل دبیرخانه به انبار نویز.
- وابستگی مستقیم Core Registry به UI یا Group Controller.

---

## 9. تصمیم نهایی Audit
وضعیت پروژه برای شروع دبیرخانه **مناسب است**؛ زیرا domainهای منبع (Group/Meeting/Governance/Action/Najm Hoda) بخش مهمی از داده مورد نیاز را دارند. اما هیچ Registry رسمی استانداردی در کد فعلی وجود ندارد و تلاش برای ارتقای `files`, `pages` یا `tickets` به دبیرخانه معماری نادرستی خواهد بود.

بنابراین Build Order باید با `SecretariatOffice + SecretariatRecord + Version + Numbering + Audit + Policy` آغاز شود و سپس integrationها به‌صورت adapter/service روی domainهای موجود افزوده شوند.
