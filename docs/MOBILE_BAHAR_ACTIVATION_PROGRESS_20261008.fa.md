# پیگیری اجرای Native Bahar Participation Activation — ۸ اکتبر ۲۰۲۶

## وضعیت قطعی

- Source branch: `agent/mobile-bahar-transfer-20261007@2f04ed1147dd9addb685acd3a6182e19ff682c29`
- Implementation branch: `agent/mobile-bahar-activation-20261008`
- Design: `docs/superpowers/specs/2026-10-07-mobile-bahar-activation-design.md`
- Plan: `docs/superpowers/plans/2026-10-07-mobile-bahar-activation.md`
- Release scope: Native participation-point conversion into the user's own Active Bahar; no money creation, no offline financial mutation.
- `main` is divergent; do not merge blindly. The large integration reconciliation remains an independent gate before merging/deploying.

## Progress ledger

### Task 1 — Eligibility hardening: code + contract assertions written; validation pending

1. Contract test update commit: `2b9f054a6f696884db17fa5916f376d5f3e85473`
2. Implementation update commit: `936f52cb84967cd70b36c4524d41ccf20c4aecdb`

Changes:
- `GET /api/v1/najm-bahar/activation/eligibility` adds integer `activation_contract_version=1`.
- Adds `policy_version_id` provided by MonetaryPolicyService; nullable for legacy settings.
- Adds `max_activation_points`, calculated from the lesser of convertible whole-point capacity and available Dim multiplied by conversion ratio.
- Keeps existing response fields and legacy POST behavior untouched.
- Test now checks version/id/max points and verifies that the eligibility read did not create a conversion or point-consumption entry.

**No execution of PHPUnit or CI is claimed in this checkpoint.** The connector supports repository edits but no new workflow dispatch. Task 1 cannot be marked GREEN until its targeted PHPUnit run succeeds. Verify `php artisan test --filter=NajmBaharActivationContractTest` on the exact branch SHA, then inspect the result and fix failures before proceeding.

### Tasks 2–7

Not started. Respect the approved task order:
- Task 2: strict expected snapshot / fail-closed POST preserving legacy behavior.
- Task 3: scoped, read-only idempotency reconciliation.
- Task 4: Flutter wire DTO and transport.
- Task 5: Flutter controller and session-safe intent.
- Task 6: Native screen/review/receipt.
- Task 7: focused + final suites, review and checkpoint.

## Distribution and authorization

+18 remains the last signed and verified Android UAT APK; it does not contain external transfer or activation mutation. Do not build/install a misleading APK for this partial task. No main merge, production deploy, FTP publication, FCM driver enablement, live financial action or phone acceptance is authorized by this checkpoint.

## Continuation rule

Resume with targeted Task 1 validation, not reimplementation. Read `docs/MOBILE_DEVELOPMENT_STATUS_20261008.fa.md` and this file first; then inspect exact latest branch and main state. Do not infer successful tests from source changes.

## Follow-up — 2026-10-08: Task 2 test-first checkpoint

- GitHub Actions query for the isolated branch returned **zero workflow runs** at inspection time. Consequently Task 1 is **not GREEN**; its contract assertions and implementation are only source-reviewed, not runtime-validated.
- Task 2 RED contracts have been added in commit `4ddff57405347b0a1757c88a6390880b5cb35b18`.
- RED case A: a strict Native `expected` request using 250 points with ratio 100 must reject partial-multiple input without creating point consumption or moving money.
- RED case B: policy ratio changed after GET review must return `activation_terms_changed` without creating conversion or moving money.
- Under the currently inspected controller, `expected` is still an unsupported field, so the tests should currently fail with validation instead of the desired domain errors. **This is a predicted RED, not a claimed executed RED.**
- Do not implement financial POST mutation on top of this unverified baseline. First run the targeted `NajmBaharActivationContractTest` suite on commit `4ddff57405347b0a1757c88a6390880b5cb35b18`; inspect the precise failure signatures. Then implement strict binding inside the financial transaction, preserving legacy no-expected calls and ensuring stale snapshot does not consume ledger entries.
- Scope of this continuation: tests and documentation only for Task 2; no financial behavior changes and no release build.

