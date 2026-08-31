# Reputation & Participation Points Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** تبدیل سیستم فعلی Reputation/Points به یک Participation Accounting System قابل‌اعتماد، ضد سوءاستفاده، audit-friendly و امن برای اتصال Points → Dim → Active Bahar.

**Architecture:** هسته فعلی `ReputationService`, `UserPoint`, `UserPointTransaction`, `ReputationRule` حفظ می‌شود و مرحله‌به‌مرحله سخت‌سازی می‌گردد. DB برای ruleهای runtime منبع حقیقت می‌شود؛ config فقط bootstrap/default است. امتیاز قابل تبدیل از reputation/اعتماد/تخصص تفکیک می‌شود، eventها idempotent می‌شوند و conversion تنها Participation قابل‌مصرف را از طریق `MonetaryService::activateDim()` فعال می‌کند؛ هیچ mint مستقیم یا balance mutation جدید مجاز نیست.

**Tech Stack:** Laravel/PHP, Eloquent, PHPUnit, MySQL/PostgreSQL-compatible migrations, Najm Bahar MonetaryService/MonetaryPolicyService.

**Spec:** `docs/REPUTATION_PARTICIPATION_POINTS_AUDIT.fa.md`

## Global Constraints

- Branch اجرای برنامه: `agent/economic-system-current-integration`.
- هیچ merge یا تغییر مستقیمی روی `main` انجام نشود.
- TDD اجباری: هر behavior جدید/اصلاحی ابتدا RED، سپس GREEN، سپس refactor.
- هیچ activity جدید قبل از بسته‌شدن P0های R1 تا R4 به اقتصاد قابل تبدیل وصل نشود.
- تبدیل Points حق خلق Bahar ندارد؛ فقط `Dim → Active` از Dim همان عضو مجاز است.
- عملیات اقتصادی باید integer Gol باشد؛ float در مسیر conversion ممنوع است.
- تغییرات UI/UX خارج از نیاز مستقیم subsystem امتیاز انجام نشود.
- history و audit داده‌های قبلی بی‌دلیل حذف/بازنویسی نشوند؛ migration/backfill باید صریح و قابل بازگشت باشد.
- هر release با focused tests + Full Validation بسته شود.

---

### Task R1: Rule Control Plane & Runtime Source of Truth

**Files:**
- Modify: `tests/Unit/ReputationServiceTest.php`
- Modify: `app/Services/ReputationService.php`
- Modify: `app/Http/Controllers/Admin/ReputationController.php`
- Test: `tests/Unit/ReputationServiceTest.php`

**Interfaces:**
- Consumes: `ReputationRule(key, weight, daily_cap, active)` و config defaults.
- Produces: `ReputationService::applyAction()` با DB-first semantics؛ existing DB rows هرگز در page load overwrite نمی‌شوند؛ `daily_cap` DB در runtime authoritative است.

- [x] **Step 1: Write RED tests for real config caps and DB authority**

Tests must prove: config-only action really awards up to cap; DB cap overrides config cap; inactive DB rule does not fall back to config; opening admin page preserves saved values.

- [ ] **Step 2: Run RED tests and confirm expected failures**

Run:
```bash
php artisan test tests/Unit/ReputationServiceTest.php
```
Expected: DB cap / inactive rule / admin persistence contracts fail against current production code.

- [ ] **Step 3: Implement DB-first rule resolution**

`ReputationService::applyAction()` must resolve an existing DB row regardless of active state. If row exists and `active=false`, return null. If active, use both `weight` and `daily_cap` from row. Only if row is absent may config `weights` and `daily_caps` be used.

- [ ] **Step 4: Make admin bootstrap insert-only**

Replace page-load `updateOrCreate` semantics with create-missing-only semantics so existing `weight`, `daily_cap`, `active`, `description`, and module values survive page visits.

- [ ] **Step 5: Run focused tests and commit GREEN checkpoint**

Run:
```bash
php artisan test tests/Unit/ReputationServiceTest.php
```
Expected: PASS.

Commit message:
```text
fix(reputation): make database rules authoritative
```

---

### Task R2: Participation vs Reputation Domain Separation

