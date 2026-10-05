# Mobile integration checkpoint — 2026-10-05

User authorized continuing all feasible work without a phone. Google/provider filtering and network outage resilience remain deferred to final app completion. No new feature scope or production activation.

## Frozen inputs and rulings

Native +14 receipt: 4c100a9e57c50e0f342db89e22f8bf3c6cc324fa; tested mobile source: 5fc4c7536ad323335982c753d3a9a33dea929592. Current main verified by compare API: 9f916d80be35925ec6f51b2fac0670cee9b64438. Shared ancestor: aec19ca1a27176f74e47678c6c1675219edd0aa6.

Ruling: preserve main's FcmAccessTokenProvider implementation — it adds tested bounded OAuth connect/read timeout and disables redirects for deployed readiness; using the older mobile branch implementation would regress host diagnostics. Cost if wrong: provider authentication regression caught by server tests.

Ruling: retain the native branch's longer NATIVE_PUSH_PROVIDER_SERVER_RELEASE_20261004 receipt — main's shorter independently imported copy lacks deployment/vendor evidence. Host readiness was subsequently accepted in the FCM progress receipt. Cost if wrong: documentation inconsistency, no runtime change.

Only these two add/add conflicts exist. All other main changes merge automatically, including readiness console, privacy page, footer and documentation links. Mobile runtime/tests/toolchain/version remain byte-identical to verified +14. One isolated integration branch receives the merge and automated gates before any main integration.

## Verification plan

- Strict mobile formatter/analyzer and all unit/widget tests on the integrated SHA.
- Stable-signed, Firebase-configured Android +14 APK with source metadata retained as artifact, no FTP.
- iOS unsigned compile on macOS; no claim of APNs configuration, signing or phone acceptance.
- One complete EarthCoop integration validation on this candidate: migrations, route boot, domain regressions and full PHPUnit.
- Independent integration review of conflict resolution and preservation of main/mobile inputs.

Pending: automated receipts. Physical acceptance remains open; original 81-group acceptance stays accepted. Push driver remains disabled. No production merge, data mutation, migration execution on host or APK publication.
