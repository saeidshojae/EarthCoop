# ممیزی جامع سیستم امتیازدهی و Participation Points — EarthCoop

**Branch:** `agent/economic-system-current-integration`  
**Baseline commit:** `e93cc1da94358b576e89c48e3fdd50e8fbf9651b`  
**تاریخ:** 2026-08-31

## 1. جمع‌بندی اجرایی

هسته‌ی امتیازدهی فعلی از نظر داشتن مدل مستقل (`UserPoint`)، تاریخچه تراکنش (`UserPointTransaction`)، Rule قابل تنظیم (`ReputationRule`) و سرویس مرکزی (`ReputationService`) پایه‌ی قابل استفاده‌ای دارد؛ اما در وضعیت فعلی **برای اتصال مستقیم به ارزش اقتصادی production-grade نیست**.

مشکل اصلی کمبود feature نیست، بلکه نبود چند invariant اساسی است: idempotency رویداد، تفکیک reputation از participation قابل تبدیل، reversal، rule versioning، anti-collusion و consistency میان تنظیمات پنل ادمین و runtime.

چند finding بحرانی نیز وجود دارد:

1. صفحه مدیریت Reputation در هر بار بازشدن `seedFromConfig()` را اجرا می‌کند و `updateOrCreate` وزن/فعال بودن/daily_cap را دوباره با config overwrite می‌کند؛ در نتیجه تنظیمات ادمین پایدار نیستند.
2. `ReputationService` برای daily cap فقط `config('reputation.daily_caps')` را می‌خواند و `reputation_rules.daily_cap` را اصلاً مصرف نمی‌کند؛ بنابراین daily cap قابل تنظیم در پنل عملاً runtime-effective نیست.
3. برای eventها هیچ unique/idempotency constraint عمومی بر `(user, action, source, reference)` وجود ندارد؛ یک رویداد واحد می‌تواند چند بار reward شود.
4. Like/Upvote فعلی به **کاربری که لایک می‌کند** امتیاز می‌دهد، نه به صاحب محتوای پسندیده‌شده. علاوه بر این با toggle off/on می‌توان همان post/comment را چندبار reward کرد تا سقف روزانه پر شود.
5. `post_created` و `comment_created` سقف روزانه ندارند؛ بنابراین creation spam مستقیماً Participation Points تولید می‌کند.
6. تست‌های فعلی `ReputationServiceTest` وزن actionهای تستی را تعریف نمی‌کنند؛ چون weight=0 می‌شود، assertions `<= cap` بدون اینکه امتیازی واقعاً صادر شده باشد سبز می‌شوند. بنابراین این تست‌ها protection مؤثری ایجاد نمی‌کنند.
7. conversion فعلی می‌تواند در partial cashing امتیاز از بین ببرد: اگر فقط بخشی از `delta` یک transaction مصرف شود، کل transaction `is_cashed=true` می‌شود.
8. conversion از `intval(points / ratio)` استفاده می‌کند؛ remainder امتیاز می‌تواند مصرف شود بی‌آنکه Gol متناظر بگیرد. مثال: ratio=100 و تبدیل 150 امتیاز → 1 Gol، ولی 150 امتیاز cash می‌شود.
9. penaltyهای منفی (`fraud`, `report_received`, ...) موجودی Reputation را کم می‌کنند، ولی ظرفیت cashable از مجموع transactionهای مثبت `is_cashed=false` محاسبه می‌شود. بنابراین penalty منفی لزوماً توان تبدیل قبلی را کاهش نمی‌دهد.
10. Ruleهای متعددی فقط config/admin-only هستند و runtime wiring ندارند.

**Verdict:** Foundation قابل نگهداری است، اما پیش از گسترش incentive economy باید یک بازطراحی محدود و contract-first انجام شود.

---

## 2. معماری فعلی

### هسته

