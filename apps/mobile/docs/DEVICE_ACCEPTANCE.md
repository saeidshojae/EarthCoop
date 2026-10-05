# M6 Physical Device Acceptance

This checklist records the hardware-dependent acceptance gates for the Flutter native foundation. A row may be marked PASS only after execution on the stated device. Never commit provider credentials, bearer tokens, raw push tokens, service-account files, or other secrets as evidence.

## Consolidated next phone run — latest verified integrated +14

Historical candidate sections below are receipts, not separate installation tasks. Use the latest verified integrated +14 artifact referenced in MOBILE_INTEGRATION_CHECKPOINT_20261005.md. Do not repeat the already accepted 81-group count unless a new regression appears.

| Flow | Required observation | Status |
| --- | --- | --- |
| Upgrade and login | Upgrade with stable UAT signature, open and restore/login to an existing approved account | NOT EXECUTED on latest candidate |
| Same-group activity and text | Refresh the previously affected group, send as an eligible ordinary member, verify acknowledgement and visibility from a second approved member | NOT EXECUTED |
| Message retry | Network failure retains the draft; explicit retry of the unchanged intent yields one message | NOT EXECUTED |
| Existing attachment | Authorized file download, Android destination chooser, save and open the selected file | NOT EXECUTED |
| Logout/account boundary | Logout returns to login; Back cannot restore protected Home; login to a second account exposes only its groups/cache/notifications | NOT EXECUTED |
| Read queue | Load unread online, mark read offline, reconnect, reopen Notifications and verify authoritative read state after fresh bootstrap | NOT EXECUTED |
| FCM registration and foreground | Check permission allow/deny and registration; a real approved server notification refreshes the open inbox | NOT EXECUTED |
| FCM delivery/open | Background and cold/warm tap reach the authorized destination; queued old-account tap cannot open after logout/account change | NOT EXECUTED |

FCM delivery steps require the separate controlled provider activation and approved sender/recipient setup. Host validate-only readiness is already PASS; it does not prove delivery. Keep the delivery driver disabled until that device test is arranged. Never record passwords, bearer tokens, raw push tokens or provider credential files in evidence.

This is Android UAT for existing accounts, not a general production/store release. Production signing/distribution policy, iOS signing/provider configuration and HMS configuration/device acceptance remain separate gates. Google/network resilience is deferred to final app completion at the user's request.

## Current status

| Gate | Status | Notes |
| --- | --- | --- |
| Deterministic vertical slice | AUTOMATED | Covered by the M6 Task 13 targeted workflow. |
| Offline reconnect/replay policy | AUTOMATED | Covered by deterministic acceptance tests. |
| Push-open typed-link recovery | AUTOMATED | Covered by deterministic acceptance tests. |
| Android GMS physical delivery | NOT EXECUTED | Requires a real GMS-capable Android device and configured FCM deployment credentials. |
| Huawei non-GMS physical delivery | NOT EXECUTED | Requires a real non-GMS Huawei device, HMS app configuration, and an Android HMS-compatible server adapter and credentials. |
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

Prerequisites: configured Android HMS-compatible backend adapter, Huawei Push Kit app configuration supplied outside Git, real Huawei device without GMS, test account.

1. Install the exact candidate build without Google Mobile Services dependency being required for startup.
2. Launch and confirm bootstrap/session flow works.
3. Confirm provider selection is `hms`.
4. Confirm HMS token registration reaches the backend and no raw token is echoed or logged.
5. Trigger a **server-originated** EarthCoop notification through the Android HMS-compatible adapter. Local-only notification simulation does not satisfy this step.
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


## Server activation receipt — 2026-10-04