## Follow-up — 2026-10-08: Task 2 implementation candidate (not GREEN)

- Controller typed Native `expected` validation and legacy-field compatibility: `573906163268ffb29fe12b06a7204d517436e783`.
- Service strict ratio/review-snapshot validation: `1269c0298dead0643a86a27b451ab57cfa2b8c56`.
- When `expected` is omitted, legacy floor-to-ratio behavior is kept; Native requests with `expected` reject non-multiples and compare the frozen terms before point conversion identity creation.
- This is an **unverified implementation candidate**, not a financial production-ready change.
- **Open concurrency audit:** current application service reads policy and calculates ratio before the DB transaction; the new snapshot check runs in the transaction but does not yet guarantee a policy-row lock or a single locked snapshot of all point-consumption and account balances. Verify and harden the atomic validation/consumption order before claiming strict concurrency-safe behavior.
- **Open validation:** no CI workflow runs were found for the isolated branch in the latest query; PHPUnit RED/GREEN is unexecuted, and no passing test evidence exists for these commits.
- Next mandatory actions: run focused activation contracts and finance architecture check at exact candidate SHA; review wrong-error cases and Laravel validation grammar; add parallel/stale policy/points/Dim tests; fix results and only then proceed to reconciliation Task 3.
- No Android build, main merge, release deployment or real monetary activation.

## +19 scope and readiness — 2026-10-08

The authoritative `docs/MOBILE_DEVELOPMENT_STATUS_20261008.fa.md` identifies +18 as the latest verified signed UAT, with membership payment; native external transfer is software-complete and **not** in +18. The activation implementation plan defines seven tasks, and explicitly says to **bundle** the next signed candidate instead of building a disposable APK while phone access is unavailable.

**No calendar delivery date for +19 is specified in those documents.** The operational +19 readiness criterion is a consolidated Android signed UAT candidate containing already-complete external transfer plus completed Native Participation Activation, after:
1. Activation Tasks 1–7 pass focused server/Flutter and final full validation;
2. financial architecture / same-user-ownership / concurrency audit passes;
3. safe reconciliation with up-to-date `main` (branch divergence unresolved);
4. signed Android candidate `1.0.0+19` is built and verified against the stable certificate, app/package, binary hash and expected capability inclusion.

Physical Android acceptance, production API release, FTP/host publication, iOS/HMS gates and main integration are independent gates; signed UAT does not mean those were performed.

Latest source-only step: tests for points/Dim changes after review were added in `0982bae03b7d98225fb9e9c043e7a79d05d9fd35`. They assert 409 `activation_terms_changed` and no new point-conversion/consumption and no money transfer, but **they have not been run**. No Android +19 artifact is claimed.

Next checkpoint is still focused PHP validation and real concurrency review before marking Task 2 complete. Avoid calendar ETA without measured/verified CI and device access.

## Continuation — 2026-10-08: strict validation ordering corrected

Review found a real ordering defect: the native multiple-of-ratio check was executed using a policy read **before** the consent snapshot comparison. If ratio changed after review, this could incorrectly emit `activation_not_eligible` instead of the intended `activation_terms_changed`. Fixed in commit `aec2b82dbc8212e5ab6bceb849807d551892dac1`: snapshot comparison now precedes multiple-of-ratio validation inside the transaction.

**Evidence limit:** no Actions runs reported on this branch, so neither the new stale-policy/points/Dim tests nor legacy tests can be called passing. The policy-read and transactional consistency/concurrency questions remain open; do not close Task 2 on code inspection alone. Prioritize a test execution runner/authorized CI dispatch and review the updated service, then proceed to the remaining activation tasks.

Next signed UAT candidate remains **+19 only after activation Tasks 1–7 are validated**, bundled with already completed native transfer. The plan does **not** provide a calendar ETA.

