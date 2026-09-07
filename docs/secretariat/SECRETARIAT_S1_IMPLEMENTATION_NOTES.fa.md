# یادداشت پیاده‌سازی Phase S1 دبیرخانه EarthCoop

## وضعیت این Slice
این سند وضعیت implementation واقعی S1 را در branch `agent/secretariat-s1-registry-core` ثبت می‌کند و مکمل `SECRETARIAT_S1_SCHEMA_DESIGN.fa.md` است.

## اجزای ساخته‌شده
- `secretariat_offices`
- `secretariat_records`
- `secretariat_record_versions`
- `secretariat_sequences`
- `secretariat_audit_events`
- FK جداگانه `current_version_id`
- پنج Model اصلی
- `SecretariatOfficeService`
- `RegistryNumberService`
- `SecretariatRecordService`
- `SecretariatVersionService`
- `SecretariatAuditService`
- `SecretariatTransitionService`
- morph map پایدار برای scope/source
- `SecretariatOfficePolicy`
- `SecretariatRecordPolicy`
- ثبت Policyها در `AuthServiceProvider`
- test suite اختصاصی S1

## Invariantهای enforceشده
1. شماره ثبت از DB id یا `MAX()+1` تولید نمی‌شود.
2. sequence بر پایه `office + year + family` و با row lock تخصیص می‌یابد.
3. retry ثبت رکوردی که قبلاً شماره گرفته، شماره دوم ایجاد نمی‌کند.
4. taxonomy رکورد محدود به قرارداد S0 است.
5. raw class-name در provenance پذیرفته نمی‌شود.
6. source polymorphic فقط با tokenهای morph map معتبر پذیرفته می‌شود.
7. registered/formal record hard-delete نمی‌شود.
8. official version update/delete نمی‌شود.
9. audit event update/delete نمی‌شود.
10. فیلدهای هویتی/محتوایی رکورد رسمی مستقیماً overwrite نمی‌شوند.
11. amendment جدید تا قبل از approval جای current official version را نمی‌گیرد.
12. amendment قدیمی‌تر نمی‌تواند بعد از ایجاد نسخه جدیدتر، آن را supersede کند.
13. `restricted/confidential` تا قبل از ACL در S2 به‌صورت default-deny رفتار می‌کند.
14. group manager فقط در scope گروه خودش authority ثبت دارد.

## تصمیم مهم درباره Amendment
نسخه جدید پس از registration ابتدا `is_official=false` ساخته می‌شود و current رسمی را تغییر نمی‌دهد.

پس از approval:
- نسخه جدید official می‌شود؛
- `current_version_id` به آن منتقل می‌شود؛
- snapshot نمایشی record فقط در mutation کنترل‌شده به نسخه جدید sync می‌شود؛
- نسخه‌های رسمی قبلی دست‌نخورده باقی می‌مانند.

این رفتار از نمایش ناخواسته محتوای تأییدنشده به‌عنوان سند رسمی جلوگیری می‌کند.

## محدودیت آگاهانه S1
ACL صریح برای `restricted/confidential` در S2 ساخته می‌شود. بنابراین S1 به‌جای حدس‌زدن ACL، sensitive recordها را برای non-admin deny می‌کند.

## Test Coverage ثبت‌شده
- registration numbering/idempotency
- sequence independence
- lifecycle transition rejection
- no hard delete
- immutable official versions
- formal record overwrite guard
- append-only audit model
- amendment approval/current-version semantics
- stale amendment rejection
- taxonomy/source validation
- group scope authorization
- confidential default-deny

## Validation Environment Note
در نشست توسعه فعلی GitHub connector برای خواندن/نوشتن فعال بود، اما محیط shell به `github.com` دسترسی DNS و `gh` CLI نداشت؛ بنابراین اجرای محلی PHPUnit در همین نشست ممکن نشد. تست‌ها در repository ثبت شده‌اند و باید توسط checkout/CI دارای dependencyهای پروژه اجرا شوند. این محدودیت به‌عنوان gate باز باقی می‌ماند و نباید به‌معنای pass شدن تست‌ها تلقی شود.

## Gate باز پیش از merge
- اجرای migrations روی test DB
- اجرای `tests/Feature/Secretariat/*`
- اجرای suite regression مرتبط با Group/Policy
- بررسی MySQL foreign-key behavior برای current-version cycle در migrate:fresh/rollback
- بررسی واقعی concurrency شماره ثبت روی DB engine هدف
- review نهایی schema/tests قبل از هر merge به `main`
