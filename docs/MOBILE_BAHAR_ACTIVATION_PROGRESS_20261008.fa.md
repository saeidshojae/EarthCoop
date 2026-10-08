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