## 2026-10-08 continuation — +19 test gate

Native Participation Activation remains part of the planned bundled Android +19, along with previously completed native transfer. No signed +19 APK exists yet.

- Commit a73d19397c9ef3accf2c5c98a2b18bbd3d765411 added test coverage for exact native activation and same-key replay after balances change. This test is written, not executed.
- Commit c7d49b427dee208f35df76818fa950577419dd85 added a focused PHP and financial architecture GitHub Actions workflow on the isolated activation branch. Immediately after this commit, the Actions run query showed zero runs, so no green result is claimed.
- Open: verify replay before stale eligibility rejection, transactional consistency, targeted test results, Flutter tasks, safe main reconciliation, final suite, signed +19 APK.
- No main merge or production release.

## 2026-10-08 evening — first actual focused CI run inspected

Workflow `Native Bahar activation focused contracts`, run `37779521099`, source `c7d49b427dee208f35df76818fa950577419dd85`: **FAILED**. Laravel setup/migrations/assets were successful. Activation API test step ended at **10 tests / 127 assertions / 1 error**; financial mutation architecture step was skipped because the prior test step failed. The log identifies a test-fixture issue at `NajmBaharActivationContractTest.php:343/458`: duplicate unique `najm_bahar_monetary_policy_versions.version=1` on the second pass of the combined points/Dim stale snapshot test.

Correction committed in `b963b9dbec7e9be26327f5ff9b394af0b559a513`: the two variants use distinct policy versions, and the test factory accepts a version parameter. The immediate GitHub Actions query still showed only the previous failed run. **Do not claim that the correction has passed CI** before fetching the next run's result. The financial concurrency and same-key replay contract still need green evidence and review. No production financial mutation, main merge or APK rebuild.

## Verified focused GREEN — 2026-10-08

GitHub Actions run **37824363224** on source **b963b9dbec7e9be26327f5ff9b394af0b559a513** completed SUCCESS. Logs: activation contract **10 tests / 134 assertions**, financial mutation architecture **1 test / 1 assertion**; each reported one PHPUnit deprecation. PHP dependencies, MySQL migrations and Vite preparation also succeeded. This verifies the focused API/architecture gate at that source SHA, not entire mobile/server suite, concurrency safety, or any later commit. Next work: Task 3 GET-only, actor/route-scoped activation reconciliation with a complete successful receipt and no mutation. Task 2 concurrency audit is a separate open gate.

## 2026-10-08 — Task 3 GET-only reconciliation candidate

Focused green evidence now confirmed: run 37824363224 on b963b9dbec7e9be26327f5ff9b394af0b559a513 completed successfully, with activation 10 tests / 134 assertions and financial architecture 1 test / 1 assertion. This does not cover subsequent commits.

Task 3 development: test added in 59c33efadc98c47391e51ce8ec51a06756772169, route in cdf007ff5451cec693d1950da015f338d8fcb676, and controller GET-only implementation in 90a850cf4d820eb8d49745916576b2d915259939. New endpoint GET /api/v1/najm-bahar/activation/by-idempotency/{key} filters the idempotency receipt by authenticated user, exact POST route scope, completed state, HTTP 201, and matching applied user point conversion. It returns no result for unknown or incomplete records; no financial POST is made.

Status: implementation candidate only. GitHub Actions run 37827311119 was pending at the most recent inspection. Do not call Task 3 green before observing that run, reviewing its logs, and adding negative coverage for foreign user/route, malformed evidence and unsuccessful receipts. No main merge, Production deployment, signed +19 APK or live activation.

## Task 3 security hardening — 2026-10-08

New negative contract test commit `f92d72cdbc2bc6d8e5d8774cc492f267814f9853`: reject a string-valued monetary receipt amount and a forged, nonexistent transaction ID. It is an added test, not yet validated by an all-green run.

