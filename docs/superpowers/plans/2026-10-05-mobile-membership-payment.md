# Dim and Active Membership Payment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking. Native inline execution is recommended; implementation begins after the user reviews this plan and selects the method.

**Goal:** Pay membership safely from either available main-account Dim or explicitly selected owned Active funds, preserving EarthCoop monetary rules and recoverable results.

**Architecture:** Add a backward-compatible consent/capability extension to the existing membership API and use its shared financial services. The native page adds source selection, explicit confirmation and an in-memory same-intent controller; financial POST disables transport automatic retry while existing callers keep their defaults. Accounting and evidence regressions are fixed before shipping any payment control.

**Tech Stack:** Existing Laravel/PHP8.2/MySQL8 testing workflow; Flutter3.47.5/Dart/Dio; no new package or database migration.

**Spec:** docs/superpowers/specs/2026-10-05-mobile-membership-payment-design.md — approved by Saeed after both Dim and Active were explicitly included.

## خلاصهٔ اجرا برای سعید

شش تحویل وابسته داریم:۱) صحت ثبت و تشخیص پرداخت؛۲) قرارداد امن سرور و منابع مجاز؛۳) ارسال و پاسخ موبایل؛۴) مدیریت نتیجهٔ نامعلوم؛۵) انتخاب منبع و تأیید در صفحه؛۶) آزمون نهایی، بازبینی مستقل و یک خروجی آزمایشی. روش پیشنهادی این است که همان عامل اصلی همهٔ مراحل را انجام دهد و یک بازبین مستقل در پایان بررسی کند؛ این روش برای رابط‌های وابسته به هم هزینهٔ کمتری دارد. پرداخت واقعی، ادغام main و انتشار هاست در این برنامه انجام نمی‌شوند. فعال‌شدن پرداخت موبایل به قرارداد جدید سرور وابسته است.

## Global Constraints

- Both Dim and Active are mandatory. Available Dim main only; Active one eligible owned main/subaccount with reservations excluded; no mixed bucket or multi-account aggregation.
- Server policy controls fee/split/version and server anniversary controls period. Defaults12/6/3/3 Bahar are not hard-coded client charges; integer Gol throughout.
- Preserve committed Dim, financial ownership, system treasury transfer permissions, participation once and old API/web behavior.
- Full expected consent binds fee/year/breakdown/policy/account; capability payment_contract_version=1 is required for new UI payment.
- Strict main selection in a new Active intent does not silently fall back; legacy requests retain automatic main/oldest-eligible behavior.
- No financial offline queue or POST on restart, no live charge, no new package/migration, no main merge/FTP/provider activation. Signed+17 remains historical; no promise of host deployment.
- Explicit same-intent retry keeps body/key and rechecks identity/bootstrap;23-hour monotonic lifetime from first submit. Native retry flag defaults true for existing requests, false for membership POST.
- Initial focused RED must reproduce the behavior rather than fail due to an invalid fixture. One final broad gate and one whole-change review after the source stabilizes; no broad CI for docs/style only.

## Review Focus

- Funds spread across accounts can make aggregate Active look sufficient without a single eligible source; task2/5 must keep that distinct.
- A legacy subaccount with no mirror must appear without GET creating/syncing money rows; task2 must match actual pay's reservation/balance authority.
- A policy change after an original payment must not invalidate durable paid evidence or a completed idempotent replay; task1/2 pin this.
- A page dismissal or fresh GET saying unpaid cannot prove a submitted POST did not commit; task4 freezes the ambiguous intent and restart never auto-submits.
- A late response after known success or session expiry must not replace success with refresh failure or repopulate money; task4/5 pin it.

---

### Task1: Complete paid evidence and preserve committed balances

