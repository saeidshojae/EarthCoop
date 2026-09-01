# Phase S1 — Registry Core Schema Design

## 1. هدف
این سند implementation design فاز S1 را بر اساس قرارداد S0 و ممیزی مخزن تثبیت می‌کند. هدف S1 ساخت کوچک‌ترین هسته رسمی قابل اعتماد برای Office، Record، Version، Numbering و Audit است؛ بدون attachment، correspondence، relation graph و semantic search که متعلق به فازهای بعدی‌اند.

## 2. Baseline
- Runtime baseline: Laravel 12 / PHP 8.2.
- S1 روی branch مستقل `agent/secretariat-s1-registry-core` توسعه می‌یابد.
- branch از `agent/secretariat-master-roadmap` منشعب شده تا اسناد S0/Audit/Master Roadmap در همان lineage باشند.
- هیچ تغییر S1 مستقیماً روی `main` انجام نمی‌شود.

## 3. Namespace پیشنهادی
Core دبیرخانه در bounded namespace زیر قرار می‌گیرد:

`App\Modules\Secretariat`

زیرشاخه‌ها:
- `Models`
- `Services`
- `Policies`
- `Enums` یا value constants در صورت نیاز

Policy registration می‌تواند در `AuthServiceProvider` انجام شود؛ controller/UI در S1 خارج از scope است.

## 4. Morph Map Contract
S0 استفاده از class-name خام را رد کرده است. tokenهای اولیه پیشنهادی:

### Office scope
- `group` → `App\Models\Group`
- `najm_bahar_project` → `App\Modules\NajmBahar\Models\Project`

### Record provenance/source
- `group_session` → `App\Models\GroupSession`
- `meeting_minute` → `App\Models\NajmHodaGroupMeetingMinute`
- `action_item` → `App\Models\NajmHodaGroupActionItem`
- `governance_proposal` → `App\Modules\Governance\Models\Proposal`
- `governance_resolution` → `App\Modules\Governance\Models\Resolution`
- `message` → `App\Models\Message`
- `poll` → `App\Models\Poll`
- `najm_bahar_project` → `App\Modules\NajmBahar\Models\Project`
- `ticket` → `App\Models\Ticket`

`manual` و `external_document` class-backed morph نیستند و به‌صورت source descriptor مدیریت می‌شوند؛ در S1 source_type/source_id nullable هستند.

## 5. جدول `secretariat_offices`
فیلدها:
- `id` bigint PK
- `code` varchar(80) unique
- `name` varchar(255)
- `office_type` varchar(32)
- `scope_type` varchar(64) nullable
- `scope_id` bigint nullable
- `status` varchar(24), default `active`
- `numbering_policy` json nullable
- `default_confidentiality` varchar(32), default `office_members`
- `metadata` json nullable
- timestamps

Indexes:
- unique `code`
- index `(scope_type, scope_id)`
- index `(office_type, status)`

قواعد:
- central office می‌تواند scope null داشته باشد.
- برای office غیرمرکزی، service-level validation وجود scope را enforce می‌کند.
- در S1 uniqueness عمومی روی `(scope_type, scope_id, office_type)` اجباری نمی‌شود تا committee/legal-entity future scopes محدود نشوند؛ OfficeService duplicate semantic را کنترل می‌کند.

## 6. جدول `secretariat_records`
فیلدها:
- `id` bigint PK
- `office_id` FK → secretariat_offices
- `registry_number` varchar(160) nullable
- `registry_sequence` bigint unsigned nullable
- `registry_year` integer nullable
- `registry_family` varchar(48) nullable
- `record_type` varchar(64)
- `direction` varchar(24), default `none`
- `title` varchar(500)
- `subject` varchar(500) nullable
- `summary` text nullable
- `status` varchar(32), default `draft`
- `confidentiality` varchar(32)
- `current_version_id` bigint nullable
- `registered_by` FK users nullable
- `registered_at` timestamp nullable
- `approved_by` FK users nullable
- `approved_at` timestamp nullable
- `effective_at` timestamp nullable
- `source_type` varchar(64) nullable
- `source_id` bigint nullable
- `metadata` json nullable
- timestamps

Indexes/constraints:
- unique `(office_id, registry_number)` با nullable semantics DB
- unique `(office_id, registry_year, registry_family, registry_sequence)` برای registration allocation
- index `(office_id, status)`
- index `(office_id, record_type)`
- index `(source_type, source_id)`
- index `registered_at`

نکته circular FK:
`current_version_id` در migration اولیه nullable ایجاد می‌شود و FK آن پس از ایجاد جدول versions با `Schema::table` اضافه می‌شود.

## 7. جدول `secretariat_record_versions`
فیلدها:
- `id` bigint PK
- `record_id` FK cascade محدود به cleanup draft/test؛ حذف registered در service/policy ممنوع است
- `version_number` integer unsigned
- `title` varchar(500)
- `subject` varchar(500) nullable
- `summary` text nullable
- `body` longText nullable
- `change_reason` text nullable
- `created_by` FK users
- `approved_by` FK users nullable
- `approved_at` timestamp nullable
- `content_checksum` char(64) nullable
- `is_official` boolean default false
- timestamps

Constraints:
- unique `(record_id, version_number)`
- index `(record_id, is_official)`

قواعد:
- آخرین draft version قابل replace نیست؛ edit draft باید version یا snapshot کنترل‌شده‌ای بسازد. برای ساده‌بودن invariant، S1 هر تغییر محتوایی را version جدید می‌سازد.
- در registration، version جاری به `is_official=true` تبدیل می‌شود و immutable است.
- version 2+ پس از registration فقط با `SecretariatVersionService` ایجاد می‌شود.