**Files:**
- Create migration: `database/migrations/*_add_point_dimension_and_convertibility_to_reputation.php`
- Modify: `app/Models/ReputationRule.php`
- Modify: `app/Models/UserPointTransaction.php`
- Modify: `app/Services/ReputationService.php`
- Modify: `config/reputation.php`
- Modify: `app/Http/Controllers/Admin/ReputationController.php`
- Modify: `resources/views/admin/system-settings/reputation/index.blade.php`
- Create/modify tests under `tests/Unit/` and `tests/Feature/Reputation/`.

**Interfaces:**
- Produces dimensions: `participation`, `reliability`, `expertise`, `civic_trust`.
- Produces rule property `convertible` (true only where economic activation is intended).
- `UserPointTransaction` records dimension and convertibility snapshot so later rule edits do not rewrite history.

- [ ] **Step 1: RED tests for dimension snapshots and convertible-only accounting**
- [ ] **Step 2: Add schema and model casts/fillables**
- [ ] **Step 3: Seed explicit dimensions for all current rules**
- [ ] **Step 4: Mark economically unsuitable current rules non-convertible**

At minimum: `elected_manager`, `elected_inspector`, raw bid/status popularity signals and penalties must not become cashable participation merely because they change total reputation.

- [ ] **Step 5: Expose dimension/convertible controls readably in admin UI**
- [ ] **Step 6: Run focused tests + migration tests + Full Validation; commit**

Commit message:
```text
feat(reputation): separate participation from trust dimensions
```

---

### Task R3: Event Idempotency, Anti-Farming & Correct Recipients

**Files:**
- Create migration adding stable `event_key`/idempotency support to point transactions.
- Modify: `app/Services/ReputationService.php`
- Modify: `app/Http/Controllers/Group/ReactionController.php`
- Modify: `app/Http/Controllers/Group/BlogController.php`
- Modify: `app/Http/Controllers/Group/CommentController.php`
- Review/modify legacy Stock reward call-sites only as needed.
- Add feature tests for group reactions/content.

**Interfaces:**
- `applyAction(..., eventKey: ?string)` or equivalent stable event identity.
- Same economic event can create at most one point transaction per intended recipient/rule.

- [ ] **Step 1: RED tests for duplicate post/comment events and reaction toggle farming**
- [ ] **Step 2: RED test proving recipient semantics explicitly**
- [ ] **Step 3: Add unique event identity and concurrency-safe duplicate handling**
- [ ] **Step 4: Remove raw repeatable reaction farming**
- [ ] **Step 5: Add conservative caps/quality gates to raw creation rewards or make them non-convertible pending outcome validation**
- [ ] **Step 6: Verify no duplicate reward on retry, double-submit, toggle off/on, or duplicated worker execution; commit**

Commit message:
```text
fix(reputation): make participation awards idempotent
```

---

### Task R4: Financial Conversion Ledger & Consumption Safety

**Files:**
- Create migration/model for point consumption ledger, e.g. `PointConsumption` / `point_consumptions`.
- Modify: `app/Http/Controllers/ReputationConversionController.php`
- Modify: `app/Models/UserPointTransaction.php`
- Review: `app/Modules/NajmBahar/Services/MonetaryService.php` without changing constitutional money rules.
- Add feature tests for conversion.

**Interfaces:**
- Available cashable points = unconsumed positive **convertible participation** minus applicable participation reversals/adjustments according to explicit policy.
- Consumption is exact; partial source transactions remain partially available.
- Only whole convertible units activate Gol unless policy explicitly supports a deterministic remainder ledger.

- [ ] **Step 1: RED test for partial transaction consumption**
- [ ] **Step 2: RED test for ratio remainder preservation**
- [ ] **Step 3: RED test for duplicate/concurrent conversion safety**
- [ ] **Step 4: RED test proving non-convertible reputation cannot activate Dim**
- [ ] **Step 5: Implement consumption ledger and exact available-points query**
- [ ] **Step 6: Keep activation exclusively through `MonetaryService::activateDim()` with policy/version metadata and stable idempotency key**
- [ ] **Step 7: Run economic invariant/feature suite + Full Validation; commit**

Commit message:
```text
fix(reputation): make point conversion lossless and auditable
```

---

### Task R5: Outcome-Based Participation Catalogue & Runtime Wiring