**Files:** Modify app/Modules/NajmBahar/Services/Api/NajmBaharMembershipFeeApplicationService.php, app/Services/MembershipFeeStatusService.php, app/Modules/NajmBahar/Services/TransactionService.php. Create app/Services/MembershipFeePaymentEvidence.php and tests/Feature/NajmBahar/MembershipFeeCompletionEvidenceTest.php. Extend tests/Feature/NajmBahar/TransactionReservationProtectionTest.php only for uncovered committed-total cases.

**Interfaces:** Evidence service produces hasCompletePaymentEvidence(int $userId, int $paymentYear): bool. Distribution metadata adds expected_breakdown_gol (three named integer Gol parts) and membership_fee_total_gol. MembershipFeeStatusService consumes that proof before its unchanged legacy fallback. Proof uses immutable recorded values, completed actual transfers, exact positive-part set/amounts and matching user/year; no new table.

- [ ] **Write focused failing tests**: `test_zero_burn_split_is_paid_once` uses fee1200/split900,300,0 and asserts has_paid=true, two positive membership transfers, balance debit1200 and one participation award; repeat causes no new effects. `test_recorded_split_remains_paid_after_policy_change` changes to1234/600,300,334 after payment and asserts old period remains paid. `test_incomplete_or_inconsistent_evidence_is_not_paid` removes/changes one required part and expects false. `test_membership_debit_preserves_committed_dim_total` uses active1500, availableDim100, committedDim200,total1800, fee1200 and expects stored total600, active300, available100, committed200. Add the analogous receiving-main/system committed-total case to the generic transfer tests without duplicating existing reservation settlement/refund coverage.
- [ ] **Run behavioral RED:** `vendor/bin/phpunit -c phpunit.xml.dist tests/Feature/NajmBahar/MembershipFeeCompletionEvidenceTest.php tests/Feature/NajmBahar/TransactionReservationProtectionTest.php` in the existing MySQL8 fixture runtime. Expected: new completion/total assertions expose actual old behavior; existing reservation tests pass. A compilation/fixture error must be corrected before treating a run as RED.
- [ ] **Implement evidence and atomic completion:** add server-generated evidence to each positive distribution, check completion before participation/commit and roll back incomplete new effects. Keep recognition of old canonical/legacy/operations-only evidence from completed records without the new proof metadata; malformed new proof records must not fall through to the legacy set recognizer. Pin this with a three-part malformed-proof test. If mixed pre-existing domain-idempotency records cannot safely prove completion, use the spec's stop-and-revise rule, not a replacement charge. Correct only reproduced TransactionService totals with the canonical AccountBalanceService sum while retaining internal-transfer/mirror semantics.
- [ ] **Run focused GREEN:** the same command plus existing MembershipFeePaymentTest, MembershipFeePolicyIntegrityTest, ActiveReservationCommittedDimInvariantTest and TransactionIdempotencyHardeningTest must have zero failures. Record counts and any existing warnings separately.
- [ ] **Commit** product/tests together: `fix(bahar): preserve membership completion and committed money invariants`. Do not build an APK for this server-only task.

### Task2: Consent-checked API and trustworthy source projection

**Files:** Modify app/Http/Controllers/API/V1/NajmBaharMembershipFeeController.php and the existing membership application service. Create app/Modules/NajmBahar/Services/Api/NajmBaharMembershipPaymentSources.php and tests/Feature/Api/V1/NajmBaharMembershipConsentContractTest.php. Add .github/workflows/mobile-membership-payment.yml using the existing M3 PHP/MySQL preparation; keep unrelated workflows unchanged.

**Interfaces:** `NajmBaharMembershipPaymentSources::forAccount(Account $main, int $fee): array` returns source rows from the spec. `pay(User $user,string $paymentSource,?int $subAccountId=null,?array $expected=null): array` preserves old three-argument callers; new expected source is strict. GET adds payment_contract_version1/payment_sources; new POST response adds actual payment_account_number. Expected object has payment_year,fee_gol,breakdown,policy_version_id (present nullable),account_number; all-or-none, no extra keys.

