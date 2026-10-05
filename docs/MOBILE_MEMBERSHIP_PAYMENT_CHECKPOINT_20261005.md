# Native membership payment checkpoint — 2026-10-05

Saeed approved both available Dim and Active payment, the design and six-task plan, and Native inline execution. Phone tests remain pending; development continues without a device. The future Google/network resilience work remains deferred until the app functionality is complete.

## Verified task 1

Source `7812dea987a6a1cb34eba7676fc083f5e6ca5364`, isolated branch `agent/mobile-membership-payment-20261005`.
GitHub Actions run `37362821813`, job `111941238435`, SUCCESS:
- 7 new financial regressions, 18 assertions.
- 16 existing financial safety checks, 109 assertions.
- One existing PHPUnit deprecation reported in each invocation.

New membership transfers carry the server's complete expected split and total, including zero parts. Completed proof must match amounts without missing/duplicate parts; malformed new proof never falls through into legacy detection. Existing completed legacy proof remains compatible. Payment completion is checked before participation is awarded. Account debit and credit preserve committed Dim in local totals. Membership payments from owned Active subaccounts use the existing canonical transfer executor through server-owned routing metadata.

Task 1's RED run `37362265960` reproduced six financial evidence/local-total failures and one actual canonical subaccount routing error. The corrected source passed all targeted checks.

## Verified task 2

Contract tests in source `322f4a0cf621a7484099d0d7ade1b4f83b6a7a20` reproduced eight missing capability/consent failures in run `37363385764`, job `111942956065`; existing financial checks remained successful. Product implementation source `2021714ca9e905bf39b7892ce0066bd0f01ca986` adds read-only per-source projection, strict consent snapshot and owned source selection, anniversary checks and actual source receipt. Its targeted rerun37363852923/job111949930698 succeeded: completion7tests/18assertions, financial16tests/109assertions and consent/legacy API/journey/idempotency23tests/245assertions, one existing PHPUnit deprecation per invocation.

Native wire/controller/UI tests are being prepared. No native payment control is shipped, no +18 has been built, no live payment performed, and no host/main publication made. +17 remains the historical tested UAT artifact, not a current payment build.

## Decisions

- Keep the existing linked worktree and isolated remote branch; local PHP/Flutter runtimes are absent, so executable verification uses real PHP/MySQL and Flutter CI fixtures. CI/connector availability can delay checks.
- Run focused suites per task, then one final broad server/mobile gate. More distant regressions may be discovered at that final gate.
- Route subaccount payment through existing canonical execution. The initial suspicion about legacy parent-total projection was corrected by the real routing error; no generic parent projection block was removed.

The remaining sequence is task 2 verification, native wire/transport, single-intent ambiguity handling, UI/runtime integration, then broad verification, one fresh whole-change review and one stable-signed +18 UAT build. Physical device acceptance and capability deployment on the host remain separate gates.

Native missing-interface wire tests are published on preparatory branch `agent/mobile-membership-native-20261005`, source `b819f82fad1a4f8501d55d125449369144bb958a`, while the independent server check remains queued. No native product change has been made. Server corrections, if any, must be included in the final source before broad gates.

Additional frozen-period anniversary regression source `e78f07bf0beadaea716e074aaee9d259d6757a2b` is awaiting RED on server run `37365965231`. Native wire check run `37364432226` was cancelled before executing and a targeted changes-job rerun remains pending. A dedicated `macos-14` wire-test workflow in source `453133618d710f57a8ac9eee2cf67c5ceb04126a` now provides a device-independent execution alternative; final Android/Linux validation remains required. No native RED/GREEN result or new APK is claimed yet.
