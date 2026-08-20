# Master Roadmap دبیرخانه EarthCoop

## 1. چشم‌انداز
دبیرخانه EarthCoop یک bounded module مستقل برای **ثبت رسمی، نسخه‌بندی، اعتبار، مکاتبات، پرونده‌ها، بایگانی، ارجاع و بازیابی اسناد کل سامانه** است.

اصل کلیدی:

**One Secretariat Module → Many Offices/Registries → Many Records**

یعنی برای گروه، پروژه، شرکت یا دبیرخانه مرکزی سیستم‌های جداگانه ساخته نمی‌شوند؛ همه از یک هسته استاندارد استفاده می‌کنند و فقط Office/Scope آن‌ها متفاوت است.

---

## 2. اصول معماری ثابت
1. **Record First, File Second** — فایل فقط پیوست است.
2. **Office First** — هر رکورد به یک دفتر ثبت مشخص تعلق دارد.
3. **Source Domains Keep Ownership** — Group/Governance/Najm Bahar/Action Item مالک منطق خود می‌مانند.
4. **Registered Means Immutable History** — ویرایش بعد از ثبت با Version/Amendment انجام می‌شود.
5. **Human Authority for Formal Effects** — نجم هدا پیشنهاد می‌دهد؛ ثبت/تأیید/ابلاغ فقط با actor مجاز.
6. **Server-side Authorization** — UI و LLM مرجع مجوز نیستند.
7. **No Hard Delete for Official Records** — void/archive/supersede استفاده می‌شود.
8. **Search Must Be Permission-aware** — هیچ semantic search حق دور زدن ACL را ندارد.
9. **Registry Number ≠ Database ID** — شماره ثبت concern مستقل و transactional است.
10. **Audit Is Append-only** — تاریخچه رسمی حذف یا rewrite نمی‌شود.

---

## 3. مدل دامنه هدف

### SecretariatOffice
دفتر ثبت استاندارد برای یک scope.

فیلدهای مفهومی:
- id
- code
- name
- scope_type / scope_id
- office_type: central | group | project | legal_entity | committee | other
- status
- numbering_policy
- default_confidentiality
- metadata

### SecretariatRecord
Aggregate Root سند رسمی.

- office_id
- registry_number
- record_type
- direction
- title
- subject
- summary
- status
- confidentiality
- current_version_id
- registered_by / registered_at
- approved_by / approved_at
- effective_at
- source_type / source_id
- metadata

### SecretariatRecordVersion
- record_id
- version_number
- title/subject/summary/body snapshot
- change_reason
- created_by
- approved_by
- checksum/content hash در صورت نیاز
- created_at

### SecretariatAttachment
- record_id
- version_id nullable
- original_name
- storage_disk / storage_key
- mime_type
- file_size
- checksum
- uploaded_by
- uploaded_at
- state

### SecretariatRelation
روابط جهت‌دار مثل:
- derived_from
- refers_to
- supersedes
- amends
- implements
- responds_to
- decision_of
- action_of
- report_of
- part_of_case
- related_to

### SecretariatParty
طرف‌های داخلی/خارجی:
- user
- group
- project
- legal entity
- external person/organization

### SecretariatDispatch
گردش/ابلاغ/ارجاع رسمی:
- record_id
- from_party
- to_party
- type: dispatch | referral | cc | notice
- status
- due_at
- sent_at / received_at
- actor

### SecretariatCase
پرونده‌ای برای جمع‌کردن چند رکورد حول یک موضوع.

### SecretariatAuditEvent
تاریخچه append-only.

### SecretariatAclEntry
برای `restricted/confidential` در صورت نیاز به دسترسی صریح.

### SecretariatSequence
برای شماره ثبت race-safe و transactional.

---

## 4. Taxonomy اولیه رکوردها
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
- election_record
- case_record
- other

`other` fallback است و نباید taxonomy را مبهم کند.

---

## 5. Lifecycle استاندارد
مسیر عمومی:

`draft → pending_approval → registered → active/dispatched → closed → archived`

مسیرهای استثنا:
- rejected
- cancelled
- superseded
- voided

قواعد:
- draft آزادانه قابل ویرایش است.
- pending_approval فقط با workflow کنترل‌شده تغییر می‌کند.
- registered به بعد overwrite ممنوع است.
- superseded/voided حذف فیزیکی نیست.

---

# 6. Build Roadmap

## Phase S0 — Domain Contract & Architecture Freeze
### هدف
بستن تصمیم‌های معماری قبل از migration.

### کارها
- نهایی‌کردن Office model و scopeهای اولیه
- نهایی‌کردن taxonomy
- transition matrix lifecycle
- permission matrix برای central/group/project office
- confidentiality + ACL contract
- source/provenance contract
- relation types
- numbering scope/format contract
- morph map پایدار برای scope/source/party
- source-of-truth rules برای Governance/Meeting/Action/Najm Bahar

