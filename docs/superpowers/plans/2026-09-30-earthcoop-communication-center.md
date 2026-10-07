# EarthCoop Communication Center Implementation Plan

> **Execution status — 2026-10-07:** This plan has been implemented and closed for Communication Center v1. The granular unchecked boxes below are retained as the original execution recipe/history and are **not** a live completion tracker. The authoritative as-built status is `docs/operations/COMMUNICATION_CENTER_CLOSURE_STATUS.md`. Time/Temporal integration was subsequently completed and merged into `main`.


> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a production-grade, programmable Communication Center for EarthCoop that supports event-driven, scheduled, and delayed-conditional email; template/version management; sender identities; user preferences; queued reliable delivery; campaigns; audit; registration welcome; and weekly role-aware reports while preserving current working email flows during migration.

**Architecture:** EarthCoop domains emit registered events or provide safe report context. The Communication Center resolves rules, audience, classification/preferences, immutable template versions, sender identity, scheduling, persistence-backed deduplication, queue priority, delivery, retry, and audit. Existing email paths move to this core incrementally through a strangler migration; direct `Mail::*` remains temporarily allowed only for unmigrated legacy paths and the canonical delivery adapter.

**Tech Stack:** PHP 8.2+, Laravel 12, Eloquent, Laravel Queue, Laravel Scheduler, Blade/Tailwind admin UI, PHPUnit 11.

**Spec:** `docs/superpowers/specs/2026-09-30-earthcoop-communication-center-design.md`

## Global Constraints

- Never implement directly on `main`; execution starts from an isolated branch/worktree based on the approved spec/plan branch.
- Preserve support email threading (`Message-ID`, `In-Reply-To`, `References`) and reply correlation during migration.
- `SystemIdentityService` remains an in-app actor concern; outbound sender identity is separate and may optionally link to it.
- SMTP/provider credentials and API secrets remain in environment/secret configuration, never Communication Center tables/admin forms.
- No arbitrary SQL/PHP/Blade execution or free executable rule expressions in admin automation UI.
- Email is the only active v1 channel; do not build SMS/WhatsApp/full push or speculative digest machinery.
- Published template versions are immutable; editing published content creates a new version.
- `required` bypasses suppressive preferences; `operational` is default-on but user-adjustable where policy permits; `optional` honors opt-in/out policy.
- Registration welcome is `operational`, default-on, and deduplicated.
- All normal outbound mail is queued; domain/HTTP requests never wait on SMTP.
- Deduplication is enforced by storage uniqueness plus transactional handling, not only `exists()` checks.
- Large audiences are chunked and never loaded into a single admin dropdown.
- Scheduler orchestrates due work and delayed checks; it never sends SMTP directly.
- Development uses targeted tests; full validation runs only at release checkpoints/final gate to protect CI time.

## Review Focus

1. Every successful registration Step3 branch must emit exactly one completion event; repeated submission/event delivery must not duplicate welcome mail.
2. Queue retry, worker crash, and scheduler re-entry must converge on one logical communication while preserving append-only attempts.
3. Required mail must remain unsuppressible; operational/optional preferences must behave independently and safely.
4. Support migration must preserve sender, reply-to, ticket metadata, `Message-ID`, `In-Reply-To`, and `References`.
5. Campaign pause/cancel must stop pending work only and remain safe while workers process chunks concurrently.

---

## File Structure Map

### Domain/data
- `app/Enums/Communication/CommunicationClassification.php`
- `app/Enums/Communication/CommunicationStatus.php`
- `app/Enums/Communication/DeliveryStatus.php`
- `app/Models/CommunicationTemplate.php`
- `app/Models/CommunicationTemplateVersion.php`
- `app/Models/CommunicationSenderIdentity.php`
- `app/Models/CommunicationRule.php`
- `app/Models/CommunicationRuleSchedule.php`
- `app/Models/CommunicationCampaign.php`
- `app/Models/CommunicationRun.php`
- `app/Models/Communication.php`
- `app/Models/CommunicationRecipient.php`
- `app/Models/CommunicationDeliveryAttempt.php`
- `app/Models/CommunicationPreference.php`

