# Reputation & Participation Points Hardening Implementation Plan

**Goal:** تبدیل سیستم فعلی Reputation/Points به یک Participation Accounting System قابل‌اعتماد، ضد سوءاستفاده، audit-friendly و امن برای اتصال Points → Dim → Active Bahar، همراه با Control Plane کامل ادمین برای سیاست‌های امتیازدهی.

**Architecture:** هسته فعلی `ReputationService`, `UserPoint`, `UserPointTransaction`, `ReputationRule` حفظ می‌شود. DB منبع حقیقت runtime است و config فقط bootstrap/default. هر Rule دارای dimension و convertible مستقل است و transaction snapshot سیاست زمان صدور را حفظ می‌کند. در دوره bootstrap، فعالیت‌های واقعی گروهی می‌توانند حتی بدون outcome نهایی قابل‌نقد باشند، اما باید محدود، idempotent و قابل خاموش/تنظیم‌کردن از پنل باشند. تبدیل فقط از طریق `MonetaryService::activateDim()` انجام می‌شود و Bahar جدید خلق نمی‌کند.

**Tech Stack:** Laravel/PHP, Eloquent, PHPUnit, MySQL/PostgreSQL-compatible migrations, Najm Bahar MonetaryService/MonetaryPolicyService.

**Spec:** `docs/REPUTATION_PARTICIPATION_POINTS_AUDIT.fa.md`  
**Live handoff / authoritative product decisions:** `docs/REPUTATION_PARTICIPATION_POINTS_HANDOFF.fa.md`

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

### Task R2: Policy Dimensions, Convertibility & Admin Control Plane

**Files:**
- Create migration adding rule/transaction policy fields.
- Modify `app/Models/ReputationRule.php`, `app/Models/UserPointTransaction.php`, `app/Services/ReputationService.php`, `config/reputation.php`.
- Modify `app/Http/Controllers/Admin/ReputationController.php` and `resources/views/admin/system-settings/reputation/index.blade.php`.
- Add focused Unit/Feature tests.

**Interfaces:**
- Dimensions initially: `participation`, `reliability`, `expertise`, `civic_trust`.
- Rule properties: `active`, `weight`, `dimension`, `convertible`, `daily_cap`, and repeat-policy metadata appropriate to the rule.
- Transaction snapshots dimension + convertible + relevant policy version/metadata.
- Admin can manage policy without deploy; edits affect future awards, not historical transaction eligibility snapshots.

- [ ] **Step 1: RED — transaction policy snapshot**
Prove a transaction records dimension and convertible status from the rule at award time and later rule edits do not mutate history.

- [ ] **Step 2: RED — full admin policy persistence**
Prove admin can persist active, weight, dimension, convertible and daily_cap independently and page reload preserves them.

- [ ] **Step 3: RED — active and convertible independence**
An active/non-convertible rule may still award social reputation; a disabled rule awards nothing.

- [ ] **Step 4: RED — election trust eligibility identity**
Prove economic election reward identity is `user + role + governance level`: same manager/same level only once; inspector at same level independently eligible; same role at another level independently eligible.

- [ ] **Step 5: GREEN — schema/models/runtime snapshots**
Add minimal schema and model/runtime support satisfying Steps 1–4.

- [ ] **Step 6: GREEN — admin control plane**
Expose active, weight, dimension, convertible, daily cap and rule repeat policy in a clear admin UI; DB remains authoritative.

- [ ] **Step 7: bootstrap catalogue classification**
Current real group activity such as post/comment/poll/reaction may be convertible during bootstrap when enabled by policy. Assign conservative defaults/caps; do not hard-code a permanent economic judgement into service code.

- [ ] **Step 8: focused tests + Full/Responsive Validation; update handoff**

Commit family: `feat(reputation): add managed incentive policy dimensions`

---

### Task R3: Event Idempotency, Anti-Farming & Correct Recipients

**Files:** point transaction event identity migration; `ReputationService`; Group reaction/blog/comment/poll call-sites; focused tests.

- [ ] RED duplicate/retry tests for post/comment/poll events.
- [ ] RED reaction toggle farming tests.
- [ ] Define recipient semantics per Rule explicitly; do not assume every reaction rewards the same party.
- [ ] Add stable event key and concurrency-safe duplicate handling.
- [ ] Add caps/cooldowns for bootstrap activities so enabled convertible click/activity rewards remain bounded.
- [ ] Verify toggle/retry/double-submit/worker duplication cannot duplicate economic reward.

Commit family: `fix(reputation): make participation awards idempotent`

---

### Task R4: Financial Conversion Ledger & Consumption Safety

**Files:** consumption ledger migration/model; `ReputationConversionController`; `UserPointTransaction`; Najm Bahar integration tests.

- [ ] RED partial transaction consumption.
- [ ] RED ratio remainder preservation.
- [ ] RED duplicate/concurrent conversion safety.
- [ ] RED non-convertible transaction exclusion.
- [ ] Implement exact consumption ledger and available-convertible-points query from transaction snapshots.
- [ ] Activation exclusively through `MonetaryService::activateDim()` with policy/version metadata and idempotency.
- [ ] Economic invariant suite + Full Validation.

Commit family: `fix(reputation): make point conversion lossless and auditable`

---

### Task R5: Bootstrap + Outcome Participation Catalogue & Runtime Wiring

**Principle:** EarthCoop در دوره bootstrap هم فعالیت اجتماعی را تشویق می‌کند و هم به‌تدریج rewardهای outcome-based اضافه می‌کند. این دو دسته در catalogue مشخص و از پنل قابل مدیریت‌اند.

- [ ] Wire current legitimate group activities that already have real domain events: post, comment, poll participation/creation, reactions and other verified group actions where implementation supports a stable event identity.
- [ ] Make `invite_member` explicit, bounded, verified and once-only after the membership condition is satisfied.
- [ ] Wire election reward to actual systemic-election outcome: `elected_manager` and `elected_inspector` can be convertible, with once-per-user-role-level eligibility. Re-election same role+level adds no second cashable reward; other role or level is independent.
- [ ] Add outcome-based set where current domains can prove completion: fulfilled action item, on-time public contribution obligation, verified milestone/report, accepted specialist review, approved documentation/secretariat follow-up.
- [ ] Wealth amount, money transfer amount, raw bid amount and login must not automatically create scalable cashable points. Any future exception must be an explicit admin policy/rule with anti-abuse semantics.
- [ ] Every new convertible action specifies recipient, source, event identity, award moment, cap/cooldown/repeat policy, reversal policy and evidence/reference.

Commit family: `feat(reputation): expand managed participation catalogue`

---

### Task R6: Migration, Transparency UI, Admin/UAT & Final Constitution

- [ ] Define/test deterministic legacy backfill policy.
- [ ] Backfill historical rows without erasing original action/source/reference metadata.
- [ ] User UI distinguishes total/social reputation, convertible participation, consumed and remaining convertible points.
- [ ] Admin UI exposes complete current policy and audit trail.
- [ ] Add immutable transaction/consumption views needed for support/admin.
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
9. Historical migration is explicit/audit-friendly.
10. Admin/user UI is unambiguous and focused + Full + Responsive validation and UAT are recorded.

## Continuation Protocol

In every new chat: verify branch/head/CI; read audit, this plan and `docs/REPUTATION_PARTICIPATION_POINTS_HANDOFF.fa.md`; treat the handoff product-decision section as authoritative; continue from first open step; never merge to main without explicit user approval.
