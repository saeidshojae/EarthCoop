# Handoff — Reputation / Participation Points Hardening

**Working branch:** `agent/r3-reputation-close`  
**Integration base:** `agent/economic-system-current-integration`  
**Do not merge to:** `main`  
**Spec:** `docs/REPUTATION_PARTICIPATION_POINTS_AUDIT.fa.md`  
**Implementation plan:** `docs/superpowers/plans/2026-08-31-reputation-participation-points-hardening.md`

## هدف

بستن subsystem امتیاز در شش مرحله R1 تا R6 به‌نحوی که policy در DB قابل مدیریت، audit-friendly و امن برای اتصال Points → Dim → Active باشد. Reputation/Trust/Expertise از Participation اقتصادی جدا هستند و هر Rule می‌تواند به‌صورت مستقل convertible یا non-convertible باشد.

## تصمیم‌های authoritative

1. DB runtime source of truth است؛ config فقط bootstrap/default است و existing DB rule را overwrite نمی‌کند.
2. transaction اقتصادی snapshot زمان award را با `dimension` و `convertible` نگه می‌دارد؛ تغییر policy آینده history را بازنویسی نمی‌کند.
3. conversion فقط positive `participation + convertible=true` را مصرف می‌کند و Bahar جدید mint نمی‌کند؛ فقط Dim همان عضو از مسیر `MonetaryService::activateDim()` به Active تبدیل می‌شود.
4. penalty فقط وقتی entitlement اقتصادی Participation را کم می‌کند که transaction منفی هم `dimension=participation` و هم `convertible=true` باشد؛ clawback از Active قبلاً فعال‌شده انجام نمی‌شود.
5. self-like در UI مجاز است. رخداد self-like می‌تواند reputation غیرمالی ثبت کند، اما snapshot آن اجباراً `convertible=false` است و هیچ ظرفیت Points→Bahar ایجاد نمی‌کند.
6. پاداش outcome انتخابات فقط از direct active appointment واقعی پس از پذیرش مسئولیت می‌آید؛ inherited appointment پاداش مستقل ندارد. هویت اقتصادی `user + role + governance level` است.
7. هیچ status یا activity صرفاً بر اساس اسمش به reward وصل نمی‌شود؛ R5 فقط outcomeهایی را wire کرده که actor/recipient و completion evidence صریح در domain دارند.

## R1 — Rule Control Plane & Runtime Source of Truth

Status: **GREEN / COMPLETE**

Established:
- DB rule authoritative even when inactive.
- inactive DB rule هرگز به config fallback نمی‌کند.
- DB daily cap runtime-authoritative است.
- bootstrap insert-only است و policy ادمین را حفظ می‌کند.

Reference validation: Full #1945 / Responsive #311 — success.

## R2 — Policy Dimensions, Convertibility & Admin Control Plane

Status: **GREEN / COMPLETE**

Established:
- dimensions: `participation`, `reliability`, `expertise`, `civic_trust`.
- Rule policy: active, weight, dimension, convertible, daily_cap, repeat_policy.
- award transaction snapshot: dimension + convertible.
- admin edits future policy without rewriting history.
- `invite_member`: weight 10, participation, convertible, once_per_context.
- `membership_fee_paid`: weight 12, participation, convertible, once_per_context.

Presentation/semantic cleanup remains R6 work, not R2 ledger debt.

## R3 — Event Idempotency, Anti-Farming & Correct Recipients

Status: **GREEN / COMPLETE**

Closed items:
- generic unique `event_key` ledger and idempotent pre-check.
- graceful duplicate-key race handling for SQLSTATE `23000` / `23505`, only when canonical event row exists after rollback; unrelated DB errors rethrow.
- stable post/comment creation keys.
- stable onboarding/email/profile keys.
- verified invitation key: `invite_member:referrer:{referrer}:member:{member}`.
- membership fee key: `membership_fee_paid:user:{user}:year:{paymentYear}`.
- Stock keys per bid+recipient for `bid_placed`, `bid_won`, `successful_settlement`.
- raw creation anti-farming bootstrap caps: post 50 points/day, comment 20 points/day.
- self-like policy: UI allowed; award snapshot forced `convertible=false` for reactor and owner-side like/upvote events.

