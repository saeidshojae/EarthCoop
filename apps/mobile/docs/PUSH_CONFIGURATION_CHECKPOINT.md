# Android push configuration checkpoint — 2026-10-04

## Scope

Candidate 1.0.0+8 fixes an actual SDK boundary failure in +7: subscribing to
FirebaseMessaging.instance.onTokenRefresh before Firebase initialization raised
[core/no-app]. The source now exposes its own safe stream, initializes Firebase,
attaches the SDK stream, then obtains the initial token. Failed initialization can
retry; disposal blocks late acquisition and cancels token observation.

Without client configuration, production returns no FCM token source. This is an
explicit unavailable state, not proof that push works. No Firebase/Huawei account
configuration has been supplied or verified by this checkpoint.

## Supplying client configuration

Obtain the Android google-services.json for package
`coop.earthcoop.earthcoop_mobile` from the project owner. Store its base64 content
in GitHub Actions secret `EARTHCOOP_FIREBASE_ANDROID_JSON_BASE64`. Do not commit the
file. This is Android client configuration, never a service-account private key.

`scripts/mobile/firebase_configuration.py` validates the exact matching client,
project number and Android app ID, rejects server credential fields, and generates
both Dart defines and Android XML resources from the same client. The Android
resources permit native default-app initialization in a background process.
Generated files are ignored; configuration values are withheld from CLI output.

The manual mobile UAT workflow accepts `require_fcm_configuration=true` to fail
if the client configuration is missing. It defaults to an explicitly unavailable
FCM build and records `fcm_configured` in build.json. Host publication remains a
separate opt-in. For a local authorized build, run:

```bash
python scripts/mobile/firebase_configuration.py --input /secure/google-services.json \
  --output apps/mobile/.generated/firebase-defines.json \
  --android-resources apps/mobile/android/app/src/main/res/values/earthcoop_firebase.xml
```

Pass the generated defines file to `flutter build apk --debug
--dart-define-from-file=.generated/firebase-defines.json` from apps/mobile.

Backend FCM credentials must be provisioned separately on the server; never put
server credentials in the APK client secret. No account operation was performed.

## Remaining acceptance

Real GMS delivery, Android notification permission, foreground/background behavior,
notification taps and account/logout recovery require configuration and a physical
device. The Android Huawei token source and existing server V3 adapter still need
protocol compatibility confirmation before claiming non-GMS delivery. HMS app
configuration is absent; no HMS readiness claim is made.

Historical +7 receipt remains in PUSH_SESSION_CHECKPOINT.md. The final +8 receipt
is appended after the exact candidate passes its workflow.

## Final automated receipt

Verified source: `a0587178c7c8b7b29ef5e69bee37729aff3e8d8d`.
[Targeted run 37204210736](https://github.com/saeidshojae/EarthCoop/actions/runs/37204210736)
passed: 174 Flutter tests, formatting of 108 files without changes, analyzer with
no issues, 5 configuration CLI tests, 62 server contracts / 356 assertions and
stable-signed Android 1.0.0+8 compilation. PHPUnit reported one deprecation;
no contract failed.

[Signed +8 APK artifact](https://github.com/saeidshojae/EarthCoop/actions/runs/37204210736/artifacts/11304436534)
expires 2026-11-03. Artifact ZIP digest (not APK digest):
`df5f02847e86bbff86ea76b5770d490ebe60032de57fb1a7ac16eb47d3dba36e`.
Build output explicitly reported FCM client configuration unavailable. No real
push delivery or device result is claimed. The hosted/installed +5 is unchanged.