PR #192 merged as `5115a8222b62bfebcdc342682ee6d52130949041` and production
[deploy 37197422692](https://github.com/saeidshojae/EarthCoop/actions/runs/37197422692)
passed its Safety, Strict Readiness and FTP jobs. An unauthenticated POST to the
new message endpoint changed from 404 to 401 with `unauthenticated` and api_version v1.
This proves the route and authentication boundary are live, not that a physical
member has sent successfully. All 57 server contracts passed, including exact
multiline acknowledgement/feed and legacy-break regressions.

The signed +6 artifact from run 37195651590 remains the same; no mobile runtime or
Android build input changed for this server correction. Hosted/installed +5 is
unchanged. Install +6 when the phone returns before testing the composer. The SAME
group-feed retest and all previously open physical gates remain OPEN.

## Push/session follow-up — 2026-10-04

Candidate +7 supersedes +6 for the pending device checks. It adds recovery after a
failed push registration, session-owned provider lifetime, captured bearer/device
credentials, and bootstrap-gated foreground retries. See PUSH_SESSION_CHECKPOINT.md
for the automated test and signed-build receipt (run 37201824043: 167 tests,
formatting, analysis, and stable-signed Android +7 build all passed).
The hosted +5 installation and
accepted 81-group count are unchanged.

**OPEN when the phone returns:** install +7 over the previous stable UAT-signed
installation, verify the real text-message acknowledgement and same-group activity
feed, then verify provider registration/recovery across background/foreground,
account change, and logout. A real server notification is required for delivery
and link-navigation acceptance; a group with no activity is not evidence of failure.
Provider application configuration and notification permission remain prerequisites
for delivery. No hardware row is marked PASS by this follow-up.


## Current candidate +8 — push configuration and attachment API

+8 supersedes +7 for the next physical run. Earlier +6/+7 paragraphs are historical
receipts. +8 corrects Firebase initialization order and accepts validated external
Android client configuration. Missing configuration leaves FCM unavailable.
Install the latest stable-signed candidate over +5 when the phone returns, then
perform the already-open text composer and same-group activity checks. Previously
accepted 81-group counting remains accepted.

FCM/HMS real delivery and notification taps remain NOT EXECUTED. Provider credentials
and permission are prerequisites. HMS Android/server adapter compatibility remains
an open configuration gate. No device result is inferred from automated tests.

Existing legacy attachment retrieval is prepared at the API layer only; it is not
yet deployed and there is no Flutter download action. New uploads remain deferred.
See PUSH_CONFIGURATION_CHECKPOINT.md and GROUP_ATTACHMENT_READ_CHECKPOINT.md.

Automated +8 receipt: run 37204210736 passed 174 Flutter tests, 62 server contracts,
5 configuration tests and stable-signed APK build. Artifact 11304436534 is retained
until 2026-11-03. FCM configuration was unavailable during this build.


## Current candidate +9 — existing attachment download

+9 supersedes +8 for the next phone test. Group file activities show filename and
an authenticated bounded download action, progress/cancellation and the Android
save destination chooser. See GROUP_ATTACHMENT_DOWNLOAD_CHECKPOINT.md for scope
and the exact pending steps. Do not retest earlier candidates separately.

The server API was merged independently through PR #193; its deployment receipt
will be recorded after the deployment run completes. No migrations are added.
New attachment uploads and real provider delivery remain open. Previously pending
composer, same-group feed and notification-read checks remain pending.


+9 receipt: run 37210614102 passed 187 Flutter tests, formatting (112 files),
analyzer and stable-signed APK build. Artifact 11306372747 expires 2026-11-03.
PR #193 production deployment 37210355865 passed Safety, Strict Readiness and FTP.
The workspace's anonymous route probe returned 502 and did not prove live API
reachability; authorized device download acceptance stays OPEN. No migration is
required. FCM client configuration remains unavailable in this candidate.


## Independent production route verification — 2026-10-04

[Probe run 37211498383](https://github.com/saeidshojae/EarthCoop/actions/runs/37211498383)
passed from GitHub's runner. earthcoop.ir /api/v1/me and both earthcoop.ir/net
/api/v1/groups/1/messages/1/attachment returned HTTP 401, error_code unauthenticated
and api_version v1. The unauthenticated attachment route is live and protected.
No authenticated file content was accessed or user/group data changed.

The workspace's earlier HTTP 502 was generated by mitmproxy 12.2.3 with
[Errno 111] Connection refused, not a Laravel JSON response. The independently
successful API responses isolate the observed workspace failure to its connection
path; no server code fix or additional deployment was necessary for that symptom.
Physical authorized retrieval, Android save/open and notification delivery remain
OPEN. This receipt supersedes earlier uncertainty about public route reachability.

## Current candidate +10 — account-owned group cache

+10 supersedes +9 for the next physical run. Source
427cd3fdda6585ef872f8e85d1e7d66db0175fdc was verified in run 37236983954:
193 Flutter tests, formatting 117 files, analysis and Android APK build passed.
Artifact 11315613308 is retained until 2026-11-03T21:47:22Z:
https://github.com/saeidshojae/EarthCoop/actions/runs/37236983954/artifacts/11315613308

Group projections now belong to the captured account/device, old views cannot
return/cache data after a session change, and logout clears the outgoing group
cache. The unowned legacy cache is not imported: load groups online once after
upgrading, then test cached read-only groups offline. When the phone returns,
verify logout/login does not expose old group details/actions. Same-group text,
notification/offline recovery and attachment save/open remain OPEN. Previously
accepted 81-group counting remains accepted. No physical-phone success is claimed.

No +10 host publication is performed by this checkpoint. FCM client configuration
was unavailable during the APK build; live push and the separate server vendor
installation/configuration are still unverified. See
GROUP_ACCOUNT_ISOLATION_CHECKPOINT.md and
../../../docs/NATIVE_PUSH_PROVIDER_SERVER_RELEASE_20261004.md.

## FCM open follow-up — latest candidate +13

+13 introduces Firebase foreground refresh and cold/warm system-notification tap intake. The push link is ignored; the active account must authorize the notification read endpoint, and navigation uses only its returned typed group link. Captured session scope prevents buffered/queued old events and late replies from opening after logout/relogin; disposed runtime cannot navigate. Mounted inbox observes foreground recovery without a lifecycle resume.

Phone unavailable: physical permission allow/deny, token registration, foreground inbox refresh, background delivery, cold/warm tap and account-change safety are NOT EXECUTED. Keep the existing composer/feed, offline read/cache/logout and Android attachment save/open checks pending. Previously accepted 81-group count remains accepted. Test only the latest verified stable-signed candidate once the artifact receipt below is recorded. No +13 host download-page publication is performed.

Server diagnostic PR196 was separately deployed in run 37248899603; the console operation fcm_readiness can validate credentials/OAuth/FCM permission without delivery. Its configured-host execution PASSED on 2026-10-05: ready true / fcm_validation_ready / exit 0. Server push driver stays disabled.

Verified Android 1.0.0+13 source 5c6e125b55df530d819dda231e7c1f083cd364b4: https://github.com/saeidshojae/EarthCoop/actions/runs/37307643684 completed successfully. Canonical and strict formatter: 123 files / 0 changed; analyzer no issues; all 208 mobile tests passed, including three behavioral review regressions. Required FCM client configuration ready, stable UAT signing, Android APK build and staged metadata verified.

Artifact https://github.com/saeidshojae/EarthCoop/actions/runs/37307643684/artifacts/11344029983 . Archive size 95184967 bytes; archive SHA256 94558ddf326046bb74c5e14e3039f98e49b46c8b33040b1616e38e765e0baaae; expires 2026-11-04T12:17:11Z. This is the ZIP digest, not the inner APK digest. +13 supersedes +12 for one consolidated phone acceptance. No FTP APK publication and no real-device delivery/tap acceptance performed.

## Native logout follow-up — +14

When the latest +14 artifact is verified, it supersedes +13 for the single consolidated phone run. Home now has a native logout action. Test successful logout/login, protected route history removal, old-account cache isolation and late notification tap rejection. While logout waits, Home actions must stay disabled and duplicate taps must not repeat cleanup. If local cleanup fails, the retry must stay on Home with protected actions disabled until cleanup succeeds; unread/push/media checks from earlier checkpoints remain pending. Phone unavailable: these physical checks are NOT EXECUTED. Host FCM readiness already PASSED on 2026-10-05; no driver activation or APK host publication accompanies this checkpoint.

User priority: Google/provider filtering, international/total internet loss and their resilience work are deferred to final app completion; see docs/MOBILE_REMAINING_WORK_20261005.md in the repository root. Continue required M6 development now.


## Verified +14 receipt

Android 1.0.0+14 source 5fc4c7536ad323335982c753d3a9a33dea929592: https://github.com/saeidshojae/EarthCoop/actions/runs/37316990396 completed successfully. Formatter 123 files / 0 changed, analyzer no issues, all 212 mobile tests passed. Required Firebase client configuration, stable UAT signing, APK build and staged publication verification succeeded. Independent review found no Critical/Important findings.

Artifact: https://github.com/saeidshojae/EarthCoop/actions/runs/37316990396/artifacts/11349465458 . ZIP size 95187420 bytes; ZIP SHA256 f7dae1de413ebd40aba14b9bba87fbb909d094366d7e8cf8f7954e983d5af19b; expires 2026-11-04T13:33:44Z. Digest refers to ZIP, not inner APK. +14 supersedes +13 for one consolidated physical acceptance. Phone tests remain NOT EXECUTED. No APK FTP publication, production merge or push-driver activation. Host FCM readiness passed separately. Google/network resilience remains deferred to final app completion.
