# Handoff — Reputation / Participation Points Hardening

**Working branch:** `agent/r3-reputation-close`  
**Integration base:** `agent/economic-system-current-integration`  
**Do not merge to:** `main`  
**Functional Draft PR:** #91 → `agent/economic-system-current-integration`  
**Validation-only Draft PR:** #92 → `main` — **هرگز merge نشود**  
**Spec:** `docs/REPUTATION_PARTICIPATION_POINTS_AUDIT.fa.md`  
**Plan:** `docs/superpowers/plans/2026-08-31-reputation-participation-points-hardening.md`

## وضعیت نهایی

R1 تا R6 برای دامنه فعلی evidence-backed بسته شده‌اند.

**R6 implementation freeze checkpoint:** `8503e2c73b20dbca5a915190f052a75610822320`  
**Full Validation #2051:** SUCCESS  
**Responsive #417:** SUCCESS  
**Full Project PHPUnit:** SUCCESS  
**Specialty gates:** Route/Command Boot، Group Chat، Group Admin/Identity، Najm Hoda+n8n، Governance، Najm Bahar، Stock و Group Chat JavaScript همگی SUCCESS.

این checkpoint شامل public profile Reputation، تب خصوصی «امتیازات من»، canonical conversion summary، legacy deprecation، admin audit و فارسی‌سازی semantic UI است. commit مستندات پس از این checkpoint باید دوباره exact-head validate شود و فقط در صورت سبز بودن به‌عنوان freeze نهایی handoff ثبت شود.

## اصول authoritative

1. DB runtime source of truth است؛ config فقط bootstrap/default است و existing DB rule را overwrite نمی‌کند.
2. هر award transaction snapshot زمان صدور را با `dimension` و `convertible` نگه می‌دارد؛ تغییر policy آینده history را بازنویسی نمی‌کند.
3. Reputation اجتماعی از conversion اقتصادی جدا است. تبدیل امتیاز، `UserPoint.points` و اعتبار تاریخی را کم نمی‌کند.
4. conversion فقط positive `participation + convertible=true` را مصرف می‌کند، Bahar جدید mint نمی‌کند و فقط Dim همان عضو را با `MonetaryService::activateDim()` به Active تبدیل می‌کند.
5. exact consumption در `user_point_consumptions` ثبت می‌شود؛ partial consumption remainder را حفظ می‌کند و retry با request identity یکسان دوباره مصرف/فعال‌سازی نمی‌کند.
6. negative economic Participation reversal فقط ظرفیت آینده را کم می‌کند؛ clawback از Active قبلی نداریم.
7. self-like در UI مجاز است اما snapshot reward آن اجباراً `convertible=false` است.
8. outcome انتخابات فقط direct active appointment واقعی پس از پذیرش مسئولیت را reward می‌کند؛ inherited appointment reward مستقل ندارد.
9. هیچ status/activity صرفاً به‌خاطر نامش reward اقتصادی نمی‌گیرد؛ recipient، evidence، event identity و reversal semantics باید روشن باشند.
10. public profile فقط داده public-safe Reputation را دریافت می‌کند و هیچ داده conversion/cashability به آن منتقل نمی‌شود.

## R1 — Rule Control Plane & Runtime Source of Truth

Status: **GREEN / COMPLETE**

- DB rule authoritative حتی در حالت inactive.
- inactive DB rule به config fallback نمی‌کند.
- DB daily cap runtime-authoritative است.
- bootstrap insert-only است.

Reference: Full #1945 / Responsive #311 — SUCCESS.

## R2 — Dimensions, Convertibility & Admin Policy

Status: **GREEN / COMPLETE**

ابعاد canonical:
- `participation` — مشارکت
- `reliability` — اعتمادپذیری
- `expertise` — تخصص
- `civic_trust` — اعتماد مدنی

Rule policy شامل active، weight، dimension، convertible، daily_cap و repeat_policy است. transactionها dimension/convertibility را snapshot می‌کنند.

Defaults مهم:
- `invite_member`: weight 10، participation، convertible، once_per_context.
- `membership_fee_paid`: weight 12، participation، convertible، once_per_context.

## R3 — Event Identity, Anti-Farming & Recipients

Status: **GREEN / COMPLETE**

- unique `event_key` ledger.
- duplicate-key race فقط برای SQLSTATE `23000`/`23505` و فقط در صورت وجود canonical event بعد از rollback به‌عنوان duplicate پذیرفته می‌شود؛ خطاهای نامرتبط rethrow می‌شوند.
- stable keys برای onboarding، profile، post/comment، invitation، membership fee و Stock.
- membership fee: `membership_fee_paid:user:{user_id}:year:{paymentYear}`.
- Stock:
  - `bid_placed:bid:{bid_id}:user:{user_id}`
  - `bid_won:bid:{bid_id}:user:{user_id}`
  - `successful_settlement:bid:{bid_id}:user:{user_id}`
