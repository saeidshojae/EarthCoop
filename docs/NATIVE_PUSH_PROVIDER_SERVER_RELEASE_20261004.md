# Native push provider server release — 2026-10-04

Bounded main-based release of opt-in FCM HTTP v1 and Android HMS HTTP v1 providers. Base: 856b722f0b33337051a2b4d321c01b8a1e21a8fa. Verified source: 9794e406d93b035dba5385ede9a8fa3eb09dcb91. Workflow: https://github.com/saeidshojae/EarthCoop/actions/runs/37213666405.

28 focused provider, container binding, device registration/release and failure-isolation contracts (95 assertions) passed. Composer validation and production dependency security audit passed. A first run found an incorrect namespace in the new dependency assertion; only the test assertion was corrected before the successful rerun. Production provider code is unchanged from the reviewed native checkpoint.

Default PUSH_DELIVERY_DRIVER=null preserves disabled transport. Opt-in providers need FCM_PROJECT_ID / FCM_SERVICE_ACCOUNT_FILE and/or HMS_CLIENT_ID / HMS_CLIENT_CREDENTIALS_FILE. HMS credentials are client_id/client_secret JSON, not a HarmonyOS JWT service account. No credentials, provider account, live delivery or mobile receipt were configured or verified by this release.

composer.lock changes require the deployment vendor ZIP to be installed separately using the authenticated admin deployment console, operation vendor_package_install, confirmation INSTALL-VENDOR-PACKAGE. FTP upload alone does not activate vendor. Keep transport disabled until package installation and provider configuration are verified. No database migration is added.

The native foundation PR is not part of this merge. Mobile +9 APK and physical-device UAT remain separate. Hosted mobile manifest last independently verified at +5, source 86b1717004affe3db31386edc512cdcb24cadd1b; no mobile artifact is published by this release.

## Deployment receipt and remaining actions

PR194: https://github.com/saeidshojae/EarthCoop/pull/194 . Main merge d1ecab0c494c8108a32908cfedf6854f435a055a. Deployment https://github.com/saeidshojae/EarthCoop/actions/runs/37213860129: Safety Gate, Strict Production Readiness and FTP application/vendor-package upload all passed. Safety regression: 507 tests / 3046 assertions; user-import boundary: 3 tests / 3 assertions. Readiness blockers: 0. composer.lock SHA256: ac1e9af068de443e140fefc5d783a2002bf975f1fb64e952d1f476a1edb3c45b.

Post-deploy public route probe https://github.com/saeidshojae/EarthCoop/actions/runs/37214553460 passed, using the canonical nested error.code and meta.api_version envelope. The initial probe had an incorrect top-level error-code assertion; the observed HTTP status was already 401, and the corrected probe reused the prior canonical check. This checks anonymous API reachability, not authenticated provider configuration or live push delivery.

Vendor installation verified by administrator screenshot: installed_at 2026-10-05T01:23:53+03:30, source d1ecab0c494c8108a32908cfedf6854f435a055a, lock SHA256 ac1e9af068de443e140fefc5d783a2002bf975f1fb64e952d1f476a1edb3c45b, current lock match yes, pending package none. The administrator subsequently created the earthcoop-push-sender service account in earthcoop-3ad00 with Firebase Cloud Messaging API Admin; uploaded its credential to a private host directory and verified file permission 0600; reported setting FCM_PROJECT_ID and FCM_SERVICE_ACCOUNT_FILE in .env. optimize_clear then returned exit 0 (config/cache/compiled/events/routes/views DONE). Host readability, OAuth exchange, FCM project permission and real delivery remain unverified. PUSH_DELIVERY_DRIVER has not been instructed to enable. No secret content is recorded here.

Physical-phone UAT remains OPEN: latest verified stable-UAT candidate update/install (superseding +9), same-group message and retry behavior, notification navigation/read state/offline recovery, attachment save/open, and actual FCM/HMS delivery/tap when configured. User currently has no phone available. No real-device success is inferred from CI.
