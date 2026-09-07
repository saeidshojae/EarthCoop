# وضعیت پیاده‌سازی Phase S6 دبیرخانه EarthCoop

## هدف

Phase S6 — Search, Knowledge & Retrieval — از آخرین head سبز S5 آغاز شده است.

اصل امنیتی این فاز:

`Authorization prefilter → deterministic retrieval → authoritative RecordPolicy recheck → bounded authorized packet → ranker → grounded Najm Hoda answer`

هیچ LLM، embedding provider، agent یا index مجاز نیست authority مستقل برای خواندن `SecretariatRecord` داشته باشد.

## 1 — Permission-aware deterministic search

پیاده‌سازی شده:

- `SecretariatRecordAccessQuery`
- DB-level candidate authorization قبل از ورود رکورد به retrieval
- final `SecretariatRecordPolicy` recheck به‌عنوان defense in depth
- جلوگیری از starvation نتایج مجاز در limit توسط candidateهای غیرمجاز
- Group Office ordinary access مطابق membership فعال
- leadership فقط برای roleهای 2/3
- `restricted/confidential` فقط با ACL صریح user/group
- Project Office ordinary record عمداً در prefilter مجاز نشده چون `SecretariatRecordPolicy` فعلی آن را مجاز نمی‌داند

فیلترهای deterministic:

- office
- registry number
- record type
- status
- confidentiality
- title
- text روی title/subject/summary/current version body
- date range
- party text
- party user/group
- source type/id با morph-map validation
- case

## 2 — Natural-language candidate generation

برای سؤال طبیعی کاربر، retrieval دیگر فقط کل جمله را به `LIKE` نمی‌دهد.

پیاده‌سازی شده:

- full-query search
- bounded meaningful-term fan-out
- حذف stopwordهای رایج فارسی/انگلیسی
- dedupe رکوردها بین query/termها
- تمام searchهای term-level همچنان از همان authorization prefilter و Policy عبور می‌کنند

بنابراین سؤال‌هایی مانند «در اسناد رسمی دبیرخانه درباره آب چه تصمیمی گرفته شده؟» می‌توانند سند مرتبط با واژه «آب» را پیدا کنند، بدون اینکه fan-out مرز Office/ACL را دور بزند.

## 3 — Knowledge Retrieval Boundary

پیاده‌سازی شده:

- `SecretariatKnowledgeRetrievalService`
- فقط از `SecretariatSearchService` مجوزدار تغذیه می‌شود
- query خالی یا بیش از 2000 کاراکتر رد می‌شود
- limit، per-record character budget و total character budget دارد
- raw query در audit ذخیره نمی‌شود؛ فقط SHA-256 fingerprint ثبت می‌شود
- retrieval محتوای `confidential` event `access_sensitive` ایجاد می‌کند
- خروجی packet شامل هویت رکورد، Office، registry number، type/confidentiality/source و excerpt محدود است
- هیچ global vector index برای اسناد رسمی ساخته نشده است

## 4 — Authorized ranker abstraction

پیاده‌سازی شده:

- `SecretariatKnowledgeRanker`
- `DeterministicSecretariatKnowledgeRanker`
- ranker فقط packetهای ازپیش‌مجاز را دریافت می‌کند
- ranker به Query Builder یا جدول خام دبیرخانه دسترسی ندارد
- spy-ranker test ثابت می‌کند metadata رکورد غیرمجاز حتی وارد ranker نمی‌شود

این boundary اجازه می‌دهد embedding/semantic provider آینده بدون تغییر authority model جایگزین ranker شود.

## 5 — Najm Hoda read-side bridge

پیاده‌سازی شده:

- `NajmHodaSecretariatKnowledgeBridge`
- Bridge در answer/read path است، نه Action Executor
- `User $actor` واقعی از application boundary دریافت می‌شود
- `actor_id` یا `user_id` داخل context هرگز authority retrieval را تغییر نمی‌دهد
- فقط whitelist محدود فیلترها forwarding می‌شود
- `text` و `registry_number` دلخواه context اجازه override کردن query اصلی retrieval را ندارند

## 6 — Grounded responder در مسیر واقعی runtime

پیاده‌سازی شده:

- `NajmHodaSecretariatGroundedResponder`
- اتصال مستقیم به `NajmHodaExecutionService`
- درخواست صریح درباره «دبیرخانه / سند رسمی / نامه رسمی / مصوبه رسمی / شماره ثبت ...» قبل از LLM intercept می‌شود
- responder فقط با `User` واقعی resolve‌شده در server boundary اجرا می‌شود
- legacy orchestrator برای این درخواست فراخوانی نمی‌شود
- در نبود نتیجه مجاز، responder نبودن نتیجه در scope قابل مشاهده کاربر را اعلام می‌کند و نبود جهانی سند را ادعا نمی‌کند
- پاسخ grounded، read-only و bounded است

## 7 — Secretariat page awareness

`NajmHodaPageContextResolver` اکنون routeهای واقعی دبیرخانه را می‌شناسد:

- Directory
- Office
- Cases
- Record Create/Show
- Correspondence
- ACL view

Page Context فقط page identity و capabilityهای توصیفی را حمل می‌کند.

Browser-provided `title/body` یا payload آزاد هرگز وارد context نمی‌شود و resource id دبیرخانه نیز بدون resolver مجوزدار به‌عنوان resource معتبر پذیرفته نمی‌شود.

محتوای واقعی سند فقط از Knowledge Retrieval Boundary می‌آید.

## Evidence

- Run #1 / `32182687638`: Search foundation — PASS
- Run #3 / `32183014056`: Knowledge retrieval boundary — PASS
- Run #6 / `32183613035`: Najm Hoda bridge — PASS
- Run #7 / `32183963777`: final bridge documentation head — PASS
- Run #8 / `32184426812`: authorized ranker isolation — PASS
- Run #9 / `32184851854`: natural-language fan-out + isolation — PASS
- Run #10 / `32187349574`: grounded responder + `ExecutionService` interception + legacy execution regressions — PASS
- Run #11 / `32187842394`: Secretariat page awareness + browser payload non-leakage — PASS

در runهای کامل S6 موارد زیر نیز تکراراً PASS شده‌اند:

- PHP syntax
- MySQL `migrate:fresh`
- all Secretariat S1-S6 feature tests
- Najm Hoda Secretariat bridge/responder/runtime/page-context tests
- 3 × 12-process Registry numbering concurrency
- Group authorization regressions

## وضعیت فعلی

S6 اکنون یک مسیر کامل read-side دارد:

`Najm Hoda widget/runtime → authenticated actor → grounded Secretariat intent → permission-aware retrieval → authorized ranking → bounded answer`

بدون اینکه LLM یا provider خارجی authority مستقل به اسناد رسمی داشته باشد.

## گام‌های بعدی

1. در صورت نیاز، افزودن provider semantic/embedding فقط پشت `SecretariatKnowledgeRanker`
2. اضافه‌کردن resource-specific Secretariat page resolver برای Office/Case/Record فقط با Policy صریح، اگر UI برای page context به metadata بیشتری نیاز پیدا کرد
3. افزودن citation/navigation به صفحه سند برای packetهای مجاز
4. سپس حرکت از «پاسخ درباره اسناد» به قابلیت‌های راهنمایی/آماده‌سازی draft؛ هر write action باید از Capability/Consent/Policy path مستقل عبور کند