### Core services
- `app/Services/Communication/CommunicationEventRegistry.php`
- `app/Services/Communication/CommunicationConditionRegistry.php`
- `app/Services/Communication/CommunicationAudienceRegistry.php`
- `app/Services/Communication/CommunicationTemplateService.php`
- `app/Services/Communication/CommunicationTemplateRenderer.php`
- `app/Services/Communication/SenderIdentityResolver.php`
- `app/Services/Communication/PreferenceDecision.php`
- `app/Services/Communication/CommunicationPreferenceService.php`
- `app/Services/Communication/CommunicationDispatcher.php`
- `app/Services/Communication/CommunicationRuleEngine.php`
- `app/Services/Communication/CommunicationScheduleService.php`
- `app/Services/Communication/CommunicationCampaignService.php`
- `app/Services/Communication/DeliveryFailureClassifier.php`
- `app/Services/Communication/EmailDeliveryAdapter.php`

### Jobs/commands
- `app/Jobs/Communication/DeliverCommunicationRecipient.php`
- `app/Jobs/Communication/ResolveCommunicationRunAudience.php`
- `app/Jobs/Communication/EvaluateDelayedCommunicationCondition.php`
- `app/Console/Commands/CommunicationProcessDueRules.php`

### First automations
- `app/Events/RegistrationCompleted.php`
- `app/Listeners/DispatchRegistrationCompletedCommunication.php`
- `app/Services/Communication/Context/WelcomeCommunicationContextBuilder.php`
- `app/Services/Communication/Context/WeeklyMemberReportContextBuilder.php`
- `app/Services/Communication/Context/WeeklyManagerReportContextBuilder.php`
- `app/Services/Communication/Context/WeeklyInspectorReportContextBuilder.php`

### Admin/UI
- `app/Http/Controllers/Admin/Communication/*`
- `app/Http/Controllers/Profile/CommunicationPreferenceController.php`
- `resources/views/admin/communications/*`
- `resources/views/profile/communication-preferences.blade.php`
- `routes/communication-center.php`

### Tests
- `tests/Feature/Communication/*`
- `tests/Unit/Communication/*`
- `tests/Architecture/CommunicationDeliveryBoundaryTest.php`

---

### Task 1: Canonical schema, enums, and models

**Files:**
- Create: `database/migrations/2026_09_30_180000_create_communication_content_tables.php`
- Create: `database/migrations/2026_09_30_180100_create_communication_rule_and_campaign_tables.php`
- Create: `database/migrations/2026_09_30_180200_create_communication_delivery_and_preference_tables.php`
- Create: the three communication enums and eleven communication models listed above.
- Test: `tests/Feature/Communication/CommunicationSchemaTest.php`

**Interfaces:**
- Produces all canonical relations for later tasks.
- `communications.deduplication_key` has a unique database constraint for non-null deterministic keys.
- `communication_recipients` references the exact immutable template version used for that recipient/history.
- Delivery attempts are append-only records.

- [ ] **Step 1: Write RED schema tests** for all tables, FKs/indexes, structured JSON columns, immutable-version references, append-only attempts, and unique dedupe key.
- [ ] **Step 2: Run** `php artisan test tests/Feature/Communication/CommunicationSchemaTest.php`; expect RED because tables/models do not exist.
- [ ] **Step 3: Implement the three focused migrations plus enums/models** with Eloquent casts and no digest table.
- [ ] **Step 4: Run** `php artisan test tests/Feature/Communication/CommunicationSchemaTest.php` and `php artisan migrate:fresh --env=testing`; expect PASS.
- [ ] **Step 5: Commit** `feat(communication): add canonical communication data model`.

**Checkpoint:** existing mail behavior is untouched.

---

### Task 2: Registries, template versioning, rendering, and sender identities

**Files:**
- Create the six registry/template/sender services listed in the file map.
- Modify: `app/Services/SystemIdentityService.php` only if a resolver adapter is necessary; never merge the concepts.
- Modify/adapt legacy template integrations only through compatibility code: `app/Observers/NajmHoda/FounderManagedContentObserver.php`, `app/Services/NajmHoda/FounderOps/FounderEmailTemplateDecisionService.php`, `app/Services/NajmHoda/FounderOps/FounderEmailDraftService.php` where required.
- Test: `tests/Unit/Communication/CommunicationRegistryTest.php`
- Test: `tests/Feature/Communication/TemplateVersioningTest.php`
- Test: `tests/Feature/Communication/SenderIdentityTest.php`
- Re-run: `tests/Feature/NajmHoda/FounderEmailManagementConnectivityTest.php`

