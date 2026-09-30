# EarthCoop Communication Center Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a production-grade, programmable Communication Center for EarthCoop that supports event-driven, scheduled, and delayed-conditional email; template/version management; sender identities; user preferences; queued reliable delivery; campaigns; audit; and the first real automations (registration welcome + weekly role-aware reports) while preserving current working email flows during migration.

**Architecture:** Domain code emits registered business events or supplies registered report context; the Communication Center resolves rules, audience, classification/preferences, immutable template versions, sender identity, scheduling, deduplication, queue priority, and delivery attempts. Existing email paths are migrated incrementally using a strangler approach; direct `Mail::*` use remains temporarily allowed only for unmigrated legacy paths and controlled delivery adapters.

**Tech Stack:** PHP 8.2+, Laravel 12, Eloquent, Laravel Queue, Laravel Scheduler, Blade/Tailwind admin UI, PHPUnit 11.

**Spec:** `docs/superpowers/specs/2026-09-30-earthcoop-communication-center-design.md`

## Global Constraints

- Work on an isolated branch/worktree; never implement directly on `main`.
- Preserve existing ticket reply threading (`Message-ID`, `In-Reply-To`, `References`) while migrating support email.
- `SystemIdentityService` remains an in-app system-actor concern; sender identity is a separate communication concern with optional linkage.
- SMTP/provider credentials and API secrets remain in environment/secret configuration; never persist them in Communication Center tables.
- No arbitrary SQL, PHP, Blade execution, or free executable rule expressions from admin UI.
- Email is the only active v1 channel; schema may be channel-ready but must not build SMS/WhatsApp/full push prematurely.
- Published template versions are immutable; edits publish a new version.
- `required` communication bypasses suppressive user preferences; `operational` is default-on but user-adjustable where policy permits; `optional` honors preference/opt-in policy.
- Registration welcome after successful stage-3 completion is `operational`, default-on, and deduplicated.
- All normal outbound email is queued. HTTP/domain operations must not block on SMTP.
- Deduplication must be persistence-enforced, not only an application `exists()` check.
- Large audiences must be chunked; do not load all users into admin dropdowns.
- The scheduler orchestrates due work but never performs SMTP delivery directly.
- Keep targeted test runs during development; run the project-wide validation gate only at release checkpoints/final gate to avoid unnecessary CI time.

## Review Focus

1. **Registration has several successful stage-3 branches** (canonical location, reference settlement, pending proposal, legacy path): every actual completion transition must emit exactly one canonical completion event and repeated submissions must not duplicate welcome mail.
2. **Queue retries and duplicate scheduler/event delivery:** repeated jobs/events must converge on one logical communication per dedupe scope while retaining append-only delivery attempts.
3. **Preference edge cases:** required messages must still send; operational/optional messages must respect the effective preference without allowing unsubscribe to suppress account-security mail.
4. **Support email migration:** outbound support mail must preserve sender identity, reply-to, ticket metadata, and mail-thread headers exactly enough for inbound reply correlation to continue working.
5. **Bulk campaign interruption:** pause/cancel must stop only pending work, never claim to recall delivered email, and must remain safe if workers are concurrently consuming chunks.

---

## File Structure Map

### Domain/data
- `app/Enums/Communication/CommunicationClassification.php` — required/operational/optional.
- `app/Enums/Communication/CommunicationStatus.php` — logical communication lifecycle.
- `app/Enums/Communication/DeliveryStatus.php` — recipient/attempt lifecycle.
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
- `app/Services/Communication/CommunicationTemplateRenderer.php`
- `app/Services/Communication/SenderIdentityResolver.php`
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

---

### Task 1: Canonical schema, enums, models, and migration safety

**Files:**
- Create: `database/migrations/2026_09_30_180000_create_communication_core_tables.php`
- Create: `app/Enums/Communication/CommunicationClassification.php`
- Create: `app/Enums/Communication/CommunicationStatus.php`
- Create: `app/Enums/Communication/DeliveryStatus.php`
- Create: the eleven `app/Models/Communication*.php` models listed in the file map.
- Test: `tests/Feature/Communication/CommunicationSchemaTest.php`

**Interfaces:**
- Produces: canonical tables/relations used by every later task.
- Critical DB invariant: `communications.deduplication_key` has a unique constraint for non-null deterministic keys.
- Critical DB invariant: published template versions can be referenced permanently by recipients/history.

