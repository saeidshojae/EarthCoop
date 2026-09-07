# Master Roadmap بازسازی انتخابات سیستمی EarthCoop

> وضعیت: مرجع اجرایی بازسازی انتخابات سیستمی  
> شاخه توسعه: `agent/elections-systemic-rebuild`  
> پایه امن: `agent/integration-pre-elections-current-main` / PR #80  
> PR توسعه: #81  
> قاعده ایمنی: هیچ ادغامی به `main` در این برنامه مجاز نیست مگر با تصمیم صریح مالک پروژه پس از validation نهایی.

## 1. هدف

این سند نتیجه reconcile چهار منبع است:

1. تصمیم‌های تثبیت‌شده در گفت‌وگوی پروژه «گزارش ممیزی انتخابات»؛
2. تصمیم‌های تثبیت‌شده در گفت‌وگوی پروژه «بررسی و مستندسازی انتخابات»؛
3. اسناد انتخابات موجود در repository، به‌ویژه `ELECTIONS_SYSTEMIC_REBUILD_BASELINE_AUDIT.fa.md`؛
4. رفتار واقعی کد در branch `agent/elections-systemic-rebuild`.

هدف، patch کردن چند bug پراکنده نیست. مقصد یک **Election Domain دائمی، خودکار، قطعی، auditپذیر، قابل تنظیم و مستقل از Controller/UI** است که بتواند انتخابات بدون نامزد رسمی EarthCoop را از آغاز تا نصب مدیران و بازرسان و نمایندگی سطوح بالاتر اداره کند.

## 2. اصول محصولی تثبیت‌شده

### 2.1 دو سامانه تصمیم‌گیری مستقل

- **System Election** برای انتخاب مسئولان رسمی، در حال حاضر مدیر و بازرس.
- **Poll / Internal Decision Voting** برای نظرسنجی، پیشنهاد، تصمیم و مصوبه.

این دو domain نباید دوباره در یک مدل یا lifecycle ادغام شوند.

### 2.2 انتخابات بدون نامزد رسمی

عضو از میان اعضای واجد شرایط، اشخاص مورد اعتماد خود را برای نقش مدیر/بازرس معرفی می‌کند. وجود یک slate از «کاندیداهای ثبت‌نام‌کرده» شرط رأی‌گیری نیست.

در نتیجه، اصطلاح legacy `candidate_id` در جدول `votes` که امروز در واقع **User ID منتخب** است، یک بدهی معنایی P0 محسوب می‌شود.

### 2.3 نقش‌ها و ظرفیت‌ها policy-driven هستند

تعداد مدیر، تعداد بازرس، حدنصاب شروع، زمان‌های مرحله‌ها، response window و متن قرارداد/پذیرش نباید magic number یا متن hard-coded باشند. منبع سیاست باید قابل audit و قابل تنظیم از پنل ادمین باشد.

### 2.4 نتیجه رأی ≠ پذیرش مسئولیت ≠ نصب در سمت

سه مفهوم مستقل‌اند:

1. **Ranked / Selected by tally**: شخص بر اساس شمارش رأی در محدوده کرسی‌ها یا reserve قرار گرفته است.
2. **Accepted / Declined / Expired**: شخص مسئولیت پیشنهادی را پذیرفته، رد کرده یا مهلتش گذشته است.
3. **Appointed**: نصب رسمی و side-effectهای role/representation با موفقیت و به‌صورت idempotent اعمال شده‌اند.

کد فعلی این سه مرحله را مخلوط کرده و باید تفکیک شود.

### 2.5 جایگزینی باید قطعی باشد

در decline، timeout یا invalidation، نفر بعدی باید طبق ranking ثبت‌شده و tie-breaker تعریف‌شده انتخاب شود. **انتخاب تصادفی ممنوع است.**

### 2.6 کامنت رأی/پس‌گرفتن رأی

معماری مقصد باید برای rationale/comment همراه رأی یا withdrawal آماده باشد و visibility آن policy-driven باشد. تصمیم محصولی موجود شامل امکان محدودکردن مشاهده به مخاطب منتخب نیز هست.