- [ ] **Write failing boundary tests:** dim/main-active/subaccount-active consent success at1200/600/300/300; available Active1500 reserved400 cannot fund1200; aggregate two700 accounts has no individually eligible1200 source; a no-mirror owned enabled subaccount appears without Account/Transaction/Ledger row creation. Separate main-active intent with main500/sub2000 must reject without fallback; old no-expected request retains its existing fallback. Foreign/disabled source, incomplete expected/null required fields and unexpected nested keys reject with zero effects. Change fee to1234, period across anniversary, a part or policy version after consent and assert `membership_fee_terms_changed`409 and unchanged balances/ledger/points. Replay unchanged completed intent after policy change still replays; modified body same key conflicts.
- [ ] **Run RED** using only `tests/Feature/Api/V1/NajmBaharMembershipConsentContractTest.php` and the existing membership contract. Ensure missing additive fields and unbound consent are the actual failures, not invalid auth fixtures. Use established nativeSession fixtures and production API middleware.
- [ ] **Implement additive validation/projection/atomic snapshot:** compute one immutable terms snapshot under the main lock, use it for comparisons/effects/response, check current membership period before effects. New dim rejects a subaccount selector; new active null-ID means strict main, positive-ID means that owned enabled subaccount. GET projection performs no ensure/sync write. Source mismatch is a safe domain rejection, terms mismatch409. Keep old default behavior without expected. Include participation context and proof from task1.
- [ ] **Run GREEN** for new consent and old membership contracts, existing NajmBaharMobileJourneyTest and IdempotencyContractTest. Pin zero-effects on every rejected consent and equal replay response. The dedicated workflow runs focused suites first; full server suite is reserved for task6.
- [ ] **Commit:** `feat(api): bind membership payment consent to terms and owned source`. Retain logs; no server publication or main merge.

### Task3: Native wire types and deliberate financial transport

**Files:** Create apps/mobile/lib/features/najm_bahar/najm_bahar_membership_payment_dto.dart and its repository test. Modify najm_bahar_policy_dto.dart, najm_bahar_repository.dart, core/api/request_context.dart and api_client.dart. Extend apps/mobile/test/core/api/api_client_test.dart and retry_policy_test.dart for per-request retry behavior.

**Interfaces:** RequestContext adds `bool allowAutomaticRetry=true`. Existing client behavior stays default. MembershipSource exposes kind,subAccountId?,accountNumber,name,activeAvailableGol,dimAvailableGol,canPayActive,canPayDim. MembershipPaymentIntent has immutable source/bucket,expected snapshot/key. MembershipPaymentReceipt.fromJson validates hasPaid,period/fee/split/bucket/actual source. Repository `Future<MembershipPaymentReceipt> payMembership(MembershipPaymentIntent intent)` supplies expected and `RequestContext(idempotencyKey:intent.key,allowAutomaticRetry:false)` after existing session/bootstrap guards. Policy DTO retains nullable capability/empty immutable sources for old responses.

- [ ] **Write failing real-adapter tests:** old+17 fee fixture decodes read-only; version1 sources decode exact units/ownership selectors; malformed source or receipt fields fail. Both buckets emit the exact POST body and stable key;401 identity change clears through callers. Timeout/5xx with financial retry flag yields one POST and no retry delay; existing GET and default keyed POST retain old retry tests.
- [ ] **Run RED:** `flutter test --reporter expanded test/features/najm_bahar/najm_bahar_membership_payment_repository_test.dart` plus `test/core/api/api_client_test.dart test/core/api/retry_policy_test.dart`. Missing interface failure is recorded accurately.
- [ ] **Implement** strict DTOs and repository methods; catch paths recheck identity but do not mask a real payment outcome with temporary bootstrap. Apply retry flag at every ApiClient automatic-retry decision used by this request; do not globally change RetryPolicy. No automatic mutation retry or financial caching added.
- [ ] **Run GREEN:** new repository/core retry tests and existing wallet-policy/repository tests, then analyzer. No new dependency, precision conversion to double or formatter-only suite rerun.
- [ ] **Commit:** `feat(mobile): add guarded membership payment wire contract`.

