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

### Task R4: Financial Conversion Ledger & Consumption Safety — COMPLETE

Final verified financial checkpoint: `bc8883093767ccd60980f5395fc2687e125bf0fa`  
Full Validation #2006: success  
Responsive #372: success

Established contracts:
- [x] exact consumption ledger via `user_point_consumptions`; partial source rows are not falsely marked fully cashed.
- [x] ratio remainder is preserved exactly; conversion consumes only whole-ratio points.
- [x] eligible sources require positive + `convertible=true` + `dimension=participation` snapshots.
- [x] sequential same-key retry does not double-consume or double-activate.
- [x] canonical conversion identity is user-scoped; same client key across users cannot collide.
- [x] parent `user_point_conversions` identity plus unique `(user_id, request_key)` and row locking establish one atomic owner for a same-user/same-key request; no synthetic parallel stress benchmark was claimed.
- [x] child consumptions belong to the parent conversion operation and applied retries replay the completed identity.
- [x] historical `is_cashed=true` positive convertible Participation rows are conservatively excluded from new conversion and counted as historical cashed; old lossy partial remainder is never guessed or re-credited.
- [x] behavioral eligibility suite proves non-convertible and non-Participation snapshots cannot enter conversion.
- [x] browser/UI conversion submits a stable form request identity, accepted by the controller alongside the HTTP header contract, without redesigning wallet/dashboard UI.
- [x] activation remains exclusively through `MonetaryService::activateDim()`; conversion never mints Bahar.
- [x] approved penalty semantics: only an explicit negative transaction that is both `dimension=participation` and `convertible=true` reduces future conversion capacity. Reliability/Civic Trust/Expertise penalties do not silently claw back Participation economic entitlement. Reversal does not claw back already-applied Active money.
- [x] final conversion invariant family is covered by `ParticipationConversionLedgerContractTest`, `ParticipationConversionBehaviorTest`, `ParticipationConversionAtomicIdentityTest`, `ParticipationConversionLegacyCompatibilityTest`, `ParticipationConversionEligibilityTest`, `ParticipationConversionUiIdempotencyContractTest`, and `ParticipationConversionPenaltySemanticsTest`; Full Project PHPUnit passed at #2006.

Key R4 checkpoints:
- lossless ledger: `7bbab2168bf6de4d55c5ba3ccfcf30cc2beb5510` — Full #1984 / Responsive #350.
- retry exactness: `93cf58b74154ea636c80324f66625a018eb7e114` — Full #1986 / Responsive #352.
- cross-user isolation: `f6f4ba997fcbd548edd673226eca1efb7452cd3f` — Full #1988 / Responsive #354.
- atomic request identity RED `c6e867c0c7ab3b478782a7578e77322ee2f3c585`; final atomic production through `dc8828ba481132d3ca2648ac0f73c154ce8e41cf` — Full #1995 / Responsive #361.
- legacy cashing compatibility production `5118a6d6fb1deaae59db72d8a667091934141ae0`, fixture correction `23db91a443b7b0c70e1a9bde4431b97f9fc8173e` — Full #1998 / Responsive #364.
- behavioral eligibility characterization `fc85652179522a67334bf5d700aefbc24660ed86`.
- UI idempotency production family `0aa44423428ea4e872dea03fa93e16bb664e0023`, `3e1009ca6d9e031c73798bb0c8db479cab54c1ae`, `c9ddf94a174f884f6b60cd5c2beb0b5a19ba723c`, `6ad684210f4c6939be28568021c7a75ff1a2bed8`.
- penalty semantics RED `79dab3a16900eecc082add93c61755a4aef49a99`; production `bc8883093767ccd60980f5395fc2687e125bf0fa` — Full #2006 / Responsive #372.

R4 is no longer a launch blocker by itself. Launch decision must now evaluate remaining R3 catalogue/anti-farming debt and R5/R6 scope rather than reopening conversion-ledger semantics without new evidence.

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
