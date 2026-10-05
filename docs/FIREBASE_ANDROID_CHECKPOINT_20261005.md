# Firebase Android checkpoint — 2026-10-05

Android 1.0.0+11 source c3bcfaf19048b61e02b6cb0dae5e276d8c5a4d96. CI https://github.com/saeidshojae/EarthCoop/actions/runs/37243023358 completed successfully: stable UAT signing, formatter/analyzer, all 193 Flutter tests, required client configuration materialization (FCM client configuration: ready), APK build and artifact staging. The administrator added EARTHCOOP_FIREBASE_ANDROID_JSON_BASE64 to repository Actions secrets. Actual client JSON and server credentials remain outside Git.

Artifact 11318635304: https://github.com/saeidshojae/EarthCoop/actions/runs/37243023358/artifacts/11318635304 . Archive size 95180436 bytes. Archive SHA256 0a26cde8cbc47ceae8395e26f4bff6a492888e96745dbdee403a9c80b8361636. Expiry 2026-11-03T23:21:17Z. This digest is the ZIP archive digest, not the inner APK digest. No FTP publication was performed.

A subsequent source audit found no notification runtime permission request before FCM getToken. Android 13+ needs this permission. The +11 build verifies embedded configuration and compilation; it does not establish phone receipt. A bounded correction is being tested separately before recommending a phone build.

Open host checks: configured project, private credential readability and structural validity, bounded OAuth exchange, FCM authorization without sending an actual notification, driver enablement and worker behavior. Current deployment console does not expose an FCM-specific diagnostic operation. Do not equate optimize_clear with a provider connection check.

Open phone UAT: latest candidate upgrade over stable UAT, permission allow/deny, token registration, background delivery and notification tap, same-group message/retry, account-owned offline cache, notification read/offline recovery and attachment save/open. Only the latest verified candidate should be tested; phone unavailable at checkpoint.

## Confirmed follow-up from source audit

FCM tap wiring is incomplete: PushOpenIntake exists and decodes typed links, but has no production call site; FirebaseMessaging.onMessageOpenedApp and getInitialMessage have no production binding. Existing in-app notification navigation must not be described as system-notification tap handling. This is an implementation follow-up, not just a phone test. Foreground FCM reception also has no SDK listener in production. Keep server driver disabled until host diagnostics and the remaining client integration are reviewable.

Permission correction: RED source 22934116a2f7e612814d58dd974c363410fac063, run 37245765278, two expected assertion failures (unapproved token obtained before permission; denial returned a token). Candidate b5a95497b1e94a444e41339897c5fc6b4e6699fc adds permission gating before refresh subscription/token acquisition and preserves disposal checks after the prompt. The first GREEN candidate run 37245961897 stopped at formatter width for one new test declaration; the declaration was wrapped, without changing assertions or runtime behavior. Verified +12 source 0d50271c1301e5c3837c8d2e47dfe10a5f4eeaf6, run https://github.com/saeidshojae/EarthCoop/actions/runs/37246188450 : formatter 117 files / 0 changed, analyzer no issues, all 197 tests passed, required FCM configuration ready, stable-signed Android APK and staged artifact verified. Local Dart formatting could not execute (runtime stack-bounds error); remote formatter is the verification evidence. Tests cover ordering, denial, later grant and disposal while prompt is pending.

## Next bounded server checkpoint (no phone required)

Add an authenticated deployment-console operation dedicated to FCM readiness. Report only categorical statuses, not key contents, access tokens or provider response bodies. Check configured project, readable private JSON, expected service_account type, matching project/client identity, required credential fields and installed Google Auth class. Obtain an OAuth token with explicit bounded network timeouts and report success/failure without disclosing it. The official send API documents validate_only as testing without delivering (https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages/send). A separate FCM HTTP v1 validate_only probe may establish project authorization only if its payload is accepted; it must never send a notification. Keep this operation independent of PUSH_DELIVERY_DRIVER so diagnostics do not enable transport. Existing location-governance readiness is not an FCM diagnostic. Host execution remains a separate acceptance step.

Client follow-up must bind actual FCM SDK open events, including cold start, to the authenticated semantic-link resolver and notification recovery. Verify closed session, duplicate events, malformed payload and foreground behavior with deterministic tests before one consolidated latest-build phone UAT. Do not infer delivery or tap success from SDK configuration alone.

## Latest candidate artifact

Android +12 supersedes +11 for subsequent phone UAT. Artifact https://github.com/saeidshojae/EarthCoop/actions/runs/37246188450/artifacts/11319222557 , archive size 95185174 bytes, archive SHA256 59de21215a253ed2307f6f667ab073043204c10d0156ad06eca1f5d306d6783a, expires 2026-11-04T00:13:37Z. No FTP publication or phone test was performed. Source receipt commit only adds documentation after this verified source, avoiding a duplicate full build.

Python Firebase materialization regression suite: python -m unittest discover -s tests/mobile -p test_firebase_configuration.py, 5 tests passed locally. This validates the script, not host credentials. An initial discovery in scripts/mobile found zero tests; the actual test directory was then used. No local Flutter test success is claimed.