### Task4: Single-intent payment state and ambiguous outcome recovery

**Files:** Create apps/mobile/lib/features/najm_bahar/najm_bahar_membership_payment_controller.dart and apps/mobile/test/features/najm_bahar/najm_bahar_membership_payment_controller_test.dart. Consume repository task3 and existing shared session invalidation callbacks.

**Interfaces:** `MembershipPaymentController` takes repository, sessionChanges?, onSessionInvalidated?, injectable `String Function() keyFactory`, injectable `Duration Function() elapsedSinceStart`. States enum loading,ready,confirming,submitting,confirmedPaid,definiteRejected,outcomeUnknown. Methods `Future<void> prepare()`, `void selectSource(MembershipSource source,String bucket)`, `void beginConfirmation()`, `Future<void> confirm()`, `Future<void> retrySameIntent()`, `Future<void> reconcile()`, `void invalidateSession()`, `dispose()`. Immutable intent remains private while pending/unknown; receipt and authoritative paid status are distinct outputs. Callback `Future<void> Function()? refreshFinancialViews` runs after known success without changing that success when refresh fails.

- [ ] **Write failing tests** with real repository adapter/completers: repeat confirm is one POST; timeout→unknown freezes choices; unpaid GET while POST pending does not allow new intent; explicit retry reuses exact body/key; paid same-period GET resolves obligation without constructing an own-request receipt; different period stays unknown; success→refresh failure retains known success; expired23-hour intent sends no POST; restart begins GET and never POST; bootstrap pause sends nothing;401/logout/identity/disposal reject pending publication.
- [ ] **Run RED:** `flutter test --reporter expanded test/features/najm_bahar/najm_bahar_membership_payment_controller_test.dart`; distinguish unsupported interface versus actual behavioral assertions.
- [ ] **Implement** state transitions, shared pending future, generation guards, monotonic23-hour lifetime, production secure random key through dart:math Random.secure (existing SDK, no package), explicit rechecked retry and reconciliation. A malformed201 is unknown. Already-paid invokes GET. Explicit definite server rejection unlocks fresh prepare; uncertain result does not.
- [ ] **Run GREEN** controller/repository tests and existing financial-session wallet/policy tests. Compare request counts, exact keys/bodies and state after every delayed completion.
- [ ] **Commit:** `feat(mobile): reconcile single membership intent without duplicate charge`.

### Task5: Both source choices and confirmation in the existing page

**Files:** Create apps/mobile/lib/features/najm_bahar/najm_bahar_membership_payment_section.dart and apps/mobile/test/features/najm_bahar/najm_bahar_membership_payment_screen_test.dart. Modify najm_bahar_screen.dart and app/runtime/production_runtime.dart. Existing wallet-only optional injection must continue to work.

**Interfaces:** `MembershipPaymentSection({required MembershipPaymentController controller})`. Runtime creates/disposes the payment controller with wallet/policy, captures the same repository/session scope and connects latched invalidation; refreshFinancialViews calls existing refresh methods after success. NajmBaharScreen accepts optional paymentController. Full three-controller invalidation has no cycle or publication after dispose.

