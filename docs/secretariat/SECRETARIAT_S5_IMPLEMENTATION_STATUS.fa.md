# وضعیت پیاده‌سازی Phase S5 دبیرخانه EarthCoop

## وضعیت فعلی

Phase S5 از آخرین head سبز S4 آغاز شده است و PR محصول آن `#35` است.

هدف S5 طبق Master Roadmap:

- Case Management
- فعال‌سازی Officeهای سراسری EarthCoop با authority دامنه مبدأ
- قرارداد صریح cross-office reference/transfer
- حفظ اصل `Source Domains Keep Ownership`

## آنچه تا این نقطه ساخته شده

### Case foundation

- `secretariat_cases`
- `secretariat_case_records`
- `SecretariatCase`
- `SecretariatCaseService`
- `SecretariatCasePolicy`
- ثبت Policy در `AuthServiceProvider`
- تست‌های Case lifecycle/integrity
- تست‌های authorization

### Invariants فعلی

- Case سند را کپی یا مالک truth آن نمی‌کند؛ فقط به `SecretariatRecord` رسمی reference می‌دهد.
- draft record وارد پرونده رسمی نمی‌شود.
- در slice اول، membership پرونده same-office است؛ cross-office قبل از policy صریح فعال نمی‌شود.
- archived case عضو جدید نمی‌پذیرد.
- mutation مستقیم Case از مسیر معمول Eloquent مسدود است.
- hard delete پرونده ممنوع است.
- create/add-record/transition در audit append-only ثبت می‌شوند.

### Office authority

- Group Office از membership/role موجود گروه استفاده می‌کند.
- Project Office از authority موجود Najm Bahar Project استفاده می‌کند؛ owner حقیقی پروژه می‌تواند view/manage/inspect کند.
- برای central/legal_entity/committee هنوز authority حدسی ساخته نشده و non-admin default-deny است.
- `restricted/confidential` Case هنوز Case ACL مستقل ندارد؛ برای جلوگیری از permission shortcut، فعلاً برای non-admin default-deny است.

## Recovery بعد از قطع نشست

branch پس از اولین چهار commit S5 تا head زیر جلو رفته بود:

`5df28f3053a137b096c86601a9ad754cf73bcf49`

شش commit بعدی شامل hardening authorization و Policy registration بوده‌اند؛ بنابراین کار از آن head ادامه یافته و چیزی بازسازی یا دوباره‌نویسی نشده است.

## CI

اولین S5 validation run:

- run: `32177527436`
- result: FAILURE before product validation
- cause: workflow `composer install` را پیش از ایجاد `bootstrap/cache` و مسیرهای writable Laravel اجرا می‌کرد؛ `artisan package:discover` متوقف شد.
- نتیجه: این failure هیچ failure در migration/service/policy/test محصول را اثبات نمی‌کرد، چون آن مراحل اصلاً اجرا نشدند.

validation base اصلاح شد تا همان ترتیب اثبات‌شده S4 را داشته باشد:

1. prepare Laravel writable directories
2. composer validate/install
3. isolated MySQL test environment
4. PHP syntax
5. migrate:fresh
6. S5 rollback/re-apply
7. all Secretariat S1-S5 tests
8. three rounds of 12-process Registry numbering concurrency
9. group authorization regressions

## Gate باز

تا زمانی که run جدید روی head جاری همه مراحل بالا را PASS نکند، Case foundation سبز اعلام نمی‌شود.

پس از Gate سبز، ترتیب ادامه S5:

1. permission-aware Case HTTP/UI
2. Case timeline و record membership UI
3. explicit cross-office reference/transfer contract
4. central/project office activation فقط در حد authority واقعی source domain
5. تست isolation و no-truth-copy برای cross-office behavior
