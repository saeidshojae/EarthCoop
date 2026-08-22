# ماتریس انطباق پیاده‌سازی انتخابات با سند E0

> وضعیت سند: **Reconciled implementation candidate — در انتظار validation نهایی یک head واحد**  
> شاخه: `agent/elections-systemic-rebuild`  
> PR: #81 — Draft  
> قاعده closure: هیچ بند این ماتریس صرفاً به‌دلیل وجود migration یا enum «بسته» محسوب نمی‌شود؛ write path، read/access path، audit، policy، تست و Gate باید با هم معتبر باشند.

## 1. وضعیت کلی

| بخش E0 | وضعیت پیاده‌سازی | شواهد اصلی |
|---|---|---|
| چرخه پیوسته، snapshot، توقف و اعمال نتیجه | پیاده‌شده؛ validation نهایی pending | `ElectionCycleService`، `ElectionLifecycleService`، `ElectionVoteSnapshotService`، command زمان‌بندی‌شده `elections:process-lifecycle`، Gateهای E3/E5/E9 |
| صلاحیت، رتبه‌بندی و تساوی | پیاده‌شده؛ validation نهایی pending | eligibility snapshot، tally قطعی از snapshot، tie-break قابل‌بازتولید و evidence، Gate E6 |
| پیشنهاد مسئولیت، decline/timeout/backfill | پیاده‌شده؛ validation نهایی pending | `ElectionResponsibilityOfferService`، evidence پذیرش، vacancy/backfill، Gateهای E7/E9 |
| انتصاب و نمایندگی پلکانی | پیاده‌شده؛ validation نهایی pending | `ElectionAppointmentService`، representation assignment، highest-valid-seat/supersession/topology rules، Gate E8 |
| policy versioning و freeze چرخه | پیاده‌شده؛ validation نهایی pending | `ElectionPolicyVersionService`، effective dating، policy freeze per cycle، Gate E10 |
| 7.1 حریم رأی | پیاده‌شده؛ validation نهایی pending | `ElectionBallotVisibilityService`، ordinary-read projection، negative authorization tests، Gate E11 |
| 7.2 دلیل رأی/تغییر/پس‌گرفتن | پیاده‌شده؛ validation نهایی pending | ballot audit events + entity مستقل `ElectionVoteFeedback`، moderation، privacy-safe read service، Gate E12 |
| 7.3 محبوبیت و رضایت | پیاده‌شده؛ validation نهایی pending | `ElectionCandidateReportService`، suppression حد نمونه/بازه، inflow/outflow/net/cutoff/retention، meaningful trend، Gate E13 |
| عدالت رویه‌ای و بازبینی | پیاده‌شده؛ validation نهایی pending | immutable-evidence verification، human review، endorsement، interim stay، reasoned decision و protected audit access، Gate E14 |
| پاسخ عمومی به موضوعات بازخورد | پیاده‌شده؛ validation نهایی pending | topic aggregation/response بدون افشای نویسنده ناشناس، Gate E15 |
| تعارض مسئولیت‌ها | پیاده‌شده؛ validation نهایی pending | versioned conflict-policy matrix و اعمال در appointment، Gate E16 |
| قرارداد مسئولیت | پیاده‌شده؛ validation نهایی pending | قرارداد versioned/immutable، admin governance، freeze در policy/cycle و acceptance evidence، Gate E17 |
| تغییر سیاست در چرخه فعال | پیاده‌شده؛ validation نهایی pending | active-cycle override با ثبت دلیل/actor و حفاظت از policy تاریخی، Gate E18 |
| user surface | پیاده‌شده؛ validation نهایی pending | ballot سیستمی، privacy controls، portal تاریخچه/گزارش/review، lifecycle-owned UI |

## 2. E0 §7.1 — حریم رأی

پیاده‌سازی فعلی:

- `ElectionVoteVisibility` سه دامنه canonical دارد: `confidential`, `all_members`, `elected_officials`.
- visibility برای **هر انتخاب** در ballot ذخیره می‌شود و compatibility path در نبود مقدار صریح fail-safe به `confidential` می‌افتد.
- `ElectionBallotVisibilityService` تنها ordinary-read policy هویت رأی است.
- رأی‌دهنده رأی خود را می‌بیند؛ برای دیگران:
  - `confidential`: هویت همیشه پنهان؛
  - `all_members`: فقط عضو فعال همان گروه؛
  - `elected_officials`: فقط appointment فعال manager/inspector همان گروه.