**Interfaces:**
- `CommunicationTemplateService::publish(...)` creates the next immutable localized version.
- `CommunicationTemplateRenderer::render(CommunicationTemplateVersion $version, array $context): array{subject:string,body:string}`.
- `SenderIdentityResolver::resolve(string $key): CommunicationSenderIdentity`.
- Unknown registered keys/required variables fail closed.

- [ ] **Step 1: Write RED tests** for unknown event/condition/audience rejection, immutable published versions, next-version publication, missing required variable failure, and sender records containing no transport secrets.
- [ ] **Step 2: Add RED compatibility/import tests** for importing existing `EmailTemplate` into canonical template+v1 and existing `SystemEmail` into canonical sender identity without deleting source rows.
- [ ] **Step 3: Implement minimal registries and services** only for approved v1 use cases (`registration.completed`, event user/specific user, role audiences, initial report conditions).
- [ ] **Step 4: Adapt Najm Hoda managed-template connectivity to the canonical template service while preserving its existing tests/approval behavior.
- [ ] **Step 5: Run targeted tests and commit** `feat(communication): add safe templates registries and sender identities`.

---

### Task 3: Preference policy and idempotent logical dispatch

**Files:**
- Create: `app/Services/Communication/PreferenceDecision.php`
- Create: `app/Services/Communication/CommunicationPreferenceService.php`
- Create: `app/Services/Communication/CommunicationDispatcher.php`
- Test: `tests/Feature/Communication/CommunicationPreferenceTest.php`
- Test: `tests/Feature/Communication/CommunicationDeduplicationTest.php`

**Interfaces:**
- `PreferenceDecision` is a small value object carrying at least `allowed: bool` and a stable reason key for audit (`required_bypass`, `default_operational`, `user_allowed`, `user_suppressed`, `optional_not_opted_in`).
- `CommunicationPreferenceService::decide(User $user, string $topicKey, CommunicationClassification $classification): PreferenceDecision`.
- `CommunicationDispatcher::dispatch(string $purposeKey, array $source, array $recipients, array $context, array $options = []): Communication`.

- [ ] **Step 1: Write RED preference tests** for required bypass, operational default-on, explicit operational suppression, optional off/opt-in, and missing/invalid email handling.
- [ ] **Step 2: Write RED duplicate-race tests** proving two equivalent dispatches with the same deterministic key create one logical communication.
- [ ] **Step 3: Implement preference resolution and transactional dispatch**, resolving duplicate-key races by loading the already-created canonical row.
- [ ] **Step 4: Run targeted tests and commit** `feat(communication): add preferences and idempotent dispatch`.

---

### Task 4: Queued delivery, priority, retry, and delivery attempts

**Files:**
- Create: `app/Services/Communication/EmailDeliveryAdapter.php`
- Create: `app/Services/Communication/DeliveryFailureClassifier.php`
- Create: `app/Jobs/Communication/DeliverCommunicationRecipient.php`
- Modify: `config/queue.php` only if documentation/default queue mapping requires it; do not add an external queue product.
- Test: `tests/Feature/Communication/CommunicationQueueTest.php`
- Test: `tests/Feature/Communication/CommunicationRetryTest.php`
- Test: `tests/Feature/Communication/PermanentFailureTest.php`

**Interfaces:**
- Queue names: `communications-critical`, `communications-normal`, `communications-bulk`.
- Each real attempt appends `CommunicationDeliveryAttempt`; prior attempts are never overwritten.
- Delivery adapter is the canonical Laravel Mail boundary for migrated flows.

- [ ] **Step 1: Write RED tests** proving logical dispatch queues mail rather than sending synchronously and maps required/normal/bulk priorities correctly.
- [ ] **Step 2: Add RED tests** for transient timeout/rate-limit retry with bounded backoff and invalid destination permanent failure.
- [ ] **Step 3: Implement job/adapter/classifier and persisted attempt state.
- [ ] **Step 4: Run Tasks 1–4 communication suite and commit** `feat(communication): add reliable queued email delivery`.