Controller hardening commit `28b65f1965b6e5f4fc41ddb4762ce421055e3633`: receipt integers are now type-checked strictly; actual completed Najm transaction must match conversion id, amount, idempotency key, participation metadata and owner. The owned ledger projection is compared to the stored receipt instead of trusting a client-facing transaction id alone. GET remains read-only. Caveat: PHP/MySQL JSON attribute matching and full projection equality need real MySQL PHPUnit evidence.

At last poll, run `37828424933` for the new RED test source was in progress; no completed run on the controller hardening commit had been observed. Tasks 3 and 7 are not yet green; additional negative tests for cross-user, route scope, incomplete and foreign records remain required. Do not publish/merge financial code from this checkpoint.

## 2026-10-08 — Security CI and isolation regressions

Run 37828462659 on commit 28b65f1965b6e5f4fc41ddb4762ce421055e3633 completed successfully: activation contract 12 tests / 154 assertions and financial boundary 1 test / 1 assertion (each reported one PHPUnit deprecation). It verifies strict receipt value checks and ledger match at this source SHA, but not the newly added negative test.

Commit 0cd857839c013444f44b41dc160d78ac4aa803ed added a new API contract asserting foreign authenticated users cannot recover another user's activation and changing the stored idempotency scope to the transfer route must prevent recovery. This needs its own CI green before Task 3 can be closed. Financial concurrency audit and Flutter Tasks 4–6 remain open. No +19 signed artifact or main/production deployment.

## 2026-10-08 — Verified security and isolation GREEN

GitHub Actions run 37830043063, source 0cd857839c013444f44b41dc160d78ac4aa803ed, completed SUCCESS. Activation API tests: 13 tests / 162 assertions; financial mutation architecture: 1 test / 1 assertion; both reported one PHPUnit deprecation. Thus the tested actor/route isolation and tampered receipt checks are green. This is focused evidence only, not whole-system/Flutter validation. Task 3's basic GET-only success/negative security coverage now has a passing checkpoint; concurrency review, potential malformed receipt variants, Tasks 4–7, and eventual main reconciliation remain open. No Android +19 signed APK, Production deployment or main merge.

## 2026-10-08 — Task 4 Flutter wire candidate

Implemented native activation DTO in `apps/mobile/lib/features/najm_bahar/najm_bahar_activation_dto.dart` (commit `63d0bbad0b0c4104576deac8c271fe9eefff060f`) with strict versioned eligibility, integer and policy consistency checks, frozen exact `expected` snapshot, positive whole-ratio points and immutable idempotency key, and strict receipt matching.

Updated `NajmBaharRepository` (commit `acfa42d2f5f744c97d4f8eb4558c48f67ea1c8ab`) to add `activationTerms()`, `activateParticipation()` with `allowAutomaticRetry: false`, and GET-only `reconcileActivation()` with session guards. Added DTO negative tests in `f6ab8b3570dec274fb5f748cefcbc5b02ea9fdd2`. Added isolated Flutter focused test/analyzer/format CI workflow in `c044ad6637faaf7a5f29217cc47daa495acb124e`.

**Status: candidate / NOT GREEN**. No Flutter test/analyzer/format result was available at last query. Repository HTTP transport tests and full wire DTO receipt tests remain to be added; implementation must be reviewed against server serialized activation transaction projection, including success/reconcile consistency. Do not declare Task 4 complete on source existence. Tasks 5–7 remain. Existing +18 binary is unchanged; +19 not packaged.

## Native Flutter continuation — 2026-10-08 evening

Task 4 Flutter CI run 37830932068 on c044ad6637faaf7a5f29217cc47daa495acb124e failed ONLY at Dart formatting: `NajmBaharActivationEligibility` DTO's new activation companion file and `najm_bahar_repository.dart` require canonical `dart format`; Flutter DTO tests and Flutter analyzer steps passed before that failure. This is **not a green Task 4 gate**. Canonical formatting and dedicated repository tests remain required.

