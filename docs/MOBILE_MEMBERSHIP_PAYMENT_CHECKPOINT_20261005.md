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

The official status page https://www.githubstatus.com/ reported an unresolved Actions runner-assignment delay incident (19:11UTC start,19:15UTC update) during this session. This supports the infrastructure explanation for delayed starts, while exact queued-job cancellation causes remain unconfirmed. Server hardening targeted rerun job111956423550, native Linux rerun changes job111951829583 and native macOS job111955523404 are pending. Controller/UI tests are durably retained as noncompiled drafts; no device/payment/build gate is declared passed by writing those drafts.

At20:21UTC the additional anniversary RED reproduced the real membership_fee_incomplete error (8tests/18assertions, run37365965231/job111956423550). Fix source08db3b9d636b2f6c22bd7a5ec75ec52d09ed4545 uses frozen-period proof for completion and receipt; GREEN is pending in run37367875583/job111957348786. The dedicated macOS targeted rerun37367327140/job111960803177 is queued; no native RED observed and no native payment implementation/GUI/+18 completed. Ten minutes of bounded status monitoring produced no further executed checks. The official unresolved incident API still reports investigating runner-assignment delays, last update19:50UTC. Code, drafts and these execution gates are retained for continuation without needing a phone or user action.


## Task 6 final software gate — 2026-10-07

Frozen reviewed software source: `17f6521c84508b6c7038757a88377135233b66df` on isolated candidate branch `agent/mobile-membership-candidate-20261007`.

Final GitHub Actions run `37551833441` completed successfully:
- `final-server` job `112568883921`: financial mutation architecture gate passed; full PHPUnit passed **2426 tests / 13820 assertions**, with **47 existing PHPUnit deprecations** and **2 skipped tests**.
- `final-mobile` job `112568884189`: strict formatter **142 files / 0 changed**, analyzer **No issues found**, complete Flutter suite **285 tests passed**.
- Server evidence artifact `11453540470`, archive digest `sha256:bbc09e34f037dd3b3f1a8a44c1c1fc05fd239fa94553ae60c34ad396d0540d78`.
- Mobile evidence artifact `11452324599`, archive digest `sha256:ec93f4fa410b89dba79a98e7c5eed0b6e82293e766515aa418fc57d628284bfd`.

The earlier broad-gate failures on runs `37549338703` and `37550120587` were validation-environment failures around the Vite manifest, not membership-payment regressions. The final gate now follows the repository's established Node/Vite setup and is green.

A separate read-only whole-change review was performed over PR #228 (31 changed files) against the approved design and plan, focusing on consent/evidence/accounting, source ownership, reservation handling, idempotency/retry behavior, ambiguous-result recovery, session boundaries and UI confirmation. One suspected subaccount receipt mismatch was investigated and rejected: `resolveActiveSource()` returns the selected subaccount code and that exact value is distributed and returned as `payment_account_number`; the server contract test also asserts this. No remaining Critical or Important finding was identified in this review pass. GitHub Copilot reviewer was requested, but the repository did not attach it as an actual reviewer; therefore no external-bot review result is claimed.

The signed +18 workflow is prepared as artifact-only packaging. It is pinned to the exact frozen software SHA above, requires Firebase Android configuration and the stable UAT keystore, verifies release/non-debuggable/package/Internet properties, and now enforces the historical stable-certificate SHA-256:
`eda5c77121b0bbf1c08b82fb61e59f55a7ea61a2fe0fbbb23537d5bc134c8543`.

**Still open:** the +18 workflow has not been dispatched from this connector, so no +18 APK or +18 build.json/hash receipt is claimed yet. No `main` merge, production/FTP publication, live financial charge, push-driver activation or physical phone acceptance has been performed.