**Checkpoint A:** foundation green before any production flow migration.

---

### Task 5: Event, scheduled, and delayed-conditional rule engine

**Files:**
- Create: `app/Services/Communication/CommunicationRuleEngine.php`
- Create: `app/Services/Communication/CommunicationScheduleService.php`
- Create: `app/Jobs/Communication/ResolveCommunicationRunAudience.php`
- Create: `app/Jobs/Communication/EvaluateDelayedCommunicationCondition.php`
- Create: `app/Console/Commands/CommunicationProcessDueRules.php`
- Modify: `app/Console/Kernel.php`
- Test: `tests/Feature/Communication/CommunicationRuleMatchingTest.php`
- Test: `tests/Feature/Communication/ScheduledRuleTest.php`
- Test: `tests/Feature/Communication/ConditionalRuleTest.php`

**Interfaces:**
- `CommunicationRuleEngine::handleEvent(string $eventKey, array $payload): void`.
- `CommunicationScheduleService::processDue(\Carbon\CarbonInterface $now): int`.
- Schedule processing creates/queues work only; SMTP remains in Task 4 adapter.

- [ ] **Step 1: Write RED event-rule tests** for registered event/audience, disabled rules, rejected unknown conditions, and deterministic dedupe.
- [ ] **Step 2: Write RED schedule tests** proving one recurrence creates one `CommunicationRun`, chunked audience work, and scheduler re-entry does not duplicate the run.
- [ ] **Step 3: Write RED delayed-condition tests** proving true sends, false stops, and arbitrary expressions cannot execute.
- [ ] **Step 4: Implement engine/jobs/command and schedule `communications:process-due` with `withoutOverlapping()`.
- [ ] **Step 5: Run targeted tests and commit** `feat(communication): add programmable automation engine`.

---

### Task 6: Registration completion event and welcome automation

**Files:**
- Create: `app/Events/RegistrationCompleted.php`
- Create: `app/Listeners/DispatchRegistrationCompletedCommunication.php`
- Create: `app/Services/Communication/Context/WelcomeCommunicationContextBuilder.php`
- Modify: `app/Providers/EventServiceProvider.php`
- Modify: `app/Http/Controllers/Auth/Register/Step3Controller.php`
- Add canonical seed/migration data for `onboarding.welcome` Persian v1 and active event rule.
- Test: `tests/Feature/Communication/WelcomeEmailEndToEndTest.php`
- Re-run relevant `tests/Feature/LocationGovernance/*Registration*` tests.

**Interfaces:**
- Event payload: `{user_id:int, completed_at:string, locale:string}`.
- Event fires only after the registration completion transition and after residence/group/profile-completion work succeeds.

- [ ] **Step 1: Write RED E2E tests** for active canonical location, reference-settlement, pending-proposal, and legacy successful Step3 branches.
- [ ] **Step 2: Add duplicate-event/repeated-submit test** asserting one logical welcome communication.
- [ ] **Step 3: Run targeted Location/Governance registration tests; confirm no pre-existing behavior regresses.
- [ ] **Step 4: Refactor Step3 successful exits through one completion-notification boundary and emit `RegistrationCompleted` without changing location semantics.
- [ ] **Step 5: Wire context/template/rule/listener, rerun targeted registration + communication tests, commit** `feat(communication): send welcome after registration completion`.

**Checkpoint:** stop on any Location/Governance regression.

---

### Task 7: Weekly role-aware reports

**Files:**
- Create the three weekly context builders.
- Add template/rule data for `reports.member.weekly`, `reports.manager.weekly`, `reports.inspector.weekly`.
- Test: `tests/Feature/Communication/WeeklyMemberReportTest.php`
- Test: `tests/Feature/Communication/WeeklyManagerReportTest.php`
- Test: `tests/Feature/Communication/WeeklyInspectorReportTest.php`

**Interfaces:**
- Each builder exposes `build(User $user, \Carbon\CarbonPeriod $period): array`.
- Common v1 keys: `period_start`, `period_end`, `display_name`, `groups_count`, `open_elections_count`, `open_polls_count`, `unread_notifications_count`.
- Manager adds: `managed_groups_count`, `managed_group_ids`, `open_elections_in_managed_groups_count`, `open_polls_in_managed_groups_count`.
- Inspector adds: `inspected_groups_count`, `inspected_group_ids`, `open_elections_in_inspected_groups_count`, `open_polls_in_inspected_groups_count`.
- Role resolution uses the current EarthCoop group-role mapping/responsibility semantics; manager/inspector still receive their personal member context.

