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

Automated receipts are recorded below. Physical acceptance remains open; original 81-group acceptance stays accepted. Push driver remains disabled. No production merge, data mutation, migration execution on host or APK publication.

## Integration review and release boundaries

Independent mobile_integration_review: no Critical, Important or Minor findings. Main changes match main byte-for-byte except the deliberately retained deployment receipt. Mobile runtime, tests, Android/iOS projects, lockfile, toolchain and version match native +14. Strict whitespace and source-preservation checks passed.

Release boundary discovered during readiness inspection: Android release signing still uses the scaffold debug fallback; current artifact is explicitly stable-UAT signed debug, not production/store signing. Do not describe it as a public production release. A production signing/distribution contract and corresponding release build remain necessary before public release.

iOS compile is a build-only gate. Production runtime currently reports platform android for bootstrap/device context; Firebase client define validation is Android-specific. iOS runtime/provider configuration, platform identity, APNs entitlements, signing and physical delivery therefore remain unverified and are not made ready by an unsigned compile. Android remains the current limited-UAT target. This inspection does not alter platform behavior during the frozen integration gate.

## Verified mobile build receipt

Integrated source f14b1381f307354ce12ca90890eae11ee7ec6def, mobile gate https://github.com/saeidshojae/EarthCoop/actions/runs/37323451404 . Android job 111808090172 and iOS job 111808089713 both completed successfully. Formatter: 123 files / 0 changed; analyzer: no issues; all 212 mobile tests passed. Required Firebase Android client configuration and stable UAT signing succeeded. Android APK staged metadata verified. Xcode unsigned iOS build produced build/ios/iphoneos/Runner.app (build only, not signed/exported or device accepted).

Integrated +14 artifact: https://github.com/saeidshojae/EarthCoop/actions/runs/37323451404/artifacts/11351133466 . ZIP size 95187420 bytes; ZIP SHA256 aa4a56b2940f5d8b90ff0aba6658ad8d87457884c432d49a9223b350b6576943; expires 2026-11-04T14:24:27Z. ZIP digest is not inner APK digest. Use this integrated +14 receipt instead of earlier +14 source artifacts for the next consolidated phone run. No version bump or mobile runtime change.

Complete server validation run: https://github.com/saeidshojae/EarthCoop/actions/runs/37323451290 . Completed successfully; job 111808081371. Composer/frontend build, both isolated schemas, route/command boot and all domain stages succeeded. Complete PHPUnit: 2408 tests / 13703 assertions, zero failures, 2 skipped, 47 PHPUnit deprecations. Skips and deprecations are retained as limitations; this is not a claim every test executed or warning-free output. Source-preservation checks and independent review passed.
