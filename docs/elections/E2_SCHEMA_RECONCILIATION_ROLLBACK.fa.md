# راهبرد Rollback و بازیابی E2 انتخابات

> وضعیت: سند اجرایی Gate فاز E2  
> شاخه: `agent/elections-systemic-rebuild`  
> دامنه: فقط Schema Repair & Data Reconciliation  
> قاعده ایمنی: این سند هیچ مجوزی برای merge به `main` یا deploy production ایجاد نمی‌کند.

## 1. هدف

فاز E2 باید بدهی‌های معنایی و ساختاری انتخابات legacy را بدون حذف یا حدس‌زدن داده تاریخی اصلاح کند. Rollback این فاز نیز باید همین اصل را حفظ کند: برگشت از canonical schema نباید به قیمت از دست رفتن رأی، نامزد، وضعیت legacy یا هویت کاربر تمام شود.

## 2. migrationهای E2

ترتیب canonical این فاز:

1. `2026_08_21_145500_harden_election_vote_candidate_identity.php`
2. `2026_08_21_150000_add_canonical_election_lifecycle_and_acceptance.php`
3. `2026_08_21_151000_add_election_reconciliation_indexes.php`

هیچ‌یک از این migrationها ستون legacy اصلی را rename یا drop نمی‌کنند.

## 3. اصول غیرقابل نقض rollback

- `votes.candidate_id` در E2 حفظ می‌شود؛ `candidate_user_id` یک canonical companion است.
- `candidates.accept_status` حفظ می‌شود؛ `acceptance_status` canonical companion است.
- `elections.is_closed` حفظ می‌شود؛ `lifecycle_status` canonical companion است.
- داده مبهم هرگز برای آسان‌کردن rollback حذف یا تصحیح حدسی نمی‌شود.
- rollback نباید رأی‌ها یا رکوردهای Candidate/Election را delete کند.
- `votes.position` در rollback migration هویت رأی عمداً drop نمی‌شود، چون در قرارداد application legacy وجود داشته و ممکن است در بعضی installationها پیش از E2 توسط patch دیگری ایجاد شده باشد.
- `candidates.accept_status` پس از rollback به ENUM قدیمی `accepted/declined` برگردانده نمی‌شود. application legacy مقادیر `0/1/2` نیز نوشته است و narrow کردن ستون می‌تواند destructive باشد. باقی‌ماندن آن به شکل widened `VARCHAR(32)` یک تصمیم ایمنی عمدی است.

## 4. Preflight قبل از apply

در هر محیط دارای داده واقعی ابتدا باید audit بدون mutation اجرا شود:

```bash
php artisan elections:audit-data --json
```

موارد زیر blocker برای hard constraint هستند:

- `votes_unresolved_candidate_user > 0`
- رأی با voter مفقود
- رأی با election مفقود
- `candidate_user_id` با User مفقود
- Candidate با User یا Election مفقود
- duplicate vote key
- duplicate candidate membership
- هر raw status غیرمنتظره

وجود blocker به معنی حذف خودکار داده نیست. داده باید evidence-preserving باقی بماند و reconciliation جداگانه شود.

## 5. Gate ارتقای legacy

فایل `scripts/ci/elections-e2-gate.sh` مسیر زیر را روی یک دیتابیس کاملاً ایزوله اجرا می‌کند:

1. ساخت pre-E2 schema واقعی با کنارگذاشتن موقت فقط سه migration E2؛
2. تزریق fixture نماینده legacy؛
3. apply ترتیبی سه migration E2؛
4. اجرای `elections:audit-data --json --fail-on-issues`؛
5. assert روی identity backfill و canonical lifecycle/acceptance؛
6. rollback دقیق migrationهای E2؛
7. assert حفظ رأی‌ها و Candidateها و حذف canonical columns؛
8. re-apply migrationها؛
9. audit مجدد و assert مجدد backfill.

این Gate عمداً هر دو معنای تاریخی `votes.candidate_id` را پوشش می‌دهد: User ID مستقیم و Candidate ID قابل اثبات.

## 6. شرایط Abort

apply یا rollout E2 باید متوقف شود اگر هر یک از موارد زیر رخ دهد:

- migration exception؛
- افزایش غیرمنتظره unresolved candidate identity؛
- ایجاد orphan جدید؛
- duplicate جدید در ballot/candidate membership؛
- از بین رفتن row count رأی یا Candidate؛
- failure در audit fail-closed؛
- failure در targeted Elections tests؛
- failure در Full Integration Validation.

در صورت failure هیچ تلاش خودکاری برای «تمیز کردن» داده production مجاز نیست.

## 7. Rollback اجرایی

در محیط کنترل‌شده و فقط پیش از ورود قابلیت‌های وابسته E3+، سه migration E2 باید از آخر به اول rollback شوند. پس از rollback این کنترل‌ها الزامی است:

- row count جدول `votes` تغییر نکرده باشد؛
- row count جدول `candidates` تغییر نکرده باشد؛
- ستون‌های canonical اضافه‌شده E2 حذف شده باشند؛
- ستون‌های legacy و مقادیرشان باقی مانده باشند؛
- widened بودن `accept_status` به عنوان safety residue پذیرفته شود؛
- اگر `position` توسط migration E2 ایجاد شده باشد نیز به دلیل قرارداد legacy به‌صورت عمدی باقی می‌ماند.

Rollback فقط schema compatibility را برمی‌گرداند؛ historical ambiguity را حل یا پاک نمی‌کند.

## 8. Re-apply / Recovery

بعد از rollback باید امکان re-apply همان سه migration بدون تغییر داده legacy وجود داشته باشد. Gate این مسیر را نیز تست می‌کند. پس از re-apply:

```bash
php artisan elections:audit-data --json --fail-on-issues
```

باید مجدداً موفق باشد و canonical identities/statuses قابل اثبات همان نتایج قبلی را تولید کنند.

## 9. Hard FK / UNIQUE

FK و UNIQUEهای سخت در E2 فقط زمانی مجازند که audit روی snapshot واقعی نشان دهد `hard_constraints_ready=true` و هیچ unresolved/orphan/duplicate blocker باقی نمانده است.

تا آن زمان indexهای reconciliation کافی‌اند و constraint سخت عمداً deferred است. هدف این تصمیم جلوگیری از دو خطر است:

1. شکست migration روی production legacy؛
2. وسوسه حذف داده برای عبور دادن migration.

## 10. شرط بسته‌شدن E2

E2 فقط زمانی `DONE` اعلام می‌شود که همگی برقرار باشند:

- migrate fresh موفق؛
- targeted Elections tests موفق؛
- legacy upgrade gate موفق؛
- rollback + re-apply gate موفق؛
- audit fail-closed موفق؛
- Full Integration Validation موفق؛
- هیچ regression شناخته‌شده‌ای در حوزه‌های گروه‌ها، نجم هدا، نجم بهار و Stock دیده نشود.

تا قبل از این نقطه E3 نباید به عنوان source-of-truth runtime فعال شود.