### خروجی
- `SECRETARIAT_S0_DOMAIN_CONTRACT.fa.md`
- transition matrix
- permission matrix
- schema proposal بدون migration

### Acceptance Gate
هیچ migration قبل از بسته‌شدن S0 ساخته نشود.

---

## Phase S1 — Registry Core
### هدف
ساخت کوچک‌ترین هسته رسمی قابل اعتماد.

### جداول/مدل‌ها
- secretariat_offices
- secretariat_records
- secretariat_record_versions
- secretariat_sequences
- secretariat_audit_events

### سرویس‌ها
- SecretariatOfficeService
- RegistryNumberService
- SecretariatRecordService
- SecretariatVersionService
- SecretariatAuditService
- SecretariatTransitionService

### Policy
- SecretariatOfficePolicy
- SecretariatRecordPolicy

### قابلیت‌ها
- create draft
- edit draft
- submit for approval
- approve/register
- view record
- create version/amendment
- void/supersede/archive

### تست‌های الزامی
- numbering race/idempotency
- no hard delete
- no overwrite after registered
- forbidden transition tests
- RBAC scope isolation
- audit event creation

### Gate
یک رکورد بدون فایل باید از draft تا registered و version 2 به‌صورت امن طی شود.

---

## Phase S2 — Attachments, Relations & Basic UI
### هدف
تبدیل Core به یک Registry قابل استفاده روزمره.

### جداول
- secretariat_attachments
- secretariat_relations
- secretariat_acl_entries در صورت تأیید S0

### قابلیت‌ها
- upload attachment
- checksum
- attach to version
- relation graph
- linked-record view
- quick deterministic search

### UI مستقل دبیرخانه
- داشبورد Office
- ثبت جدید
- پیش‌نویس‌ها
- منتظر تأیید
- ثبت‌شده‌ها
- جست‌وجو با شماره/عنوان/نوع/تاریخ
- صفحه جزئیات record + timeline

### Gate
مدیر بتواند سندی با پیوست ثبت کند و تاریخچه/نسخه/روابط آن را ببیند.

---

## Phase S3 — Meetings & Governance Integration
### هدف
اتصال اولین domainهای بالغ پروژه بدون ساخت source-of-truth دوم.

### Meeting Adapter
`GroupSession → approved NajmHodaGroupMeetingMinute → Secretariat meeting_minute`

- snapshot رسمی صورتجلسه
- source reference
- registration proposal در نجم هدا

### Decision/Resolution Adapter
- decision confirmed از meeting
- Governance Resolution
- هرکدام با source type روشن
- رکورد resolution/formal_decision در Registry

### Action Link
- Action Item ↔ official resolution relation
- پایان Action Item می‌تواند draft `execution_record/official_report` پیشنهاد دهد.

### Gate
زنجیره زیر end-to-end و قابل ممیزی باشد:

`Session → Minute → Resolution → Action Item → Execution Report`

---

## Phase S4 — Correspondence
### هدف
دبیرخانه واقعی وارده/صادره/داخلی.

### جداول
- secretariat_parties
- secretariat_dispatches

### قابلیت‌ها
- نامه وارده
- نامه صادره
- مکاتبه داخلی
- external reference number
- sender/recipient/cc
- reply-to/responds-to
- ارجاع به مدیر/بازرس/واحد
- deadline/follow-up
- delivery status پایه

### UI
- Inbox
- Outbox
- Internal
- Referred to me
- Awaiting reply
- Overdue correspondence

### Integration
- SystemEmail فقط provider identity
- email/API/physical delivery adapterها خارج از core

### Gate
یک نامه وارده ثبت، ارجاع، پاسخ و مختومه شود و تمام chain حفظ شود.

---

## Phase S5 — Case Management & EarthCoop-wide Offices
### هدف
تبدیل دبیرخانه گروهی به دبیرخانه سراسری واقعی.

### جداول
- secretariat_cases
- case-record relation

### Office scopes فعال
- EarthCoop Central Office
- Group Office
- Project Office
- LegalEntity/Company when domain exists
- Committee/Board when domain exists

### قابلیت‌ها
- پرونده موضوعی
- timeline پرونده
- cross-office references
- transfer/copy/reference policy بین officeها

### Gate
یک پرونده بتواند اسناد چند domain را بدون کپی بی‌قاعده truth کنار هم نمایش دهد.

---

## Phase S6 — Search, Knowledge & Retrieval
### هدف
بازیابی حرفه‌ای اسناد.

### لایه قطعی
- registry number
- type/status/confidentiality
- date range
- party
- source
- case
- office
- full-text