Important limitation: no synthetic high-load concurrency benchmark was run; race invariant is enforced structurally by DB uniqueness and graceful duplicate handling plus tests.

R3 closure evidence is included in the later R5 production checkpoint Full #2025, which is fully green.

## R4 — Financial Conversion Ledger & Consumption Safety

Status: **GREEN / COMPLETE**

Established:
- exact point-consumption ledger; no whole-source false cashing.
- exact ratio remainder preservation.
- only positive `convertible=true + dimension=participation` snapshots are eligible.
- canonical user-scoped parent identity in `user_point_conversions` with uniqueness on `(user_id, request_key)`.
- child `user_point_consumptions` record exact source-point consumption.
- same-user/same-key retry replays applied parent rather than consuming/activating twice.
- legacy `is_cashed=true` convertible Participation rows remain historical and are conservatively excluded from future capacity.
- non-convertible and wrong-dimension snapshots are excluded.
- negative economic Participation reversals reduce remaining future capacity only.
- activation remains exclusively through `MonetaryService::activateDim()`.

Final R4 validation: Full #2006 / Responsive #372 — success.

Core invariant suite:
- `ParticipationConversionLedgerContractTest`
- `ParticipationConversionBehaviorTest`
- `ParticipationConversionAtomicIdentityTest`
- `ParticipationConversionLegacyCompatibilityTest`
- `ParticipationConversionEligibilityTest`
- `ParticipationConversionUiIdempotencyContractTest`
- `ParticipationConversionPenaltySemanticsTest`

## R5 — Bootstrap + Outcome Participation Catalogue & Runtime Wiring

Status: **GREEN / COMPLETE FOR CURRENT EVIDENCE-BACKED DOMAIN**

**Freeze production checkpoint:** `f11c2418a553bcaaae999921a5c19a252e2ab14d`  
**Full Validation #2025:** success  
**Full Project PHPUnit:** success  
All specialty gates (Group Chat, Group Admin/Identity, Najm Hoda, Governance, Najm Bahar, Stock, JS) also passed.

### Normal group polls

Only `main_type=1` normal polls participate in generic poll rewards. `main_type=0` election-style polls are explicitly excluded.

Stable identities:
- `poll_created:poll:{poll_id}:creator:{user_id}`
- `poll_participated:poll:{poll_id}:user:{user_id}`

Vote removal does not award. Re-vote/re-add cannot mint another reward for the same poll+user.

Bootstrap defaults:
- `poll_created`: weight 5, daily cap 25, participation, convertible, once_per_context.
- `poll_participated`: weight 2, daily cap 100, participation, convertible, once_per_context.

### Verified invitation

Reward is bound to the actually consumed `InvitationCode.used_by` path, not a claimed/referral parameter. Historical system invitation issuer is excluded by the existing runtime guard. Stable identity is referrer+new member. Default remains weight 10, participation, convertible, once_per_context.

### Systemic election outcome

Reward source is existing after-commit `ElectionAppointmentApplied`.

Only `direct + active` appointments award. Inherited appointments from electoral compression are excluded.

Actions:
- `elected_manager`
- `elected_inspector`

Stable identity:
- `elected_{role}:user:{user_id}:level:{location_level}`

Thus re-election to the same role/level does not create a new economic event; another role or governance level is independent.

Both rules are `participation + once_per_context` but **convertible=false by bootstrap default**. Admin may deliberately enable convertibility later.

### Completed professional referral outcome

`ProposalReferral` provides real domain evidence: pending → in_review → completed, with target-group manager/inspector authorization, `completed_by`, `assessment`, and `completed_at`.

Reward is emitted only after the Governance transaction has completed. Reputation failure is fail-open and cannot roll back Governance completion.

Action:
- `professional_referral_completed`

Stable identity:
- `professional_referral_completed:referral:{referral_id}`

Bootstrap default:
- weight 10
- daily cap 50
- participation
- convertible=false
- once_per_context

### Deliberately unwired candidates

