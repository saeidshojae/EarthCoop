# وضعیت اجرای Phase S1

**Branch:** `agent/secretariat-s1-registry-core`

**Base architecture:** `agent/secretariat-master-roadmap` / PR #27

## وضعیت
S1 Registry Core در سطح schema + domain services + policies + tests پیاده‌سازی شده و پس از یک دور self-review و hardening عمیق، Gateهای اجرایی اصلی آن روی GitHub Actions با MySQL 8 و PHP 8.2 با موفقیت عبور کرده‌اند.

آخرین validation کامل موفقِ کد:
- Workflow: `EarthCoop Secretariat S1 Validation`
- Run: `#50`
- GitHub Actions run id: `32166580419`
- Result: `success`

## Gateهای اثبات‌شده
1. PHP syntax gate برای فایل‌های S1: **PASS**
2. `migrate:fresh` روی MySQL 8: **PASS**
3. rollback شش migration دبیرخانه و re-apply: **PASS**
4. PHPUnit کل `tests/Feature/Secretariat`: **PASS**
5. concurrency واقعی شماره ثبت با 12 process مستقل: **PASS**
   - یک Office
   - یک calendar year
   - یک record family
   - sequenceهای یکتا و gap-free از 1 تا 12
   - `last_value = 12`
6. regression authorization گروه‌ها: **PASS**
   - `MessageAuthorizationTest`
   - `GroupRoleManagementTest`

## اصلاحات حاصل از validation و self-review
### Version allocation
CI یک خطای واقعی در version allocation پیدا کرد: رابطه `versions()` برای read ترتیب صعودی داشت و استفاده مجدد از آن برای پیدا کردن latest version باعث می‌شد amendment دوم دوباره version 2 درخواست کند. allocation نسخه اکنون با query مستقل `ORDER BY version_number DESC` انجام می‌شود و تست‌های amendment متوالی آن را پوشش می‌دهند.

### Registration idempotency
ثبت رسمی idempotent است حتی اگر lifecycle رکورد پس از registration به `active` پیش رفته باشد؛ retry ثبت شماره جدید مصرف نمی‌کند و state را به عقب برنمی‌گرداند.

### Aggregate mutation boundary
`SecretariatRecord` بعد از create دیگر اجازه تغییر مستقیم business fields را از مسیر معمول Eloquent نمی‌دهد. تغییر عنوان/موضوع/summary، status، confidentiality، current version pointer، registration identity، provenance و metadata باید از serviceهای دامنه عبور کند.

این guard علاوه بر جلوگیری از overwrite سند رسمی، دو bypass مهم را نیز می‌بندد:
- تغییر مستقیم محتوای draft بدون ایجاد Version
- پرش مستقیم `pending_approval → registered` بدون registration service

### Formal-state structural invariants
هر record در state رسمی باید هم‌زمان دارای موارد زیر باشد:
- registry number/sequence/year/family
- registered actor/time
- approved actor/time
- current version
- current version متعلق به همان record
- current version با `is_official=true`

حتی mutation کنترل‌شده نیز حق شکستن این invariantها را ندارد.

### Version append-only boundary
تمام Versionهای persist‌شده append-only هستند. pending amendment نیز قابل overwrite مستقیم نیست. تنها update مجاز، promotion کنترل‌شده به official با actor/time approval است.

برای رکورد رسمی، pending version نیز بدون workflow کنترل‌شده قابل حذف نیست. self-promotion مستقیم `is_official=true` و تغییر pointer نسخه جاری نیز تست شده و رد می‌شود.

### Permission separation
برای Group Office:
- role 2 (Inspector) می‌تواند draft/report را آماده و submit کند.
- role 2 نمی‌تواند register یا lifecycle رسمی (`activate/close/archive/void/supersede`) را هدایت کند.
- role 3 (Manager) authority ثبت و transition رسمی را دارد.
- `restricted/confidential` تا S2 و ACL صریح، default-deny است.

برای Central/Project Office، schema از S1 آماده است اما authority غیرادمین بدون قرارداد دامنه صریح ساخته نشده؛ این عمداً از ایجاد role engine موازی جلوگیری می‌کند.

### Canonical Office race safety
برای Group/Project Office، duplicate canonical office فقط با `exists()` کنترل نمی‌شود. ساخت office در transaction انجام می‌شود و source row گروه/پروژه با `lockForUpdate()` قفل می‌شود تا دو درخواست هم‌زمان نتوانند دو دفتر canonical برای یک scope بسازند.

## Scope فعلی
فقط S1:
- Office
- Record
- Version
- Sequence
- Audit
- lifecycle
- policy
- invariants
- stable morph tokens
- registry numbering concurrency safety
- aggregate mutation boundary
- formal-state integrity

خارج از scope و عمداً ساخته نشده:
- Attachment
- Relation graph
- ACL table
- Correspondence
- Case
- UI
- Semantic search
- Najm Hoda mutation integration

این موارد طبق Master Roadmap در S2+ اجرا می‌شوند.

## وضعیت review / merge
از نظر automated S1 gate و self-review فنی، implementation به Gate S1 رسیده است. PR #28 همچنان Draft نگه داشته می‌شود تا تصمیم merge آن به branch roadmap آگاهانه انجام شود. هیچ merge مستقیمی به `main` انجام نشده است.