- audit هویت واقعی را نگه می‌دارد ولی ordinary UI/API از audit privilege ارث نمی‌برد.
- مسیر protected audit برای review جداگانه ثبت و audit می‌شود.

**Invariant:** مدیر، بازرس یا شخص منتخب به‌صرف سمت خود حق دیدن هویت یک رأی `confidential` را ندارد.

## 3. E0 §7.2 — دلیل رأی، تغییر و پس‌گرفتن

پیاده‌سازی فعلی:

- دلیل اختیاری است و cast/change/withdraw در ballot event append-only ثبت می‌شود.
- `comment_anonymous` مستقل از دامنه visibility است.
- visibility دلیل سه حالت دارد: `all_members`, `elected_officials`, `subject_only`.
- `ElectionVoteFeedback` projection مستقل و moderation-aware از ballot event است.
- `ElectionVoteFeedbackReadService` در ordinary read:
  - `ballot_event_id` را افشا نمی‌کند؛
  - timestamp خام رویداد رأی را افشا نمی‌کند؛
  - نویسنده feedback ناشناس را null می‌کند؛
  - feedback تأییدنشده را برای غیرنویسنده پنهان می‌کند؛
  - `subject_only` را فقط به همان subject نشان می‌دهد.
- moderation pipeline، وضعیت انتشار و topic aggregation/response وجود دارد.

## 4. E0 §7.3 — گزارش محبوبیت و رضایت

`ElectionCandidateReportService` گزارش aggregation-only تولید می‌کند و identity رأی‌دهندگان را خروجی نمی‌دهد. موارد پیاده‌شده:

- current vote count؛
- selection cutoff و margin-to-cutoff؛
- inflow / outflow / net change؛
- bucketهای زمانی؛
- retention rate؛
- حداقل تعداد distinct voters؛
- حداقل طول بازه گزارش؛
- suppression خودکار در نمونه یا بازه کوچک؛
- meaningful-trend threshold و notification service؛
- policy-driven reporting thresholds در نسخه سیاست چرخه.

هدف suppression این است که گزارش aggregate به مسیر بازسازی هویت رأی‌دهنده از bucket کوچک یا timestamp تبدیل نشود.

## 5. عدالت رویه‌ای و بازبینی

workflow بازبینی دیگر یک gap باز نیست:

- درخواست فقط برای عضو فعال غیرسیستمی گروه؛
- challenged event به evidence واقعی همان election متصل می‌شود؛
- timestamp قابل‌اعتماد از event canonical گرفته می‌شود، نه مقدار spoof‌شده client؛
- verification خودکار از immutable vote snapshot و tally evidence بازسازی می‌شود؛
- ranking/tie-break با policy فریز‌شده همان چرخه بازتولید می‌شود؛
- human review window، endorsement threshold، interim stay و final reasoned decision وجود دارند؛
- دسترسی حفاظت‌شده audit با actor/purpose/scope ثبت می‌شود؛
- correction نتیجه تاریخی را بی‌صدا rewrite نمی‌کند و remediation reference لازم دارد.

## 6. قرارداد، تعارض و تغییر policy

- متن/نسخه قرارداد مسئولیت versioned است و acceptance evidence به نسخه مشخص متصل می‌شود.
- policy چرخه freeze می‌شود و تغییرات آینده تاریخچه چرخه را بازنویسی نمی‌کنند.
- conflict policy به‌صورت versioned matrix اداره می‌شود.
- تغییر policy در چرخه فعال مسیر صریح override، actor، reason و audit دارد؛ تغییر تنظیمات عمومی به‌تنهایی snapshot چرخه فعال را mutate نمی‌کند.

## 7. مالکیت lifecycle و بازنشستگی legacy mutation

مالک canonical تغییر وضعیت، command/service انتخابات است:

`ElectionCycleService → ElectionLifecycleService → snapshot/tally → ResponsibilityOffer → Appointment → Representation`

