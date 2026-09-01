# Reputation & Participation Points Hardening Implementation Plan

**Goal:** تبدیل سیستم فعلی Reputation/Points به یک Participation Accounting System قابل‌اعتماد، ضد سوءاستفاده، audit-friendly و امن برای اتصال Points → Dim → Active Bahar، همراه با Control Plane کامل ادمین برای سیاست‌های امتیازدهی.

**Architecture:** هسته فعلی `ReputationService`, `UserPoint`, `UserPointTransaction`, `ReputationRule` حفظ می‌شود. DB منبع حقیقت runtime است و config فقط bootstrap/default. هر Rule دارای dimension و convertible مستقل است و transaction snapshot سیاست زمان صدور را حفظ می‌کند. در دوره bootstrap، فعالیت‌های واقعی گروهی می‌توانند حتی بدون outcome نهایی قابل‌نقد باشند، اما باید محدود، idempotent و قابل خاموش/تنظیم‌کردن از پنل باشند. تبدیل فقط از طریق `MonetaryService::activateDim()` انجام می‌شود و Bahar جدید خلق نمی‌کند.

**Tech Stack:** Laravel/PHP, Eloquent, PHPUnit, MySQL/PostgreSQL-compatible migrations, Najm Bahar MonetaryService/MonetaryPolicyService.

**Spec:** `docs/REPUTATION_PARTICIPATION_POINTS_AUDIT.fa.md`  
**Live handoff / authoritative current state:** `docs/REPUTATION_PARTICIPATION_POINTS_HANDOFF.fa.md`

## Global Constraints

- Branch: `agent/economic-system-current-integration`; هیچ merge/change مستقیم روی `main`.
- TDD: behavior جدید ابتدا RED، سپس GREEN.
- DB runtime source of truth؛ config فقط bootstrap/default.
- active و convertible مستقل‌اند.
- policy edit آینده history قبلی را بازنویسی نمی‌کند؛ transaction باید snapshot داشته باشد.
- Points conversion فقط Dim همان عضو را به Active تبدیل می‌کند؛ mint مستقیم ممنوع.
- integer Gol در مسیر اقتصادی.
- UI خارج از subsystem امتیاز تغییر نکند.
- هر release با focused tests + Full Validation بسته شود؛ Responsive وقتی UI درگیر است.

---

### Task R1: Rule Control Plane & Runtime Source of Truth — COMPLETE

GREEN checkpoint: `9a773cff52ed509676c889c3f49de956c7563052`  
Full Validation #1945: success  
Responsive #311: success

Established contracts:
- existing DB row authoritative even if inactive;
- inactive DB rule never falls back to config;
- DB daily_cap authoritative;
- config fallback only when DB row absent;
- admin page creates missing bootstrap rows only and preserves admin-authored policy.

---

### Task R2: Policy Dimensions, Convertibility & Admin Control Plane — CORE COMPLETE

Core/admin GREEN through `caf05273c3f541f9c3915b49decc8fed52998167`  
Full #1965 / Responsive #331: success

Established:
- dimensions: participation/reliability/expertise/civic_trust;
- active, weight, dimension, convertible, daily_cap, repeat_policy admin-managed;
- transaction snapshot of dimension + convertible;
- DB authoritative, config bootstrap only;
- invite/member-fee bootstrap + runtime rules present.

Deferred to R5/R6 rather than blocking R2 core:
- systemic election role+level wiring;
- final catalogue classification;
- semantic Persian labels/grouping.

---

### Task R3: Event Idempotency, Anti-Farming & Correct Recipients — CORE GREEN, CLOSURE REMAINS

Core event-key ledger GREEN: `bbdf87c84fe6306464ecde1c463789638ae0107b`, Full #1969 / Responsive #335.

Major GREEN checkpoints:
- two-sided reactions: `f044b8f8b7cf3ff14a3cbd80fe049b341af5573f`, `aa5cd4aeea68643c9b68de08a43128a25b91c4ff`, structural/behavioral verification through Full #1975.
- post/comment/referral keys through `e2086b1fdaeb7fc26791e8bb2745684d8ab7a761`, Full #1979 / Responsive #345.
- email/profile onboarding keys through `2e80ee9c5b8703f49dfce38374183b70af65ae2c`, Full #1982 / Responsive #348.

Still open before R3 final freeze:
- membership fee explicit generic event key;
- Stock bid/win/settlement event keys if retained;
- final spam/cap policy for raw content;
- self-like business policy;
- graceful true-race duplicate handling if needed.

---

### Task R4: Financial Conversion Ledger & Consumption Safety — CURRENT PRIORITY

Current verified financial checkpoint: `f6f4ba997fcbd548edd673226eca1efb7452cd3f`  
Full Validation #1988: success  
Responsive #354: success

