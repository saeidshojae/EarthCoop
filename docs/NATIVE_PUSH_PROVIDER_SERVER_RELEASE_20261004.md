# Native push provider server release — 2026-10-04

Bounded main-based release of opt-in FCM HTTP v1 and Android HMS HTTP v1 providers. Base: 856b722f0b33337051a2b4d321c01b8a1e21a8fa. Verified source: 9794e406d93b035dba5385ede9a8fa3eb09dcb91. Workflow: https://github.com/saeidshojae/EarthCoop/actions/runs/37213666405.

Focused provider, container binding, device registration/release and failure-isolation contracts passed. Composer validation and production dependency security audit passed. A first run found an incorrect namespace in the new dependency assertion; only the test assertion was corrected before the successful rerun. Production provider code is unchanged from the reviewed native checkpoint.

Default PUSH_DELIVERY_DRIVER=null preserves disabled transport. Opt-in providers need FCM_PROJECT_ID / FCM_SERVICE_ACCOUNT_FILE and/or HMS_CLIENT_ID / HMS_CLIENT_CREDENTIALS_FILE. HMS credentials are client_id/client_secret JSON, not a HarmonyOS JWT service account. No credentials, provider account, live delivery or mobile receipt were configured or verified by this release.

composer.lock changes require the deployment vendor ZIP to be installed separately using the authenticated admin deployment console, operation vendor_package_install, confirmation INSTALL-VENDOR-PACKAGE. FTP upload alone does not activate vendor. Keep transport disabled until package installation and provider configuration are verified. No database migration is added.

The native foundation PR is not part of this merge. Mobile +9 APK and physical-device UAT remain separate. Hosted mobile manifest last independently verified at +5, source 86b1717004affe3db31386edc512cdcb24cadd1b; no mobile artifact is published by this release.