> Source gap: عبارت دقیق تمام گزینه‌های visibility و متن نهایی بعضی بندهای «بخش ۱۰» در snapshot فعلی repository وجود ندارد. تا بازیابی متن authoritative، schema/policy point ساخته می‌شود ولی copy نهایی از خودمان اختراع نمی‌شود.

### 2.7 Najm Hoda فقط کمک‌کننده است

در فاز بعدی نجم هدا می‌تواند پیشنهاد explainable و اختیاری درباره اعضای مناسب ارائه کند؛ اما:

- رأی را به جای عضو ثبت نمی‌کند؛
- انتخاب را پنهانی هدایت نمی‌کند؛
- داده خصوصی اعضای دیگر را افشا نمی‌کند؛
- دلیل پیشنهاد باید قابل توضیح باشد؛
- معیارهای حساس/خصوصی مبنای پنهان recommendation نمی‌شوند.

## 3. Reconciliation با کد فعلی

### 3.1 موارد قابل حفظ

- مدل پایه `Election` و ارتباط آن با Group می‌تواند با migration تکاملی حفظ شود.
- تنظیمات سطحی `GroupSetting` نقطه شروع مناسبی برای policy lookup است.
- تفکیک رأی manager/inspector در داده legacy قابل migrate است.
- Group event publishing و notification infrastructure قابل reuse است.

### 3.2 ایرادهای P0 تأییدشده در کد

1. `Vote.candidate_id` در `ElectionController::submitVote` مستقیماً `userId` دریافت می‌کند؛ نام ستون و relation با معنای واقعی ناسازگار است.
2. `finishElection()` ابتدا status نامزدها را reset می‌کند و سپس برندگان tally را با `accept_status = 1` علامت می‌زند؛ یعنی «برنده» با «پذیرفته» یکی شده است.
3. `candidates.accept_status` در migration legacy enum متنی `accepted/declined` است، در حالی که کد جاری مقادیر عددی `0/1/2` مصرف می‌کند.
4. lifecycle canonical persisted وجود ندارد؛ وضعیت از timestampها و `is_closed` استنتاج می‌شود.
5. پایان انتخابات دستی و Controller-driven است.
6. پذیرش/رد مسئولیت در `ProfileController` قرار دارد و همان‌جا role/representation side-effect اعمال می‌شود.
7. tie/backfill در مسیر legacy می‌تواند به انتخاب random برسد.
8. eligibility snapshot برای voter/selected member وجود ندارد.
9. ballot invariants و ظرفیت‌ها در همه مسیرها یک منبع مرکزی ندارند.
10. subtypeهای GroupSetting با lookup تکراری و پراکنده resolve می‌شوند.
11. comment/withdrawal audit model کامل وجود ندارد.
12. تست‌های domain-level کافی برای lifecycle، tally، acceptance، replacement و representation وجود ندارد.

## 4. Invariantهای غیرقابل نقض

1. هر Election دقیقاً به یک Group تعلق دارد.
2. در هر Group در هر لحظه حداکثر یک election cycle فعال از یک نوع مجاز است.
3. رأی‌دهنده باید در snapshot واجدان رأی همان cycle باشد.
4. منتخب باید در snapshot افراد قابل انتخاب همان cycle باشد.
5. یک عضو نمی‌تواند در یک ballot برای یک position بیش از یک رأی به یک شخص بدهد.
6. تعداد انتخاب‌های هر position از ظرفیت policy تجاوز نمی‌کند.
7. tally با داده freeze‌شده و algorithm/version ثبت‌شده انجام می‌شود.
8. نتیجه tally بعد از finalize immutable است؛ correction فقط با event/decision صریح و audit trail.
9. tie-breaker قطعی، مستند و قابل بازتولید است.
10. Decline یا timeout نتیجه تاریخی tally را پاک نمی‌کند؛ فقط appointment chain را جلو می‌برد.
11. appointment idempotent است.
12. representation side-effect فقط از Appointment Service اعمال می‌شود.
13. هیچ Controller مستقیماً role/representation را به‌عنوان side-effect انتخابات mutate نمی‌کند.
14. transitionها فقط از Election domain service انجام می‌شوند.
15. job retry نباید transition یا appointment را دوبار اجرا کند.
16. همه تغییرات حساس actor/reason/reference/timestamp دارند.

