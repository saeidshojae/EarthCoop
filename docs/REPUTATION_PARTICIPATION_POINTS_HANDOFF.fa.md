# Handoff — Reputation / Participation Points Hardening

**Branch:** `agent/economic-system-current-integration`  
**Do not merge to:** `main`  
**Spec:** `docs/REPUTATION_PARTICIPATION_POINTS_AUDIT.fa.md`  
**Implementation plan:** `docs/superpowers/plans/2026-08-31-reputation-participation-points-hardening.md`

## هدف

بستن subsystem امتیاز در ۶ تسک R1 تا R6 به‌نحوی که سیاست امتیازدهی در DB قابل مدیریت، audit-friendly و امن برای اتصال Points → Dim → Active باشد. Reputation/Trust/Expertise از Participation اقتصادی قابل تفکیک‌اند، اما تصمیم محصول این است که هر Rule به‌صورت مستقل می‌تواند توسط ادمین convertible یا non-convertible باشد.

## تصمیم‌های محصول authoritative

1. EarthCoop در دوره bootstrap است؛ بنابراین فعالیت‌های واقعی گروهی مانند پست، دیدگاه، نظرسنجی و واکنش می‌توانند امتیاز قابل‌نقد داشته باشند، مشروط به anti-farming، cap/cooldown/idempotency و کنترل ادمین.
2. active و convertible مستقل‌اند.
3. Config فقط bootstrap/default است و DB runtime source of truth است.
4. تغییر policy آینده history قبلی را بازنویسی نمی‌کند؛ transaction باید snapshot اقتصادی زمان award را نگه دارد.
5. Like دو reward مستقل دارد: `post_liked`/`comment_liked` برای reactor و `post_upvoted`/`comment_upvoted` برای صاحب محتوا؛ هر چهار Rule مستقل و قابل مدیریت‌اند.
6. دعوت موفق (`invite_member`) و پرداخت موفق حق عضویت (`membership_fee_paid`) Participation reward مستقل و admin-managed دارند.
7. پاداش مدیر/بازرس می‌تواند convertible باشد و هویت اقتصادی آن `user + role + governance level` است؛ انتخاب مجدد همان role/level پاداش اقتصادی جدید نمی‌سازد، ولی role یا level دیگر مستقل است.
8. تبدیل Points هرگز Bahar جدید mint نمی‌کند؛ فقط Dim موجود همان عضو را با `MonetaryService::activateDim()` به Active تبدیل می‌کند.
9. تا قبل از freeze نهایی R4، برای launch می‌توان کسب/نمایش امتیاز را فعال نگه داشت ولی conversion را با policy flag `reputation_conversion_enabled` خاموش نگه داشت.

## Baseline و اسناد

- UI/economic baseline پیش از audit: `e93cc1da94358b576e89c48e3fdd50e8fbf9651b`
- Audit document: `d7e7e3d48d3f1480520383f57f618dd8260fb89d`
- Plan creation/update family: `e524fa9af03bd3bc34feb479b0dd18956c37c9cf`, `d1272142c2734b853da928392d4fad40584a5620`

## R1 — Rule Control Plane & Runtime Source of Truth

Status: **GREEN / COMPLETE**

- RED: `2c8071126060e3aa59e6417568f14d1ea07ec9ac`
- GREEN: `9a773cff52ed509676c889c3f49de956c7563052`
- Full Validation #1945: success
- Responsive #311: success

Established:
- DB rule authoritative even when inactive.
- inactive DB rule never falls back to config.
- DB daily_cap runtime-authoritative.
- config fallback only when DB row absent.
- admin bootstrap insert-only and preserves admin-authored policy.

## R2 — Policy Dimensions, Convertibility & Admin Control Plane

Status: **CORE + ADMIN UI GREEN; catalogue/governance wiring continues in R5**

Key checkpoints:
- RED snapshot contracts: `9edf318ed32b122a4e7d01157c6b15092328abcb`
- schema/model/service production: `3f10899821a6188277468b1308b17f1ebe9b2edb`, `5faff40568d1aac9670565879314f9b5a5b402f5`, `6d38ed594339c0b89d3539f043795e2076bc81a4`, `ffe31485cf983ed694d2b6e711cd10ed26c312d4`
- initial GREEN: Full #1952 / Responsive #318
- admin persistence RED: `1134e2ca46ce46948599448fde33e5e4e8549b0a`
- admin backend GREEN: `93f2ca73b92b50525f31a608f9a1f3a6d6f3a154`, Full #1954 / Responsive #320
- membership runtime reward: `1dc0bc37058f23e679de907cef77029e3a623537`, Full #1956 / Responsive #322
- invite bootstrap/runtime: `329c6ff029ff4fd44ed59d2214da6c921df056b2`, `7ac0114065b45860992653cebf2dc5daa433174c`, Full #1960 / Responsive #326
- membership bootstrap: `aedbded8f6daacd0d81c29ee2d2a196427f3b137`, Full #1962 / Responsive #328
- admin Blade policy controls RED: `54d8789e7c7805326405a84aaf043df608129c3b`
- final admin UI formatting-safe GREEN: `caf05273c3f541f9c3915b49decc8fed52998167`, Full #1965 / Responsive #331