Task 5 source candidate `apps/mobile/lib/features/najm_bahar/najm_bahar_activation_controller.dart` committed at a450e421a3e6381d47ec378c071e5e5d4f0cc155. It follows the already-tested transfer controller model: loading/ready/reviewing/submitting/confirmed/definiteRejected/outcomeUnknown, coalesced confirm, immutable intent, 23-hour same-intent retry cutoff, GET-only reconciliation for ambiguous outcomes, session invalidation and stale async response suppression. **This candidate has NOT passed its own Flutter controller tests, formatter or analyzer.** It is NOT UI-enabled, no financial POST is reachable from a screen, and the +19 APK does not exist. Next: add deterministic controller/repository tests; run formatter, analyzer and targeted Flutter suite; integrate Native UI only after those gates. Do not merge to main or deploy Production.

## 2026-10-08 — Flutter controller test continuation

Inspected run 37832376409 on 60832c7ebb97063b3f61571a08c8f073c8e4bbaf: failed **only** at Dart format gate. Flutter test and analyzer stages passed; formatter identified two noncanonical files (`najm_bahar_activation_dto.dart`, `najm_bahar_repository.dart`). To capture the exact canonical patch, CI format step now prints the `git diff` before enforcing clean formatting (commit 051145c72dbaa48cec8484f695e893a579e9633b). Its run 37832725930 was still in progress when inspected.

Added deterministic native controller behavioral tests for preview (GET only), coalesced confirmation (one POST) and ambiguous-POST recovery using only GET (commit 9543fe9e68f6b525a535397e215dae906288919e). Wired the focused Flutter workflow to execute the new test file (commit 506120cc4bce761786bd6cb091e581e75424c0a4). **These new tests and canonical formatting have not yet been confirmed green.** Before calling Task 5 complete, inspect the latest run, apply canonical formatting without unrelated changes, and test session invalidation and 23-hour replay guard. No UI or signed +19 yet.

## Flutter formatting and controller guards — 2026-10-08

CI run 37832801310 failed only on Dart format; its diagnostics printed the exact canonical diff for two files. Applied those formatting edits verbatim in commits `1c5bcea11898868deca81bf060d339d26193ee99` (activation DTO) and `300a7e561aa6cbc44333a71f75214af1f0178a76` (repository). The DTO blob now matches the CI-generated formatted blob. New controller tests in `4b52734c75ebc537c984a4b3be12f473a64a838b` cover 23-hour unknown-outcome retry expiry and discarding stale in-flight financial response after session invalidation. At the latest query a newly queued/re-running formatter CI was not yet complete, and the new guard tests had not been confirmed green. Keep Task 4/5 open pending explicit successful run at a source SHA including these changes. No Flutter UI wiring, Android +19 APK, main merge or Production release.

## Verified Flutter targeted GREEN — 2026-10-08

CI run 37833939486 on commit 4b52734c75ebc537c984a4b3be12f473a64a838b finished SUCCESS: activation DTO 2 tests passed, activation native controller 5 tests passed, Flutter analyzer `No issues found`, formatter 4 files / 0 changes. This closes the *focused implementation test and format* gate for controller/DTO, not overall Tasks 4–7 or financial concurrency. Repository transport boundary tests, native screen integration, final Android test/build and deployment reconciliation are still outstanding. No +19 APK or main/production deployment.

## 2026-10-08 — Task 6 native RTL UI integration candidate

Created Persian RTL `NajmBaharActivationSection` on `71223eab7be19ec602abfac8547bf76264bf0115`: eligibility and exact point multiples, read-only preview, explicit review/confirm, submitting, successful receipt/tracking, definite failure refresh, and unknown result with GET reconciliation plus clearly distinguished same-intent retry. Wired into `NajmBaharScreen` in `5c194e8d80de04c15196476375c1b06e0d6ba49a` and production mobile runtime in `52b1f2d75f9b74f04b3a14c34cccb6d93440151a`; controller loads, refreshes balance/history/policy after success, and invalidates on session changes. Scoped Flutter workflow extended in `5ceca20b398083796a9c226725fc11a8962bccb5`.

