# Existing group attachment API checkpoint — 2026-10-04

## Implemented scope

GET /api/v1/groups/{group}/messages/{message}/attachment retrieves files already
attached to legacy web group messages. It requires the existing native bearer and
device middleware, active canonical group membership, matching route/message group,
and the existing web message authorization and file storage resolver. Deleted and
missing files are rejected. Successful downloads use private,no-store and nosniff.

Native file feed snapshots expose attachment file_name, mime_type and a relative
API download_path. Storage paths are not exposed. File identities are loaded in
one group-scoped batch query rather than one query per event.

This checkpoint prepares the server API on the native source branch. It is not
production deployed. The Flutter download action is not yet implemented. Do not
represent this as completed end-user attachment support.

## Deferred new uploads

New native group attachment upload remains unavailable: group.message purpose,
opaque attachment association, scanning and image privacy processing must be
completed before enabling uploads. The generic media ready state is not sufficient
proof of clean scan/privacy processing.

## Verification

Five added contracts cover member downloads, cross-group rejection, removed
membership, deleted/missing files and feed metadata without private storage paths.
The final workflow receipt is appended after a green run.

## Device follow-up

When the mobile download UI is implemented and the small server change is deployed,
use an authorized group with an existing attachment to verify download/opening.
Do not create group activity or notify other members solely as an automated test.

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


## Superseding follow-up

The API has now been independently merged through PR #193 and deployment
37210355865 passed. Candidate +9 adds Flutter download and Android document export;
see GROUP_ATTACHMENT_DOWNLOAD_CHECKPOINT.md for current scope and pending device
acceptance. Earlier statements that the API is undeployed/UI missing describe +8.
