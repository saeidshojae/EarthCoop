# Native mobile remaining work — 2026-10-05

User priority: continue required app development in the existing M6 plan. Google filtering/provider restriction, international/total network loss, independent foreground recovery, cold offline startup and infrastructure dependency audit are explicitly deferred to the final app-completion stage. Do not activate that resilience work now. Keep existing offline/session safety regressions intact.

Verified candidate +13: 208 tests, run 37307643684. Host fcm_readiness accepted: ready true / fcm_validation_ready / exit 0. Physical phone acceptance remains open and must be performed only on the latest verified candidate.

## Current required checkpoint: reachable native logout

M6 plan completion gate requires login/restore/logout and account cleanup. SessionController.logout exists, but HomeScreen/AppRouter/MobileAppRuntime expose no logout action. Add a Persian native logout button on Home; block repeat taps and home navigation while it runs. Enter a non-authenticated session phase synchronously when logout starts, retaining outgoing identity only for existing cleanup hooks. Existing network-revoke, provider disable, secure credential clear and account-cache/queue cleanup order remains intact; unreachable server must not prevent local logout. Return to login and replace protected route history after completion. Scoped APIs and push opens already require authenticated phase, so they reject outstanding/queued old session work immediately.

No dependency, server API, migration, driver activation, new upload or Google/network resilience implementation in this checkpoint. Deterministic tests: missing UI reproduces RED; pending revoke immediately closes authorization while preserving cleanup identity; repeated logout performs one cleanup; unreachable revoke still navigates to login; protected semantic link cannot navigate while logout pending. Full mobile formatter/analyzer/tests and stable UAT +14 build, then independent whole-change review and receipt.

Physical gates remain pending: upgrade, actual logout/login/cache boundaries, notification permission/token/foreground/background/cold/warm tap, same-group messaging/feed and Android attachment save/open. iOS compile/physical and HMS configuration/device acceptance remain open separate platform gates. New attachment upload remains gated by privacy/scanning/association work; do not present it as completed.

Initial RED source 41c5004696a6b114118f099f41242a740b0d7521, run 37315718384, job 111781828990: 13 pass / 3 fail. Pending logout incorrectly stays authenticated, duplicate cleanup runs twice, native home logout action is absent. Additional RED source 8f3f50d15db38a4d240c935abda5bb4bf44c5b92 checks retry ownership after local cleanup failure. The outgoing identity must survive solely for cleanup retry, while protected actions remain blocked.

Second behavioral RED run 37316040296, job 111782911253: 13 pass / 4 fail, including cleanup retry identity [42, null] rather than [42, 42]. Implementation enters checking phase with cleanup-only outgoing identity, shares pending logout, retains failed cleanup ownership for retry, blocks Home actions immediately, shows a retry on cleanup failure and navigates to login only after successful local cleanup. No new server API or resilience behavior added. Full GREEN +14 verified in the receipt below.

Independent reviewer native_logout_review: no Critical/Important findings in reachable Home logout flow. Minor (deferred): add dedicated widget coverage for local cleanup failure/retry and pending logout runtime disposal; source guard and controller retry tests reviewed. First full candidate run 37316597098 stopped at two Home mounted-if brace style infos before tests/APK; canonical formatting applied and both infos corrected. Ruling: use the existing M6 session/logout contract and complete its missing UI before unrelated feature expansion, following the user's request to continue required development. Google/network resilience stays explicitly deferred.


## Verified +14 receipt

Android 1.0.0+14 source 5fc4c7536ad323335982c753d3a9a33dea929592: https://github.com/saeidshojae/EarthCoop/actions/runs/37316990396 completed successfully. Formatter 123 files / 0 changed, analyzer no issues, all 212 mobile tests passed. Required Firebase client configuration, stable UAT signing, APK build and staged publication verification succeeded. Independent review found no Critical/Important findings.

Artifact: https://github.com/saeidshojae/EarthCoop/actions/runs/37316990396/artifacts/11349465458 . ZIP size 95187420 bytes; ZIP SHA256 f7dae1de413ebd40aba14b9bba87fbb909d094366d7e8cf8f7954e983d5af19b; expires 2026-11-04T13:33:44Z. Digest refers to ZIP, not inner APK. +14 supersedes +13 for one consolidated physical acceptance. Phone tests remain NOT EXECUTED. No APK FTP publication, production merge or push-driver activation. Host FCM readiness passed separately. Google/network resilience remains deferred to final app completion.


## Verified Android Release UAT +15 — final receipt

Verification run https://github.com/saeidshojae/EarthCoop/actions/runs/37331003335 / job 111833765158 succeeded. Exact retained APK build source e01ec2ce1389eec64c9c8b4188f6b98433b82f79; verification source efaef4da4906c4db7fdc9033e4a7328003ff7ba9. The builder passed five signing-policy rejection cases, Debug task graph, formatter (123 files / zero changes), analyzer and all 212 mobile tests; its initial checker failed only on V2 Signer output parsing. Corrected verification reused the exact binary without another Flutter build.

Actual APK: 1.0.0+15, 67,362,191 bytes, SHA256 26f7cb13640ac2b042e28be085fe75ea167b15f6ceca0ba24e531a8242325a9b. Signature verifies; non-debuggable, expected package and Internet permission confirmed. Actual +14 and +15 certificate equality passed, public SHA256 eda5c77121b0bbf1c08b82fb61e59f55a7ea61a2fe0fbbb23537d5bc134c8543. Final artifact https://github.com/saeidshojae/EarthCoop/actions/runs/37331003335/artifacts/11353588861 contains APK and build metadata; ZIP 33,676,704 bytes, SHA256 2cc99218da70bbe7790c853bd885c908c9b1ba4a0310096c8df420debc187426, expires 2026-11-04T15:13:22Z.

Use +15 for one consolidated physical run; historical candidates do not require separate installs. Physical acceptance remains NOT EXECUTED. No FTP APK publication, main merge or push-driver activation. Host download +15 availability is not claimed. Independent review found no Critical/Important findings. Keyless Debug graph coverage is deferred; current unminified Release profile also applies to production, whose credentials/distribution and optimization remain open. Existing Drift test warnings do not constitute warning-free test logs. Earlier deferred review items remain open. iOS +14 unsigned compilation passed, but iOS runtime identity/provider/signing and HMS physical acceptance remain unverified. Google/network resilience remains deferred to final completion.
