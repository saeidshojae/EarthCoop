# Wallet bootstrap recovery checkpoint — 2026-10-05

User requested continued phone-free work after verified +16. This is the existing wallet recovery bugfix, not the deferred end-of-project Google/network resilience work. Previous independent review's Minor bootstrap classification finding is now resolved in source.

Root cause: runtime's captured identity predicate included pushBootstrap?.allowsProtectedNetwork. onForeground deliberately sets pushBootstrap null before awaiting bootstrapService.start; a wallet response overlapping that pause was classified session_changed, clearing financial data and permanently invalidating the page controller.

Change: repository separately checks identity and network readiness. Identity always takes precedence and current-session getter is identity-only; temporary bootstrap unavailability is a recoverable bootstrap_unavailable failure with no financial GET started when blocked. Successful responses crossing a bootstrap pause are rejected; controller preserves dated prior account/history and permits explicit retry after recovery. Error paths recheck identity without replacing a real API failure with a bootstrap error, preserving authentication-failure clearing. Screen has a localized recoverable message. No new provider, endpoint, money write, persistent financial cache, dependency or platform change.

Verification:
- Missing guard interface RED: 37352038413 / 111905005303, source78c3763d756ef5722f8680c4514cc469340e2ddf; analyzer reports absent isNetworkAllowed. This is missing-interface evidence, not a behavioral run.
- Source8535adeedcb727eb1eeddfce75122dfce1d4812e: https://github.com/saeidshojae/EarthCoop/actions/runs/37352586458 / job111907208463 SUCCESS. Analyzer no issues. Behavioral negative control temporarily omits the network guard and confirms exactly the two bootstrap rejection cases fail without compilation failure, then restores source. Actual corrected focused suite13 repository +9 controller +13 interface tests PASS; complete mobile suite247 PASS. Existing Drift warnings remain.
- Formatter131 files/2 changed; exact formatting patch of runtime/repository retained with this receipt, no behavior change. Superseded37352364821 is not completion evidence.

No new APK was built for this small source fix; previously verified +16 is still the exact historical APK with its previous behavior. Next feature packaging should include this fix and use one newer stable-UAT candidate for consolidated phone acceptance. No claim that +16 binary contains the source correction. No main merge, host APK publication or driver activation.

The next-flow source audit/proposal is docs/MOBILE_NAJM_BAHAR_NEXT_FLOW_AUDIT_20261005.fa.md. Proposed next bounded page extension: activation eligibility and membership-fee status with independent loading/errors, exact units and existing authorization guards; no activation/payment/transfer button. Money writes require their own confirmed-intent/recovery design. The source audit includes server legacy-settings write side effects, idempotency and policy restrictions; live host configuration was not exercised. No financial operation was sent.

Phone/live-account/accessibility/platform/provider/distribution gates and prior unrelated Minor coverage items remain open; latest verified binary remains +16. Google/network resilience remains deferred as instructed. No fresh independent reviewer was dispatched for this focused follow-up; its guard/state behavior was verified by real repository/controller tests, the existing idle-session/logout widgets and negative control.
