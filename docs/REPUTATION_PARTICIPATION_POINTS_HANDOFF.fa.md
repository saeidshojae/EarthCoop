# Handoff — Reputation / Participation Points Hardening

**Branch:** `agent/economic-system-current-integration`  
**Do not merge to:** `main`  
**Spec:** `docs/REPUTATION_PARTICIPATION_POINTS_AUDIT.fa.md`  
**Implementation plan:** `docs/superpowers/plans/2026-08-31-reputation-participation-points-hardening.md`

## هدف

بستن subsystem امتیاز در ۶ تسک R1 تا R6 به‌نحوی که سیاست امتیازدهی در DB قابل مدیریت، audit-friendly و امن برای اتصال Points → Dim → Active باشد. Reputation/Trust/Expertise از Participation اقتصادی قابل تفکیک‌اند، اما تصمیم محصول این است که هر Rule به‌صورت مستقل می‌تواند توسط ادمین convertible یا non-convertible باشد.

## تصمیم محصول مصوب 2026-08-31

1. EarthCoop در دوره bootstrap است؛ بنابراین فعالیت‌هایی که الزاماً outcome اجتماعی نهایی ندارند—از جمله پست، دیدگاه، نظرسنجی، واکنش و سایر فعالیت‌های واقعی گروهی—می‌توانند امتیاز قابل‌نقد داشته باشند، مشروط به محدودیت ضد farming، cap/cooldown/idempotency مناسب و کنترل ادمین.
2. انتخاب‌شدن به‌عنوان مدیر یا بازرس ارزش اقتصادی ناشی از اعتماد اعضا دارد و می‌تواند convertible باشد.
3. پاداش انتخاباتی برای هر ترکیب `user + role + governance level` فقط یک‌بار در تمام تاریخ قابل‌نقد است. انتخاب مجدد همان شخص در همان role و همان level پاداش قابل‌نقد جدید نمی‌سازد.
4. `manager` و `inspector` achievementهای مستقل‌اند؛ یک فرد می‌تواند در یک level یک‌بار برای manager و یک‌بار برای inspector پاداش مستقل بگیرد.
5. انتخاب همان فرد در level دیگری achievement مستقل است و می‌تواند پاداش مستقل داشته باشد.
6. ادمین باید بتواند سیاست هر Rule را از Control Panel مدیریت کند: حداقل active، weight، convertible، dimension، daily cap و محدودیت تکرار متناسب با نوع Rule. تغییر سیاست آینده نباید history transactionهای قبلی را بازنویسی کند.
7. فعال بودن Rule و convertible بودن آن دو مفهوم مستقل‌اند.
8. Config فقط bootstrap/default است؛ DB runtime source of truth است.

## وضعیت فعلی

### Baseline audit
- Audit baseline: `e93cc1da94358b576e89c48e3fdd50e8fbf9651b`
- Audit document commit: `d7e7e3d48d3f1480520383f57f618dd8260fb89d`

### R1 — Rule Control Plane & Runtime Source of Truth
Status: **GREEN / COMPLETE**

RED checkpoint: `2c8071126060e3aa59e6417568f14d1ea07ec9ac`
GREEN production checkpoint: `9a773cff52ed509676c889c3f49de956c7563052`
Validation on GREEN checkpoint:
- Full Validation #1945: success
- Responsive Contract Validation #311: success

R1 contracts now established:
- existing DB rule is authoritative even when inactive;
- inactive DB rule does not fall back to config;
- DB daily_cap is runtime-authoritative;
- config is fallback only when no DB rule exists;
- admin Reputation page only inserts missing bootstrap rules and does not overwrite saved DB policy.

### R2 — Policy Dimensions, Convertibility & Admin Control Plane
Status: **IN PROGRESS — product policy approved; next step RED tests**

R2 must no longer assume `elected_manager` / `elected_inspector` are non-convertible. They are admin-manageable convertible rules with once-per-role-per-level economic eligibility.

### Next exact execution step

1. Update implementation plan R2/R5 language to match approved bootstrap policy.
2. Write RED tests before production code for:
   - transaction snapshot of dimension + convertible policy;
   - admin persistence/control of active, weight, convertible, dimension, daily_cap;
   - convertible and active are independent;
   - manager election once-per-user-role-level economic eligibility;
   - inspector is independent from manager at same level;
   - another governance level is independently eligible.
3. Verify RED failures.
4. Add schema/model/runtime/admin changes minimally to satisfy contracts.
5. Run focused tests, then Full/Responsive validation and record exact GREEN checkpoint here.

## Six-task ledger

- [x] R1 — Rule Control Plane & Runtime Source of Truth
- [ ] R2 — Policy Dimensions, Convertibility & Admin Control Plane
- [ ] R3 — Event Idempotency, Anti-Farming & Correct Recipients
- [ ] R4 — Financial Conversion Ledger & Consumption Safety
- [ ] R5 — Bootstrap + Outcome Participation Catalogue & Runtime Wiring
- [ ] R6 — Migration, Transparency UI, Admin/UAT & Final Constitution

## P0 findings that must not be forgotten

- point event idempotency is absent.
- like/upvote rewards the reactor and can be farmed by toggling; recipient semantics must be deliberately defined per rule.
- raw post/comment creation is repeatable and requires bounded bootstrap policy rather than unlimited economics.
- conversion can lose partial transaction points.
- ratio remainder can be consumed without Gol equivalent.
- negative reputation does not necessarily constrain cashable positive transactions.
- `invite_member` has a call-site but no config bootstrap rule.
- several poll/election/profile/penalty rules are config-only and not runtime-wired.
- legacy Stock bid rewards should not define future canonical participation economics.

## Constitutional/economic invariants

- Participation conversion must never mint new Bahar.
- Conversion must only activate the member's own existing Dim via `MonetaryService::activateDim()`.
- Only transactions whose policy snapshot is explicitly convertible may enter conversion.
- Admin may make a Reputation/Trust/Participation rule convertible or non-convertible according to product policy; historical snapshots remain unchanged.
- Event retries/toggles/duplicates must not duplicate economic rewards.
- Election trust reward uses stable identity `user + role + governance level` and is economically once-only for that identity.
- Historical audit trail must be preserved through migrations and reversals.

## New-chat continuation prompt

«ادامه سخت‌سازی سیستم Reputation/Participation Points را روی branch `agent/economic-system-current-integration` انجام بده. ابتدا `docs/REPUTATION_PARTICIPATION_POINTS_AUDIT.fa.md`، `docs/superpowers/plans/2026-08-31-reputation-participation-points-hardening.md` و `docs/REPUTATION_PARTICIPATION_POINTS_HANDOFF.fa.md` را از branch فعلی بخوان، head و CI روز را بررسی کن و دقیقاً از اولین Task باز ادامه بده. تصمیم محصول 2026-08-31 درباره bootstrap rewards، کنترل کامل ادمین و once-per-role-per-level بودن پاداش مدیر/بازرس authoritative است. هیچ merge به main انجام نده و TDD/RED→GREEN را حفظ کن.»