- [ ] **Step 1: Write failing schema tests** asserting tables, required foreign keys/indexes, JSON columns for structured definitions/context, sender/template relations, append-only attempt relation, and a unique database constraint on `communications.deduplication_key`.
- [ ] **Step 2: Run** `php artisan test tests/Feature/Communication/CommunicationSchemaTest.php` and verify RED because tables/models do not exist.
- [ ] **Step 3: Implement the migration and focused models/enums.** Use string-backed enums and Eloquent casts for JSON/status/classification; do not add speculative digest tables.
- [ ] **Step 4: Run the schema test plus** `php artisan migrate:fresh --env=testing` and verify PASS.
- [ ] **Step 5: Commit** `feat(communication): add canonical communication data model`.

**Checkpoint:** no existing email behavior changes in Task 1.

---

### Task 2: Safe registries, template versioning, rendering, and canonical sender identities

**Files:**
- Create: `app/Services/Communication/CommunicationEventRegistry.php`
- Create: `app/Services/Communication/CommunicationConditionRegistry.php`
- Create: `app/Services/Communication/CommunicationAudienceRegistry.php`
- Create: `app/Services/Communication/CommunicationTemplateRenderer.php`
- Create: `app/Services/Communication/SenderIdentityResolver.php`
- Create: `app/Services/Communication/CommunicationTemplateService.php`
- Modify: `app/Services/SystemIdentityService.php` only if an adapter method is needed; do not collapse the two concepts.
- Test: `tests/Unit/Communication/CommunicationRegistryTest.php`
- Test: `tests/Feature/Communication/TemplateVersioningTest.php`
- Test: `tests/Feature/Communication/SenderIdentityTest.php`

**Interfaces:**
- Produces: `CommunicationTemplateService::publish(...)`, `CommunicationTemplateRenderer::render(version, context)`, and `SenderIdentityResolver::resolve(key)`.
- Registered variables must be explicit; missing required values cause a render failure.

- [ ] **Step 1: Write RED tests** proving unregistered event/condition/audience keys are rejected, published template versions are immutable, publishing a change creates the next version, unresolved required placeholders fail, and sender identities never expose/provider-store SMTP secrets.
- [ ] **Step 2: Run the three targeted test files** and verify RED.
- [ ] **Step 3: Implement minimal registries and template/sender services.** Initial registry entries are limited to use cases needed later (`registration.completed`, event user, specific user, role audiences, the first report conditions).
- [ ] **Step 4: Add a migration/seeding adapter test** that can import current `SystemEmail` records into canonical sender identities without deleting source rows yet; support/management keys may resolve optional linkage to `SystemIdentityService`.
- [ ] **Step 5: Run targeted tests and commit** `feat(communication): add safe registries templates and sender identities`.

---

### Task 3: Preference policy and deterministic logical dispatch

**Files:**
- Create: `app/Services/Communication/CommunicationPreferenceService.php`
- Create: `app/Services/Communication/CommunicationDispatcher.php`
- Test: `tests/Feature/Communication/CommunicationPreferenceTest.php`
- Test: `tests/Feature/Communication/CommunicationDeduplicationTest.php`

**Interfaces:**
- Produces: `CommunicationDispatcher::dispatch(string $purposeKey, array $source, array $recipients, array $context, array $options = []): Communication`.
- Produces: `CommunicationPreferenceService::allows(User $user, string $topicKey, CommunicationClassification $classification): PreferenceDecision`.

- [ ] **Step 1: Write RED preference tests** for required bypass, operational default-on, explicit operational suppression, optional off/opt-in behavior, and invalid/no-email recipient handling.
- [ ] **Step 2: Write RED dedupe tests** with two concurrent-equivalent dispatch attempts using the same deterministic key and assert one logical `communications` row.
- [ ] **Step 3: Run targeted tests and confirm RED.**
- [ ] **Step 4: Implement preference resolution and transactional logical dispatch.** Catch duplicate-key races by reloading the canonical existing communication; do not rely on preflight `exists()` alone.
- [ ] **Step 5: Re-run targeted tests and commit** `feat(communication): add preference policy and idempotent dispatch`.

---

### Task 4: Queued email delivery, priorities, attempts, retry, and failure normalization

**Files:**
- Create: `app/Services/Communication/EmailDeliveryAdapter.php`
- Create: `app/Services/Communication/DeliveryFailureClassifier.php`
- Create: `app/Jobs/Communication/DeliverCommunicationRecipient.php`
- Modify: `config/queue.php` only if named queue defaults/documentation are required; do not add a new queue product.
- Test: `tests/Feature/Communication/CommunicationQueueTest.php`
- Test: `tests/Feature/Communication/CommunicationRetryTest.php`
- Test: `tests/Feature/Communication/PermanentFailureTest.php`