Status: **UI source candidate, not verified GREEN**. The last observed passing run 37833939486 predates these UI/runtime commits. Need targeted widget tests covering explicit confirmation, small-screen/RTL, disabled eligibility and unknown outcome; canonical Dart format, analyzer, repository tests and full Flutter gate. Task 2 transactional concurrency review and safe main reconciliation remain mandatory. No +19 APK/production changes.

## 2026-10-08 — Native UI verification and runtime fix

CI run 37835618149 (source 5ceca20b398083796a9c226725fc11a8962bccb5) FAILED at Flutter analyzer although DTO 2 tests and controller 5 tests passed. Analyzer identified accidental activation-controller initialization inside `_GroupsRuntimeViewState` (undefined `_activation`, mismatched GroupRepository, invalid refresh methods). This was fixed surgically at commit `d6b50f54924658456b4be3c7108c60b89eb13ea4`: removed misplaced initialization from Groups and put it exclusively in `_NajmBaharRuntimeViewState` ahead of native Bahar preparation. No unrelated group code behavior intentionally changed.

Added first native RTL UI widget checks in `2a5d2cce572de27ecbf0ea055d8b34a5e7570441` covering correct RTL direction, exact-points validation, GET-only review and explicit confirmation. Wired test into dedicated Flutter CI in `7717fb24b2f0dca3eb5883e049ebda2e269a2851`. Run 37838750459 was pending at latest poll. **UI gate NOT yet green**; the new widget suite and runtime analyzer must pass and subsequent cross-screen regressions must be checked before treating Task 6 as done. Financial concurrency and main reconciliation remain open; no signed Android +19 or Production deployment.

## 2026-10-08 — Flutter RTL UI targeted test results and formatter coverage

Verified GitHub Actions run `37838750459` on commit `7717fb24b2f0dca3eb5883e049ebda2e269a2851` completed SUCCESS: DTO 2 tests, controller 5 tests, screen 2 tests; Flutter analyzer no issues. However its format step rewrote `najm_bahar_activation_section.dart` (5 files inspected, 1 changed) and mistakenly omitted that file from `git diff --exit-code`, resulting in a false-green formatting gate. Workflow fixed in commit `df14db5ed0ffb220d2a95e847ec1e4766c692388` so the section is included in the enforced diff. New run `37839743202` was in progress at latest poll. Do not claim fully green formatting for the RTL UI until that run passes and the section is committed in canonical format. Repository transport tests and broader integration/financial concurrency audit remain open. No signed +19 APK, Production deployment, or main merge.

## 2026-10-09 — Native UI green and repository transport tests pending

GitHub Actions `37840127752` completed SUCCESS on source `0f32e43833bf348586a434618fd46ccaaf8dfb78`, confirming targeted DTO/controller/screen widget tests, Flutter analyzer and complete formatter diff gate. Canonical section formatting commit now matches runner-generated blob.

Added dedicated Flutter repository transport tests in `197e91a0bb33149d87aa5d4e31f21ba9d1655f8a`: eligibility GET-only, exact idempotent POST payload and key, no automatic POST replay after timeout, mismatched receipt rejected, GET-only reconciliation, and session-switch isolation. Wired test to CI at `c326b11382b3f30eb3b119248d6c6270d445af08`; run `37840608148` queued on latest check. **Transport test suite not yet green**. Do not claim Task 4 or full +19 release complete until observed evidence. Remaining: wider regression suite, financial concurrency audit, reconciliation with main, and signed Android artifact verification. No production changes.

## 2026-10-09 — Verified Flutter wire and server concurrency audit checkpoint

Flutter focused run **37840608148** source `c326b11382b3f30eb3b119248d6c6270d445af08` is SUCCESS: activation DTO **2**, controller **5**, native screen **2**, repository transport **6** tests (total **15**); analyzer no issues; formatter **5 files / 0 changed**. This confirms the scoped Flutter gate, not real Android/device/whole-app test.

