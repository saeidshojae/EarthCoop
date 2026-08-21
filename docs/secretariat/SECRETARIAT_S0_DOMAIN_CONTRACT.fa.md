# قرارداد دامنه Phase S0 دبیرخانه EarthCoop

این سند قرارداد معماری S0 برای bounded module مستقل `Secretariat / Registry` است و جایگزین نسخه اولیه pre-audit می‌شود.

## 1. Boundary
Secretariat مالک این concernهاست:
- Office/Registry scope
- registration number
- record identity
- official versioning
- attachments of official records
- relation graph
- correspondence registry
- parties/referrals/dispatch
- cases
- confidentiality/ACL
- audit trail
- archive/retention policy

Secretariat مالک این concernها نیست:
- Group chat/session execution
- Governance voting/quorum/adoption
- Action Item execution status
- Najm Bahar ledger/transaction truth
- Election engine
- public CMS
- generic support tickets

## 2. Office Contract
هر record باید به یک `SecretariatOffice` تعلق داشته باشد.

Office types:
- central
- group
- project
- legal_entity
- committee
- other

Scope contract:
- `scope_type`
- `scope_id` nullable فقط برای office مرکزی platform در صورتی که implementation نهایی از sentinel/domain entity استفاده نکند.
- ترجیح معماری: morph map پایدار و نه class-name خام پراکنده.

Office concernها:
- code
- name
- status
- numbering policy
- permission context
- default confidentiality

## 3. Aggregate Root
`SecretariatRecord` Aggregate Root رسمی سند است.

هر Record:
- office_id
- registry_number nullable تا زمان registration
- record_type
- direction
- title
- subject
- status
- confidentiality
- current_version_id
- registration/approval metadata
- provenance/source
- metadata

## 4. Record Types MVP
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

`other` فقط fallback است.

## 5. Direction
- incoming
- outgoing
- internal
- none

## 6. Lifecycle
مسیر عمومی:
`draft → pending_approval → registered → active/dispatched → closed → archived`

exception states:
- rejected
- cancelled
- superseded
- voided

قواعد:
- draft قابل ویرایش آزاد است.
- pending_approval فقط از workflow کنترل‌شده تغییر می‌کند.
- registered و بعد از آن overwrite محتوایی ممنوع است.
- تغییر محتوا بعد از registration باید version/amendment بسازد.
- hard delete برای registered record ممنوع است.

## 7. Confidentiality
- public
- office_members
- leadership
- restricted
- confidential

قواعد:
- `restricted` با ACL صریح محدود می‌شود.
- `confidential` علاوه بر ACL، access audit دارد.
- search/index layer باید قبل از return نتیجه policy را enforce کند.

## 8. Permission Contract
### Platform Admin
- فقط طبق policy platform؛ bypass خام در controller ممنوع.

### Office Manager
- create/edit draft
- submit approval
- approve/register در types مجاز
- dispatch/referral
- version/amend
- close/archive/void طبق policy

### Office Inspector
- create draft/report
- review/approve در types مجاز
- view audit trail
- oversight note/report
- دسترسی به leadership/restricted طبق ACL

### Ordinary Member
- view public/office records طبق policy
- submit incoming/request draft فقط از workflow مشخص

### Najm Hoda
- actor حقوقی مستقل نیست.
- draft/proposal/classification/relation/search/summary مجاز در context actor.
- register/approve/dispatch فقط با user actor معتبر و confirmation.

## 9. Registration Contract
registration باید atomic باشد و حداقل داشته باشد:
- office
- record type
- title/subject
- current official version
- actor
- registered_at
- unique registry_number
- audit event

## 10. Numbering Contract
`RegistryNumberService` concern مستقل است.

الزامات:
- race-safe
- transaction-safe
- deterministic scope
- configurable period/year
- format جدا از sequence logic
- DB id هرگز شماره رسمی نیست.

Scope پیش‌فرض MVP: `office + calendar year + record family`؛ format دقیق configurable می‌ماند.

## 11. Version Contract
- قبل از registration محتوای draft می‌تواند در record/draft version مدیریت شود.
- Version 1 در registration تثبیت می‌شود.
- versionهای رسمی immutable هستند.
- current_version_id نسخه جاری را نشان می‌دهد.
- version جدید audit event و change reason دارد.

