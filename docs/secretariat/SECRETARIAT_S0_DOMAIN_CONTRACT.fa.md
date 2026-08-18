# قرارداد دامنه Phase S0 دبیرخانه EarthCoop

این سند قرارداد اولیه دامنه برای ماژول مستقل `Secretariat / Registry` است. هدف Phase S0 تثبیت واژگان، وضعیت‌ها، انواع رکورد، روابط، نقش‌ها و قواعد تغییرناپذیری پیش از طراحی migration و API است.

## 1. Aggregate Root
موجودیت مرکزی `SecretariatRecord` است. هر رکورد ثبتی یک هویت مستقل دارد و تمام نسخه‌ها، پیوست‌ها، روابط، طرف‌ها و audit eventها حول آن سازمان‌دهی می‌شوند.

## 2. Record Types اولیه
Taxonomy اولیه MVP:
- incoming_letter
- outgoing_letter
- internal_correspondence
- meeting_minute
- resolution
- formal_decision
- contract
- memorandum_of_understanding
- agreement
- policy
- directive
- official_report
- notice
- official_note
- financial_record
- execution_record
- case_record
- other

نوع `other` فقط fallback است و نباید جای taxonomy روشن را بگیرد.

## 3. Lifecycle Status
چرخه عمومی استاندارد:
- draft
- pending_approval
- registered
- active
- dispatched
- closed
- archived

و وضعیت‌های استثنا:
- rejected
- cancelled
- superseded
- voided

قواعد:
- فقط draft قابل ویرایش آزاد است.
- pending_approval فقط با workflow کنترل‌شده قابل اصلاح است.
- registered و بعد از آن overwrite محتوایی ممنوع است؛ تغییر باید version/amendment ایجاد کند.
- voided رکورد را حذف نمی‌کند؛ فقط اعتبار جاری را از آن می‌گیرد.

## 4. Confidentiality
سطوح اولیه:
- public_group
- leadership
- restricted
- confidential

`restricted` نیازمند ACL صریح خواهد بود. `confidential` باید audit دسترسی داشته باشد.

## 5. Direction برای مکاتبات
- incoming
- outgoing
- internal
- none

برای انواع غیرمکاتبه‌ای مقدار `none` استفاده می‌شود.

## 6. Relation Types
روابط استاندارد اولیه:
- derived_from
- refers_to
- supersedes
- superseded_by
- amends
- amended_by
- implements
- implemented_by
- responds_to
- responded_by
- decision_of
- action_of
- report_of
- attachment_of
- part_of_case
- related_to

روابط باید جهت‌دار باشند، ولی service می‌تواند inverse relation را در read model بسازد.

## 7. Source References
هر رکورد می‌تواند منبع تولید داشته باشد:
- group_session
- meeting_minute
- decision_candidate
- action_item
- message
- post
- poll
- election
- external_document
- manual

Source relation برای provenance است و با SecretariatRelation یکی نیست.

## 8. Owner Contract
مالک دبیرخانه polymorphic است:
- Group در MVP
- Project در آینده
- LegalEntity/Company در آینده
- Committee/Board در آینده

Core نباید به Group-specific UI یا controller وابسته باشد.

## 9. Role Contract MVP برای Group Owner
### Manager
- create draft
- edit draft
- request approval
- approve/register در حدود policy
- dispatch/referral
- create amendment/version
- close/archive
- view leadership/restricted طبق ACL

### Inspector
- create draft
- view registered records
- review/approve در انواعی که policy اجازه می‌دهد
- add oversight note/report
- inspect audit trail
- view leadership و restricted طبق ACL

### Ordinary active member
- view public_group records
- submit incoming/request draft فقط اگر workflow آینده اجازه دهد

### Najm Hoda
- actor مستقل حقوقی نیست
- می‌تواند draft/proposal بسازد، classify کند، relation پیشنهاد دهد و missing fields را تشخیص دهد
- registration/approval/dispatch فقط با user actor معتبر و policy مجاز انجام می‌شود

