# M6 Physical Device Acceptance

This checklist records the hardware-dependent acceptance gates for the Flutter native foundation. A row may be marked PASS only after execution on the stated device. Never commit provider credentials, bearer tokens, raw push tokens, service-account files, or other secrets as evidence.

## Current status

| Gate | Status | Notes |
| --- | --- | --- |
| Deterministic vertical slice | AUTOMATED | Covered by the M6 Task 13 targeted workflow. |
| Offline reconnect/replay policy | AUTOMATED | Covered by deterministic acceptance tests. |
| Push-open typed-link recovery | AUTOMATED | Covered by deterministic acceptance tests. |
| Android GMS physical delivery | NOT EXECUTED | Requires a real GMS-capable Android device and configured FCM deployment credentials. |
| Huawei non-GMS physical delivery | NOT EXECUTED | Requires a real non-GMS Huawei device, HMS app configuration, and configured HMS V3 server credentials. |
| iOS compile | NOT EXECUTED | Requires a macOS runner/Xcode. |
| iOS physical delivery | NOT EXECUTED | Optional for M6 only when a physical iOS device/signing profile is available. |

## Evidence record

For every physical run record:

- exact Git commit SHA;
- UTC execution timestamp;
- device model and OS version;
- app version/build number;
- provider (`fcm`, `hms`, or `apns`);
- result for each numbered step;
- request/correlation IDs where useful;
- provider token only as a non-reversible hash/fingerprint, never the raw token;
- screenshots or logs only after checking that they contain no secrets or personal data.

## Android GMS gate

Prerequisites: configured backend FCM adapter, app-side FCM configuration supplied outside Git, real GMS-capable Android device, test account.

1. Install the exact candidate build.
2. Launch and confirm bootstrap allows the candidate version.
3. Log in and confirm a native device session is created.
4. Confirm push registration reaches the backend as provider `fcm` and the raw token is not returned by the API.
5. Trigger a server-originated EarthCoop notification.
6. Confirm the device receives the notification while the session/device remains valid.
7. Tap the notification and confirm authoritative notification recovery happens before typed deep-link navigation.
8. Confirm the destination is resolved by the semantic-link registry and protected data is still fetched/authorized by the API.
9. Rotate the provider token and confirm the backend registration changes without duplicate delivery to the old token.
10. Log out/revoke the current device and confirm protected push delivery stops.

PASS requires all ten steps on the same candidate SHA.

## Huawei non-GMS gate

Prerequisites: configured backend HMS V3 adapter, Huawei Push Kit app configuration supplied outside Git, real Huawei device without GMS, test account.

1. Install the exact candidate build without Google Mobile Services dependency being required for startup.
2. Launch and confirm bootstrap/session flow works.
3. Confirm provider selection is `hms`.
4. Confirm HMS token registration reaches the backend and no raw token is echoed or logged.
5. Trigger a **server-originated** EarthCoop notification through the HMS V3 adapter. Local-only notification simulation does not satisfy this step.
6. Confirm real delivery on the device.
7. Tap the notification and confirm authoritative recovery precedes typed deep-link navigation.
8. Rotate the HMS token and confirm backend registration updates without duplicate use of the previous token.
9. Log out/revoke the device and confirm protected delivery stops.

PASS requires all nine steps. Token registration without real server-originated delivery is not sufficient.

## Offline reconnect gate on device

1. Start from an authenticated, compatible session and complete one successful bootstrap.
2. Disable network connectivity.
3. Mark one unread notification as read.
4. Confirm the UI updates optimistically and the mutation remains queued with one idempotency key.
5. Re-enable connectivity.
6. Confirm a fresh bootstrap succeeds before replay.
7. Confirm the queued mutation is submitted once with the original idempotency key and removed only after success.
8. Repeat with `update_required=true` or a revoked session and confirm replay remains blocked.

## Deep-link safety gate on device

1. Open a valid `group.detail` notification and confirm it reaches the expected group only after normal authorization/data loading.
2. Open a malformed/unknown semantic link and confirm it falls back safely inside the app.
3. Confirm `fallback_url` is never treated as an authority grant or arbitrary executable route.

## iOS gates

### Compile gate

On a macOS runner with supported Xcode, build the iOS target from the exact candidate SHA. Record Xcode/SDK versions and build result.

### Physical delivery gate

When device/signing access exists, repeat the relevant session, APNs delivery, notification recovery/deep-link, token rotation, and logout/revocation checks. Until then this gate stays NOT EXECUTED; it must never be inferred from Android results.


## Pending user checks — 2026-10-04

Confirmed on installed `1.0.0+5`: update over the previous APK without uninstall,
81 groups including pending cards, and opening group details. Do not repeat these
checks without a new regression reason.

**OPEN: group activity retest.** User enabled
`GROUP_CHAT_FEATURE_FEED_SEQUENCE_V1=true` and
`GROUP_CHAT_FEATURE_DELTA_SYNC_V1=true`, then cleared caches. Phone is temporarily
unavailable. Reopen the same group when it is available; the previous message was
an error (“فعالیت‌های گروه فعلاً دریافت نشد”), not a verified empty feed.
Do not mark this issue resolved until that check.

The next candidate is `1.0.0+6`. Its notification-read queue is connected to the
production screen, persists per account/device, retains retryable failed reads,
and replays after a fresh bootstrap on opening Notifications, retrying that
screen, or returning to the foreground while that screen is open. A sender's own
activity does not provide a recipient notification; use another approved member
when testing real notification navigation.

For the offline-read device check, load an unread notification while online,
disconnect, mark it read, reconnect, and reopen/foreground Notifications. Confirm
the server read state persists after refreshing. Cold offline startup, real push
delivery, and media upload remain separate unaccepted gates. This checkpoint does
not claim those capabilities are complete.

## Native group messages — candidate +6 (2026-10-04)

Candidate source now includes text composition in a live group, acknowledgement,
same-intent retry without duplicates, and refresh of the latest 20 activities.
The backend must deploy `POST /api/v1/groups/{group}/messages` and the additive
`window=latest` feed query before this candidate can send/display recent messages.
A missing endpoint retains the draft and reports that this server version cannot send.
Content is not queued for automatic offline submission.

**OPEN when phone returns:** use an ordinary eligible member in an open group;
send a short message, confirm acknowledgement and the refreshed feed, then check
from another approved member. Confirm an observer cannot send. Disconnect before
an explicit send and confirm draft retention; reconnect and retry the unchanged
text, checking that only one message appears. Refresh during a pending send and
confirm the eventual acknowledgement clears the visible draft and refreshes the feed.
The previously accepted +5 installation and 81-group checks need not be repeated.

Media attachments and real push remain OPEN. Media currently has no
`group.message` purpose, opaque attachment association, authenticated native
retrieval, or completed scan/privacy pipeline. Push token primitives exist, but
production session lifecycle wiring and Firebase/Huawei app configuration are
still absent. Neither is represented as completed by this text checkpoint.