- `app/Services/ReputationService.php`
- `app/Models/UserPoint.php`
- `app/Models/UserPointTransaction.php`
- `app/Models/ReputationRule.php`
- `config/reputation.php`
- `app/Http/Controllers/Admin/ReputationController.php`
- `app/Http/Controllers/ReputationConversionController.php`

### مسیر اقتصادی

`Participation/Reputation transaction -> uncashed positive points -> policy ratio -> MonetaryService::activateDim() -> Dim → Active Gol`

اصل مهم مثبت این است که conversion فعلی mint انجام نمی‌دهد و از `activateDim()` استفاده می‌کند؛ این جهت معماری باید حفظ شود.

---

## 3. Current Activity Matrix

| Action key | وزن config | Runtime wired | دریافت‌کننده واقعی | Cap فعلی | وضعیت | Finding |
|---|---:|---|---|---:|---|---|
| `email_verified` | 50 | بله | کاربر تأییدشده | ندارد | فعال | event عملاً one-time است ولی idempotency عمومی ندارد |
| `profile_completed` | 30 | بله | صاحب پروفایل | ندارد | فعال | guard صریح `alreadyAwarded` دارد؛ سالم‌ترین wiring فعلی |
| `post_created` | 10 | بله | نویسنده پست | ندارد | فعال/پرریسک | spam creation بدون cap/quality gate |
| `post_upvoted` | 5 | بله | **کاربر لایک‌کننده** | 50/day | فعال/معیوب | نام rule القا می‌کند author reward؛ toggle farming ممکن است |
| `comment_created` | 2 | بله | نویسنده کامنت | ندارد | فعال/پرریسک | creation spam |
| `comment_upvoted` | 1 | بله | **کاربر لایک‌کننده** | 100/day | فعال/معیوب | toggle farming و recipient semantics نامناسب |
| `bid_placed` | 1 | بله، در Stock legacy path | bidder | 500/day | legacy-only / نامناسب | خود ثبت bid ارزش اجتماعی کافی برای reward مالی نیست؛ قابلیت farming |
| `bid_won` | 20 | بله در settlement قدیمی Stock | winner | ندارد | legacy-wired | settlement service آن را award می‌کند؛ idempotency action-level ندارد |
| `successful_settlement` | 30 | بله در settlement قدیمی Stock | winner | ندارد | legacy-wired | می‌تواند همراه `bid_won` برای یک outcome دوبل reward ایجاد کند |
| `profile_photo_uploaded` | 10 | خیر | — | — | config-only | call-site پیدا نشد |
| `social_links_added` | 5 | خیر | — | — | config-only | call-site پیدا نشد |
| `documents_uploaded` | 20 | خیر | — | — | config-only | call-site پیدا نشد |
| `bio_added` | 5 | خیر | — | — | config-only | call-site پیدا نشد |
| `poll_created` | 5 | خیر | — | — | config-only | wiring در flow فعلی poll پیدا نشد |
| `poll_participated` | 2 | خیر | — | 100/day config | config-only | cap دارد ولی event ندارد |
| `election_participated` | 5 | خیر | — | — | config-only | wiring در سیستم انتخابات جاری پیدا نشد |
| `election_candidate` | 10 | خیر | — | — | obsolete/misaligned | انتخابات EarthCoop candidate-list based نیست؛ این rule با مدل فعلی ناسازگار است |
| `elected_inspector` | 50 | خیر | — | — | config-only | بهتر است Reputation/Trust باشد، نه لزوماً convertible participation |
| `elected_manager` | 100 | خیر | — | — | config-only | انتخاب‌شدن نباید به‌تنهایی پول قابل تبدیل بسازد |
| `report_received` | -10 | خیر | — | — | config-only | هیچ verified moderation outcome wiring دیده نشد |
| `bid_canceled` | -15 | خیر | — | — | config-only | event BidCancelled وجود دارد، اما Reputation action متصل نیست |
| `fraud` | -100 | خیر | — | — | config-only | penalty تعریف شده ولی adjudication wiring پیدا نشد |
| `invite_member` | 0 در config | call-site بله | معرف | ندارد | **orphan call-site** | `NajmBaharController` آن را صدا می‌زند ولی config rule وجود ندارد؛ مگر DB دستی rule داشته باشد، reward صفر است |