- post_created: weight 10، cap 50 points/day.
- comment_created: weight 2، cap 20 points/day.
- self-like may create non-economic reputation but never convertible capacity.

محدودیت مستند: synthetic high-load true-concurrency benchmark انجام نشده؛ invariant با DB uniqueness + duplicate handling + tests بسته شده است.

## R4 — Financial Conversion Ledger

Status: **GREEN / COMPLETE**

- parent identity: `user_point_conversions`, unique `(user_id, request_key)`.
- exact child consumption: `user_point_consumptions`.
- فقط positive convertible Participation eligible است.
- legacy `is_cashed=true` historical باقی می‌ماند و دوباره convert نمی‌شود.
- negative convertible Participation reversals ظرفیت آینده را کم می‌کنند.
- conversion فقط از `MonetaryService::activateDim()` عبور می‌کند.
- same key retry replay می‌شود و double activation ندارد.

Final R4 validation: Full #2006 / Responsive #372 — SUCCESS.

## R5 — Evidence-backed Runtime Catalogue

Status: **GREEN / COMPLETE FOR CURRENT EVIDENCE-BACKED DOMAIN**

**R5 freeze:** `f11c2418a553bcaaae999921a5c19a252e2ab14d`  
**Full #2025:** SUCCESS.

### Normal polls

فقط `main_type=1` normal poll reward دارد؛ election-style `main_type=0` excluded است.

- `poll_created:poll:{poll_id}:creator:{user_id}`
- `poll_participated:poll:{poll_id}:user:{user_id}`
- vote removal reward ندارد؛ re-vote/re-add duplicate نمی‌سازد.
- `poll_created`: weight 5، cap 25، participation، convertible.
- `poll_participated`: weight 2، cap 100، participation، convertible.

### Verified invitation

Reward به `InvitationCode.used_by` واقعی متصل است. historical system issuer guard فعلی حفظ شده است. Stable key:

`invite_member:referrer:{referrer_id}:member:{user_id}`

### Systemic election outcomes

Reward فقط `direct + active` appointment واقعی است.

Actions:
- `elected_manager`
- `elected_inspector`

Identity:
`{action}:user:{user_id}:level:{location_level}`

Default هر دو non-convertible است؛ admin فقط با تصمیم صریح آینده می‌تواند policy را تغییر دهد.

### Completed professional referral

Reward فقط بعد از completion واقعی Governance و خارج از transaction governance به‌صورت fail-open صادر می‌شود.

- action: `professional_referral_completed`
- key: `professional_referral_completed:referral:{referral_id}`
- default: weight 10، cap 50، participation، non-convertible، once_per_context.

### Intentionally unwired

این موارد عمداً reward ندارند تا domain evidence روشن شود:
- Najm Bahar project assignment review.
- Najm Hoda group action item `done`.
- generic milestone/report.
- secretariat/formal-record completion.
- Najm Bahar project admin approval.

برای wire شدن هر مورد باید recipient، completion evidence، idempotency identity و reversal semantics صریح باشد.

## R6 — Migration, Transparency UI, Admin/UAT & Freeze

Status: **GREEN / COMPLETE**

### Canonical summary boundary

`app/Services/ParticipationPointSummaryService.php` مرجع مشترک است.

Private summary (`forUser`) شامل:
- durable `total_points`
- level + Persian level label
- reputation dimension breakdown
- convertible awarded Participation
- exact ledger consumed points
- legacy cashed points
- Participation reversals
- remaining convertible capacity

`ReputationConversionController` و Najm Bahar wallet هر دو همین boundary را مصرف می‌کنند؛ محاسبه اقتصادی موازی وجود ندارد.

### Durable reputation semantics

مدل قدیمی «پررنگ/کمرنگ» به semantics شفاف‌تر تبدیل شد:
- اعتبار تاریخی/اجتماعی: `total_points`
- ظرفیت اقتصادی باقی‌مانده: `remaining_convertible_points`
- مصرف‌شده در conversion: exact ledger consumptions + legacy historical cashed rows.

مثال canonical: اگر عضو 100 امتیاز convertible کسب کند و 40 را تبدیل کند، اعتبار تاریخی او 100 باقی می‌ماند، 40 مصرف‌شده و 60 remaining conversion capacity است.

`total_points` الزاماً برابر consumed+remaining نیست، چون می‌تواند non-convertible و ابعاد دیگر Reputation را نیز شامل شود.

### Dimension breakdown

`reputationBreakdown()` مجموع signed transaction snapshots را برای چهار بُعد محاسبه می‌کند:
- مشارکت
- اعتمادپذیری
- تخصص
- اعتماد مدنی

null/unknown historical dimensions حدس زده نمی‌شوند و در bucket صریح `legacy_other` / «سابقه قدیمی / سایر» قرار می‌گیرند.

### Public profile

Public member profile اکنون یک کارت Reputation responsive و فارسی دارد:
- امتیاز کل اعتبار و مشارکت
- سطح
- breakdown چهار بُعد
- سابقه قدیمی/سایر، فقط اگر non-zero باشد.