- [ ] **Step 1: Write RED context tests** for the exact keys above, including no-activity users and current active role membership.
- [ ] **Step 2: Write RED schedule/preference tests** proving weekly per-recipient context and operational preference handling.
- [ ] **Step 3: Implement builders and weekly rules without generic query-builder/SQL exposure.
- [ ] **Step 4: Run report + relevant election/group-role tests and commit** `feat(communication): add weekly role-aware reports`.

**Checkpoint B:** event/scheduled/conditional automation is now proven by real EarthCoop use cases.

---

### Task 8: Admin read-only dashboard, delivery history, and failures

**Files:**
- Create: `app/Http/Controllers/Admin/Communication/DashboardController.php`
- Create: `app/Http/Controllers/Admin/Communication/DeliveryHistoryController.php`
- Create: `app/Http/Controllers/Admin/Communication/FailureController.php`
- Create: `routes/communication-center.php`
- Modify: `routes/web.php` following the repository's existing route-loading/admin boundary pattern.
- Create: `resources/views/admin/communications/dashboard.blade.php`
- Create: `resources/views/admin/communications/deliveries/index.blade.php`
- Create: `resources/views/admin/communications/failures/index.blade.php`
- Modify: `resources/views/admin/partials/sidebar.blade.php`
- Test: `tests/Feature/Communication/AdminCommunicationDashboardTest.php`

**Interfaces:**
- Dashboard reports queued, sent, retrying, permanently failed, active rules, upcoming/due runs, and only queue-health facts the current infrastructure can actually know.

- [ ] **Step 1: Write RED auth/dashboard/history/filter tests** for admin access, non-admin denial, aggregates, filters, and retry visibility.
- [ ] **Step 2: Implement read-only routes/controllers/views and a `مرکز ارتباطات` sidebar entry.
- [ ] **Step 3: Run targeted rendering/feature tests and commit** `feat(communication): add admin communication observability`.

---

### Task 9: Admin template, sender, and automation management

**Files:**
- Create: `app/Http/Controllers/Admin/Communication/TemplateController.php`
- Create: `app/Http/Controllers/Admin/Communication/SenderIdentityController.php`
- Create: `app/Http/Controllers/Admin/Communication/AutomationController.php`
- Create corresponding `resources/views/admin/communications/{templates,senders,automations}/*`.
- Modify the repository's existing canonical permission source after locating it; do not create a second permission system.
- Test: `tests/Feature/Communication/AdminTemplateManagementTest.php`
- Test: `tests/Feature/Communication/AdminAutomationManagementTest.php`

**Interfaces:**
- Permissions: `communications.view`, `communications.templates.manage`, `communications.rules.manage`, `communications.senders.manage` mapped into existing permission machinery.
- Automation forms accept only registered keys and structured values.

- [ ] **Step 1: Locate and record the existing permission registry/seeder before editing it.
- [ ] **Step 2: Write RED tests** for permission separation, new-version publication, variable preview validation, sender-in-use protection, and unknown registry-key rejection.
- [ ] **Step 3: Implement focused CRUD/publish forms and audit metadata using existing admin layout/components.
- [ ] **Step 4: Run targeted tests and commit** `feat(communication): add managed templates senders and automations`.

---

### Task 10: Campaigns, preview, scheduling, pause/cancel, and bulk safety

**Files:**
- Create: `app/Services/Communication/CommunicationCampaignService.php`
- Create: `app/Http/Controllers/Admin/Communication/CampaignController.php`
- Create: `resources/views/admin/communications/campaigns/*`
- Test: `tests/Feature/Communication/CampaignAudienceTest.php`
- Test: `tests/Feature/Communication/CampaignPauseCancelTest.php`

**Interfaces:**
- Lifecycle: draft → preview → confirm → scheduled/running → completed/paused/cancelled/failed.
- Preview counts: `matched`, `eligible`, `suppressed`, `invalid`.
- Bulk audience resolution is chunkable and routes through `communications-bulk`.

