# M6 Task 13A Device Blockers Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox syntax for tracking.

**Goal:** Close the physical-device blockers discovered during Task 13 UAT before continuing deep-link, offline, media, and push acceptance.

**Architecture:** Preserve the existing Flutter/GoRouter native foundation. Drill-down navigation must use a real route stack; presentation must not expose backend implementation keys; UAT Android builds must use one secret-backed signing identity across CI runs. Hybrid Native + WebView is recorded as the post-Task-13 direction, not implemented broadly here.

**Tech Stack:** Flutter 3.47.5, go_router, Android Gradle Kotlin DSL, GitHub Actions.

**Spec:** Physical-device findings from M6 Task 13 UAT on 2026-10-02.

## Global Constraints

- Do not merge PR #165 without explicit user approval.
- Do not commit keystores or signing passwords to Git.
- Keep production/store signing separate from UAT signing.
- Use targeted tests first; full validation only at the final gate.
- Keep deep-link resolution declarative while preserving user-initiated navigation history.

## Review Focus

- Android system Back from Group Detail returns to My Groups, then Home.
- Android system Back from Notifications returns to Home.
- Group Detail never renders raw `dimension_key` / `dimension_value_key` values.
- Known Najm Hoda action/risk tokens are human-readable in notifications.
- Consecutive UAT builds use the same secret-backed signing certificate once configured.

---

### Task 1: Back-stack navigation

**Files:**
- Modify: `apps/mobile/test/app/router/router_test.dart`
- Modify: `apps/mobile/lib/app/router/app_router.dart`

- [x] Write failing physical-navigation regression test.
- [ ] Verify RED.
- [ ] Replace user drill-down `go()` calls with stack-preserving navigation while retaining declarative initial/deep-link routing.
- [ ] Verify GREEN.

### Task 2: User-facing presentation

**Files:**
- Modify: `apps/mobile/test/features/groups/group_detail_screen_test.dart`
- Modify: `apps/mobile/lib/features/groups/group_detail_screen.dart`
- Modify: `apps/mobile/test/features/notifications/notifications_screen_test.dart`
- Modify: `apps/mobile/lib/features/notifications/notifications_screen.dart`

- [x] Pin raw-key regressions with failing widget tests.
- [ ] Verify RED.
- [ ] Remove redundant raw group identity keys from detail presentation.
- [ ] Localize known Najm Hoda action/risk tokens at presentation time.
- [ ] Verify GREEN.

### Task 3: Stable UAT signing

**Files:**
- Create: `apps/mobile/test/acceptance/uat_signing_contract_test.dart`
- Modify: `apps/mobile/android/app/build.gradle.kts`
- Modify: `.github/workflows/mobile-uat-publish.yml`

- [x] Add secret-backed signing contract test.
- [ ] Verify RED.
- [ ] Configure debug UAT signing from CI environment only when all four signing values are present.
- [ ] Decode the dedicated UAT keystore from GitHub Actions secrets into the runner temp directory.
- [ ] Fail publication clearly when signing secrets are absent; never fall back to an ephemeral CI certificate for published UAT APKs.
- [ ] Verify GREEN after secrets are configured.

### Task 4: Hybrid product direction

**Files:**
- Create: `docs/mobile/hybrid-native-webview-architecture.md`

- [ ] Record Native Core + WebView Bridge + gradual-native-migration boundaries.
- [ ] Explicitly keep broad WebView implementation outside Task 13.

### Task 5: Final Task 13A gate

- [ ] Flutter format/analyze/tests green.
- [ ] Android signed UAT APK builds and publishes.
- [ ] iOS compile remains green.
- [ ] Physical update-in-place accepted on device after the one-time transition to the stable UAT certificate.
- [ ] Physical Back sequence accepted.
- [ ] Continue Task 13B: typed deep link, offline/replay, media upload, FCM/HMS push.