- [ ] **Write failing widgets:** missing capability stays read-only; both Dim and Active options present; own-subaccount selection is displayed in confirmation; disabled/insufficient sources cannot confirm; main/sub single-source requirement is not inferred from aggregate; no POST on opening/canceling a confirmation; exact configured1234 Gol/year/split/source render; large text/RTL can reach confirmation/recovery controls without overflow; unknown result hides new payment choices and exposes result check/same-intent retry; known paid status hides payment control.
- [ ] **Run RED:** `flutter test --timeout 20s --reporter expanded test/features/najm_bahar/najm_bahar_membership_payment_screen_test.dart`; network work stays in real tester.runAsync boundaries where needed, not an artificial clock that hangs.
- [ ] **Implement** concise Persian UI matching existing wallet cards. Show `بهار کمرنگ` and `بهار فعال`, chosen account and exact formatted amount/server period. Dim confirmation explains fee activation; confirmation action `تأیید و پرداخت`; uncertain text `نتیجهٔ پرداخت هنوز مشخص نیست` and GET action `بررسی نتیجه`. No destructive auto-retry, hidden source switch or hard-coded charge. Connect controller lifecycles and update device checklist rows as NOT EXECUTED.
- [ ] **Run GREEN** new widgets plus old wallet screen/navigation/runtime/session/logout suites and analyzer. Prove all financial sections clear on a payment-origin malformed401 and late response cannot restore them.
- [ ] **Commit:** `feat(mobile): confirm membership payment from Dim or owned Active source`.

### Task6: Final evidence, independent review and one candidate

**Files:** Extend .github/workflows/mobile-membership-payment.yml with separately selected final mode, create the one signed release workflow based on the proven+17 recipe, update docs/MOBILE_REMAINING_WORK_20261005.md and apps/mobile/docs/DEVICE_ACCEPTANCE.md, create docs/MOBILE_MEMBERSHIP_PAYMENT_CHECKPOINT_20261005.md. Keep exact artifact/source receipts and DraftPR165.

- [ ] **Run final gates once after stable changes:** formatter/analyzer + complete `flutter test`; server `vendor/bin/phpunit -c phpunit.xml.dist` in isolated MySQL test environment because shared transaction/status behavior changed. Include financial mutation architecture checks. Record actual counts/skips/deprecations/Drift warnings; no success claim from green-looking UI. Never connect tests to production accounts or DB.
- [ ] **Request one whole-change independent review** with approved spec, actual base/head SHAs, consent/evidence/accounting/session/recovery requirements and exact verified logs. Implementer owns all tasks; reviewer is read-only. Valid Critical/Important findings get one regression-first fix-pass and fresh affected checks/final suite only as justified. No routine per-task agents or second review.
- [ ] **Package one new signed Android UAT build** only after verified reviewed source; bump17→18, require stable certificate continuity and configured Firebase, strict unchanged formatting, actual package/version/nondebuggable/Internet verification. Retain actual APK/build.json and compute separate inner/ZIP hashes. Reuse exact binary for checker/receipt-only follow-ups; never rebuild solely to inspect metadata.
- [ ] **Record final delivery** with actual software and binary evidence, review rulings, capability/host deployment not verified, and all physical/live-money/platform/provider/distribution gates still open. Phone acceptance uses one latest candidate;81-group count needs no repeat absent regression. No claim that+18 can pay on an undeployed old API.
- [ ] **Publish reviewable source/receipts** to the authorized draft branch and update DraftPR165; do not main-merge, FTP publish, activate push or send a real charge. Present download and the precise next server-deployment/phone gate.

## Self-review and execution handoff

The six tasks map all approved spec sections: monetary invariants/evidence(task1), ownership/consent/capability(task2), exact wire/retry behavior(task3), recovery/session/restart(task4), native source selection/confirmation/accessibility(task5), review/binary/device/publication gates(task6). Existing reservation settlement/refund and policy-split rejection tests are reused, not reinvented. Five Review Focus conditions each have owning tests above. All later method/type names refer to the Interfaces blocks. Documentation-only plan creation runs no broad CI or APK build.

Recommend **Native** execution: the same primary agent implements all six dependent tasks, with one independent final reviewer. The alternative is Subagent-driven execution with fresh implementers/reviewers per task; it costs additional contexts and is not currently authorized. User review of this plan and selection of execution method is the remaining gate before product changes.
