# Native mobile remaining work — 2026-10-05

User priority: continue required app development in the existing M6 plan. Google filtering/provider restriction, international/total network loss, independent foreground recovery, cold offline startup and infrastructure dependency audit are explicitly deferred to the final app-completion stage. Do not activate that resilience work now. Keep existing offline/session safety regressions intact.

Verified candidate +13: 208 tests, run 37307643684. Host fcm_readiness accepted: ready true / fcm_validation_ready / exit 0. Physical phone acceptance remains open and must be performed only on the latest verified candidate.

## Current required checkpoint: reachable native logout

M6 plan completion gate requires login/restore/logout and account cleanup. SessionController.logout exists, but HomeScreen/AppRouter/MobileAppRuntime expose no logout action. Add a Persian native logout button on Home; block repeat taps and home navigation while it runs. Enter a non-authenticated session phase synchronously when logout starts, retaining outgoing identity only for existing cleanup hooks. Existing network-revoke, provider disable, secure credential clear and account-cache/queue cleanup order remains intact; unreachable server must not prevent local logout. Return to login and replace protected route history after completion. Scoped APIs and push opens already require authenticated phase, so they reject outstanding/queued old session work immediately.

No dependency, server API, migration, driver activation, new upload or Google/network resilience implementation in this checkpoint. Deterministic tests: missing UI reproduces RED; pending revoke immediately closes authorization while preserving cleanup identity; repeated logout performs one cleanup; unreachable revoke still navigates to login; protected semantic link cannot navigate while logout pending. Full mobile formatter/analyzer/tests and stable UAT +14 build, then independent whole-change review and receipt.

Physical gates remain pending: upgrade, actual logout/login/cache boundaries, notification permission/token/foreground/background/cold/warm tap, same-group messaging/feed and Android attachment save/open. iOS compile/physical and HMS configuration/device acceptance remain open separate platform gates. New attachment upload remains gated by privacy/scanning/association work; do not present it as completed.