**Interfaces:**
- Consumes: recipient + immutable template version + sender identity from Tasks 1–3.
- Produces: delivery attempts and queue routing `communications-critical`, `communications-normal`, `communications-bulk`.

- [ ] **Step 1: Write RED tests** asserting dispatch queues rather than sending synchronously, required/security priority maps to critical, operational maps to normal, campaign bulk maps to bulk.
- [ ] **Step 2: Add failure tests** for timeout/rate-limit transient retry and invalid destination permanent failure; assert each retry appends a new attempt instead of overwriting prior history.
- [ ] **Step 3: Run targeted tests and confirm RED.**
- [ ] **Step 4: Implement delivery job/adapter/classifier** with bounded backoff and persisted attempt state. The adapter is the canonical place allowed to call Laravel Mail for migrated flows.
- [ ] **Step 5: Run targeted tests and commit** `feat(communication): add reliable queued email delivery`.

**Checkpoint:** run Tasks 1–4 suite together before any production flow migration.

---

### Task 5: Rule engine for event, schedule, and delayed conditional automation

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
- Produces: `CommunicationRuleEngine::handleEvent(string $eventKey, array $payload): void`.
- Produces: `CommunicationScheduleService::processDue(CarbonInterface $now): int`.
- Delayed conditions are registry-backed evaluators; scheduler creates jobs/runs and never calls SMTP.

- [ ] **Step 1: Write RED event-rule tests** for registered event, registered audience, disabled rule, invalid/unregistered condition, and deterministic run/dedupe behavior.
- [ ] **Step 2: Write RED scheduled tests** proving one due recurrence creates one `CommunicationRun`, chunks audience, and scheduler re-entry does not duplicate the run.
- [ ] **Step 3: Write RED delayed-condition tests** proving condition true sends, condition false stops, and the evaluator cannot execute arbitrary expression input.
- [ ] **Step 4: Implement rule engine/jobs/command and wire `communications:process-due` into `app/Console/Kernel.php` with `withoutOverlapping()` at an appropriate minute cadence.
- [ ] **Step 5: Run targeted tests and commit** `feat(communication): add programmable automation engine`.

---

### Task 6: Registration completion event and welcome email end-to-end

**Files:**
- Create: `app/Events/RegistrationCompleted.php`
- Create: `app/Listeners/DispatchRegistrationCompletedCommunication.php`
- Create: `app/Services/Communication/Context/WelcomeCommunicationContextBuilder.php`
- Modify: `app/Providers/EventServiceProvider.php`
- Modify: `app/Http/Controllers/Auth/Register/Step3Controller.php`
- Create: seed/migration data for template `onboarding.welcome` and its first Persian version.
- Test: `tests/Feature/Communication/WelcomeEmailEndToEndTest.php`
- Re-run relevant existing location registration tests including `tests/Feature/LocationGovernance/RegistrationPendingResidenceTest.php` and reference-settlement registration tests.

**Interfaces:**
- Produces event payload: `{user_id:int, completed_at:string, locale:string}`.
- Event is emitted after the successful completion transition only; it must not run before residence/group completion work succeeds.

- [ ] **Step 1: Write RED end-to-end tests for every successful Step3 branch**: active canonical endpoint, reference settlement branch, pending proposal branch, and legacy fallback if still enabled. Assert each produces one queued welcome communication.
- [ ] **Step 2: Add repeat-submission/idempotency test** proving a second request or duplicate event does not enqueue a second logical welcome email.
- [ ] **Step 3: Run new and targeted Location/Governance registration tests and confirm only the new communication assertions fail.
- [ ] **Step 4: Refactor `Step3Controller` completion exits through one small completion notifier/helper boundary without changing location behavior; dispatch `RegistrationCompleted` after successful completion and wire the listener/context/template.
- [ ] **Step 5: Run targeted registration + Communication tests and commit** `feat(communication): send welcome after registration completion`.

**Checkpoint:** do not proceed if any Location/Governance registration regression appears.

---

### Task 7: Weekly role-aware reports

