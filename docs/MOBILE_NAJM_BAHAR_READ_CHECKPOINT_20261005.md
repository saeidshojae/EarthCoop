# Native Najm Bahar account/history checkpoint — 2026-10-05

User approved the design, written plan and direct execution. Scope: native read-only current-account wallet and paginated owner-scoped ledger; financial writes and Hoda are separate next stages. Baseline verified Release UAT +15; Google/network resilience remains deferred.

Implemented: Home entry and protected /najm-bahar route; typed canonical integer Gol DTOs (100 Gol/Bahar); distinct local/aggregate and available/committed balances; account name/number/status; dated last successful balance; independent account/history failure and retry; newest-first cursor history with duplicate suppression; in-memory data only; captured token/user/device/epoch/bootstrap guards, late-response rejection and controller disposal. No account provisioning, write, persistent financial cache, server/schema/dependency change or arbitrary deep-link expansion.

Actual PHP Pagination::cursor requires page[limit] and page[cursor], not flat limit/cursor. Tests and request use that contract.

Evidence:
- 37338417374 / 111859507733: missing feature DTO/repository before implementation. Initial duplicate 37338365380 cancelled before tests, not behavioral evidence.
- 37338997685: compilation failed on omitted api_envelope import, corrected in 9a52a3e26857fa3019a492a5f0b603560a1c3eb3.
- 37339358248 / 111862183046: 9 repository tests pass; missing controller expected for next task.
- 37339808185 / 111863696710: 9 repository + 6 controller pass; missing screen/navigation parameters before integration.
- 37344707263: formatter succeeds, analyzer 7 brace-style infos; corrected without relaxing rules.
- 37346056944 / 111885046529: 5 navigation tests pass; screen fixture times out before data load under Flutter virtual clock. Explicit 20-second per-widget bound. Repaired harness with tester.runAsync and page-owned scrollable, no product logic change.
- https://github.com/saeidshojae/EarthCoop/actions/runs/37346739233 / job 111887072479, source a0ccc8ac811df531c80a66c11f82b8f6ce2a2719: SUCCESS. Formatter 131 files / 3 changed (exact formatter patch retained into the next source commit), analyzer no issues, focused 9 repository + 8 controller + 9 interface tests pass, complete mobile suite 238 PASS. Existing Drift duplicate-instance test warnings remain; do not claim warning-free test logs.

Independent whole-change review and +16 build are pending. Physical acceptance NOT EXECUTED; no main merge, APK FTP publication or push-driver activation. Host +16 download availability is not claimed.

Rulings: isolated sibling worktree preserves older staged work; remote CI substitutes unavailable local Flutter; existing verified212 baseline reused rather than rerunning unrelated server suite. Actual nested pagination contract takes precedence over draft flat query. Signed build will reuse verified Dart source, strictly check formatting/version/signature and compare the new public certificate digest with actual previously verified +15; no redundant full test run only for build-number change. Platform configuration still requires physical acceptance.