### نتیجه دقیق Current State

فعالیت‌هایی که در baseline فعلی با call-site مستقیم و قابل اثبات امتیاز می‌دهند:

- تأیید ایمیل
- تکمیل پروفایل
- ایجاد پست
- لایک‌کردن پست (نه دریافت لایک)
- ایجاد دیدگاه
- لایک‌کردن دیدگاه (نه دریافت لایک)
- ثبت bid در مسیر legacy Stock
- برنده‌شدن bid در settlement legacy
- settlement موفق در مسیر legacy

باقی ruleهای config در وضعیت فعلی یا config-only هستند، یا با مدل دامنه امروز ناسازگارند، یا call-site بدون rule دارند (`invite_member`).

---

## 4. Defect Analysis

### P0 — Admin configuration is not authoritative

`Admin\ReputationController::index()` هر بار `seedFromConfig()` را اجرا می‌کند. `seedFromConfig()` با `updateOrCreate` فیلدهای `weight`, `active`, `daily_cap` را از config می‌نویسد. بنابراین تغییر ادمین در DB در بازدید بعدی صفحه overwrite می‌شود.

از سوی دیگر `ReputationService` weight را از DB می‌خواند اما daily cap را فقط از config می‌خواند. این یعنی یک Rule در runtime از دو source متفاوت تغذیه می‌شود.

**اصلاح پیشنهادی:** DB rule source-of-truth runtime؛ config فقط bootstrap/default برای ruleهای missing باشد و هرگز existing row را overwrite نکند.

### P0 — No event idempotency

`UserPointTransaction` index روی `(user_id, created_at)` دارد، ولی unique key برای source event ندارد.

**اصلاح پیشنهادی:** اضافه‌شدن `event_key`/`idempotency_key` unique، ترجیحاً با قرارداد stable نظیر:

`groups:post_created:{blog_id}:{user_id}`

### P0 — Upvote semantics/farming

Reaction controller بعد از Like، `applyAction(auth()->user(), ...)` را صدا می‌زند؛ پس reward به liker می‌رسد. Toggle off امتیاز را reverse نمی‌کند و toggle on مجدد reward می‌دهد.

**اصلاح پیشنهادی:**

- اگر هدف participation صرفاً «ارزیابی محتوا» است: یک reward بسیار کوچک one-time به reactor با unique key، و toggle تغییری در reward ندهد.
- اگر هدف quality signal است: author reward فقط وقتی signal معتبر/تجمیعی به threshold برسد؛ نه برای هر like خام.
- self-like و reciprocal/collusion patterns برای convertible points نباید مؤثر باشند.

### P0 — Conversion partial-consumption bug

`convert()` transactionهای مثبت را FIFO می‌گیرد، ولی اگر فقط بخشی از delta لازم باشد، باز هم کل row را `is_cashed=true` می‌کند.

**اصلاح پیشنهادی:** به‌جای boolean whole-row cashing، یکی از این دو مدل:

1. `consumed_points` روی transaction؛ یا
2. ledger مستقل `point_consumptions` با `point_transaction_id`, `points_consumed`, `conversion_id`.

گزینه دوم حسابرسی‌پذیرتر است.

### P0 — Remainder loss

`amountInGol = intdiv(points, ratio)` باید همراه requirement باشد که `points % ratio == 0`، یا remainder به‌صورت unconsumed باقی بماند.

### P0 — Penalty does not constrain conversion

Conversion فقط positive uncashed deltas را جمع می‌کند. بنابراین reputation aggregate و convertible balance دو معنا پیدا کرده‌اند بدون اینکه domain صریحی داشته باشند.

این نقص در واقع نشان می‌دهد باید **Reputation** و **Participation Points** از هم جدا شوند.

---