## 5. معماری مقصد

```text
ElectionPolicyResolver
        │
        ▼
ElectionCycle ──────► EligibilitySnapshot
        │                    │
        ▼                    ▼
    Ballot/Vote ─────► Vote Audit Events
        │
        ▼
Deterministic Tally
        │
        ▼
Ranked Result
        │
        ▼
Responsibility Offer
  accept / decline / expire
        │
        ▼
Election Appointment
        │
        ▼
Role + Higher-Level Representation
        │
        ▼
Audit / Notifications / Group Events
```

Controllers/API/UI فقط adapter این domain خواهند بود.

## 6. Lifecycle canonical

مقصد پیشنهادی:

- `scheduled`
- `open`
- `closed`
- `tallying`
- `awaiting_acceptance`
- `appointing`
- `filled`
- `exhausted`
- `cancelled`

برای compatibility، در rollout اولیه resolver می‌تواند state legacy را از `is_closed` و timestampها ترجمه کند؛ سپس ستون canonical به source of truth تبدیل می‌شود.

## 7. Roadmap اجرایی

### Phase E0 — Baseline & Reconciliation Freeze — DONE

- branch امن و Draft PR #81.
- baseline audit ثبت‌شده.
- مرور مسیرهای Controller/Model/Migration/Settings/acceptance.
- ثبت discrepancyهای جدید، از جمله mismatch واقعی `accept_status`.
- تولید همین Master Roadmap.

**Gate:** هیچ refactor رفتاری پیش از ثبت vocabulary و invariantها.

### Phase E1 — Canonical Domain Vocabulary & Compatibility Layer — P0

- `ElectionPosition` برای manager/inspector.
- `ElectionAcceptanceStatus` برای pending/accepted/declined/expired.
- `ElectionLifecycleStatus`.
- `ElectionPolicyResolver` برای یکپارچه‌کردن GroupSetting lookup و subtypeها.
- `ElectionPhaseResolver` برای ترجمه موقت legacy state.
- تست unit برای mappingها و policy resolution.

**هدف:** حذف magic valueها بدون شکستن داده legacy.

### Phase E2 — Schema Repair & Data Reconciliation — P0

- migration تکاملی برای statusهای canonical.
- پایان دادن به mismatch enum متنی DB با integerهای کد.
- تعیین نام/ستون semantic برای selected member به جای legacy `candidate_id`.
- migration/backfill بدون از دست دادن رأی‌های فعلی.
- unique/index/FKهای لازم.
- compatibility read برای rollout تدریجی.

**Gate:** migrate fresh + migration روی snapshot legacy + rollback strategy.

### Phase E3 — Election Cycle & Automatic State Machine — P0

- `ElectionLifecycleService`.
- transition matrix صریح.
- Scheduler/command idempotent برای:
  - open when threshold/policy conditions are met؛
  - close voting window؛
  - tally؛
  - response timeout؛
  - replacement؛
  - final fill/exhaustion.
- lock/transaction برای جلوگیری از double transition.

**Gate:** هیچ `finishElection()` دستی منبع حقیقت نباشد.

### Phase E4 — Eligibility & Frozen Snapshots — P0

- snapshot واجدان رأی در زمان open.
- snapshot افراد قابل انتخاب.
- ثبت reason برای exclusion.
- policy روشن برای member status/active scope/role incompatibility.
- جلوگیری از تغییر نتیجه تاریخی با تغییر membership بعدی.

### Phase E5 — Ballot v2 + Vote/Withdrawal Audit — P0/P1

