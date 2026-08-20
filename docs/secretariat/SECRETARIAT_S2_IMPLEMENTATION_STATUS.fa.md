# وضعیت اجرای Phase S2 — Attachments, Relations, ACL & Basic UI

**Branch:** `agent/secretariat-s2-documents-relations-acl-ui`

**Base:** `agent/secretariat-s1-registry-core` / PR #28

**Review PR:** #29

## وضعیت
Phase S2 از نظر implementation و automated technical gate **بسته شده است**.

S2 هسته Registry در S1 را به یک دبیرخانه قابل استفاده روزمره تبدیل می‌کند: سند می‌تواند پیوست version-pinned داشته باشد، با اسناد دیگر رابطه جهت‌دار بسازد، برای اسناد حساس ACL صریح داشته باشد، از HTTP/UI امن خوانده و ثبت شود، و timeline/نسخه‌ها/پیوست‌ها/روابط آن دیده شوند.

هیچ merge به `main` انجام نشده است. PR #29 همچنان Draft و مبتنی بر S1 باقی می‌ماند.

## Attachments
- جدول `secretariat_attachments`
- تعلق اجباری به Record و pin شدن به RecordVersion
- storage disk/key مستقل از legacy `files`
- SHA-256 checksum
- MIME / size / original name / uploader / upload time
- attachment object پس از create append-only است
- FK نسخه و Record از حذف بی‌صدای pin/history جلوگیری می‌کنند
- hard-delete attachment سند formal ممنوع است
- افزودن retroactive file به Version رسمی ممنوع است؛ سند formal برای evidence جدید نیازمند amendment version است
- cleanup storage برای draft delete خارج از DB transaction و فقط پس از commit موفق انجام می‌شود
- audit event: `attachment_added`

## Relations
- جدول `secretariat_relations`
- relation جهت‌دار مطابق taxonomy S0
- idempotent create
- unique source/target/type tuple
- self relation ممنوع
- cross-office relation در S2 ممنوع و طبق roadmap به S5 موکول است
- lock ordering قطعی بر اساس Record ID برای جلوگیری از A→B / B→A deadlock
- relation درگیر با Record رسمی hard-delete نمی‌شود
- audit event: `relation_added`

## ACL و اسناد حساس
- جدول `secretariat_acl_entries`
- principalهای پایدار: `user` و `group`
- permission اولیه: `view`
- expiry اختیاری
- revoke بدون حذف history
- grant generation append-only؛ re-grant پس از revoke/expiry row جدید می‌سازد
- grant identity و metadata خارج از service قابل rewrite نیست
- `restricted` و `confidential` فقط با ACL صریح قابل مشاهده‌اند
- `confidential` در HTTP read/download رویداد `access_sensitive` ثبت می‌کند
- denied access هیچ `access_sensitive` جعلی ثبت نمی‌کند
- manager با policy مستقل `manageAcl` grant/revoke می‌کند
- creator سند حساس در creation یک grant صریح و audit‌شده دریافت می‌کند؛ hidden bypass وجود ندارد
- audit events: `acl_granted`, `acl_revoked`, `access_sensitive`

## Permission-aware deterministic search
`SecretariatSearchService` در S2 search سریع محدود و قطعی ارائه می‌دهد:
- office
- registry number
- record type
- status
- title/subject
- date range

حداکثر 100 نتیجه خروجی دارد و هر candidate قبل از return از `SecretariatRecordPolicy` عبور می‌کند. بنابراین restricted/confidential حتی از طریق search نیز metadata leak نمی‌کنند.

Semantic/pre-filter search مقیاس‌پذیر همچنان مطابق roadmap متعلق به S6 است.

## HTTP boundary و Basic UI
Routeهای دبیرخانه در فایل مستقل `routes/secretariat.php` و پشت authentication قرار دارند.

قابلیت‌های UI/HTTP:
- `/secretariat` — فهرست Officeهای قابل مشاهده کاربر
- Office dashboard
- create new draft
- optional initial attachment
- drafts / pending / formal record visibility
- quick search by number/title/type/status/date
- record detail page
- current version + version history
- attachment list + permission-aware download
- directed incoming/outgoing relations
- audit timeline
- submit for approval
- manager approve/register
- ACL management page
- ACL grant/revoke با history preservation

Write endpointهای حساس rate-limited هستند و controllerها business mutation مستقیم انجام نمی‌دهند؛ همه mutationها از serviceهای domain عبور می‌کنند.

## HTTP security invariants
- Record متعلق به Office دیگر حتی با URL دست‌کاری‌شده 404 می‌دهد.
- کاربر فاقد ACL نمی‌تواند confidential record یا attachment آن را با URL مستقیم بخواند.
- download همان RecordPolicy را enforce می‌کند.
- confidential show/download access audit ایجاد می‌کند.
- ACL grant/revoke فقط برای actor دارای `manageAcl` ممکن است.
- ordinary member نمی‌تواند ACL را مدیریت کند.
- Office directory فقط Officeهایی را نشان می‌دهد که OfficePolicy اجازه view می‌دهد.

## Test Suite
تست‌های S2:
- `SecretariatS2AttachmentTest`
- `SecretariatS2AclTest`
- `SecretariatS2RelationTest`
- `SecretariatS2SearchTest`
- `SecretariatS2HttpUiTest`
- `SecretariatS2AccessDirectoryTest`

همراه با تمام regressionهای S1، suite نهایی **39 test** دارد.

یک failure در validation UI آشکار شد که domain failure نبود: CI asset build نداشت و `layouts.unified` هنگام render دنبال Vite manifest می‌گشت. تست HTTP با helper رسمی Laravel یعنی `withoutVite()` اصلاح شد؛ production layout یا Vite behavior تغییر نکرد.

## Final Validation Gate
Validation روی PHP 8.2 + MySQL 8 شامل موارد زیر با موفقیت عبور کرده است:
1. PHP syntax کل Secretariat S1/S2
2. `migrate:fresh`
3. rollback و re-apply سه migration S2
4. تمام 39 feature test دبیرخانه
5. سه دور مستقل × 12 process concurrency واقعی Registry numbering
6. `MessageAuthorizationTest`
7. `GroupRoleManagementTest`

آخرین code-head validation پیش از این documentation-only commit: `EarthCoop Secretariat S2 PR Validation`, run `#18`, run id `32171592517`: **SUCCESS**.

## S2 Acceptance Gate
Gate roadmap:

> مدیر بتواند سندی با پیوست ثبت کند و تاریخچه/نسخه/روابط آن را ببیند.

این سناریو اکنون در UI/HTTP و tests پوشش داده شده است. علاوه بر آن، ACL اسناد حساس، confidential read audit، office directory و deterministic permission-aware search نیز در S2 فعال‌اند.

## خارج از S2
طبق Master Roadmap عمداً وارد این فاز نشده‌اند:
- Meeting/Governance adapters — S3
- Correspondence/Dispatch/Party — S4
- Case management و cross-office policy — S5
- semantic/full retrieval — S6
- Najm Hoda formal Secretariat operations — S7
- legal formality/signature/export package — S8
- production-scale storage/performance/legacy migration — S9

## Merge Safety
- PR #28 (S1) همچنان Draft است.
- PR #29 (S2) همچنان Draft است و base آن S1 است.
- هیچ merge مستقیمی به `main` انجام نشده است.
- PR #30 فقط harness موقت CI بوده و نباید merge شود.