Privacy invariant: public view از `publicReputationSummary()` استفاده می‌کند که عمداً هیچ‌یک از این موارد را برنمی‌گرداند:
- remaining convertible capacity
- ledger consumption
- legacy cashed
- convertible awarded
- reversal/cashability data.

بنابراین privacy در data boundary برقرار است، نه با CSS مخفی.

برای حفظ UI قدیمی، view قبلی profile بدون بازنویسی به `profile-member-base.blade.php` منتقل شده و wrapper جدید همان layout/content را با `@parent` حفظ می‌کند.

### «مشارکت‌های من» → «امتیازات من»

صفحه جدید موازی ساخته نشده است. surface موجود History حفظ شده و tab موجود `امتیازات من` کامل شده است.

نمایش خصوصی:
- امتیاز اعتبار و مشارکت
- سطح
- breakdown چهار بُعد
- مشارکت قابل تبدیل کسب‌شده
- مصرف‌شده در تبدیل
- مشارکت قابل تبدیل باقی‌مانده
- legacy/reversal note فقط هنگام نیاز.

CTA:
`تبدیل امتیاز مشارکت به بهار`

فرم مستقیم به route موجود `reputation.conversion.convert` متصل است؛ conversion algorithm یا endpoint دوم ساخته نشده است.

برای حفظ markup/navigation قدیمی، History view قبلی عیناً در `history/index-base.blade.php` نگه داشته شده و wrapper جدید summary را در همان `#tab-points` قرار می‌دهد.

### Najm Bahar wallet

Wallet همان canonical summary را مصرف می‌کند و wording روشن دارد:
- مجموع امتیاز اعتبار و مشارکت
- مشارکت قابل تبدیل کسب‌شده
- مصرف‌شده در تبدیل
- مشارکت قابل تبدیل باقی‌مانده.

### Legacy election catalogue

`election_candidate` و `election_participated` دیگر bootstrap نمی‌شوند. Existing DB rows حذف نمی‌شوند، بلکه audit-only می‌مانند و forced inactive/non-convertible هستند. Admin نمی‌تواند آن‌ها را دوباره فعال اقتصادی کند.

### Admin Reputation UI

- قواعد semantic grouping فارسی دارند.
- deprecated rules صریحاً «منسوخ» و فقط‌خواندنی‌اند.
- recent point events و recent conversions audit-only هستند.
- dimension labels، action labels و conversion statuses فارسی شده‌اند.
- headers فنی نیز فارسی‌اند؛ technical IDs/keys فقط برای trace در code-style/LTR باقی می‌مانند و معنای اصلی UI نیستند.
- `reliability` در UI یکدست «اعتمادپذیری» نمایش داده می‌شود.

### R6 contract coverage

مهم‌ترین قراردادهای closure:
- `ParticipationSummaryBoundaryContractTest`
- `WalletPointTransparencyContractTest`
- `LegacyElectionRuleDeprecationContractTest`
- `ReputationAdminSemanticLabelsContractTest`
- `ReputationUserSurfacesContractTest`
- `ReputationAdminPersianAuditContractTest`

R6 implementation checkpoint `8503e2c73b20dbca5a915190f052a75610822320` با Full #2051 و Responsive #417 کاملاً سبز است.

## Six-task ledger

- [x] R1 — Rule Control Plane & Runtime Source of Truth
- [x] R2 — Core Policy Dimensions, Convertibility & Admin Control Plane
- [x] R3 — Event Idempotency, Anti-Farming & Correct Recipients
- [x] R4 — Financial Conversion Ledger & Consumption Safety
- [x] R5 — Evidence-backed Catalogue & Runtime Wiring
- [x] R6 — Migration, Transparency UI, Admin/UAT & Final Freeze

## Final constitutional/economic invariants

- conversion never mints Bahar.
- only the member's own Dim can be activated.
- only `convertible=true + dimension=participation` snapshots enter economic conversion.
- durable Reputation does not disappear after economic conversion.
- public profile never exposes private conversion/cashability state.
- self-like never creates convertible capacity.
- exact consumption preserves remainder; duplicate/retry cannot double-consume.
- policy edits affect future awards, not historical snapshots.
- historical audit trail survives migration/deprecation.
- non-Participation penalties do not silently reduce Participation entitlement.
- ambiguous status/activity transitions do not become economic rewards without domain evidence.

## Continuation / UAT note

کد R1–R6 بسته است. گام بعدی توسعه عمیق Reputation نیست؛ در صورت نیاز UAT دستی UI روی محیط واقعی/لوکال انجام شود: public member profile، «مشارکت‌های من → امتیازات من»، conversion CTA، Najm Bahar wallet و admin Reputation audit. هر defect مشاهده‌شده باید با test/contract مستقل باز شود.

هیچ merge به `main` در این closure انجام نشده است. PR #92 validation-only است و **نباید merge شود**.