- validation مرکزی ظرفیت manager/inspector.
- جلوگیری از duplicate/self-invalid selections طبق policy نهایی.
- update ballot به‌صورت transactional، نه delete-all بدون history.
- eventهای `vote_cast`, `vote_changed`, `vote_withdrawn`.
- comment/rationale optional.
- visibility policy برای comment.
- immutable audit trail و current projection جدا.

### Phase E6 — Deterministic Tally & Tie Policy — P0

- `ElectionTallyService`.
- ranking برای هر position مستقل.
- snapshot تعداد رأی و رتبه.
- tie-breaker versioned و قطعی.
- حذف کامل `random` از election replacement.
- reproducibility test: همان input همیشه همان ranking.

### Phase E7 — Responsibility Offer & Acceptance — P0

- انتقال acceptance/decline از `ProfileController` به Election domain.
- مدل `ElectionResponsibilityOffer` یا معادل.
- status: pending/accepted/declined/expired.
- deadline و notification.
- متن شرایط/وظایف manager و inspector از policy/admin، نه controller.
- preserve history حتی پس از decline.

### Phase E8 — Appointment & Representation — P0

- `ElectionAppointmentService`.
- نصب role در group به‌صورت idempotent.
- تبدیل عضویت نماینده در سطح بالاتر از observer به active طبق قواعد EarthCoop.
- رعایت قاعده «بالاترین کرسی معتبر» و جلوگیری از role conflict.
- revoke/replace با audit.
- eventهای رسمی appointment.

### Phase E9 — Permanent/Systemic Election Continuity — P1

- شروع cycle بعدی طبق policy دوره‌ای.
- vacancy handling بین cycleها.
- ruleهای استمرار مسئولیت تا appointment جانشین.
- recovery پس از crash/job failure.
- historical cycles per group.

### Phase E10 — Admin Policy Console — P1

قابل تنظیم از پنل ادمین:

- threshold شروع؛
- manager seats؛
- inspector seats؛
- voting duration؛
- response duration؛
- cycle interval؛
- enable/disable per group level/type؛
- متن قرارداد/وظایف manager؛
- متن قرارداد/وظایف inspector؛
- comment visibility options؛
- سایر متن‌های authoritative بخش ۱۰ پس از بازیابی نسخه دقیق.

تغییر policy باید versioned/effective-dated و audit شود؛ تغییر policy نباید cycle جاری را بی‌صدا عوض کند.

### Phase E11 — UI/API Rebuild — P1

- UI phase-aware.
- حذف hardcoded top-N.
- نمایش ظرفیت واقعی هر position.
- ballot validation هم client و هم server.
- نمایش وضعیت رأی/withdrawal/acceptance روشن.
- accessibility و mobile.
- خطاهای domain به پیام‌های قابل فهم.

### Phase E12 — Notifications & Communication — P1

- election opened.
- closing soon.
- vote recorded/updated.
- responsibility offered.
- deadline reminder.
- accepted/declined/expired.
- appointment/replacement.
- cycle completed.

همه notificationها event-driven و idempotent.

### Phase E13 — Najm Hoda Election Intelligence — P2

فقط بعد از تثبیت election domain:

- explainable suggestions برای افراد شایسته؛
- معیارهای مجاز و قابل مشاهده؛
- privacy scope؛
- conflict-of-interest disclosure؛
- user remains final decision maker؛
- ثبت اینکه recommendation نمایش داده شده، بدون دستکاری vote.

### Phase E14 — Security, Audit & Abuse Resistance — P0 gate before production

- authorization matrix برای voter/manager/inspector/admin/system.
- protection against forged group/election IDs.
- rate limits.
- concurrency locks.
- immutable audit records.
- admin intervention requires reason.
- no silent mutation of historical result.
- privacy tests برای vote comments.

### Phase E15 — Test Constitution — P0 gate

حداقل suite:

