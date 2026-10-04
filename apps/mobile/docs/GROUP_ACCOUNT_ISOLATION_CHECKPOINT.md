# Group account/session isolation — 2026-10-05

The +10 candidate moves group projections from the legacy shared app database to
the existing user/device account database. Group list/detail repositories capture
the view's bearer token and device ID. An epoch/token/user/device guard rejects old
views before requests and before returning responses or accessing the cache.
Activity, unread and mark-read operations use the same guard. Cache operations
check the owner around asynchronous database work and inside write transactions.
Logout clears the outgoing account's group projections alongside notification
state and the offline queue. Database opening is lazy so unused views do not start
an unobserved account-database operation.

The old shared group cache is not imported: it has no trustworthy account owner.
The first online group read after upgrading populates the new account cache.
Existing stale/read-only behavior is retained; pending group rows are still not
cached. No message content, attachment bytes or draft is persisted by this change.
No server API, dependency, permission or server migration changes are introduced.

RED source d2ae49ef21cc92140f6b16290546883e9d0f19f5, run 37236594945:
two behavior tests failed as expected because closed-account cache errors were
swallowed. The first returned a live projection; the second returned the retryable
network failure instead of the terminal session-changed failure. No compilation
error caused these failures.

Candidate source 427cd3fdda6585ef872f8e85d1e7d66db0175fdc, workflow
https://github.com/saeidshojae/EarthCoop/actions/runs/37236983954 . Added scenarios
cover closed-account cache signals, late list responses after logout, blocked old
view activity/read mutations, same-ID groups across users/devices, account-local
logout cleanup and a delayed database open spanning logout. Full mobile formatting,
analysis, 193 deterministic tests and APK build all passed.

Physical acceptance remains OPEN: install the latest candidate over the existing
stable-signed app, load groups online once, reopen offline, then logout/login and
verify old group details/actions are not shown for another account. Existing +9
attachment save/open, same-group text/composer and notification recovery checks
remain open. Previously accepted 81-group counting is not reopened by this fix.

Push runtime/vendor installation and real provider configuration remain separate
unverified prerequisites. This workflow retains the APK as a GitHub artifact and
does not publish a new APK to the host.

## Final +10 receipt

Run 37236983954/job 111537980251 completed successfully on source
427cd3fdda6585ef872f8e85d1e7d66db0175fdc: formatting 117 files (0 changed),
analyzer with no issues, 193 tests, validated Android configuration materializer,
stable-UAT signing setup and Android APK build. Firebase client configuration was
unavailable; no live push result is inferred. Physical install/update is untested.

Artifact 11315613308: 95,180,444 bytes; archive SHA256
77ff1af6194a120370fbab0fcf5381dee5e808c583c0dbd54298559ab17af003;
expires 2026-11-03T21:47:22Z. Download:
https://github.com/saeidshojae/EarthCoop/actions/runs/37236983954/artifacts/11315613308

The deliberate concurrent-account database test emitted Drift's multiple-database
warning; its instances use different files/executors and are closed in teardown.
Existing Android dependency/Kotlin/Java deprecation warnings also remain. Neither
warning category failed analysis, tests or the build; no unrelated dependency
upgrade or warning suppression was introduced.