Read `NajmBaharActivationApplicationService::activate()` for money race hazards. Current strengths: DB transaction encloses conversion identity/point consumption/dim activation; conversion identity is `firstOrCreate` keyed to `(user_id, request_key)` then locked; convertible point transactions are `lockForUpdate`; main account is locked and balance rechecked prior to consumption and monetary mutation; all effects should roll back on exception. The code performs strict snapshot recomputation **before** row-level point/account locks, and resolves monetary policy/ratio **before** the transaction. This means those freshness checks do **not** themselves provide an atomic serializable proof under simultaneous different-key conversions, point awards/reversals or policy changes. The snapshot may be outdated between reevaluation and locks; later checks reduce overdraw but concurrency ordering and database isolation need explicit two-worker tests and lock-order review. `firstOrCreate` safety depends on verified unique DB index; inspect migration. **Do not claim financial concurrency audit green yet.** Recommend proving competing intents across two separate database connections against production-like MySQL, no duplicate consumptions, no negative Dim, at most one successful stale-snapshot intent, and bounded deadlock handling. No uncontrolled sensitive financial code changes made pending this evidence. No +19 APK/main merge/Production deployment.

## 2026-10-09 — Financial idempotency constraints verified in migration / new MySQL assertion gate

Confirmed by source inspection: `2026_09_01_010000_create_user_point_conversions_table.php` defines database UNIQUE `(user_id, request_key)` and UNIQUE `conversion_key`; `2026_09_27_000001_create_api_v1_idempotency_keys_table.php` defines database UNIQUE `(actor_key, scope, idempotency_key)`. Middleware uses insert-and-conflict on the transport key and returns `request_in_progress` for processing state; no duplicate request should be claimed through the same actor/scope/key. A MySQL schema feature assertion was added to activation API contract test in commit `e314d1306eafa582c0c2be6a5609cff15fbc3641`, checking the actual indexes after migrations (still awaiting CI).

**Important limitation:** unique keys only prevent same-key duplication. Different idempotency keys from one user still need correct serialization around available points, consumption, balance and policy. Existing `activate()` locks existing point awards and account after a transaction-local pre-lock eligibility read, while policy is fetched before transaction. Reads for reversals and aggregated consumptions may not be mutually serialized against every independent writer. A two-connection MySQL concurrency test and an audit of *all point-award/reversal/consumption writers* and consistent lock order are required before concurrency green. Do not treat MySQL index test as proof of cross-key atomicity. No sensitive monetary refactor, main merge, Android release or Production deployment.

## 2026-10-09 — DB schema green and distinct-key stale snapshot regression

Verified focused MySQL test CI run **37841452352**, source `e314d1306eafa582c0c2be6a5609cff15fbc3641`: **14 activation tests / 168 assertions**, financial architecture **1 test / 1 assertion**, success (each reports one PHPUnit deprecation). Confirms actual DB unique indexes, not different-key serialization.

Added `test_distinct_native_keys_cannot_reuse_the_same_reviewed_points_snapshot()` at commit `1f2274ebfc2a098336a66729f60c80ef389e28f2`. It creates one 300-point review and sends first 200-point strict activation under key A; then attempts same stale review under key B, asserting `activation_terms_changed` 409, one point conversion only, 200 consumed points, unchanged total and expected dim/active balances. **Sequential conflict regression, NOT a simultaneous two-connection MySQL test**, and not green until its own CI finishes. Remaining release blocker: real two-worker concurrency and cross-writer lock-order audit, plus broad mobile/full-project regressions and signed +19 artifact. No main/Production changes.

## 2026-10-09 — Distinct-key sequential review gate VERIFIED GREEN

GitHub Actions run 37841925533 on commit 1f2274ebfc2a098336a66729f60c80ef389e28f2 completed SUCCESS: **15 activation API tests / 179 assertions**, plus **1 financial architecture test / 1 assertion** (a PHPUnit deprecation noted in each). This includes the new sequential second-key stale-snapshot rejection with no duplicate conversion and unchanged total Gol. The evidence does NOT prove simultaneous different-key requests across two MySQL connections: real parallel MySQL workload, point writer lock ordering and financial race tests remain open. Flutter focused run 37840608148 remains the latest verified success for its 15 tests. No main merge, production deployment or signed +19 APK.