### لایه نجم هدا
- Secretariat domain در Unified Knowledge Graph
- semantic retrieval با pre-filter authorization
- related-record recommendation
- timeline summary
- compare versions

### قانون امنیتی
Semantic index هرگز سندی را که actor policy اجازه دیدن ندارد برنمی‌گرداند، حتی metadata آن را.

### Gate
نتایج deterministic و semantic هر دو scope-safe باشند.

---

## Phase S7 — Najm Hoda as Secretariat Minister
### هدف
نجم هدا از یک chatbot به دبیر فعال و کنترل‌شده تبدیل شود.

### Guided Operations
- ثبت سند جدید
- نامه وارده
- تهیه نامه صادره
- ثبت صورتجلسه/مصوبه
- ارجاع
- جست‌وجو
- ساخت پرونده
- تهیه گزارش اجرای مصوبه

### Intelligence
- تشخیص موارد لازم‌الثبت
- پیشنهاد taxonomy/office/confidentiality
- auto-draft از evidence
- پیشنهاد روابط
- تشخیص missing fields
- هشدار اسناد منتظر تأیید
- هشدار مکاتبات بی‌پاسخ/معوق
- خلاصه پرونده
- پیش‌نویس پاسخ به نامه با استفاده از سابقه مجاز

### Safety
همه عملیات رسمی:
`proposal → preview → human confirmation → deterministic service`

### Gate
هیچ عملیات formal توسط LLM مستقیماً mutation نکند.

---

## Phase S8 — Contracts, Formality & Integrity
### هدف
افزایش وزن حقوقی/سازمانی سیستم.

### قابلیت‌ها
- contract/MOU specialized metadata
- parties/signatories
- effective/expiry/renewal
- amendment chain
- signatures/seals adapter در صورت تصمیم حقوقی
- document integrity verification
- export package PDF/ZIP + manifest/checksums
- retention policy
- legal hold
- controlled redaction در آینده

### Gate
قرارداد با amendment و سابقه کامل بدون از دست رفتن نسخه‌های قبلی مدیریت شود.

---

## Phase S9 — Production Hardening & Legacy Migration
### هدف
آمادگی عملیاتی در مقیاس EarthCoop.

### کارها
- performance/index audit
- large-file limits
- storage lifecycle
- antivirus/malware scanning adapter در صورت امکان
- backup/restore drill
- observability/metrics
- permission penetration tests
- concurrency tests
- disaster recovery runbook
- import legacy documents if any

### Legacy Rules
- جدول `files` مستقیماً core نمی‌شود.
- Ticket attachments در صورت نیاز import/copy می‌شوند.
- Pages فقط در صورت نیاز به publication relation متصل می‌شوند.

### Gate
production readiness مستقل دبیرخانه + GameDay برای permission/integrity/restore.

---

## 7. ترتیب اجرای پیشنهادی قطعی

`S0 → S1 → S2 → S3 → S4 → S5 → S6 → S7 → S8 → S9`

دلیل ترتیب:
- ابتدا حقیقت رسمی و integrity؛
- بعد UI و attachment؛
- سپس اتصال به domainهای موجود و بالغ؛
- بعد correspondence؛
- سپس گسترش سراسری؛
- هوشمندی نجم هدا فقط روی core استاندارد ساخته می‌شود.

---

## 8. MVP واقعی دبیرخانه
MVP برای اولین استفاده production پس از **S0 تا S4** حاصل می‌شود:
- دفتر ثبت
- سند و نسخه
- شماره ثبت
- audit
- پیوست
- جست‌وجوی پایه
- صورتجلسه/مصوبه
- نامه وارده/صادره/داخلی
- ارجاع و پیگیری

S5 به بعد سیستم را از «دبیرخانه گروه» به «زیرساخت اسناد کل EarthCoop» ارتقا می‌دهد؛ با این حال schema از S1 باید از ابتدا چند-Office باشد تا migration معماری بعدی لازم نشود.

---

## 9. Definition of Done کل پروژه دبیرخانه
دبیرخانه زمانی بالغ تلقی می‌شود که:
- هر سند رسمی شناسه و نسخه معتبر مشخص داشته باشد؛
- origin و relation قابل ردیابی باشد؛
- تغییر بی‌ردپا ممکن نباشد؛
- مجوزها server-side و scope-safe باشند؛
- مکاتبات chain کامل داشته باشند؛
- جلسات/مصوبات/اقدامات بدون duplicate truth متصل باشند؛
- اسناد گروه/پروژه/مرکز در یک استاندارد واحد کار کنند؛
- نجم هدا بتواند اسناد مجاز را بفهمد و مدیریت را تسهیل کند بدون اینکه authority رسمی را تصاحب کند؛
- backup, restore, search و audit در production قابل اتکا باشند.
