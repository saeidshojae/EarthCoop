# وضعیت پیاده‌سازی Phase S6 دبیرخانه EarthCoop

## هدف

Phase S6 — Search, Knowledge & Retrieval — از آخرین head سبز S5 آغاز شده است.

اصل امنیتی این فاز:

`Authorization prefilter → deterministic retrieval → authoritative RecordPolicy recheck → bounded knowledge packet`

هیچ LLM، embedding provider، agent یا index مجاز نیست authority مستقل برای خواندن `SecretariatRecord` داشته باشد.

## بخش 1 — Permission-aware deterministic search

پیاده‌سازی شده:

- `SecretariatRecordAccessQuery`
- DB-level candidate authorization قبل از ورود رکورد به retrieval
- final `SecretariatRecordPolicy` recheck به‌عنوان defense in depth
- جلوگیری از starvation نتایج مجاز در limit توسط candidateهای غیرمجاز
- Group Office ordinary access مطابق membership فعال
- leadership فقط برای roleهای 2/3
- `restricted/confidential` فقط با ACL صریح user/group
- Project Office ordinary record عمداً در prefilter مجاز نشده چون `SecretariatRecordPolicy` فعلی آن را مجاز نمی‌داند؛ توسعه authority رکورد Project Office باید در قرارداد مستقل انجام شود

فیلترهای deterministic فعلی:

- office
- registry number
- record type
- status
- confidentiality
- title
- text روی title/subject/summary/current official-or-current version body
- date range
- party text
- party user/group
- source type/id با morph-map validation
- case

## بخش 2 — Knowledge Retrieval Boundary

پیاده‌سازی شده:

- `SecretariatKnowledgeRetrievalService`
- فقط از `SecretariatSearchService` مجوزدار تغذیه می‌شود
- query خالی یا بیش از 2000 کاراکتر رد می‌شود
- limit، per-record character budget و total character budget دارد
- raw query در audit ذخیره نمی‌شود؛ فقط SHA-256 fingerprint ثبت می‌شود
- retrieval محتوای `confidential` event `access_sensitive` ایجاد می‌کند
- خروجی packet شامل هویت رکورد، Office، registry number، type/confidentiality/source و excerpt محدود است
- در S6 هیچ global vector index برای اسناد رسمی ساخته نشده است

## بخش 3 — Najm Hoda read-side bridge

پیاده‌سازی شده:

- `NajmHodaSecretariatKnowledgeBridge`
- Bridge در answer/read path است، نه Action Executor
- `User $actor` واقعی از application boundary دریافت می‌شود
- `actor_id` یا `user_id` داخل context هرگز authority retrieval را تغییر نمی‌دهد
- فقط whitelist محدود فیلترها به Secretariat forwarding می‌شود
- `text` و `registry_number` دلخواه context اجازه override کردن query اصلی retrieval را ندارند

## Evidence موجود

S6 Gate run #1 / `32182687638` روی head اولیه Search:

- PHP syntax: PASS
- MySQL migrate:fresh: PASS
- all Secretariat S1-S6 tests: PASS
- 3 × 12-process Registry numbering concurrency: PASS
- Group authorization regressions: PASS

Knowledge Retrieval run #3 / `32183014056`:

- full S6 Gate: PASS
- confidential ACL isolation tests: PASS
- sensitive access audit tests: PASS
- context character budget tests: PASS

## Gate باز

Bridge نجم هدا پس از توسعه workflow CI باید در run جدید صریحاً با test مستقل زیر PASS شود:

`tests/Feature/NajmHoda/NajmHodaSecretariatKnowledgeBridgeTest.php`

تا آن run سبز نشده، اتصال Najm Hoda به دبیرخانه نهایی اعلام نمی‌شود.

## گام بعد پس از Gate

1. ثبت evidence نهایی Bridge
2. تصمیم درباره semantic ranking implementation بدون ایجاد permission bypass
3. semantic ranker باید فقط روی packet/candidate ازپیش‌مجاز کار کند
4. هیچ embedding/index سراسری از metadata یا body رکورد غیرمجاز ساخته نشود
5. سپس اتصال کنترل‌شده به context builder/grounded responder نجم هدا