## 5. Target Domain Model

پیشنهاد رسمی:

### A. Participation Points

فقط مشارکت‌های قابل اثبات و outcome-oriented؛ **قابل تبدیل** به activation از Dim موجود کاربر.

### B. Reliability

خوش‌قولی، completion، انجام تعهدات، deadline discipline؛ **غیرقابل تبدیل**.

### C. Expertise

کیفیت contribution تخصصی، reviewهای پذیرفته‌شده، سابقه تخصص؛ **غیرقابل تبدیل**.

### D. Civic Trust

سابقه مسئولیت، گزارش تخلف تأییدشده، انضباط governance، conflict-of-interest record؛ **غیرقابل تبدیل**.

انتخاب‌شدن به سمت مدیریت/بازرسی، report/fraud و امثال آن باید در Trust/Reliability اثر بگذارند نه مستقیماً در convertible Participation Points.

---

## 6. Potential Activity Matrix

این نامزدها بر مبنای featureهای موجود/برنامه‌ریزی‌شده EarthCoop و اصل «outcome over click» پیشنهاد می‌شوند.

| فعالیت بالقوه | Dimension | Convertible? | Gate پیشنهادی |
|---|---|---:|---|
| تکمیل onboarding معتبر | Participation | محدود/یک‌بار | verified profile |
| معرفی عضو معتبر | Participation | بله، محدود | عضویت + verification کامل؛ one invite one reward |
| ایجاد پست | بهتر است raw reward حذف/بسیار کم شود | ترجیحاً خیر | quality signal/outcome |
| پست مفید تأییدشده | Participation/Expertise | بله/بخشی | threshold کیفیت + anti-collusion |
| کامنت خام | بدون reward یا بسیار کم | خیر | — |
| پاسخ مفید پذیرفته‌شده | Participation/Expertise | بله/بخشی | author/peer outcome |
| رأی در poll | Civic Participation | معمولاً خیر یا بسیار محدود | one poll one user |
| مشارکت در انتخابات | Civic Participation | معمولاً خیر یا بسیار محدود | one election cycle |
| انجام action item | Participation + Reliability | بله | assignee + completed + verified |
| انجام تعهد در موعد | Reliability + Participation | بخشی | deadline + acceptance |
| پرداخت به‌موقع PublicContributionObligation | Participation + Reliability | بله | ledger-backed paid-on-time event |
| تکمیل milestone پروژه | Participation/Expertise | بله | verifier/approval + evidence |
| گزارش دوره‌ای پروژه | Reliability | معمولاً خیر | accepted report |
| review تخصصی مفید | Expertise + Participation | بخشی | review accepted/used |
| ارائه evidence مؤثر | Expertise/Participation | بخشی | linked decision/outcome |
| تهیه صورت‌جلسه معتبر | Participation/Reliability | بله، محدود | meeting exists + accepted minutes |
| پیگیری مصوبه تا completion | Participation/Reliability | بله | resolution/action outcome |
| بازرسی تکمیل‌شده | Civic Trust/Reliability | غیرقابل تبدیل یا محدود | formal inspection outcome |
| گزارش تخلف صحیح | Civic Trust | غیرقابل تبدیل یا reward محدود | only after adjudication |
| گزارش کاذب/سوءاستفاده | Civic Trust penalty | خیر | formal decision |
| mentoring با outcome | Participation/Expertise | بخشی | mentee milestone/evidence |
| contribution به ترجمه/مستندسازی | Participation | بله، محدود | accepted contribution |
| رفع باگ/توسعه پذیرفته‌شده | Expertise/Participation | بله، محدود | merged/accepted work; not raw PR count |

### فعالیت‌هایی که نباید convertible reward مستقیم داشته باشند

- login روزانه
- تعداد page view
- raw reaction count
- raw post/comment count بدون quality gate
- مبلغ سرمایه‌گذاری
- تعداد انتقال پول
- تعداد bid
- انتخاب‌شدن به سمت سیاسی/مدیریتی
- محبوبیت/تعداد رأی دریافتی