Established:
- dimensions: `participation`, `reliability`, `expertise`, `civic_trust`.
- Rule policy includes active/weight/dimension/convertible/daily_cap/repeat_policy.
- award transaction snapshots dimension + convertible.
- admin edits future policy without rewriting historical snapshots.
- `invite_member` default weight 10, participation, convertible, once_per_context.
- `membership_fee_paid` default weight 12, participation, convertible, once_per_context.

Open R2/R6 presentation debt:
- Persian labels/grouping for reaction, invite and membership rules still need semantic cleanup; this is not a ledger blocker.

## R3 — Event Idempotency, Anti-Farming & Correct Recipients

Status: **CORE IDEMPOTENCY + REACTIONS + MAJOR CALL-SITES GREEN; NOT FULLY CLOSED**

Generic event ledger:
- RED `753f2d30144f529035ecacccf3b5a67767283f38`
- production `5afee14b0909ad9596d21c60bc2f5ef3082954b1`, `717cb86411bd926abe4c1ad8e06cf707d7c5d81a`, `bbdf87c84fe6306464ecde1c463789638ae0107b`
- Full #1969 / Responsive #335: success

Reactions:
- structural RED `fe681ba9b4da793aeab040eac58a1ddaacbe1dbb`
- production `f044b8f8b7cf3ff14a3cbd80fe049b341af5573f`, `aa5cd4aeea68643c9b68de08a43128a25b91c4ff`
- assertion correction `b97c7cd58891494b37b7bb51283fb2b83b820d7b`
- Full #1974 / Responsive #340: success
- behavioral idempotency test `91ed0a295a650c155c65e4555cba6a8c83d49abf`
- Full #1975 / Responsive #341: success

Stable event keys for creation/referral:
- RED `34f7a1aa2a289b65bd4f5da4a63cd7de5d70c1b9`
- post `20b542d438d9a1416e0758166dfe8b3a225a61b9`
- comment `233d4ec9932ededd25c229eac9c40236543c6360`
- referral `e2086b1fdaeb7fc26791e8bb2745684d8ab7a761`
- Full #1979 / Responsive #345: success

Onboarding event keys:
- RED `4ec95a7831a6ae14b803c3684abcc99340784881`
- email verification `302ced3120e28ed3c35032ed7dbcbbd4d9b2134b`
- profile completion `2e80ee9c5b8703f49dfce38374183b70af65ae2c`
- Full #1982 / Responsive #348: success

Reaction defaults currently:
- `post_liked` = 1/day cap 20
- `post_upvoted` = 5/day cap 50
- `comment_liked` = 1/day cap 20
- `comment_upvoted` = 1/day cap 100
- all participation + convertible + once_per_context by bootstrap default; existing DB rows remain authoritative.

Open R3 items to close later:
- membership fee generic event_key still should be made explicit despite application guard.
- Stock bid/win/settlement call-sites still need stable event keys if retained in catalogue.
- raw post/comment spam bounding needs final bootstrap policy.
- self-like reward policy is product-sensitive and must not be silently changed.
- generic DB unique event_key exists, but graceful duplicate-key handling under a true race has not been separately stress-tested.

## R4 — Financial Conversion Ledger & Consumption Safety

Status: **IN PROGRESS — lossless conversion + sequential retry + cross-user isolation GREEN; atomic concurrency/history compatibility remain**

Structural RED:
- `85835788c4db8ae7b463b7f74a93f2a6c8743e76`
- Full #1983 RED as intended; Responsive #349 success

Lossless consumption ledger GREEN:
- production `7bbab2168bf6de4d55c5ba3ccfcf30cc2beb5510`
- adds `user_point_consumptions` and `UserPointConsumption`.
- conversion sources only positive `convertible=true` + `dimension=participation` snapshots.
- partial transaction consumption no longer marks whole source cashed.
- ratio remainder preserved: request 150 at ratio 100 consumes 100, leaves 50, activates 1.
- Full #1984 / Responsive #350: success