**Files:**
- Create: `app/Services/Communication/Context/WeeklyMemberReportContextBuilder.php`
- Create: `app/Services/Communication/Context/WeeklyManagerReportContextBuilder.php`
- Create: `app/Services/Communication/Context/WeeklyInspectorReportContextBuilder.php`
- Add canonical template/rule seed data for `reports.member.weekly`, `reports.manager.weekly`, `reports.inspector.weekly`.
- Test: `tests/Feature/Communication/WeeklyMemberReportTest.php`
- Test: `tests/Feature/Communication/WeeklyManagerReportTest.php`
- Test: `tests/Feature/Communication/WeeklyInspectorReportTest.php`

**Interfaces:**
- Each builder exposes `build(User $user, CarbonPeriod $period): array` and returns only safe structured report values.
- Role audiences must respect current EarthCoop role mapping and active responsibility semantics; manager/inspector retain their personal member activity context.

- [ ] **Step 1: Write RED builder tests** that pin the exact v1 fields drawn from existing domains (personal activity + role-specific actionable counts), including a user with no activity.
- [ ] **Step 2: Write RED schedule tests** proving weekly rules create per-recipient context and operational preference is honored.
- [ ] **Step 3: Implement the three builders and initial weekly schedule definitions** without generic SQL/query-builder exposure.
- [ ] **Step 4: Run report tests plus relevant election/group role tests and commit** `feat(communication): add weekly role-aware reports`.

---

### Task 8: Admin Communication Center read-only dashboard and delivery observability

**Files:**
- Create: `app/Http/Controllers/Admin/Communication/DashboardController.php`
- Create: `app/Http/Controllers/Admin/Communication/DeliveryHistoryController.php`
- Create: `app/Http/Controllers/Admin/Communication/FailureController.php`
- Create: `routes/communication-center.php`
- Modify: `routes/web.php` to load the route file inside the admin boundary or use the repository's established route-loading pattern.
- Create: `resources/views/admin/communications/dashboard.blade.php`
- Create: `resources/views/admin/communications/deliveries/index.blade.php`
- Create: `resources/views/admin/communications/failures/index.blade.php`
- Modify: `resources/views/admin/partials/sidebar.blade.php`
- Test: `tests/Feature/Communication/AdminCommunicationDashboardTest.php`

**Interfaces:**
- Dashboard reports queued/sent/retrying/permanent-failed, active rules, due runs, and available queue health without pretending provider health is known when it is not.

- [ ] **Step 1: Write RED authorization/dashboard/history tests** for admin access, non-admin denial, accurate aggregate counts, filters, and failure retry visibility.
- [ ] **Step 2: Implement read-only controllers/routes/views/sidebar entry named `مرکز ارتباطات`.
- [ ] **Step 3: Run targeted feature tests and basic Blade route rendering tests.
- [ ] **Step 4: Commit** `feat(communication): add admin communication observability`.

---

### Task 9: Admin template, sender, and automation management with permissions/audit

**Files:**
- Create: `app/Http/Controllers/Admin/Communication/TemplateController.php`
- Create: `app/Http/Controllers/Admin/Communication/SenderIdentityController.php`
- Create: `app/Http/Controllers/Admin/Communication/AutomationController.php`
- Create: corresponding `resources/views/admin/communications/{templates,senders,automations}/*` views.
- Modify the repository's canonical permission seeding/config source after locating it at implementation time; do not invent a second permission system.
- Test: `tests/Feature/Communication/AdminTemplateManagementTest.php`
- Test: `tests/Feature/Communication/AdminAutomationManagementTest.php`

**Interfaces:**
- Permissions required: `communications.view`, `communications.templates.manage`, `communications.rules.manage`, `communications.senders.manage` mapped into the existing permission framework.
- Automation forms accept only registry keys and structured values.

- [ ] **Step 1: Locate and document the existing permission registry/seeder in the branch before editing it.**
- [ ] **Step 2: Write RED tests** for permission separation, publishing new template versions, registered-variable preview validation, sender-in-use protection, and rejection of unknown event/condition/audience keys.
- [ ] **Step 3: Implement focused CRUD/publish forms and audit metadata; reuse existing admin layout/components.
- [ ] **Step 4: Run targeted tests and commit** `feat(communication): add managed templates senders and automations`.

---

### Task 10: Campaigns, audience preview, scheduling, pause/cancel, and bulk safety

**Files:**
- Create: `app/Services/Communication/CommunicationCampaignService.php`
- Create: `app/Http/Controllers/Admin/Communication/CampaignController.php`
- Create: `resources/views/admin/communications/campaigns/*`
- Test: `tests/Feature/Communication/CampaignAudienceTest.php`
- Test: `tests/Feature/Communication/CampaignPauseCancelTest.php`