- `ElectionPolicyResolverTest`
- `ElectionLifecycleTest`
- `ElectionThresholdTest`
- `ElectionEligibilitySnapshotTest`
- `ElectionBallotInvariantTest`
- `ElectionVoteAuditTest`
- `ElectionTallyDeterminismTest`
- `ElectionTieBreakTest`
- `ElectionAcceptanceTest`
- `ElectionAcceptanceTimeoutTest`
- `ElectionReplacementTest`
- `ElectionAppointmentIdempotencyTest`
- `ElectionRepresentationTest`
- `ElectionHighestValidSeatTest`
- `ElectionConcurrencyTest`
- `ElectionAuthorizationTest`
- `ElectionCommentVisibilityTest`
- `ElectionLegacyMigrationTest`

### Phase E16 — Rollout & Final Validation

- legacy data reconciliation report.
- dry-run scheduler.
- targeted election suite green.
- full application suite green.
- static analysis/build green.
- no regression در Group/Chat/Profile/Notifications.
- production migration/runbook.
- rollback/runbook.
- final owner review.

**این Gate فقط اجازه تصمیم برای merge می‌دهد؛ merge به main خودکار یا ضمنی نیست.**

## 8. ترتیب Releaseها

### Release E-A — Election Safety Core
E1 تا E3: vocabulary، schema repair، lifecycle.

### Release E-B — Democratic Integrity
E4 تا E6: eligibility، ballot audit، deterministic tally.

### Release E-C — Office Lifecycle
E7 تا E9: acceptance، appointment، representation، continuity.

### Release E-D — Policy & Experience
E10 تا E12: admin policy، UI/API، notifications.

### Release E-E — Intelligence & Production Hardening
E13 تا E16: Najm Hoda، security، exhaustive tests، rollout.

## 9. ممنوعات معماری

- استفاده جدید از magic integer برای position/status.
- ذخیره «برنده» به‌عنوان `accepted`.
- random tie/backfill.
- direct role mutation از ProfileController یا UI controller.
- delete کردن history رأی برای نمایش current ballot.
- تکیه به frontend برای seat limit/eligibility.
- hard-code کردن 7/3 یا هر تعداد دیگر در view/controller؛ مقدار پیش‌فرض می‌تواند policy باشد، نه منطق ثابت.
- اجرای transition بدون transaction/idempotency.
- تغییر policy جاری بدون snapshot/version.
- recommendation غیرقابل توضیح نجم هدا.

## 10. اولین Slice اجرایی

اولین تغییر کد پس از این roadmap عمداً کم‌ریسک است:

1. ایجاد enumهای canonical `ElectionPosition`, `ElectionAcceptanceStatus`, `ElectionLifecycleStatus`؛
2. افزودن mapper سازگار با مقادیر legacy، بدون تغییر schema در همان commit؛
3. سپس در Slice بعدی، `ElectionPolicyResolver` و تست‌ها؛
4. بعد migration repair با backfill صریح.

این ترتیب اجازه می‌دهد قبل از دست‌زدن به داده production-like، زبان domain و migration contract قفل شود.

## 11. Definition of Done نهایی

بازسازی زمانی کامل است که یک گروه واجد شرایط بدون دخالت دستی ادمین بتواند:

1. به حدنصاب برسد؛
2. cycle انتخاباتی طبق policy ایجاد و باز شود؛
3. اعضای واجد حق رأی، افراد مورد اعتماد خود را برای manager/inspector معرفی کنند؛
4. رأی را در محدوده مجاز تغییر یا پس بگیرند و audit باقی بماند؛
5. cycle خودکار بسته و به‌صورت قطعی tally شود؛
6. مسئولیت با متن policy‌شده به افراد ranked پیشنهاد شود؛
7. accept/decline/timeout بدون تحریف نتیجه تاریخی مدیریت شود؛
8. نفر جایگزین به‌صورت deterministic فراخوانده شود؛
9. appointment و representation دقیقاً یک بار اعمال شوند؛
10. همه وقایع و تصمیم‌ها auditپذیر باشند؛
11. cycle بعدی بدون شکستن مسئولیت‌های جاری قابل آغاز باشد؛
12. کل جریان در tests و CI قابل بازتولید باشد.

---

این سند از این commit به بعد مرجع ترتیب توسعه انتخابات سیستمی است. هر deviation مهم باید در خود roadmap یا ADR مرتبط ثبت شود.