These were audited and intentionally NOT connected because economic recipient/evidence is ambiguous:
- Najm Bahar project assignment review: completion fields exist, but actor/recipient entitlement is ambiguous (assignee vs reviewer vs owner).
- Najm Hoda group action item `done`: assigned user exists, but manager/inspector can mark done and no independent assignee-completion acceptance/evidence exists.
- generic milestone/report: no canonical verified member-earned outcome surface was found.
- secretariat/formal-record completion: no independent canonical member-earned outcome surface was found in current implementation.
- Najm Bahar project admin approval: administrative approval alone is not member participation entitlement.

These must stay unwired until their domain contract identifies recipient, completion evidence, idempotency identity and reversal semantics.

### Legacy catalogue debt deferred to R6

- `election_candidate` and `election_participated` have no current runtime call-sites and survive as legacy catalogue/config/admin concepts.
- `election_candidate` is semantically incompatible with the current systemic election architecture, which has no formal candidacy flow.
- bootstrap uses `firstOrCreate`; therefore removing config keys would not delete historical DB rows. R6 must deprecate/inactivate/present these rows without destroying audit history.

## R6 — Migration, Transparency UI, Admin/UAT & Final Constitution

Status: **IN PROGRESS — AUDIT STARTED**

First confirmed debt:
- `NajmBaharController::wallet()` currently calculates `cashedPoints` / `uncashedPoints` from raw positive `is_cashed` rows.
- this is not the R4 economic truth because it ignores dimension, convertibility, exact consumption ledger and Participation reversals.
- `ReputationConversionController` already contains the canonical eligibility calculation: positive convertible Participation snapshots, exact consumptions, economic reversals and historical cashed compatibility.

Required first R6 implementation:
1. extract canonical point/conversion summary into one reusable service/query boundary;
2. make `ReputationConversionController::getInfo()` consume that boundary;
3. make Najm Bahar wallet consume the same boundary instead of ad-hoc `is_cashed` sums;
4. surface clear concepts separately: total reputation/activity points, economically eligible Participation, consumed/historical cashed, and remaining conversion capacity;
5. cover with behavioral tests before UI wording/polish.

Further R6 work:
- legacy rule migration/deprecation without deleting audit history;
- semantic Persian labels/grouping in admin UI;
- user/admin transparency around dimension/convertibility/caps/source;
- invariant suite and UAT;
- final launch policy/constitution/freeze document.

## Six-task ledger

- [x] R1 — Rule Control Plane & Runtime Source of Truth
- [x] R2 — Core Policy Dimensions, Convertibility & Admin Control Plane
- [x] R3 — Event Idempotency, Anti-Farming & Correct Recipients
- [x] R4 — Financial Conversion Ledger & Consumption Safety
- [x] R5 — Evidence-backed Catalogue & Runtime Wiring
- [ ] R6 — Migration, Transparency UI, Admin/UAT & Final Constitution

## Constitutional/economic invariants

- conversion never mints Bahar.
- only the member's own Dim can be activated.
- only snapshots explicitly `convertible=true` and `dimension=participation` enter economic conversion.
- self-like never creates convertible capacity.
- exact consumption preserves remainder; duplicate/retry cannot double-consume.
- policy edits affect future awards, not historical snapshots.
- historical audit trail survives migration/deprecation.
- non-Participation penalties do not silently reduce Participation entitlement.
- ambiguous activity/status transitions do not become economic rewards without domain evidence.

## New-chat continuation prompt

«ادامه سخت‌سازی Reputation/Participation Points را از branch `agent/r3-reputation-close` و R5 freeze checkpoint `f11c2418a553bcaaae999921a5c19a252e2ab14d` ادامه بده. Full Validation #2025 روی این checkpoint کاملاً سبز است. R1 تا R5 بسته‌اند؛ آن‌ها را بدون evidence جدید بازطراحی نکن. ابتدا `docs/REPUTATION_PARTICIPATION_POINTS_HANDOFF.fa.md` و plan را بخوان و سپس R6 را از استخراج canonical Participation conversion summary و حذف محاسبه legacy `is_cashed` از Najm Bahar wallet ادامه بده. بعد legacy catalogue migration/deprecation، semantic transparency UI، UAT و final freeze را ببند. هیچ merge یا تغییر مستقیمی روی main انجام نده.»