**Files:**
- Modify `config/reputation.php` bootstrap catalogue.
- Modify admin labels/grouping.
- Wire domain events only where a real, auditable outcome exists in Projects/Governance/Secretariat/Membership.
- Add focused tests beside each domain integration.

**Interfaces:**
- Every new convertible action must specify: recipient, source domain, stable event key, award moment, cap/cooldown, reversal rule, and evidence/reference.

- [ ] **Step 1: Classify/retire misleading current rules**

Examples: candidate-based election rule incompatible with current no-candidate election model; raw `bid_placed` must not remain convertible participation.

- [ ] **Step 2: Make `invite_member` an explicit, bounded, verified participation rule**

Reward only after the referred membership condition defined by current onboarding policy is satisfied; stable invitation reference; once only.

- [ ] **Step 3: Add first outcome-based set**

Preferred first set where existing domains can prove completion: accepted/fulfilled action item, on-time public contribution obligation, verified project milestone/report, accepted specialist review, approved documentation/secretariat follow-up.

- [ ] **Step 4: Do not award merely for wealth, money transfer, bid amount, login, raw popularity or election victory**
- [ ] **Step 5: Add abuse tests and caps per new action; commit**

Commit message:
```text
feat(reputation): reward verified participation outcomes
```

---

### Task R6: Migration, Transparency UI, Admin/UAT & Final Constitution

**Files:**
- Create/update backfill command/migration for historical point rows.
- Modify wallet/user reputation presentation as minimally required.
- Modify admin reputation page for final dimensions/status/audit visibility.
- Create final docs/status matrix under `docs/`.
- Expand test suite and CI contract coverage.

**Interfaces:**
- User can distinguish total reputation/trust from currently convertible participation.
- Admin can see active rules, dimension, convertibility, weight, cap and source without page-load mutation.
- Historical data receives explicit legacy classification; no silent conversion assumptions.

- [ ] **Step 1: Define deterministic legacy backfill policy and test it**
- [ ] **Step 2: Backfill historical rows without erasing original action/source/reference metadata**
- [ ] **Step 3: Show user `Participation قابل تبدیل`, `مصرف‌شده`, and other reputation dimensions distinctly**
- [ ] **Step 4: Add immutable/auditable transaction and consumption views needed for support/admin**
- [ ] **Step 5: Run full invariant suite, Full Validation, Responsive Validation and manual UAT scenarios**
- [ ] **Step 6: Update `docs/REPUTATION_PARTICIPATION_POINTS_HANDOFF.fa.md` to FINAL and record exact freeze commit/workflows**

Commit message:
```text
docs(reputation): freeze participation accounting subsystem
```

---

## Final Definition of Done

Subsystem فقط زمانی «جمع‌شده» اعلام می‌شود که:

1. DB rule settings واقعاً runtime-authoritative و پایدار باشند.
2. Reputation/Trust از convertible Participation جدا باشد.
3. یک source event نتواند دوبار reward مالی بسازد.
4. reaction/content farming و retry duplication نتواند point قابل تبدیل تولید کند.
5. conversion هیچ point/remainder را گم نکند و concurrent/idempotent باشد.
6. فقط convertible participation بتواند Dim همان کاربر را از طریق MonetaryService فعال کند.
7. activity catalogue بر outcomeهای قابل اثبات تکیه کند، نه صرف click/popularity/wealth.
8. historical data migration صریح و audit-friendly باشد.
9. admin و user UI وضعیت واقعی را بدون ambiguity نشان دهند.
10. focused tests + Full Validation + Responsive Validation سبز و UAT نهایی ثبت شده باشد.

## Continuation Protocol

در شروع هر چت جدید:

1. branch را روی `agent/economic-system-current-integration` بررسی کن.
2. `docs/REPUTATION_PARTICIPATION_POINTS_AUDIT.fa.md` را به‌عنوان spec بخوان.
3. این plan را بخوان.
4. `docs/REPUTATION_PARTICIPATION_POINTS_HANDOFF.fa.md` را بخوان و از اولین checkbox/Task باز ادامه بده.
5. آخرین commit و CI را از GitHub verify کن؛ به حافظه گفتگو اتکا نکن.
6. هیچ merge به `main` انجام نده.