- [ ] **Step 1: Write RED lifecycle tests** for preview counts, sample/test render, scheduled start, configurable elevated confirmation threshold, and non-executable audience definitions.
- [ ] **Step 2: Write RED concurrent pause/cancel test** proving pending chunks stop while sent recipients remain sent and are never described as recalled.
- [ ] **Step 3: Implement service/controller/views and bulk routing.
- [ ] **Step 4: Run campaign + queue tests and commit** `feat(communication): add safe scheduled campaigns`.

---

### Task 11: User preferences and safe unsubscribe

**Files:**
- Create: `app/Http/Controllers/Profile/CommunicationPreferenceController.php`
- Create: `resources/views/profile/communication-preferences.blade.php`
- Modify the appropriate authenticated settings/profile route using current project organization.
- Add a signed optional-preference/unsubscribe endpoint.
- Test: `tests/Feature/Communication/UserCommunicationPreferenceTest.php`
- Test: `tests/Feature/Communication/UnsubscribeSafetyTest.php`

**Interfaces:**
- Required topics display as non-disableable.
- Operational topics support at least on/off.
- Optional topics support opt-in/out; cadence appears only where a registered schedule exists.

- [ ] **Step 1: Write RED preference UI/controller tests** including cross-user authorization denial.
- [ ] **Step 2: Write RED signed-unsubscribe tests** for optional changes, invalid/expired signature rejection, and unchanged required/security behavior.
- [ ] **Step 3: Implement UI/routes/controller and commit** `feat(communication): add member communication preferences`.

**Checkpoint C:** operator/user surfaces are usable before legacy migration.

---

### Task 12: Migrate low-risk existing email paths and legacy admin/template surfaces

**Files:**
- Modify: `app/Http/Controllers/Admin/EmailController.php`
- Modify/deprecate: `app/Services/Email/EmailDeliveryService.php`
- Modify: `app/Http/Controllers/Admin/FaqQuestionController.php`
- Modify: `app/Notifications/TicketCreatedNotification.php` and its callers as needed.
- Modify: `app/Services/EmailTicketIntegrationService.php`
- Modify legacy email/sidebar/routes so `EmailTemplate`/`SystemEmail` are no longer independent permanent sources of truth after migration; preserve safe redirect/compatibility where needed.
- Verify/adapt Najm Hoda email-template paths and `FounderEmailManagementConnectivityTest`.
- Test: `tests/Feature/Communication/LegacyAdminEmailMigrationTest.php`
- Test: `tests/Feature/Communication/FaqEmailMigrationTest.php`
- Test: `tests/Feature/Communication/SupportEmailThreadingMigrationTest.php`

**Interfaces:**
- Migrated logical sends enter the Communication Center.
- Support retains existing organizational support identity and thread correlation metadata.

- [ ] **Step 1: Write characterization tests before changing each flow.** Support assertions include sender, reply-to, `Message-ID`, `In-Reply-To`, `References`, and ticket/comment metadata.
- [ ] **Step 2: Migrate admin custom/template send** so UI reports queued/failed state instead of unconditional synchronous success.
- [ ] **Step 3: Migrate FAQ answered and ticket-created/reply one path at a time, running focused tests after each edit.
- [ ] **Step 4: Redirect/deprecate legacy template/system-email admin surfaces after canonical parity, and rerun Najm Hoda founder email-management connectivity tests.
- [ ] **Step 5: Run support/email targeted suites and commit** `refactor(communication): migrate low-risk email flows`.

**Checkpoint:** support parity must be green before auth-sensitive migration.

---

### Task 13: Migrate authentication-sensitive email last

**Files:**
- Modify: `app/Services/Invitation/InvitationManagementService.php`
- Modify: `app/Http/Controllers/Admin/InvitationCodeController.php`
- Modify: `app/Http/Controllers/Profile/ProfileController.php`
- Modify: `app/Http/Controllers/Auth/EmailVerificationController.php`
- Modify: `app/Http/Controllers/Auth/LoginController.php`
- Modify: `app/Providers/EventServiceProvider.php` only where required.
- Test: `tests/Feature/Communication/InvitationEmailMigrationTest.php`
- Test: `tests/Feature/Communication/EmailVerificationMigrationTest.php`
- Test: `tests/Feature/Communication/PasswordEmailMigrationTest.php`
- Re-run existing Google OAuth/registration/auth tests.

