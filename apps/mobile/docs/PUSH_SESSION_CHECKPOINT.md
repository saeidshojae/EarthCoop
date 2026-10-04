# Native push session checkpoint — 2026-10-04

## Behavior

The production runtime binds push registration to the authenticated user, device,
and bearer token. Successful restore, login, and token rotation start a session-owned
coordinator without delaying authentication. Changing credentials disposes the old
provider observer before constructing the replacement; late provider selection after
logout or runtime disposal cannot register a token.

Registration retains the latest provider token after a failed HTTP request. A failed
request does not poison the serialized queue. Initialization coalesces in-flight work
but does not cache a failed Future. A provider that initially returns no token is
queried again on foreground recovery. Token rotations are observed before initial
token acquisition, and a stale initial result cannot overwrite a newer rotation.

Foreground recovery first obtains a fresh bootstrap. A blocked or unavailable
bootstrap suspends registration locally; obsolete bootstrap completions cannot
replace a newer decision. The binding checks the live bootstrap/session before
queued writes while using immutable credentials for the HTTP request.

The executable app owns one runtime across rebuilds, forwards foreground events,
and disposes its push resources when removed, including late runtime completion.
Logout uses the existing native-session revoke and push-disable hooks. Failed provider
or registration setup cannot prevent login or local logout. No raw provider token or
exception text is included in push diagnostics.

## Verification and release boundary

RED reproduction: run 37201047991 failed both initial-registration recovery and
rotation-after-failure with a valid HTTP 503 v1 envelope. The first integration run
37201420922 passed the recovery fixes but exposed two test-fixture errors: provider
selection had not started before the simulated logout, and a direct paused-to-resumed
transition violated Flutter's lifecycle contract. Fixtures now wait for factory entry
and use a valid inactive-to-resumed transition; assertions were retained.

Candidate version is 1.0.0+7. Final verification run 37201824043 passed all 167 mobile unit/widget tests,
formatting (105 files unchanged), and analysis (no issues). The stable UAT-signed
Android build passed (assembleDebug: 294.0 seconds). Build source:
31a5bf0990a4b758aa8688490b8d922120d801c1.

Artifact: https://github.com/saeidshojae/EarthCoop/actions/runs/37201824043/artifacts/11302808169

The artifact contains app-debug.apk; archive size is 95,150,582 bytes, expires
2026-11-03, and archive SHA-256 is
2c12cceaea88d4188184ecd0144b0bfa4f0b4a8ead7b44350a1e2c093c47769d.
The hosted installation remains +5; mobile PR #165 remains Draft. This is not a production
push acceptance or an app store release.

## Remaining physical/provider gates

- Provider application configuration must be supplied through the approved external
  configuration mechanism; absence is handled as unavailable, not delivery success.
- Android notification permission, server-originated delivery, foreground/background
  presentation, push-open routing, and non-GMS behavior still need device acceptance.
- Install the newest signed candidate when the phone returns; verify a real group
  message and its same-group feed before testing a real notification event.
- Attachments remain a separate unfinished contract. The existing generic media
  uploader has no group.message purpose, message association, authorized native
  download, or completed privacy/scanning pipeline. Secretariat's configured scanner
  is currently an unavailable adapter and cannot supply completed scan evidence.
