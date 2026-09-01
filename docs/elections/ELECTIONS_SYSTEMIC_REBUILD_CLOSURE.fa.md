# سند اختتام بازسازی انتخابات سیستمی EarthCoop

> وضعیت: **Systemic Rebuild Engineering Closure — Validated**  
> شاخه: `agent/elections-systemic-rebuild`  
> PR توسعه: #81  
> پایه امن: `agent/integration-pre-elections-current-main` / PR #80  
> Validated Runtime SHA: `4b23706ebdb0a876136056275f4b7248ac07b5f8`  
> تاریخ ثبت: 2026-08-23  
> قاعده ایمنی: این سند **مجوز merge به `main` یا production deployment نیست**. تصمیم نهایی ادغام فقط با مالک پروژه است.

## 1. نتیجه اجرایی

بازسازی سیستمی انتخابات EarthCoop در checkpoint فوق از منظر معماری domain، schema، lifecycle، ballot/audit، tally، responsibility offer، appointment/representation، continuity، policy versioning، privacy، moderation/reporting، procedural review، conflict policy، contract governance و active-cycle override به پایان مهندسی رسیده است.

این نتیجه به معنای «تکمیل مرحله ساخت و validation» است؛ نه به معنای ادغام یا استقرار در محیط production.

## 2. شواهد validation روی همان Runtime SHA

تمام workflowهای زیر روی `4b23706ebdb0a876136056275f4b7248ac07b5f8` با نتیجه `completed / success` بسته شدند:

- Elections E2 Schema Reconciliation Gate — run #626
- Elections E3 Lifecycle State Machine Gate — run #589
- Elections E5 Ballot Audit Gate — run #553
- Elections E6 Deterministic Tally Gate — run #505
- Elections E7 Responsibility Offer Gate — run #471
- Elections E8 Appointment Representation Gate — run #421
- Elections E9 Systemic Continuity Gate — run #367
- Elections E10 Policy Versioning Gate — run #309
- Elections E11 E0 Privacy Gate — run #255
- Elections E12 E0 Feedback Moderation Gate — run #239
- Elections E13 E0 Reporting Gate — run #207
- Elections E14 E0 Procedural Review Gate — run #189
- Elections E15 E0 Topic Response Gate — run #187
- Elections E16 E0 Conflict Policy Gate — run #185
- Elections E17 E0 Contract Governance Gate — run #183
- Elections E18 E0 Active Cycle Override Gate — run #181
- EarthCoop Integration Full Validation — run #1052

Full Validation #1052 علاوه بر build و migration، این regression domains را نیز با موفقیت گذراند:

- route and command boot
- Group Chat
- Group Admin / Identity
- Najm Hoda + n8n
- Governance
- Najm Bahar
- Stock
- Group Chat JavaScript
- Full Project PHPUnit
- Enforce Regression Gate

## 3. معماری تثبیت‌شده

### 3.1 چرخه انتخابات

انتخابات از Controller/UI مستقل شده و lifecycle canonical و auditپذیر دارد. scheduler/command مسئول progression سیستمی است و retry باید idempotent بماند.

Lifecycle canonical شامل وضعیت‌های اصلی زیر است:

`scheduled → open → closed → tallying → awaiting_acceptance → appointing → filled/exhausted`

مسیرهای cancellation/review/override نیز به‌صورت صریح در domain نگهداری می‌شوند.

### 3.2 رأی و هویت منتخب

هویت عضو منتخب از semantics مبهم legacy جدا شده و compatibility برای داده تاریخی حفظ شده است. Ballot بر اساس snapshot واجدان شرایط validate می‌شود و ظرفیت roleها policy-driven است.

حذف/تغییر رأی canonical با audit event انجام می‌شود. حذف مستقیم مدل `Vote` fail-closed شده تا مسیرهایی مانند تغییر آدرس نتوانند تاریخ رأی را بی‌صدا پاک کنند.

### 3.3 شمارش و tie-break

Tally قطعی، versioned و قابل بازتولید است. داده رأی برای شمارش freeze/snapshot می‌شود و tie-break evidence ثبت می‌شود. جایگزینی تصادفی در زنجیره مسئولیت جزء معماری معتبر نیست.

### 3.4 نتیجه، پذیرش و نصب

سه مفهوم مستقل نگه داشته می‌شوند:

1. ranking/result؛
2. responsibility offer و accept/decline/expire؛
3. appointment و side-effect رسمی role/representation.

پذیرش مسئولیت به قرارداد نسخه‌بندی‌شده و evidence صریح متصل است. بازنشسته‌شدن یک contract version، قرارداد freeze‌شده یک cycle تاریخی را نامعتبر نمی‌کند.

### 3.5 نمایندگی و continuity

