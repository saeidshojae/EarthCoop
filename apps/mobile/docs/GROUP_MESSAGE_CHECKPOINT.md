# Native group text checkpoint — 2026-10-04

`POST /api/v1/groups/{group}/messages` accepts `{"message":"text"}` with the
native bearer/device session and an `Idempotency-Key`. It returns HTTP201 and a
v1 envelope containing `id`, `group_id`, and plain `message`. Text is required,
limited to 2000 characters, and stored through the mature group message flow.
Attachments, replies, and client-provided canonical message IDs are prohibited.

The route group overrides body `group_id` before fingerprinting. Current paid
participation, membership/session policy, and rate limits run before an HTTP
result can replay. A durable server-derived message key protects recovery after
a committed message loses its HTTP acknowledgement. Temporary429 results release
the HTTP claim so an unchanged intent can retry later.

The mobile composer retains its transient draft and intent key after failure,
uses captured account/device credentials, and clears only after acknowledgement.
Acknowledgement refresh belongs to the route-owned controller so replacement of
the input widget during refresh cannot lose it. Group detail requests
`feed/delta?window=latest&limit=20`; the server selects the newest actual rows,
keeps chronological ordering, and projects HTML message/content into plain text.
Older clients can continue requesting their existing delta cursor without `window`.

No migration is introduced. The backend and Flutter changes are reviewable draft
changes; they do not deploy the server or replace the hosted APK by themselves.
Physical acceptance remains in DEVICE_ACCEPTANCE.md.

## Decisions and remaining limits

Reuse of the mature store avoids duplicating participation and event rules; the
adapter remains coupled to that controller until domain-service extraction.
Temporary throttles release claims for all v1 operations; existing native-session,
idempotency, membership, and web message authorization regressions accompany it.
The canonical empty-string checks now preserve valid text `0`.

Deferred cosmetic issue: canonical `nl2br` plus plain-text conversion can add an
extra blank line to multiline text. No media/push/hardware acceptance is inferred
from deterministic tests. Group cache/read account scoping and broader lifecycle
behavior remain separate pre-existing work.

Next attachment work needs `group.message` policy, private media UUID association,
owner/purpose/state checks, scanning/privacy completion, authorized native download,
and a real picker/upload UI. Next push work needs session-owned registration,
rotation/recovery/disposal, provider app configuration supplied outside Git, and
real server-originated delivery on GMS and non-GMS devices.