## 2026-10-09 — Two independent MySQL worker race probe (unverified candidate)

Added `tests/Support/NajmBaharActivationConcurrencyProbe.php` in `ef91603bbb17645cbaddf900f501d6f0c216047a`. It prepares a 300-point/10-Dim/5-Active user with enabled policy, then launches **two separate PHP processes / independent MySQL connections**, each activating 200 points using its own distinct idempotency key but the same frozen native `expected` terms. A bounded filesystem barrier releases them together. Verification requires exactly one successful activation, one applied conversion, exactly 200 points consumed, balance Active=7, Dim=8, total=15, and both worker results present. The test captures deadlocks/retry problems as errors and enforces financial invariants, but **does not declare concurrent failure mode acceptable** without reviewing CI. Workflow `.github/workflows/mobile-bahar-activation-focused.yml` was extended in commit `53d9a1ef710851d90412910e973b3908f87bd8e8`; GitHub run `37843519441` was queued at last check. No validated race result yet; do not merge main, publish +19, or deploy Production.

## 2026-10-09 — Race probe discovered uncaught unique constraint exception

Observed run **37843519441** SUCCESS: two independent PHP/MySQL workers processed distinct keys against the same reviewed 300-point snapshot. Results: one success / 2 Gol, other `Illuminate\\Database\\UniqueConstraintViolationException` SQLSTATE `23000`; one conversion, 200 points consumed, Active=7, Dim=8 (total=15). The money invariant held in this specific race, **but the losing worker did not return a controlled business rejection**. Initial probe mistakenly accepted any error, so green is insufficient for production readiness.

Hardened diagnostic probe in `c304a7332750c0f0d7969f9468ea78bbefb879b2`: saves exception message and now requires the losing worker to produce `NajmBaharActivationException` rather than a raw DB uniqueness exception; rerun **37845184470** was in progress at last poll. This will intentionally turn RED until the root cause is identified and safely fixed. Do not claim concurrency complete. No main merge, Production or signed +19 APK.

## 2026-10-09 — Strict race probe syntax correction

Run **37845184470** failed in the parallel probe before executing workers with a PHP parse error on line 123 (double-escaped namespace in `NajmBaharActivationException::class`). Thus the result neither proves nor disproves controlled financial failure; it was a broken test. Fixed namespace syntax in commit `44bd17307bcaf3114f1f2519989044dd813f0d09`. New run **37845557448** was in progress at last check. Earlier actual two-process run **37843519441** demonstrated money invariants preserved but surfaced raw `UniqueConstraintViolationException` on the losing worker. Strict probe still requires controlled domain exception; production readiness blocked pending true result and remediation of any confirmed race. No main/Production or Android +19 release.

## 2026-10-09 — False-positive CI race gate closed; incidental notification write race isolated

Reviewed run `37845557448`: workflow falsely reported SUCCESS even though the parallel probe's `verify` process printed `Parallel activation invariant violated`. Its losing worker raised raw `Illuminate\\Database\\UniqueConstraintViolationException` due to `notification_settings.notification_settings_user_id_unique`, **not** a confirmed duplicate financial conversion. Probe failure was swallowed by Laravel's CLI exception handling despite an apparent successful process status. Updated probe in `7995e85d2a708aadec8ee5f59830ea57440a533b` to `fwrite(STDERR)` and explicit `exit(41)` for failed financial/controlled-error invariants. To isolate the financial race from independent lazy notification-setting initialization, `8ab182aa162e31bb95b25cec0bcddbe5b027ac92` now seeds the user's default notification settings once during setup, before workers are spawned. This is **test fixture isolation**, not a production fix for a notification-settings race; that independent issue needs separate assessment. At last poll race run `37846481855` was in progress and isolated-seed run `37846513013` pending. **Do not mark concurrency green yet**. No main merge, Production deployment or +19 signed artifact.
