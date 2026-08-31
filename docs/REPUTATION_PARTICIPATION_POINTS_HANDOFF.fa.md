# Handoff — Reputation / Participation Points Hardening

**Branch:** `agent/economic-system-current-integration`  
**Do not merge to:** `main`  
**Spec:** `docs/REPUTATION_PARTICIPATION_POINTS_AUDIT.fa.md`  
**Implementation plan:** `docs/superpowers/plans/2026-08-31-reputation-participation-points-hardening.md`

## هدف

بستن subsystem امتیاز در ۶ تسک R1 تا R6 به‌نحوی که فقط Participation قابل اثبات قابلیت اتصال اقتصادی Points → Dim → Active را داشته باشد و Reputation/Trust/Expertise به‌صورت مستقل باقی بمانند.

## وضعیت فعلی

### Baseline audit
- Audit baseline: `e93cc1da94358b576e89c48e3fdd50e8fbf9651b`
- Audit document commit: `d7e7e3d48d3f1480520383f57f618dd8260fb89d`
- Last known pre-R1 validation on baseline: Full Validation #1939 green, Responsive #305 green.

### R1 — Rule Control Plane & Runtime Source of Truth
Status: **IN PROGRESS — RED tests written**

RED test checkpoint before plan docs:
`2c8071126060e3aa59e6417568f14d1ea07ec9ac`

Contracts already added in `tests/Unit/ReputationServiceTest.php`:
- config-only daily cap test now uses a real configured weight and must award exactly to cap.
- DB `daily_cap` must override conflicting config cap.
- an explicitly inactive DB rule must not fall back to config.
- opening admin Reputation page must not overwrite saved DB weight/cap/active/description.

Expected production defects causing RED:
1. `ReputationService::applyAction()` queries only active DB rules, so inactive rows incorrectly fall back to config.
2. daily caps are still read from `config('reputation.daily_caps')`, ignoring DB `daily_cap`.
3. `Admin\ReputationController::seedFromConfig()` uses `updateOrCreate`, overwriting admin-authored DB values on page load.

### Next exact execution step

1. Verify CI/focused test failure for the RED checkpoint or latest descendant commit.
2. Modify only `ReputationService.php` and `Admin/ReputationController.php` minimally to satisfy the four R1 contracts.
3. Re-run `tests/Unit/ReputationServiceTest.php`.
4. Run relevant broader tests / Full Validation.
5. Mark R1 GREEN here with exact commit and workflow numbers before beginning R2.

## Six-task ledger

- [ ] R1 — Rule Control Plane & Runtime Source of Truth
- [ ] R2 — Participation vs Reputation Domain Separation
- [ ] R3 — Event Idempotency, Anti-Farming & Correct Recipients
- [ ] R4 — Financial Conversion Ledger & Consumption Safety
- [ ] R5 — Outcome-Based Participation Catalogue & Runtime Wiring
- [ ] R6 — Migration, Transparency UI, Admin/UAT & Final Constitution

## P0 findings that must not be forgotten

- Admin page currently overwrites DB rule values from config.
- DB daily cap currently is not runtime-effective.
- disabled DB rule can fall back to config because service only queries active rows.
- point event idempotency is absent.
- like/upvote rewards the reactor and can be farmed by toggling.
- raw post/comment creation is repeatable and economically unsafe if convertible.
- conversion can lose partial transaction points.
- ratio remainder can be consumed without Gol equivalent.
- negative reputation does not necessarily constrain cashable positive transactions.
- `invite_member` has a call-site but no config bootstrap rule.
- several poll/election/profile/penalty rules are config-only and not runtime-wired.
- legacy Stock bid rewards should not define future canonical participation economics.

## Constitutional/economic invariants

- Participation conversion must never mint new Bahar.
- Conversion must only activate the member's own existing Dim via `MonetaryService::activateDim()`.
- Only explicitly convertible Participation may enter the conversion pool.
- Reliability/Expertise/Civic Trust must not become cashable merely because they contribute to an aggregate reputation score.
- Event retries/toggles/duplicates must not duplicate economic rewards.
- Historical audit trail must be preserved through migrations and reversals.

## New-chat continuation prompt

«ادامه سخت‌سازی سیستم Reputation/Participation Points را روی branch `agent/economic-system-current-integration` انجام بده. ابتدا `docs/REPUTATION_PARTICIPATION_POINTS_AUDIT.fa.md`، `docs/superpowers/plans/2026-08-31-reputation-participation-points-hardening.md` و `docs/REPUTATION_PARTICIPATION_POINTS_HANDOFF.fa.md` را از branch فعلی بخوان، head و CI روز را بررسی کن و دقیقاً از اولین Task باز ادامه بده. هیچ merge به main انجام نده و TDD/RED→GREEN را حفظ کن.»