Already GREEN:
- [x] RED partial transaction consumption.
- [x] RED ratio remainder preservation.
- [x] exact consumption ledger via `user_point_consumptions`.
- [x] eligible sources require positive + `convertible=true` + `dimension=participation`.
- [x] exact partial consumption; source transaction is not falsely fully cashed.
- [x] ratio remainder preserved.
- [x] sequential same-key retry does not double-consume/activate.
- [x] canonical conversion key is scoped by user, preventing cross-user Idempotency-Key collision.
- [x] activation exclusively through `MonetaryService::activateDim()`.

Remaining R4 execution order:
- [ ] **R4-A RED/GREEN — atomic conversion request identity**: add a parent conversion identity with unique user/request identity so two concurrent same-user/same-key requests cannot both become owners. Child consumption rows must belong to one conversion operation; retry replays existing completed request rather than consuming again.
- [ ] **R4-B RED/GREEN — legacy cashing compatibility**: historical `is_cashed=true` source rows must never become newly convertible under the consumption ledger. Use a conservative deterministic rule/backfill; never guess a historical remainder that was previously lost.
- [ ] **R4-C behavioral eligibility suite**: prove non-convertible and non-participation snapshots are excluded from `getInfo()` and `convert()` and consumed ledger remainder is reported exactly.
- [ ] **R4-D end-to-end UI idempotency**: inspect current wallet/conversion UI; if browser submits do not send stable Idempotency-Key, add a minimal stable request token/double-submit contract without redesigning UI.
- [ ] **R4-E penalty semantics decision**: negative Participation/reputation effect on conversion capacity is economically material. Do not silently decide. If current R4 technical invariants are otherwise complete, surface the exact alternatives to product owner and record decision before final financial freeze.
- [ ] **R4-F final invariant suite + Full Validation + handoff update**.

Commit family: `fix(reputation): make point conversion lossless and auditable`

---

### Task R5: Bootstrap + Outcome Participation Catalogue & Runtime Wiring

**Principle:** EarthCoop در دوره bootstrap هم فعالیت اجتماعی را تشویق می‌کند و هم به‌تدریج rewardهای outcome-based اضافه می‌کند. این دو دسته در catalogue مشخص و از پنل قابل مدیریت‌اند.

- [ ] Wire legitimate normal group poll create/participate with stable event identity; avoid treating election/governance poll path as generic participation unless explicitly classified.
- [ ] Keep `invite_member` bounded/verified/once-only and close its final presentation contracts.
- [ ] Wire systemic-election outcome: `elected_manager` and `elected_inspector`, convertible if admin policy says so, once per `user + role + governance level`.
- [ ] Add outcome-based events where domain evidence exists: fulfilled action item, on-time public contribution obligation, verified milestone/report, accepted specialist review, approved documentation/secretariat follow-up.
- [ ] Wealth amount, money transfer amount, raw bid amount and login must not automatically create scalable cashable points.
- [ ] Every new convertible action specifies recipient, source, event identity, award moment, cap/cooldown/repeat policy, reversal policy and evidence/reference.

Commit family: `feat(reputation): expand managed participation catalogue`

---

### Task R6: Migration, Transparency UI, Admin/UAT & Final Constitution

- [ ] Final deterministic legacy/backfill review after R4 compatibility rule.
- [ ] User UI distinguishes total/social reputation, convertible participation, consumed and remaining convertible points.
- [ ] Admin UI exposes complete current policy and audit trail.
- [ ] Add immutable transaction/consumption views needed for support/admin.
- [ ] Semantic Persian labels/grouping for reaction/invite/membership and final catalogue.
- [ ] Full invariant suite + Full Validation + Responsive + manual UAT.
- [ ] Update handoff FINAL with freeze commit/workflows.

Commit family: `docs(reputation): freeze participation accounting subsystem`

---

## Final Definition of Done

1. DB policy is runtime-authoritative and admin-manageable.
2. Every rule explicitly records dimension and convertibility; active ≠ convertible.
3. Historical transactions snapshot economic eligibility.
4. Source event cannot duplicate reward; bootstrap activity is bounded against farming.
5. Manager/inspector trust rewards are once per user+role+governance-level, with role and level independently eligible.
6. Conversion loses no points/remainder and is concurrent/idempotent.
7. Only transactions explicitly convertible at award time can activate Dim through MonetaryService.
8. Bootstrap activity catalogue and outcome catalogue are both explicit and admin-manageable.
9. Historical migration/compatibility is explicit and audit-friendly.
10. Admin/user UI is unambiguous and focused + Full + Responsive validation and UAT are recorded.

## Continuation Protocol

In every new chat: verify branch/head/CI; read audit, this plan and `docs/REPUTATION_PARTICIPATION_POINTS_HANDOFF.fa.md`; treat handoff product decisions as authoritative; continue from first open step; never merge to main without explicit user approval.