Controller/UI حق close/tally/appointment مستقیم ندارد.

مسیر legacy `finish.election` فعلاً برای compatibility نام route را حفظ کرده، اما `ElectionController::finishElection()` **بازنشسته و non-mutating** است و HTTP `410 Gone` برمی‌گرداند. تست `ElectionManualFinishRetirementTest` تضمین می‌کند adapter قدیمی نتواند lifecycle transition، tally یا candidate acceptance انجام دهد.

همچنین `groups.chat` در rollout فعلی از route canonical `routes/elections.php` به `SystemicElectionChatController` resolve می‌شود؛ presenter فقط read path انتخابات است و ایجاد/تمدید/توقف/tally نمی‌کند. وجود declaration قدیمی در `routes/web.php` یک compatibility declaration است و باید در cleanup پس از تثبیت rollout حذف شود؛ تا آن زمان resolved route و controller canonical با تست محافظت می‌شوند.

## 8. User Surface

- برگه رأی از policy فریز‌شده همان cycle ظرفیت manager/inspector را می‌خواند.
- یک عضو در یک ballot نمی‌تواند هم‌زمان برای هر دو position انتخاب شود.
- visibility فقط برای **position واقعاً انتخاب‌شده** قابل submit است؛ select هم‌نام position دیگر disabled می‌ماند.
- search، count، clear و countdown توسط `resources/js/group-chat/elections.js` و lifecycle page اداره می‌شوند؛ Blade listener/timer مستقل ندارد.
- پایان countdown در client فقط UI را غیرفعال می‌کند و lifecycle سرور را mutate نمی‌کند.
- portal کاربر وضعیت چرخه، offer، گزارش privacy-safe، feedback/topic response و review را در مسیر read/action مجاز نشان می‌دهد.

## 9. Validation Gateها

Gateهای مستقل branch:

- E2 — Schema Reconciliation
- E3 — Lifecycle State Machine
- E5 — Ballot Audit
- E6 — Deterministic Tally
- E7 — Responsibility Offer
- E8 — Appointment / Representation
- E9 — Systemic Continuity
- E10 — Policy Versioning
- E11 — Privacy
- E12 — Feedback / Moderation
- E13 — Reporting
- E14 — Procedural Review
- E15 — Topic Response
- E16 — Conflict Policy
- E17 — Contract Governance
- E18 — Active Cycle Override
- EarthCoop Integration Full Validation

**این سند تا زمانی که همه Gateهای بالا و Full Validation روی یک head واحد سبز نشده‌اند، وضعیت «Closure Confirmed» نمی‌گیرد.**

## 10. مواردی که blocker محصولی محسوب نمی‌شوند ولی باید مستند بمانند

1. declaration قدیمی `groups.chat` در `routes/web.php` هنوز از طریق load-order توسط route canonical shadow می‌شود؛ رفتار resolved با تست pin شده است. حذف فیزیکی آن cleanup پس از rollout است، نه مسیر فعال انتخابات.
2. نام route قدیمی `finish.election` باقی است، اما endpoint non-mutating و 410 است تا client/bookmark قدیمی نتواند چرخه را تغییر دهد.
3. compatibility fields/models legacy تا پایان دوره migration برای خواندن/تطبیق داده تاریخی باقی می‌مانند؛ write path canonical نباید به semantics مبهم legacy برگردد.

## 11. شرط اعلام پایان بخش انتخابات

فقط وقتی همه موارد زیر همزمان برقرار باشند می‌توان این بخش را «کامل و جمع‌شده» اعلام کرد:

1. E2 تا E18 روی **یک head نهایی واحد** سبز؛
2. Full Validation همان head سبز؛
3. `elections:audit-data --fail-on-issues` بدون blocker؛
4. user-surface / group-chat source-contract سبز؛
5. route دستی پایان انتخابات non-mutating؛
6. ordinary read هیچ هویت confidential یا feedback ناشناس را افشا نکند؛
7. docs، roadmap و implementation با همان head reconcile باشند؛
8. هیچ gap شناخته‌شده P0/P1 برای رفتار تعریف‌شده در E0 باز نباشد.
