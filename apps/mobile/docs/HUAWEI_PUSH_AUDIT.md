# Optional Android HMS push audit — 2026-10-04

## Why this path exists

There is one Flutter Android application, not a separate Huawei product. The
runtime selects FCM when Google Mobile Services are available, otherwise HMS when
Huawei Mobile Services are available, otherwise no remote push provider. Selection
is based on installed service capability rather than brand. A Huawei device with
GMS follows the FCM path. In-app notifications and API reads are independent of
remote push delivery.

Official Firebase Flutter guidance requires Google Play services for Android FCM:
https://firebase.google.com/docs/cloud-messaging/flutter/get-started
HMS is the optional provider path for supported Android devices without GMS. No
Firebase/Huawei console project or production provider credentials were created or
verified in this session. Account registration is distinct from an app codebase.

## Confirmed code/API mismatch

The mobile source imports huawei_push (6.15.0+300) and calls Android Push.getToken.
The backend HmsPushDeliveryGateway posts to /v3/{projectId}/messages:send using
payload/target and HmsAccessTokenProvider signs a PS256 service-account JWT.

Official Android HMS documentation instead specifies
/v1/{clientid}/messages:send with an OAuth 2.0 client ID; the official Android
sample obtains an access token with client ID/client secret. The HarmonyOS FAQ
specifies /v3/{projectId}/messages:send and JWT. These are different platform
contracts. The existing server adapter does not match the documented Android
contract, so it must not be represented as ready for the current Android client.

Primary references:
- https://developer.huawei.com/consumer/en/doc/hmscore-references/https-send-api-0000001050986197
- https://developer.huawei.com/consumer/en/codelab/ChatApplication/
- https://developer.huawei.com/consumer/en/doc/harmonyos-guides/push-faq-1

This audit does not change the adapter or activate HMS. Before Android HMS delivery,
use its matching OAuth credentials/request/response contract, supply external app
configuration, verify server transport and then execute the real non-GMS device
acceptance gate. HarmonyOS NEXT must not be inferred as supported by an Android APK.
No extra brand-specific application or new APK is needed for this read-only audit.


## Planned correction — Android HMS

The bounded server correction uses POST /v1/{clientId}/messages:send with a message
object, device token array and android.notification.click_action.type=3. Access
tokens are obtained by the OAuth client_credentials grant from the fixed Huawei
auth endpoint. Server configuration uses HMS_CLIENT_ID and
HMS_CLIENT_CREDENTIALS_FILE (JSON client_id/client_secret), supplied outside Git.
The file client ID must match the configured sender ID. Legacy JWT material is
rejected rather than silently reused. Acquisition failures are not cached; valid
access tokens may be reused within the same provider instance until expiry.
Real HMS accounts/configuration and non-GMS delivery remain unaccepted.

Hosted mobile version check run 37212546568 confirmed both .ir and .net still serve
1.0.0+5, source 86b1717004affe3db31386edc512cdcb24cadd1b, stable-uat signature mode.
+9 remains only the retained GitHub artifact; it was not published to the host.


## Deployment boundary found during correction

Production main 856b722f0b33337051a2b4d321c01b8a1e21a8fa binds
PushDeliveryGateway to NullPushDeliveryGateway. FCM/HMS transport classes and their
provider wiring are currently on the native foundation Draft branch, not deployed
through attachment PR #193. Therefore production remote push activation requires
an independently verified bounded provider-infrastructure release as well as
external credentials/configuration. Existing in-app notification API remains a
separate capability. This correction must not be described as production activation.
No broad foundation merge, provider deployment or APK rebuild was performed in this checkpoint.


## Verification receipt

Android HMS source a31a061dc2a4a109477c60fcc9075c7a81ff753c passed targeted
run 37212896689: 25 tests / 89 assertions, one PHPUnit deprecation. The tests cover Android V1 wire payload, OAuth acquisition/cache,
failed acquisition recovery, sender-ID mismatch, legacy/missing credentials,
provider error normalization, FCM/HMS dispatch and device registration/release
contracts. Credentials are synthetic and HTTP calls are intercepted; no live
provider notification was sent. The APK +9 runtime/build inputs are unchanged.