## 12. Attachment Contract
هر attachment:
- record_id
- version_id nullable/required طبق policy
- original_name
- storage disk/key
- mime
- size
- checksum
- uploader
- state

registered attachment hard-delete نمی‌شود.

## 13. Relation Contract
جهت‌دار:
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

inverse relation می‌تواند read-model باشد و الزامی نیست دو row ذخیره شود.

## 14. Provenance Contract
source types اولیه:
- group_session
- meeting_minute
- meeting_decision
- action_item
- governance_proposal
- governance_resolution
- message
- post
- poll
- election
- najm_bahar_project
- najm_bahar_transaction
- ticket
- external_document
- manual

Source فقط provenance است و ownership business logic را منتقل نمی‌کند.

## 15. Party Contract
party types:
- user
- group
- project
- legal_entity
- external_person
- external_organization
- freeform_legacy فقط برای import کنترل‌شده

roles:
- sender
- recipient
- cc
- signatory
- contracting_party
- beneficiary
- issuer
- reviewer
- other

## 16. Correspondence Contract
حداقل:
- direction
- sender/recipient parties
- subject/body official version
- internal registry number
- external reference number optional
- sent/received dates
- responds_to relation
- dispatch/referral trail

Delivery provider core نیست.

## 17. Audit Contract
Append-only event types حداقل:
- created
- draft_updated
- submitted_for_approval
- approved
- rejected
- registered
- version_created
- attachment_added
- relation_added
- dispatched
- referred
- received
- closed
- archived
- superseded
- voided
- access_sensitive

هر event:
- office_id
- record_id
- actor_id
- event_type
- timestamp
- metadata
- request/ip/device metadata در صورت availability

## 18. Meeting Contract
`GroupSession` و `NajmHodaGroupMeetingMinute` source-of-truth عملیاتی هستند.

زنجیره:
`Session → approved minute → Secretariat draft → register meeting_minute`

تصمیمات می‌توانند رکورد مستقل resolution/formal_decision شوند.

## 19. Governance Contract
`GovernanceResolution` مالک adoption/vote/effect است.

Registry:
- source reference به GovernanceResolution
- snapshot official version
- registry number
- relations/archive/dispatch

Registry نباید quorum/votes/effect_status را business truth دوم کند.

## 20. Action Item Contract
Action Item مالک `status/assignee/due/priority` است.

Registry فقط:
- relation به resolution مرجع
- dispatch/notice رسمی
- completion report
- archival evidence

## 21. Najm Bahar Contract
Ledger/transactions پروژه حقیقت مالی را نگه می‌دارند.

Registry می‌تواند financial/execution record بسازد که فقط reference/snapshot ثبتی دارد و مانده یا تراکنش را محاسبه نمی‌کند.

## 22. Search Contract
Deterministic MVP filters:
- office
- registry_number
- type
- status
- confidentiality
- date range
- actor/party
- source
- case
- title/subject text

Semantic search فقط پس از permission pre-filter و در Phase S6.

## 23. ممنوعیت‌ها
- استفاده از legacy `files` به‌عنوان attachment core
- تبدیل `pages` به registry
- تبدیل TicketActivity/AdminActionLog به audit رسمی
- hard-delete registered record
- raw class-name polymorphism بدون morph map contract
- ثبت خودکار همه chat/ticketها
- mutation رسمی مستقیم توسط LLM
- numbering logic داخل controller
- duplicate governance/action/ledger truth

## 24. تصمیم‌های بسته‌شده S0
- bounded module مستقل: بله
- Office layer: بله
- Record First: بله
- versioning immutable: بله
- append-only audit: بله
- multi-office from first migration: بله
- Group-only schema: خیر
- file-manager approach: رد
- human confirmation for formal effects: اجباری

## 25. مواردی که قبل از S1 فقط باید به‌صورت implementation detail انتخاب شوند
این موارد architecture blocker نیستند و در S1 design note تعیین می‌شوند:
- نام دقیق table prefixes
- Laravel morph map tokens
- registry number display format فارسی/لاتین
- storage disk key convention
- service class namespace

با این قرارداد، Phase S0 برای شروع طراحی schema در S1 **بسته و آماده** تلقی می‌شود.