## 10. Registration Contract
هر ثبت رسمی باید حداقل داشته باشد:
- owner
- record_type
- title/subject
- registered_by
- registered_at
- registry_number
- current_version
- status=registered یا وضعیت بعدی معتبر

شماره ثبت باید unique در scope مالک و numbering period باشد.

## 11. Versioning Contract
- Version 1 در اولین ثبت رسمی تثبیت می‌شود.
- تغییر بعد از registration version جدید می‌سازد.
- نسخه‌های قبلی immutable باقی می‌مانند.
- current_version_id روی record به نسخه معتبر جاری اشاره می‌کند.
- amendment می‌تواند هم version جدید بسازد و هم relation `amends` ثبت کند.

## 12. Attachment Contract
هر پیوست:
- به record یا version مشخص تعلق دارد
- original_name
- storage_path یا storage key
- mime_type
- size
- checksum
- uploaded_by
- uploaded_at

حذف فیزیکی attachment یک رکورد registered در مسیر عادی ممنوع است؛ revoke/supersede policy لازم خواهد بود.

## 13. Audit Contract
Audit eventها append-only هستند. حداقل event types:
- created
- updated_draft
- submitted_for_approval
- approved
- rejected
- registered
- version_created
- attachment_added
- relation_added
- dispatched
- referred
- closed
- archived
- voided
- access_sensitive

هر event حداقل actor_id، timestamp، event_type و metadata دارد.

## 14. Meeting Integration Contract
پس از پایان نشست و تأیید صورتجلسه:
1. صورتجلسه می‌تواند به draft SecretariatRecord از نوع `meeting_minute` تبدیل شود.
2. پس از تأیید انسانی، registry number می‌گیرد.
3. تصمیمات منتخب می‌توانند رکورد مستقل `resolution` یا `formal_decision` شوند.
4. این رکوردها relation `decision_of` به صورتجلسه دارند.
5. Action Itemها relation مفهومی به resolution/decision مرجع خواهند داشت.

## 15. Action Item Integration Contract
Secretariat مالک execution state نیست. Action Item service مرجع وضعیت اجرا باقی می‌ماند.

Secretariat فقط این موارد را ثبت می‌کند:
- مرجع رسمی اقدام
- گزارش انجام
- ابلاغ مرتبط
- سند خاتمه در صورت نیاز

## 16. Search Contract MVP
فیلترهای الزامی:
- registry_number
- record_type
- status
- date range
- title/subject text
- registered_by
- confidentiality
- party
- source
- case

Semantic search نجم هدا در S5 اضافه می‌شود و جای فیلترهای قطعی را نمی‌گیرد.

## 17. Numbering Strategy Contract
در S1 یک `RegistryNumberService` مستقل ساخته می‌شود. قرارداد فعلی:
- sequence transactional و race-safe باشد
- scope قابل تنظیم باشد
- year/period قابل تنظیم باشد
- format از domain logic جدا باشد
- database id هرگز شماره رسمی تلقی نشود

## 18. ممنوعیت‌های معماری
- SecretariatRecord نباید فایل blob را مستقیم در DB نگه دارد.
- registered record نباید hard-delete شود.
- LLM نباید status رسمی یا registry number را مستقل تغییر دهد.
- controller نباید numbering/versioning/audit logic را inline پیاده کند.
- Group module نباید مالک جدول‌های core دبیرخانه باشد.

## 19. Acceptance Criteria برای پایان S0
Phase S0 زمانی بسته است که:
- taxonomy اولیه تصویب شده باشد؛
- lifecycle و transition matrix روشن باشد؛
- permission matrix مدیر/بازرس تثبیت شده باشد؛
- relation types و provenance contract نهایی شده باشد؛
- numbering scope تصمیم گرفته شده باشد؛
- boundary بین Registry، Group، Meeting و Action Item بدون ابهام باشد؛
- سپس migrationهای S1 طراحی شوند.
