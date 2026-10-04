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