Role و higher-level representation فقط از appointment domain اعمال می‌شوند. vacancy، succession، lineage و continuous election cycles به‌صورت system-managed و auditپذیر پیاده شده‌اند.

### 3.6 Policy governance

Policy versioning، effective dating، frozen runtime policy و active-cycle override از هم تفکیک شده‌اند. تغییر policy جدید نباید قواعد cycle جاری freeze‌شده را به‌طور ضمنی بازنویسی کند.

### 3.7 Privacy، feedback و review

Visibility رأی/کامنت، anonymity، moderation، reporting، meaningful trend، topic response و procedural review دارای سرویس‌ها و policyهای مستقل‌اند. review deadline به audit event واقعی متصل است، نه timestamp ارسالی کاربر.

### 3.8 Contract governance و conflict policy

Responsibility contract governance و conflict-policy matrix نسخه‌بندی و test شده‌اند. پذیرش مسئولیت evidence-bound است و contract lifecycle از election cycle تاریخی جدا نگه داشته می‌شود.

## 4. اصلاحات نهایی validation

در دور نهایی دو دسته failure ظاهر شد که هر دو بدون تضعیف invariantهای محصول حل شدند:

### 4.1 Lifecycle retry fixture

یک تست قدیمی election باز را بدون policy/contract معتبر می‌ساخت، در حالی که scheduler جدید پس از close باید تا tally و offer/exhaustion ادامه دهد. fixture به یک cycle معتبر ارتقا یافت و idempotency زنجیره کامل سنجیده شد.

### 4.2 Vite test-environment isolation

Gateهای E6 تا E18 در ابتدا همگی در regression suite مشترک با `Vite manifest not found` شکست می‌خوردند؛ تست تخصصی خود هر Gate سبز بود و Full Validation که frontend build داشت نیز موفق بود. علت در `ElectionUserSurfaceTest` بود که در محیط backend-only Gateها به manifest frontend وابسته می‌شد.

تست با `withoutVite()` ایزوله شد. پس از این اصلاح، همه Gateهای تخصصی و regression suite آنها روی همان runtime SHA سبز شدند. هیچ منطق محصول، eligibility، tally، policy یا governance برای عبور از CI تضعیف نشد.

## 5. بدهی‌های غیرمسدودکننده ثبت‌شده

موارد زیر در source هنوز وجود دارند، اما در runtime فعلی route-shadow شده و validation نشان داده مسیر canonical آنها را دور می‌زند:

1. `ProfileController` هنوز کد legacy پذیرش/رد مسئولیت را در source نگه داشته است؛ route فعال به responsibility-offer controller canonical resolve می‌شود و legacy GET فقط confirmation است.
2. `ChatController::chat` هنوز بلوک legacy ایجاد/تمدید election را در source دارد؛ route فعال `groups.chat` به `SystemicElectionChatController` resolve می‌شود و route contract test شده است.
3. `CandidateAccepted` listener legacy فقط notification می‌فرستد و role/representation را mutate نمی‌کند.

این موارد **Production Readiness blocker نیستند**، اما برای کاهش source-level attack surface و هزینه نگهداری، حذف فیزیکی آنها در cleanup مستقل آینده توصیه می‌شود. Cleanup نباید قبل از checkpoint/validation جدید با کارهای unrelated مخلوط شود.

## 6. مرزهای Production Readiness

این checkpoint از نظر engineering validation آماده تصمیم مالک برای مرحله integration است، مشروط به اینکه قبل از هر merge/deploy:

- وضعیت `main` دوباره با base امن reconcile شود اگر از زمان checkpoint تغییر کرده باشد؛
- migration plan و backup/rollback عملیاتی مرور شود؛
- environment-specific scheduler/cron برای `elections:process-lifecycle` بررسی شود؛
- queue/notification/runtime permissions محیط مقصد بررسی شود؛
- در صورت وجود داده production legacy، reconciliation/audit command قبل از cutover اجرا و خروجی آن نگهداری شود؛
- merge به `main` فقط با تصمیم صریح مالک پروژه انجام شود.

## 7. تصمیم اختتام

بر مبنای validation روی `4b23706ebdb0a876136056275f4b7248ac07b5f8`:

**مرحله ساخت و بازسازی سیستمی انتخابات EarthCoop بسته می‌شود.**

گام بعدی توسعه feature جدید در همین branch نیست. گام بعدی، در صورت تصمیم مالک، یک **Safe Integration Reconciliation** با آخرین وضعیت شاخه پایه/`main` است؛ ترجیحاً روی شاخه integration جداگانه، با اجرای مجدد Full Validation و Gateهای انتخابات پیش از هر تصمیم merge.

تا آن زمان PR #81 باید غیرادغام‌شده باقی بماند و هیچ production deploy از این سند استنباط نمی‌شود.
