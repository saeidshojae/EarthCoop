# وضعیت اجرای Phase S1

**Branch:** `agent/secretariat-s1-registry-core`

**Base architecture:** `agent/secretariat-master-roadmap` / PR #27

## وضعیت
S1 Registry Core در سطح schema + domain services + policies + tests پیاده‌سازی شده و Gateهای اجرایی اصلی آن روی GitHub Actions با MySQL 8 و PHP 8.2 با موفقیت عبور کرده‌اند.

آخرین validation کامل موفق:
- Workflow: `EarthCoop Secretariat S1 Validation`
- Run: `#16`
- GitHub Actions run id: `32165170252`
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

## اصلاحات حاصل از Gate
CI پیش از سبزشدن یک خطای واقعی در version allocation پیدا کرد: رابطه `versions()` برای read ترتیب صعودی داشت و استفاده مجدد از آن برای پیدا کردن latest version باعث می‌شد amendment دوم دوباره version 2 درخواست کند. allocation نسخه اکنون با query مستقل `ORDER BY version_number DESC` انجام می‌شود و تست‌های amendment متوالی آن را پوشش می‌دهند.

همچنین ثبت رسمی idempotent است حتی اگر lifecycle رکورد پس از registration به `active` پیش رفته باشد؛ retry ثبت شماره جدید مصرف نمی‌کند و state را به عقب برنمی‌گرداند.

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
از نظر automated S1 gate، implementation سبز است. PR #28 همچنان Draft نگه داشته می‌شود تا review معماری/کد انجام شود و سپس درباره merge آن به branch roadmap تصمیم گرفته شود. هیچ merge مستقیمی به `main` انجام نشده است.