**Interfaces:**
- Verification/password/security communication is `required` and routes to `communications-critical`.
- Existing token/code generation and expiry semantics remain domain/auth responsibilities; Communication Center owns rendering/delivery only.

- [ ] **Step 1: Add characterization tests** pinning invitation code, verification code, and password code/token/expiry semantics before replacing mail calls.
- [ ] **Step 2: Migrate invitation paths and run invitation tests.
- [ ] **Step 3: Migrate verification path and run registration/email-verification tests.
- [ ] **Step 4: Migrate password/security path and run login/password tests.
- [ ] **Step 5: Run combined Auth + Communication targeted gate and commit** `refactor(communication): migrate authentication email delivery`.

**Checkpoint D:** major legacy outbound mail is now canonicalized.

---

### Task 14: Architectural boundary, operations docs, and final gate

**Files:**
- Create: `tests/Architecture/CommunicationDeliveryBoundaryTest.php`
- Create/modify deployment documentation in the repository's existing deployment-doc location.
- Update the approved design spec only if implementation discovers a necessary, explicitly reviewed clarification; do not silently drift.

**Interfaces:**
- Architecture guard rejects new direct `Mail::*` calls outside `EmailDeliveryAdapter`/explicit provider adapters. After Task 13 the grandfather list should be empty except intentional test/dev utilities.

- [ ] **Step 1: Write architecture guard test** for direct production mail calls and explicit allowed adapter(s).
- [ ] **Step 2: Add operations docs** for queue worker ordering, scheduler, sender/provider env settings, failed-job/retry operations, rollback, and SPF/DKIM/DMARC deployment checklist.
- [ ] **Step 3: Run full Communication suite:** `php artisan test tests/Feature/Communication tests/Unit/Communication tests/Architecture/CommunicationDeliveryBoundaryTest.php`.
- [ ] **Step 4: Run neighboring targeted regressions:** Location/Governance registration, auth/Google OAuth, support/ticket, elections/group roles.
- [ ] **Step 5: Run the repository full-validation gate once.** On failure, fix the smallest proven regression and rerun the smallest gate first; rerun full validation only after targeted green.
- [ ] **Step 6: Commit** `test(communication): enforce communication delivery boundary`.

**Checkpoint E:** production gate complete.

---

## Release Checkpoints

- **A — Foundation (Tasks 1–4):** schema, templates, preferences, idempotency, queue, attempts, retry; no production migration yet.
- **B — Automation proof (Tasks 5–7):** event/schedule/conditional engine proven by welcome + weekly role reports; registration regressions green.
- **C — Operator usability (Tasks 8–11):** admin management/observability, campaign safety, member preferences.
- **D — Migration (Tasks 12–13):** low-risk first, authentication-sensitive last; characterization/parity tests before replacement.
- **E — Production gate (Task 14):** architectural boundary, operations docs, targeted regressions, one final full validation.

## Definition of Done

1. Every valid Step3 completion path emits one canonical `registration.completed` transition and queues exactly one operational welcome email.
2. Weekly member/manager/inspector reports run on schedule with the defined per-user context and preferences.
3. Admin can publish versioned templates, manage sender identities, create event/scheduled/conditional automations, safely preview/confirm campaigns, and inspect deliveries/failures without executable rule code.
4. Required mail cannot be suppressed by optional unsubscribe/preferences; operational/optional policy behaves as specified.
5. Duplicate event/job/scheduler execution never creates duplicate logical communication.
6. Transient failures retry with append-only attempts; permanent failures are operator-visible.
7. Bulk work does not starve critical mail; pause/cancel stops pending work only.
8. Invitation, verification, password, FAQ, support/ticket, admin email, and Najm Hoda email-template management are migrated/compatible with parity coverage; support threading remains intact.
9. Legacy `EmailTemplate`/`SystemEmail` admin surfaces cease to be independent permanent sources of truth after canonical migration.
10. Production code no longer bypasses Communication Center with uncontrolled direct mail calls.
11. Targeted neighboring regressions and final full project validation are green before merge.
