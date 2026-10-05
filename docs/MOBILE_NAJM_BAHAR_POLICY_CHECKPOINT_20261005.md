# Najm Bahar read-only policy delivery — 2026-10-05

User approved continued required development without a phone and the bounded activation-eligibility/membership-fee observation design. This extends the existing wallet page, retaining independent account/history behavior. No activation, payment or transfer command is implemented. Client requests use GET only; the existing server legacy-policy fallback can migrate old settings, so client read-only does not imply absolute server storage immutability.

## Implemented behavior

- Activation and membership sections use their real authenticated endpoints, strict integer/unit validation, participation points separately from Gol, exact Gol/Bahar formatting, server membership year, fee breakdown and paid status.
- Each section has independent refresh, loading, localized failure and last successful receipt time; a stale retained value is explicitly labelled. Activation disabled (403) does not conceal successful membership data. Missing account does not become a fabricated zero.
- Captured user/token/device/logout boundaries clear wallet/history and both policies. Any HTTP 401, including a malformed envelope, invalidates the entire view and outstanding generations. Temporary bootstrap unavailability remains retryable without authorizing financial writes or storing money offline.
- Runtime owns and disposes both controllers and connects reciprocal, latched invalidation. Old wallet-only injection and tests remain supported.

## Software verification and review

Initial missing-feature RED: run37353742928 / job111910780910, sourcecd5bf14348c0fd41288924831a1bfa65d816e3cd. Missing controller/UI imports and repository methods are compilation/interface evidence, not a behavioral run.

Initial product full GREEN: run37354617323 / job111913745202, sourcefeea211ff66eef7d45ac78fa772c17876f57080b. All seven new cases and complete suite succeeded.

One independent whole-change review (`wallet_policy_review`) found one Important issue: malformed HTTP 401 could retain financial information because invalidation recognized error codes only. No Critical or additional Minor finding. Accepted: recognize status401 in both wallet and policy controllers, with regression tests for account and eligibility origins and a delayed membership success. Reviewer inspected source/contracts; did not execute CI, APK or live account/device behavior. One fix-pass, no second review.

First regression attempt37354991589 failed to compile because a conditional mixed Future and Map; it is NOT behavioral evidence. Superseded37355131823 was cancelled. Correct behavioral RED: source1bf30152dbba75c36e21878fafe1287fa85028df, run37355166747 / job111915784592: seven pass/two fail; both failures actually retained NajmBaharAccount after malformed401.

Final corrected source18bfd21974fc05ba6691411ead30b13a52319726: https://github.com/saeidshojae/EarthCoop/actions/runs/37355385308 / job111916366862 SUCCESS. Nine policy tests, thirteen repository tests, nine wallet-controller tests, thirteen wallet-interface/navigation tests; complete suite **256 tests passed**; analyzer no issues. Existing Drift warnings and dependency/action notices remain. Exact formatter patch retained in f2736d89e14e81f6edaa6b0518e20fa6be5d048e; no behavior change.

## Binary receipt

Packaging source faa0bcf2ddbca7fdc0341951cdce8e14d3b65685 changes pubspec16→17 and adds a build/verification workflow after the normalized tested source. Product code is identical. This candidate includes the previous source-only bootstrap recovery from84c8da1f33a24f11fb3784fc6e3a9bd049d25b0c. Actual single Release UAT build and package verification SUCCESS: https://github.com/saeidshojae/EarthCoop/actions/runs/37355780177 / job111917696328. Strict formatter passed with no source normalization needed. Actual APK package coop.earthcoop.earthcoop_mobile, versionName1.0.0/versionCode17, Internet permission, nondebuggable release and stable certificate verification pass. Actual +17 public certificate SHA256 equals verified+16: eda5c77121b0bbf1c08b82fb61e59f55a7ea61a2fe0fbbb23537d5bc134c8543. Required FCM client configuration was materialized; this does not activate server push delivery.

Final artifact (APK + build.json + download page): https://github.com/saeidshojae/EarthCoop/actions/runs/37355780177/artifacts/11364836579 . Inner APK **67,591,699 bytes**, SHA256 **0d9efad192a10f150e34e6ef4a24f3be91f572e5e828122ca61dc1cef4acef17**. Artifact ZIP33,776,040 bytes; ZIP SHA25694f23c09b443c70dd64d1d05ac1f78b1d15cdfd80d095f4b8b1d46904335bc13; expires2026-11-04T18:32:10Z. ZIP hash and inner APK hash are distinct. Diagnostic artifact11364861461 is not the final download artifact. These figures come from actual successful package/staging verification and GitHub artifact metadata, not inferred from pubspec.

Independent retained-file comparison is performed by a read-only download-artifact receipt workflow at source18875987825219ce19eda36674b2cecdba76ab8b; it runs no Flutter build. Actual retained-file comparison SUCCESS: https://github.com/saeidshojae/EarthCoop/actions/runs/37356780349 . The downloaded APK independently matches the fixed builder digest/size and build.json (version17, exact build SHA, stable-uat, release, FCM configured). No Flutter rebuild or financial/provider operation occurred.

## Outstanding gates

Use one latest verified Android candidate for consolidated phone acceptance. Physical upgrade/login, RTL/text scaling/accessibility, wallet/site comparison, policy loading/errors/units/year, real missing accounts, idle/account/logout/401 boundaries, groups/messages/attachments/notification queue and FCM delivery/open remain NOT EXECUTED here. The accepted81-group count needs no repeat absent regression. Production distribution/signing/optimization, iOS/HMS configuration/runtime acceptance, new attachment-upload privacy/scanning and dedicated older Minor coverage remain open. Earlier unsigned iOS+14 compile is historical only.

No main merge, host APK FTP publication, push-driver activation or actual financial transaction. Host download availability is not claimed. Draft PR165 remains Draft. Financial writes require a later confirmed-intent/idempotency/recovery contract; Hoda remains later with failed-send persistence addressed. Google/network resilience is explicitly deferred until final app completion.