**Interfaces:**
- Campaign service exposes draft → preview → confirm → scheduled/running → terminal transitions.
- Large recipient estimate uses chunkable audience query/resolver, never a full user dropdown.

- [ ] **Step 1: Write RED lifecycle tests** for draft preview counts (`matched`, `eligible`, `suppressed`, `invalid`), sample/test render, scheduled start, elevated confirmation threshold, and non-executable audience definitions.
- [ ] **Step 2: Write RED concurrency-oriented pause/cancel test** proving pending chunks stop while already-sent recipients remain sent and are not described as recalled.
- [ ] **Step 3: Implement service/controller/views and bulk queue routing.
- [ ] **Step 4: Run campaign + queue tests and commit** `feat(communication): add safe scheduled campaigns`.

---

### Task 11: User communication preferences and safe unsubscribe

**Files:**
- Create: `app/Http/Controllers/Profile/CommunicationPreferenceController.php`
- Create: `resources/views/profile/communication-preferences.blade.php`
- Modify the appropriate authenticated profile/settings route file or `routes/web.php` following existing project organization.
- Add signed preference/unsubscribe endpoint for optional mail only.
- Test: `tests/Feature/Communication/UserCommunicationPreferenceTest.php`
- Test: `tests/Feature/Communication/UnsubscribeSafetyTest.php`

**Interfaces:**
- UI exposes required topics as non-disableable, operational topics as on/off, optional topics as eligible opt-in/out; cadence is shown only where a registered schedule exists.

- [ ] **Step 1: Write RED UI/controller tests** for required lock, operational toggle, optional toggle, unauthorized editing of another user's preferences.
- [ ] **Step 2: Write RED signed-unsubscribe tests** proving optional topic changes, invalid/expired signature rejection, and required/security preferences remain untouched.
- [ ] **Step 3: Implement UI/routes/controller and commit** `feat(communication): add member communication preferences`.

---

### Task 12: Migrate low-risk existing email flows first

**Files:**
- Modify: `app/Http/Controllers/Admin/EmailController.php`
- Modify: `app/Services/Email/EmailDeliveryService.php` or deprecate behind an adapter without deleting until migration completes.
- Modify: `app/Http/Controllers/Admin/FaqQuestionController.php`
- Modify: `app/Notifications/TicketCreatedNotification.php` and/or its calling path.
- Modify: `app/Services/EmailTicketIntegrationService.php` with exact header-preservation tests.
- Test: `tests/Feature/Communication/LegacyAdminEmailMigrationTest.php`
- Test: `tests/Feature/Communication/FaqEmailMigrationTest.php`
- Test: `tests/Feature/Communication/SupportEmailThreadingMigrationTest.php`

**Interfaces:**
- All migrated flows route logical generation/delivery through Communication Center.
- Support adapter retains existing system support sender resolution and thread headers/correlation metadata.

- [ ] **Step 1: Capture current behavior in RED/characterization tests before changing each flow.** For support, assert `Message-ID`, `In-Reply-To`, `References`, sender, reply-to, and comment/ticket metadata.
- [ ] **Step 2: Migrate admin custom/template send so success UI reflects actual queued/failed state rather than unconditional synchronous success.
- [ ] **Step 3: Migrate FAQ answered and ticket-created/reply flows one at a time, running their focused tests after each edit.
- [ ] **Step 4: Run all support/email targeted suites and commit** `refactor(communication): migrate low-risk email flows`.

**Checkpoint:** production support behavior must be parity-tested before authentication mail migration starts.

---

### Task 13: Migrate authentication-sensitive mail last

**Files:**
- Modify: `app/Services/Invitation/InvitationManagementService.php`
- Modify: `app/Http/Controllers/Admin/InvitationCodeController.php`
- Modify: `app/Http/Controllers/Profile/ProfileController.php`
- Modify: `app/Http/Controllers/Auth/EmailVerificationController.php`
- Modify: `app/Http/Controllers/Auth/LoginController.php`
- Modify: auth event/listener wiring in `app/Providers/EventServiceProvider.php` only where required.
- Test: `tests/Feature/Communication/InvitationEmailMigrationTest.php`
- Test: `tests/Feature/Communication/EmailVerificationMigrationTest.php`
- Test: `tests/Feature/Communication/PasswordEmailMigrationTest.php`
- Re-run existing Google OAuth/registration/auth tests.