Behavioral exactness + retry:
- behavior test RED `158ee58d1c6df856a0e2cc644764b79de85d5abc`
- partial exactness already passed in RED run #1985; retry failed as intended.
- sequential retry production `93cf58b74154ea636c80324f66625a018eb7e114`
- Full #1986 / Responsive #352: success

Cross-user idempotency isolation:
- RED test `3599f868809095a798c837be071002b5d7457048`
- RED Full #1987; Responsive #353 success
- production `f6f4ba997fcbd548edd673226eca1efb7452cd3f`
- canonical key now `reputation-conversion:{user_id}:{client_key}`; fallback also user-scoped.
- Full Validation #1988: success
- Responsive #354: success

R4 remaining before COMPLETE:
1. atomic request identity for concurrent same-user/same-key conversion. Current duplicate `exists()` check precedes source locks and is sequentially safe but not proven concurrent-safe.
2. legacy `is_cashed=true` compatibility. Historical already-cashed rows must never become eligible again under the new consumption ledger.
3. explicit behavioral proof that non-convertible and non-participation snapshots are excluded from both info and conversion.
4. end-to-end UI request idempotency: inspect conversion form/JS and ensure a stable request key is actually sent when browser/UI can retry/double-submit.
5. penalty semantics remains a product/economic decision: negative Participation/reputation currently affects aggregate points but not necessarily positive convertible capacity. Do not silently impose a new economic rule; surface for decision if it blocks R4 Definition of Done.
6. after the above, Full Validation on final R4 checkpoint and update this handoff.

## R5 — Bootstrap + Outcome Participation Catalogue & Runtime Wiring

Status: **NOT STARTED AS A COMPLETE TASK; some prerequisites/wiring already landed in R2/R3**

Known candidates:
- normal poll create/participate with stable event identity per poll+user; avoid election/governance poll path unless explicitly classified.
- systemic election outcome rewards for manager/inspector once per user+role+level.
- bounded group participation and verified outcomes where domain evidence exists.

## R6 — Migration, Transparency UI, Admin/UAT & Final Constitution

Status: **NOT STARTED AS A COMPLETE TASK**

Includes final legacy migration policy, semantic labels/grouping, user/admin transparency, invariant suite, UAT and freeze docs.

## Six-task ledger

- [x] R1 — Rule Control Plane & Runtime Source of Truth
- [x] R2 — Core Policy Dimensions, Convertibility & Admin Control Plane
- [ ] R3 — Event Idempotency, Anti-Farming & Correct Recipients — major core GREEN, closure items remain
- [ ] R4 — Financial Conversion Ledger & Consumption Safety — current priority
- [ ] R5 — Bootstrap + Outcome Participation Catalogue & Runtime Wiring
- [ ] R6 — Migration, Transparency UI, Admin/UAT & Final Constitution

## Launch-oriented status as of Full #1988

- earning/storing/displaying points can remain enabled for initial launch evaluation.
- conversion Points → Active should remain policy-disabled until R4 is fully closed and final financial invariant validation is green.
- not every desired future activity is wired yet; current catalogue is functional but incomplete.

## Constitutional/economic invariants

- conversion never mints Bahar.
- only the member's own Dim can be activated.
- only transaction snapshots explicitly `convertible=true` and in economic participation eligibility may enter conversion.
- conversion consumes exact points; no ratio remainder may disappear.
- duplicate/retry/cross-user idempotency must not double-consume or cross-contaminate monetary events.
- historical audit trail must survive migration/hardening.
- policy edits affect future awards, not historical eligibility snapshots.

## New-chat continuation prompt

«ادامه سخت‌سازی Reputation/Participation Points را روی branch `agent/economic-system-current-integration` انجام بده. ابتدا `docs/REPUTATION_PARTICIPATION_POINTS_AUDIT.fa.md`، `docs/superpowers/plans/2026-08-31-reputation-participation-points-hardening.md` و `docs/REPUTATION_PARTICIPATION_POINTS_HANDOFF.fa.md` را از branch فعلی بخوان. آخرین financial checkpoint ثبت‌شده در handoff را با branch head و CI روز تطبیق بده. R4 را قبل از تصمیم launch کامل کن: atomic same-user conversion identity، legacy is_cashed compatibility، eligibility behavior و UI idempotency را با TDD ببند؛ penalty semantics را بدون تصمیم محصول تغییر نده. هیچ merge به main انجام نده.»