---

## 7. Standard/Production Readiness Score

ارزیابی baseline فعلی:

| محور | وضعیت |
|---|---|
| Centralized awarding service | خوب |
| Transaction history | خوب ولی ناقص |
| Admin-configurable rules | ظاهراً موجود، runtime ناسازگار |
| Daily caps | ناقص |
| Idempotency | ضعیف/غایب |
| Reversal | غایب |
| Rule versioning | غایب |
| Anti-spam | ضعیف |
| Anti-collusion | غایب |
| Source attribution | متوسط |
| Economic conversion | جهت معماری خوب، implementation دارای P0 bug |
| Monetary invariant (Dim→Active) | خوب |
| Partial point consumption | معیوب |
| Penalty/conversion consistency | معیوب |
| Test coverage quality | ناکافی |
| Observability/audit | متوسط |

**Readiness verdict:** حدود «foundation / pre-production»؛ برای تبدیل امتیاز به ارزش اقتصادی گسترده هنوز Gate بسته است.

---

## 8. Remediation Plan — ترتیب پیشنهادی

### R0 — Freeze expansion

تا رفع P0ها rule قابل تبدیل جدید اضافه نشود.

### R1 — Contract tests اول

تست‌های RED برای:

- admin rule persistence
- DB daily cap effectiveness
- same-event idempotency
- post reaction toggle no double reward
- comment reaction toggle no double reward
- partial conversion preserves remainder
- conversion ratio remainder handling
- negative penalty/conversion semantics
- invite_member rule existence

### R2 — Rule authority cleanup

- `ReputationRule` source-of-truth
- config bootstrap-only
- `daily_cap` از DB مصرف شود
- `active=false` واقعاً پایدار بماند

### R3 — Event ledger/idempotency

- unique `event_key`
- optional `reversal_of_id`
- `rule_version_id`
- explicit dimension/type

### R4 — Split dimensions

Reputation aggregate فعلی migrate/compatibility layer داشته باشد، اما برای awardهای جدید dimension صریح لازم شود.

### R5 — Conversion ledger

- Conversion entity
- PointConsumption rows
- exact integer conversion
- stable idempotency key
- link to monetary activation transaction

### R6 — Reclassify current actions

- raw post/comment rewards کاهش/حذف از convertible pool
- stock bid rewards حذف از Participation convertible
- elected roles منتقل به Civic Trust
- quality/outcome events جایگزین click events شوند

### R7 — Expand to governance/projects

بعد از سبز شدن R1-R6، actionهای outcome-oriented به صورت event-driven اضافه شوند.

---

## 9. Test Constitution پیشنهادی

- `ReputationRulePersistenceTest`
- `ReputationRuleDailyCapTest`
- `ParticipationEventIdempotencyTest`
- `ReactionRewardAbuseTest`
- `PostCreationSpamCapTest`
- `PointReversalTest`
- `ParticipationConversionExactnessTest`
- `ParticipationConversionPartialConsumptionTest`
- `ParticipationConversionIdempotencyTest`
- `ParticipationPenaltySemanticsTest`
- `InviteParticipationRewardTest`
- `PublicObligationParticipationRewardTest`

---

## 10. نتیجه نهایی

سیستم فعلی را نباید حذف و از صفر بازنویسی کرد. `ReputationService`، transaction history و اتصال به `MonetaryService::activateDim()` پایه‌های خوبی هستند. اما قبل از توسعه باید meaning و accounting آن formal شود.

اصل مقصد:

**Raw clicks ≠ economic value.**  
**Verified useful outcomes → Participation Points.**  
**Participation Points → فقط activation از Dim موجود کاربر، هرگز mint.**  
**Trust/Expertise/Reliability → اعتبار غیرقابل تبدیل و مستقل.**

این تغییر EarthCoop را از gamification ساده به یک contribution-accounting system قابل دفاع نزدیک می‌کند.