**Interfaces:**
- Verification/password/security messages classify as required and route to critical queue.
- Existing tokens/codes/expiry semantics remain unchanged; Communication Center owns presentation/delivery, not credential-generation policy.

- [ ] **Step 1: Add characterization tests** pinning current invitation code, verification-code, password-code/token semantics before replacing any mail call.
- [ ] **Step 2: Migrate invitation flows and run invitation tests.
- [ ] **Step 3: Migrate verification flow and run registration/email-verification tests.
- [ ] **Step 4: Migrate password/security flow and run login/password tests.
- [ ] **Step 5: Run the combined Auth + Communication targeted gate and commit** `refactor(communication): migrate authentication email delivery`.

---

### Task 14: Architectural guard, operational documentation, and release gate

**Files:**
- Create: `tests/Architecture/CommunicationDeliveryBoundaryTest.php`
- Create/Modify: deployment documentation for queue workers/scheduler and sender/domain configuration under the repository's existing deployment docs location.
- Update: `docs/superpowers/specs/2026-09-30-earthcoop-communication-center-design.md` only if implementation discovers a necessary approved clarification; do not silently drift from spec.

**Interfaces:**
- Architecture guard fails on new direct `Mail::*` usage outside the canonical adapter and explicitly grandfathered unmigrated paths; after Task 13, the grandfather list should be empty except intentional test/dev utilities.

- [ ] **Step 1: Write architecture test** scanning application production code for direct Laravel Mail calls and allowlisting only `EmailDeliveryAdapter` plus any explicitly justified provider adapter.
- [ ] **Step 2: Add operational docs** for `queue:work` queue ordering, scheduler requirement, environment sender/provider settings, retry/failed-job operations, SPF/DKIM/DMARC deployment checklist, and rollback behavior.
- [ ] **Step 3: Run the full Communication Center suite:** `php artisan test tests/Feature/Communication tests/Unit/Communication tests/Architecture/CommunicationDeliveryBoundaryTest.php`.
- [ ] **Step 4: Run targeted neighboring regressions:** Location/Governance registration, authentication/Google OAuth, support/ticket, elections/roles used by report builders.
- [ ] **Step 5: Only now run the repository's full validation gate/CI once.** Do not repeatedly trigger it during small tasks.
- [ ] **Step 6: Review failures, fix only proven regressions, rerun the smallest failing gate first, then final full gate once green locally/targeted.
- [ ] **Step 7: Commit** `test(communication): enforce communication delivery boundary`.

---

## Release Checkpoints

### Checkpoint A — Foundation (Tasks 1–4)
No production email flow migrated. Schema, templates, preferences, idempotent logical dispatch, queue, attempts, retry are proven independently.

### Checkpoint B — Automation proof (Tasks 5–7)
Event/schedule/conditional engine is proven by welcome automation and weekly member/manager/inspector reports. Registration regressions must remain green.

### Checkpoint C — Operator usability (Tasks 8–11)
Admins can observe, manage templates/senders/rules/campaigns safely; users can control eligible preferences.

### Checkpoint D — Migration (Tasks 12–13)
Migrate low-risk flows first, authentication-sensitive flows last. Each legacy path gets characterization/parity coverage before replacement.

### Checkpoint E — Production gate (Task 14)
Direct-mail architectural boundary, deployment operations, targeted neighboring regressions, and one final full validation run.

## Definition of Done

The plan is complete when all of the following are demonstrated by tests and admin-visible state:

1. Completing stage 3 of registration through any valid completion branch emits one canonical `registration.completed` transition and queues exactly one operational welcome email.
2. Weekly member, manager, and inspector reports run on schedule with per-user context and preferences.
3. Admin can create/publish versioned templates, manage sender identities, create event/scheduled/conditional automations, preview/confirm campaigns, and inspect delivery/failures without executable rule code.
4. Required email cannot be suppressed by optional unsubscribe/preferences; operational/optional policies behave as specified.
5. Duplicate events/jobs/scheduler runs do not create duplicate logical communications.
6. Transient delivery failures retry with append-only attempts; permanent failures become operator-visible.
7. Bulk work cannot starve critical queue; pause/cancel stops pending work only.
8. Existing invitation, verification, password, FAQ, support/ticket, and admin email behaviors are migrated with parity coverage; support threading remains intact.
9. Production application code no longer bypasses the Communication Center with uncontrolled direct mail calls.
10. Targeted subsystem regressions and final full project validation are green before merge.
