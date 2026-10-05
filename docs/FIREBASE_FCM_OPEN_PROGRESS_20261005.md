# FCM open checkpoint progress — 2026-10-05

Plan: docs/superpowers/plans/2026-10-05-fcm-open.md. Spec: docs/FIREBASE_FCM_OPEN_DESIGN.md.

Server diagnostic PR196 merged as cfb921b1b3f843438bec5222aaa75eaaf81f9977. Deployment run https://github.com/saeidshojae/EarthCoop/actions/runs/37248899603 completed successfully, including safety gate, strict readiness and application FTP sync. Composer lock unchanged: vendor package regeneration/install skipped. Actual host fcm_readiness execution remains pending; transport driver remains disabled. Deployment success does not prove OAuth or FCM authorization on the host.

Native initial RED a2b0c0c68be5059754d777a60b20b75a656a334f, run 37248689860: new AuthorizedPushOpen and FcmMessageBinding classes absent. Candidate beceaf8d8504474f2392cfac4dc7fecea5234c97 adds SDK event intake, owner read, fresh bootstrap, runtime disposal and +13. Static review detected a stray class-level return from source insertion; removed in a7de4f350d1204fb89c844e1fa26491ae253c6a2. No verified APK exists for these candidates.

Independent whole-feature review found two material lifecycle gaps: mounted inbox does not observe background controller recovery, and queued/startup events do not retain their receipt-time session scope. Regression RED f8c3ce9f4b0d43327c6c14f1f5c6332cb21375c0 adds queue/startup scope tests and mounted inbox refresh widget test. Corrections and GREEN evidence pending.

Ruling: CI performs canonical Dart formatting and emits the source diff before strict formatting/analyzer/tests/build — local Dart cannot execute reliably in this workspace — apply the exact normalized diff to the final source; no claim of unformatted source verification.

Final minor (deferred): foreground controller load absorbs failure, so a duplicate receipt may not retry until manual refresh/resume.
Final minor (deferred): combined 128-entry successful-event deduplication permits foreground traffic to evict older open identifiers.

Pending phone acceptance: only latest verified final candidate, upgrade over existing UAT without uninstall; permission denial/grant; token registration; foreground visible inbox refresh; cold/warm notification tap; logout/account-change safety; prior messaging/offline/attachment acceptance. Phone remains unavailable; do not mark these tested.

Review behavioral RED e9babc1db7e170031031dc8603bea97d808b56d8, run 37306965189, job 111752866285: 3 pass / 3 fail. Queued tap incorrectly dispatched second event; startup scope replacement dispatched both; mounted inbox stayed empty after refresh signal. Corrective GREEN now captures scope at receipt/initial startup and connects foreground signals to the visible inbox controller; hidden inbox still uses account-scoped recovery. Compile/analyzer insertion/lint corrections included. Full suite/build evidence pending.

First corrected full run 37307336403 stopped at two analyzer infos (public widget missing key and redundant nested const); no tests/APK ran. Canonical formatting diff applied locally, both infos corrected before the next run. Python Firebase materialization suite: 5/5 passed locally.

Verified Android 1.0.0+13 source 5c6e125b55df530d819dda231e7c1f083cd364b4: https://github.com/saeidshojae/EarthCoop/actions/runs/37307643684 completed successfully. Canonical and strict formatter: 123 files / 0 changed; analyzer no issues; all 208 mobile tests passed, including three behavioral review regressions. Required FCM client configuration ready, stable UAT signing, Android APK build and staged metadata verified.

Artifact https://github.com/saeidshojae/EarthCoop/actions/runs/37307643684/artifacts/11344029983 . Archive size 95184967 bytes; archive SHA256 94558ddf326046bb74c5e14e3039f98e49b46c8b33040b1616e38e765e0baaae; expires 2026-11-04T12:17:11Z. This is the ZIP digest, not the inner APK digest. +13 supersedes +12 for one consolidated phone acceptance. No FTP APK publication and no real-device delivery/tap acceptance performed.

Task 1: complete — RED 37248689860; review behavioral RED 37306965189 (3 pass / 3 fail); GREEN 37307643684 (208/208, analyzer, formatter, stable APK). Final review: fresh reviewer fcm_open_final_review. Final fixed: class-level stray return removed; compilation/analyzer now clean. Final fixed: queued/startup session scope and mounted inbox refresh — named regression tests RED→GREEN, suite 208/208. Previously recorded minors remain deferred; retry/resume remains available. Ruling cost: if canonical source were not copied exactly, artifact/source reproducibility would differ; final run confirms 0 formatting changes and working source matches the verified commit. Native PR165 remains Draft and unmerged pending physical acceptance.

## Host acceptance — 2026-10-05

Administrator screenshot image(20261005-124635).png shows earthcoop.ir/admin/deployment-console fcm_readiness exit code 0 and {"ready":true,"code":"fcm_validation_ready"}. This verifies configured host credential preflight, bounded OAuth and FCM validate-only authorization without delivering a notification. Installed vendor package matches current Composer lock; no pending vendor package. Host diagnostic gate is now PASSED, superseding earlier pending notes. Real device token registration, actual delivery/cold/warm tap and phone acceptance remain OPEN. No transport driver activation is inferred or performed.
