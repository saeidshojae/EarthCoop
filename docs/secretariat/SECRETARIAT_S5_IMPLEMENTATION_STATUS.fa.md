# وضعیت پیاده‌سازی Phase S5 دبیرخانه EarthCoop

## وضعیت نهایی این فاز

Phase S5 از آخرین head سبز S4 آغاز شد و PR محصول آن `#35` است.

هدف S5 طبق Master Roadmap:

- Case Management
- فعال‌سازی Officeهای سراسری EarthCoop فقط در حد authority واقعی دامنه مبدأ
- قرارداد صریح cross-office reference/transfer
- حفظ اصل `Source Domains Keep Ownership`

## خروجی‌های ساخته‌شده

### Case foundation

- `secretariat_cases`
- `secretariat_case_records`
- `SecretariatCase`
- `SecretariatCaseService`
- `SecretariatCasePolicy`
- ثبت Policy در `AuthServiceProvider`
- Case lifecycle/integrity tests
- authorization tests
- HTTP/UI مستقل پرونده‌ها

### شماره پرونده

شماره پرونده دیگر از database id یا `MAX(id)` مشتق نمی‌شود.

`RegistryNumberService::allocateFamily()` برای family مستقل `CASE` استفاده می‌شود و همان lock-order و sequence architecture اثبات‌شده S1 را reuse می‌کند.

Scope شماره پرونده:

`office + calendar year + CASE family`

### Case UI

- فهرست پرونده‌های مجاز هر Office
- ایجاد پرونده
- صفحه پرونده
- افزودن رکورد رسمی همان Office
- lifecycle: open / on_hold / closed / archived
- نمایش permission-aware رکوردهای عضو
- entry point از Office dashboard

### Cross-office reference

S5 عملیات `reference` را فعال کرده، نه move/copy.

قواعد:

- رکورد مبدأ رسمی است و Office خودش را حفظ می‌کند.
- Case مقصد هیچ Version/Attachment/Registry identity از رکورد مبدأ کپی نمی‌کند.
- pivot نوع `cross_office_reference` و `source_office_id` را ثبت می‌کند.
- actor باید Case مقصد را manage کند، Office مبدأ را ببیند و خود Record مبدأ را طبق RecordPolicy ببیند.
- هر viewer پرونده هنگام rendering دوباره از RecordPolicy مبدأ عبور می‌کند؛ Case visibility مجوز دیدن Record ایجاد نمی‌کند.
- audit دوطرفه در Office مقصد و Office مبدأ ثبت می‌شود.
- move کردن registered record بین Officeها ممنوع است.
- generic copy تا قرارداد snapshot رسمی آینده غیرفعال است.

قرارداد تفصیلی:

`docs/secretariat/SECRETARIAT_S5_CROSS_OFFICE_CONTRACT.fa.md`

### Invariants نهایی S5

- Case مالک یا کپی‌کننده business truth سند نیست.
- draft record وارد Case رسمی نمی‌شود.
- archived case عضو/reference جدید نمی‌پذیرد.
- mutation مستقیم Case از مسیر معمول Eloquent مسدود است.
- hard delete پرونده ممنوع است.
- رکورد رسمی با reference بین‌دفتری از Office مبدأ جابه‌جا نمی‌شود.
- confidentiality رکورد عضو مستقل از Case باقی می‌ماند؛ حتی title و registry number بدون مجوز leak نمی‌شود.
- restricted/confidential Case تا زمان Case ACL مستقل برای non-admin default-deny است.
- non-admin از HTTP نمی‌تواند Case حساس ایجاد کند که بعداً خودش اجازه خواندنش را نداشته باشد.

## Office authority

### Group Office

از membership/role موجود گروه استفاده می‌کند.

### Project Office

Project visibility عمومی به Registry authority تبدیل نمی‌شود.

تا زمانی که Project domain نقش‌های صریح تیم پروژه برای دبیرخانه تعریف نکند:

- project owner: view/manage/inspect
- platform admin: طبق platform policy
- سایر کاربران، حتی برای پروژه `approved + public`: default-deny در Secretariat Office

### Central / Legal Entity / Committee

برای non-admin همچنان default-deny هستند تا source domain واقعی authority صریح ارائه کند. S5 برای این scopeها role engine موازی اختراع نکرده است.

## Recovery بعد از قطع نشست

در بازیابی نشست مشخص شد branch بعد از چهار commit اولیه تا head زیر جلو رفته بود:

`5df28f3053a137b096c86601a9ad754cf73bcf49`

تغییرات موجود بازیابی شدند و توسعه از همان نقطه ادامه یافت؛ چیزی دوباره‌سازی نشد.

اولین validation run (`32177527436`) قبل از اجرای product code شکست خورد، زیرا CI، Composer را قبل از ایجاد `bootstrap/cache` اجرا می‌کرد. validation workflow اصلاح شد و سپس Gate واقعی روی MySQL اجرا شد.

## Gate نهایی اثبات‌شده

کد نهایی S5 قبل از این documentation update روی head:

`68e280745a87ec90dffb5f86f3596d643a415bd4`

Validation:

- workflow: `EarthCoop Secretariat S5 PR Validation`
- run #25
- run id: `32181113001`
- PHP 8.2.33
- Laravel 12.65.0
- MySQL 8.0.46

نتایج:

- PHP syntax: PASS
- `migrate:fresh`: PASS
- rollback + re-apply migration S5: PASS
- Secretariat S1-S5 suite: **78 tests / 307 assertions — PASS**
- Registry numbering concurrency round 1: **12 parallel allocations, 1..12, last_value=12 — PASS**
- round 2: PASS
- round 3: PASS
- `MessageAuthorizationTest`: **23 tests / 121 assertions — PASS**
- `GroupRoleManagementTest`: **4 tests / 16 assertions — PASS**

هشدارهای موجود فقط warning/deprecationهای ابزارها هستند و failure محصول محسوب نمی‌شوند.

## Definition of Done S5

یک Case می‌تواند رکوردهای رسمی همان Office و referenceهای کنترل‌شده از Office دیگر را کنار هم نمایش دهد، بدون جابه‌جایی یا کپی بی‌قاعده truth، و بدون دورزدن Policy رکورد مبدأ.

با این Gate، S5 از نظر فنی بسته و آماده ورود به S6 است.