## 8. جدول `secretariat_sequences`
فیلدها:
- `id` bigint PK
- `office_id` FK
- `calendar_year` integer
- `record_family` varchar(48)
- `last_value` bigint unsigned default 0
- timestamps

Constraint:
- unique `(office_id, calendar_year, record_family)`

الگوریتم allocation:
1. داخل DB transaction اجرا شود.
2. row sequence با `lockForUpdate()` خوانده/ایجاد شود.
3. `last_value + 1` محاسبه و persist شود.
4. number display از policy office format شود.
5. record و audit در همان transaction register شوند.

این جدول تنها source شماره sequence است؛ `MAX(registry_sequence)+1` ممنوع است.

## 9. جدول `secretariat_audit_events`
فیلدها:
- `id` bigint PK
- `office_id` FK
- `record_id` FK nullable
- `actor_id` FK users nullable برای system/import future
- `event_type` varchar(64)
- `event_at` timestamp
- `metadata` json nullable
- `request_ip` varchar(64) nullable
- `user_agent` text nullable
- timestamps

Indexes:
- `(record_id, event_at)`
- `(office_id, event_at)`
- `(event_type, event_at)`

Contract:
- model فاقد update/delete business API است.
- AuditService فقط append می‌کند.
- DB hard immutability trigger در S1 الزامی نیست؛ invariant با service/policy/tests enforce می‌شود و در production hardening قابل تقویت است.

## 10. Lifecycle Transition Matrix S1
Allowed transitions:
- `draft → pending_approval`
- `draft → cancelled`
- `pending_approval → draft`
- `pending_approval → rejected`
- `pending_approval → registered`
- `registered → active`
- `registered → voided`
- `registered → superseded`
- `active → closed`
- `active → superseded`
- `active → voided`
- `closed → archived`
- `closed → superseded`
- `archived` terminal در S1
- `rejected`, `cancelled`, `superseded`, `voided` terminal در S1

`dispatched` state در taxonomy سراسری وجود دارد ولی workflow correspondence در S4 ساخته می‌شود؛ S1 service آن را enforce نمی‌کند.

## 11. Services
### `SecretariatOfficeService`
- create office
- validate central/scoped office
- resolve default confidentiality

### `RegistryNumberService`
- family mapping
- year resolution
- transactional sequence allocation
- display formatting

MVP format پیشنهادی:
`{OFFICE-CODE}/{YEAR}/{FAMILY}/{SEQ}`
مثال:
`GRP-104/2026/GEN/000001`

format در `numbering_policy` قابل تغییر است اما sequence identity از display format مستقل می‌ماند.

### `SecretariatRecordService`
- create draft + initial version + created audit
- edit draft → new draft version
- submit approval
- register via TransitionService + RegistryNumberService
- view helpers
- void/supersede/archive orchestration

### `SecretariatVersionService`
- append immutable version
- set `current_version_id`
- protect official versions
- calculate checksum روی canonical content snapshot

### `SecretariatAuditService`
- append only
- capture actor/event/metadata/request metadata when available

### `SecretariatTransitionService`
- allowed transition map
- transactional transition
- registration special-case
- audit event emission

## 12. Idempotency Contract
S1 حداقل دو operation کلیدی باید idempotent باشند:
- registration
- version creation from explicit external command

پیشنهاد schema-light برای S1:
- registration: status/registry_number guard + row lock روی record؛ retry همان record شماره جدید نمی‌گیرد.
- external command idempotency عمومی در S1 table جدا نمی‌سازد؛ caller می‌تواند reference metadata بدهد. اگر integrationهای S3 نیاز قطعی ایجاد کردند، idempotency key registry مستقل اضافه می‌شود.

## 13. Authorization Contract
Policyها:
- `SecretariatOfficePolicy`
- `SecretariatRecordPolicy`

S1 scope اولیه:
- platform administrator مجاز طبق helper موجود پروژه
- group office به roleهای GroupPolicy/ResolvesGroupMembership تکیه می‌کند
- manager/inspector از membership roleهای موجود resolve می‌شوند
- ordinary member فقط view/create محدود
- Najm Hoda policy bypass ندارد و باید actor انسانی را carry کند

اصل: controller/service حق استفاده از role magic مستقل ندارد.

## 14. Required Tests
حداقل feature/unit tests:
1. `SecretariatRegistryNumberTest`
   - sequence uniqueness
   - same office/year/family increments
   - different offices independent
   - retry registration does not allocate second number

2. `SecretariatRecordImmutabilityTest`
   - official version cannot be overwritten
   - registered record content edit creates new version

3. `SecretariatTransitionTest`
   - valid transitions
   - forbidden transitions
   - terminal states

4. `SecretariatAuditTest`
   - create/submit/register/version/void events appended
   - no update/delete API path

5. `SecretariatPolicyTest`
   - cross-group scope isolation
   - ordinary member cannot register
   - manager authorized only in own office context

6. `SecretariatNoHardDeleteTest`
   - registered records cannot be deleted through domain service

## 15. Migration Order
1. create `secretariat_offices`
2. create `secretariat_records` without current_version FK
3. create `secretariat_record_versions`
4. add `secretariat_records.current_version_id` FK
5. create `secretariat_sequences`
6. create `secretariat_audit_events`

Rollback reverse همین dependency order است.

## 16. S1 Gate
S1 فقط وقتی آماده review است که سناریوی زیر با test خودکار pass شود:

`Create Office → Create Record Draft → Version 1 → Submit → Register atomically with unique registry number → append Audit → Create Version 2 without overwriting Version 1 → transition/void/archive policy enforcement`

هیچ attachment، correspondence، relation یا semantic capability برای عبور از S1 Gate لازم نیست.
