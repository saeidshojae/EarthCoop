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
