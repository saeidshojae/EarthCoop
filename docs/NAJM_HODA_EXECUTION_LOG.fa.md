# لاگ اجرای برنامه تحول نجم هدا

## 2026-02-19

### انجام شد
- ایجاد سند roadmap:
  - `docs/NAJM_HODA_TRANSFORMATION_ROADMAP.fa.md`
- ایجاد برنامه Sprint 1:
  - `docs/NAJM_HODA_SPRINT1_EXECUTION_PLAN.fa.md`
- ایجاد ریزتسک های فاز 0:
  - `docs/NAJM_HODA_PHASE0_TASKS.fa.md`
- شروع اجرای فاز 0 (Feature Flag Enforcement):
  - گارد `NAJM_HODA_ENABLED` در API نجم هدا
  - گارد `NAJM_HODA_ENABLED` در listener گروهی نجم هدا
  - گارد `NAJM_HODA_ENABLED` در commandهای اصلی نجم هدا
  - گارد `NAJM_HODA_ENABLED` در admin chat endpoint
- اضافه شدن audit log برای مسیرهای blocked در حالت disabled
- شروع ارزیابی mojibake و ثبت گزارش مرحله ای:
  - `docs/NAJM_HODA_MOJIBAKE_ASSESSMENT_PHASE0.fa.md`
- اصلاح هدفمند mojibake در مقادیر پیش فرض نام بات گروهی:
  - `app/Services/NajmHoda/NajmHodaGroupAssistantService.php`
- تکمیل بازطراحی مسیر تنظیمات Auto-Fixer:
  - حذف وابستگی `saveAutoFixerSettings` به cache
  - ذخیره تنظیمات در `.env` و اعمال runtime config
  - حذف `rand()` از `testAutoFixer` و جایگزینی با تست واقعی بر پایه scan summary
  - حذف وابستگی `cleanBackups` به cache و اتصال به config پایدار

### راستی آزمایی
- syntax check برای فایل های تغییر داده شده انجام شد و خطای syntax گزارش نشد.

### وضعیت فعلی
- `P0-T01`: انجام شد (feature flag روی entrypointهای اصلی enforce شد)
- `P0-T02`: انجام شد (disabled response استاندارد با کد `NAJM_HODA_DISABLED` اعمال شد)
- `P0-T03`: انجام شد (پاکسازی mojibake در فایل های هسته ای تکمیل شد)
- `P0-T04`: انجام شد (runtime config حساس پایدار شد)
- `P0-T05`: انجام شد (لاگ blocked در مسیرهای اصلی افزوده شد)
- `P0-T06`: انجام شد (smoke check مسیرهای کلیدی و syntax validation انجام شد)


### Update - P0-T03-COMPLETE-2026-02-19
- [P0-T03-COMPLETE-2026-02-19] Targeted mojibake cleanup was finalized for 11 NajmHoda core files.
- Residual marker scan result for target set: 0 (patterns: ?, ?, ?, ???, ??, ??).
- Deep repair completed for `app/Services/NajmHoda/NajmHodaGroupAssistantService.php` with backup snapshots and verification.

### Update - P1-RUNTIME-KICKOFF-2026-02-19
- Added initial runtime event layer for Najm Hoda with an internal event bus abstraction.
- New files:
  - `app/Services/NajmHoda/Runtime/RuntimeEventBus.php`
  - `app/Services/NajmHoda/Runtime/InMemoryRuntimeEventBus.php`
- `NajmHodaOrchestrator` now emits base lifecycle runtime events:
  - `najm_hoda.request.received`
  - `najm_hoda.intent.detected`
  - `najm_hoda.response.ready`
  - `najm_hoda.response.failed`
- `NajmHodaServiceProvider` registers `RuntimeEventBus` as singleton.
- Added runtime config: `najm-hoda.runtime.event_bus.max_events`.

### Update - P1-T02-RUNTIME-PERSISTENCE-2026-02-19
- Implemented DB-backed runtime event bus with safe in-memory fallback:
  - `app/Services/NajmHoda/Runtime/DatabaseRuntimeEventBus.php`
- Added persistence model and migration:
  - `app/Models/NajmHodaRuntimeEvent.php`
  - `database/migrations/2026_02_19_210000_create_najm_hoda_runtime_events_table.php`
- Event bus binding now supports driver selection:
  - `database` (default) or `in_memory`
  - configured in `config/najm-hoda.php` via:
    - `NAJM_HODA_RUNTIME_EVENT_BUS_DRIVER`
    - `NAJM_HODA_RUNTIME_EVENT_RETENTION_DAYS`
    - `NAJM_HODA_RUNTIME_EVENT_PRUNE_INTERVAL_SECONDS`
- Retention policy is active in DB bus (periodic prune of old events).

### Update - P1-T03-POLICY-GATE-2026-02-19
- Implemented centralized policy gate for runtime action authorization:
  - `app/Services/NajmHoda/Runtime/NajmHodaPolicyGate.php`
- Integrated policy gate into group assistant action pipeline:
  - `app/Services/NajmHoda/NajmHodaGroupAssistantService.php`
- Centralized checks now pass through one component:
  - action executor enable/deny
  - requester role authorization
  - hourly action rate limit
  - propose-before-execute mode
  - per-capability authorization (`create_post`, `create_poll`, `create_comment`, `react_*`)
  - moderation authorization
- Registered policy gate as singleton in:
  - `app/Providers/NajmHodaServiceProvider.php`

### Update - P1-T04-ACTION-EXECUTOR-KICKOFF-2026-02-19
- Started standard action executor implementation with dry-run support:
  - `app/Services/NajmHoda/Runtime/GroupActionExecutor.php`
- Integrated executor into group action pipeline:
  - `app/Services/NajmHoda/NajmHodaGroupAssistantService.php`
- Added `dry_run` policy/config support for group action executor:
  - `config/najm-hoda.php`
  - action policy defaults and effective policy mapping updated in group assistant service.

### Update - P1-T04-ACTION-EXECUTOR-COMPLETE-2026-02-19
- Group action execution path (`post/poll/comment/reaction`) now runs via central executor wrapper:
  - `app/Services/NajmHoda/NajmHodaGroupAssistantService.php`
- Private-message action path is also routed through the same executor:
  - standardized result contract (`decision`, `reason`, `group_reply`, `context`)
  - dry-run support applied consistently.
- Executor now normalizes action results and appends execution metadata:
  - `action_type`
  - `dry_run`

### Update - P1-T05-EVENT-INTAKE-KICKOFF-2026-02-19
- Added runtime input capture listener to map key group events into shared runtime event model:
  - `app/Listeners/CaptureNajmHodaRuntimeInput.php`
- Registered event intake mappings in `app/Providers/EventServiceProvider.php`:
  - `MessageCreated` -> `najm_hoda.input.group_message`
  - `GroupPollUpdated` -> `najm_hoda.input.group_poll`
  - `GroupFeedUpdated` -> `najm_hoda.input.group_feed`
  - `ElectionStarted` -> `najm_hoda.input.group_election_started`
  - `ElectionFinished` -> `najm_hoda.input.group_election_finished`

### Update - P1-T05-EVENT-INTAKE-COMPLETE-2026-02-19
- Group moderation command intake was connected directly in group assistant runtime path:
  - `najm_hoda.input.group_moderation_command`
  - emitted from `app/Services/NajmHoda/NajmHodaGroupAssistantService.php`
- Runtime input model now covers:
  - chat (`group_message`)
  - poll (`group_poll`)
  - feed (`group_feed`)
  - election start/finish
  - moderation command

### Update - P1-T06-SAFETY-GUARDRAILS-KICKOFF-2026-02-19
- Added circuit breaker guardrail to `GroupActionExecutor`:
  - blocks repeated failing action types for a cooldown window
  - exposes skip reason: `circuit_breaker_open`
- Added runtime safety configuration in `config/najm-hoda.php`:
  - `NAJM_HODA_RUNTIME_CB_FAILURE_THRESHOLD`
  - `NAJM_HODA_RUNTIME_CB_FAILURE_WINDOW`
  - `NAJM_HODA_RUNTIME_CB_COOLDOWN_SECONDS`

### Update - P1-T06-SAFETY-GUARDRAILS-COMPLETE-2026-02-19
- Added per-minute runtime action rate guard in `GroupActionExecutor`:
  - global per-scope cap
  - per-action per-scope cap
  - skip reason: `action_rate_limited`
- Added runtime rate-limit config keys:
  - `NAJM_HODA_RUNTIME_RATE_MAX_PER_MINUTE`
  - `NAJM_HODA_RUNTIME_RATE_MAX_PER_ACTION_PER_MINUTE`

### Update - P1-T07-TESTS-COMPLETE-2026-02-19
- Added executor regression tests:
  - `tests/Feature/NajmHoda/GroupActionExecutorTest.php`
- Covered scenarios:
  - dry-run result contract
  - normalization of minimal success response
  - rate-limit guard behavior
  - circuit-breaker open behavior
- Test execution:
  - `vendor/bin/phpunit --configuration phpunit.xml.dist --filter GroupActionExecutorTest`
  - Result: `OK (4 tests, 19 assertions)`

### Update - PHASE2-KICKOFF-ENTRYPOLICY-EXECUTIONAPI-2026-02-19
- Started Phase 2 with platform-wide policy and execution abstraction:
  - `app/Services/NajmHoda/Runtime/NajmHodaEntryPolicy.php`
  - `app/Services/NajmHoda/Runtime/NajmHodaExecutionService.php`
- Integrated new services into main chat entrypoints:
  - `app/Http/Controllers/API/NajmHodaController.php`
  - `app/Http/Controllers/Admin/NajmHodaController.php`
- Added runtime entry policy configuration:
  - `NAJM_HODA_ENTRY_RATE_MAX_PER_MINUTE`
  - `NAJM_HODA_ENTRY_RATE_CHAT_MAX_PER_MINUTE`
- Added initial Phase 2 plan:
  - `docs/NAJM_HODA_PHASE2_TASKS.fa.md`
- Added initial tests for entry policy and execution service:
  - `tests/Feature/NajmHoda/EntryPolicyAndExecutionServiceTest.php`

### Update - PHASE2-COVERAGE-AND-TESTS-COMPLETE-2026-02-19
- Completed API entrypoint coverage using centralized policy deny helper:
  - `app/Http/Controllers/API/NajmHodaController.php`
  - entrypoints now mapped with dedicated keys (`api.welcome`, `api.chat`, `api.conversation.*`, `api.feedback.submit`, `api.stats`)
- Completed Admin AI entrypoint coverage using centralized policy deny helper:
  - `app/Http/Controllers/Admin/NajmHodaController.php`
  - protected paths: `admin.chat`, `admin.agent.design`, `admin.agent.save`, `admin.code.analyze`, `admin.code.suggestion`
- Removed policy-check duplication in controllers by introducing shared helper methods:
  - `denyByEntryPolicy(...)` in both API/Admin NajmHoda controllers
- Expanded execution regression tests:
  - `tests/Feature/NajmHoda/EntryPolicyAndExecutionServiceTest.php`
  - added failure normalization and exception-handling scenarios
- Validation:
  - `php -l app/Http/Controllers/API/NajmHodaController.php`
  - `php -l app/Http/Controllers/Admin/NajmHodaController.php`
  - `vendor/bin/phpunit --configuration phpunit.xml.dist --filter EntryPolicyAndExecutionServiceTest`
  - result: `OK (5 tests, 23 assertions)`

### Update - PHASE3-OPS-KICKOFF-AND-BASELINE-COMPLETE-2026-02-19
- Started and completed Phase 3 baseline for autonomous operations management:
  - `docs/NAJM_HODA_PHASE3_TASKS.fa.md`
- Added runtime ops health monitoring service:
  - `app/Services/NajmHoda/Runtime/NajmHodaOpsHealthMonitor.php`
  - emits `najm_hoda.ops.health.snapshot`
- Added runtime ops triage service with low-risk playbook runner:
  - `app/Services/NajmHoda/Runtime/NajmHodaOpsTriageService.php`
  - emits:
    - `najm_hoda.ops.incident.detected`
    - `najm_hoda.ops.playbook.executed`
  - low-risk playbook behavior:
    - sets/clears degraded mode cache flag `najm_hoda:ops:degraded_until`
- Added operations command:
  - `app/Console/Commands/NajmHodaOpsMonitor.php`
  - command: `najm-hoda:ops-monitor` (`--window`, `--limit`, `--dry-run`)
- Integrated command into scheduler:
  - `app/Console/Kernel.php`
  - schedule: every five minutes
- Registered new runtime services in DI container:
  - `app/Providers/NajmHodaServiceProvider.php`
- Added Phase 3 runtime ops configuration:
  - `config/najm-hoda.php`
  - keys:
    - `runtime.ops.monitor.*`
    - `runtime.ops.thresholds.*`
    - `runtime.ops.triage.*`
- Added tests for Phase 3 ops baseline:
  - `tests/Feature/NajmHoda/OpsHealthMonitorAndTriageTest.php`

### Update - PHASE3-ESCALATION-INTEGRATION-COMPLETE-2026-02-19
- Added runtime incident escalation service for ops incidents:
  - `app/Services/NajmHoda/Runtime/NajmHodaOpsEscalationService.php`
  - creates support tickets for `warning/critical` incidents
  - applies cooldown to avoid duplicate ticket floods
  - emits:
    - `najm_hoda.ops.escalation.created`
    - `najm_hoda.ops.escalation.skipped`
    - `najm_hoda.ops.escalation.dry_run`
- Integrated escalation flow into ops monitor command:
  - `app/Console/Commands/NajmHodaOpsMonitor.php`
  - monitor now reports incident and escalation actions together
- Registered escalation service in DI:
  - `app/Providers/NajmHodaServiceProvider.php`
- Added runtime config for escalation controls:
  - `config/najm-hoda.php`
  - keys:
    - `runtime.ops.escalation.enabled`
    - `runtime.ops.escalation.notify_admins`
    - `runtime.ops.escalation.cooldown_seconds`
    - `runtime.ops.escalation.max_incidents_per_run`
- Added escalation regression tests:
  - `tests/Feature/NajmHoda/OpsEscalationServiceTest.php`

### Update - PHASE3-DYNAMIC-DEGRADED-RATE-CONTROL-COMPLETE-2026-02-19
- Upgraded low-risk playbook behavior to apply dynamic entry-rate control:
  - `app/Services/NajmHoda/Runtime/NajmHodaOpsTriageService.php`
  - playbook now sets `najm_hoda:ops:entry_rate_multiplier` for:
    - `healthy` -> baseline multiplier
    - `warning` -> warning multiplier
    - `critical` -> critical multiplier
- Connected runtime entry policy to ops multiplier:
  - `app/Services/NajmHoda/Runtime/NajmHodaEntryPolicy.php`
  - effective request limits are scaled by ops multiplier during degraded conditions
- Added runtime config keys for dynamic degraded-rate control:
  - `config/najm-hoda.php`
  - keys:
    - `runtime.ops.triage.entry_rate_multiplier_base`
    - `runtime.ops.triage.entry_rate_multiplier_warning`
    - `runtime.ops.triage.entry_rate_multiplier_critical`
- Expanded tests:
  - `tests/Feature/NajmHoda/EntryPolicyAndExecutionServiceTest.php`
    - added ops multiplier enforcement scenario
  - `tests/Feature/NajmHoda/OpsHealthMonitorAndTriageTest.php`
    - added multiplier apply/reset verification on critical/healthy states

### Update - PHASE3-PLAYBOOK-CATALOG-SAFETY-COMPLETE-2026-02-19
- Refactored triage playbook execution to policy-driven catalog + plan:
  - `app/Services/NajmHoda/Runtime/NajmHodaOpsTriageService.php`
  - added:
    - status-based playbook plan resolution
    - action safety validation
    - low-risk-only enforcement
    - max-actions-per-run cap
    - structured skip event emission (`najm_hoda.ops.playbook.skipped`)
- Added runtime configuration for playbook governance:
  - `config/najm-hoda.php`
  - keys:
    - `runtime.ops.playbooks.enforce_low_risk_only`
    - `runtime.ops.playbooks.max_actions_per_run`
    - `runtime.ops.playbooks.plan.*`
    - `runtime.ops.playbooks.catalog.*`
- Extended Phase 3 tests:
  - `tests/Feature/NajmHoda/OpsHealthMonitorAndTriageTest.php`
  - added high-risk playbook skip scenario with safety enforcement assertions

### Update - PHASE3-PLAYBOOK-COOLDOWN-TELEMETRY-COMPLETE-2026-02-19
- Added per-action playbook cooldown controls:
  - `app/Services/NajmHoda/Runtime/NajmHodaOpsTriageService.php`
  - action-level cooldown key prefix:
    - `najm_hoda:ops:playbook:cooldown:*`
  - cooldown enforcement now skips repeated actions with reason `cooldown_active`
- Added playbook telemetry counters and events:
  - `app/Services/NajmHoda/Runtime/NajmHodaOpsTriageService.php`
  - event: `najm_hoda.ops.playbook.telemetry`
  - counter keys:
    - `najm_hoda:ops:playbook:telemetry:{action}:{bucket}:total`
    - `najm_hoda:ops:playbook:telemetry:{action}:{bucket}:{outcome}`
- Added runtime config keys for cooldown governance:
  - `config/najm-hoda.php`
  - keys:
    - `runtime.ops.playbooks.default_action_cooldown_seconds`
    - `runtime.ops.playbooks.action_cooldowns.*`
- Expanded tests for cooldown + telemetry:
  - `tests/Feature/NajmHoda/OpsHealthMonitorAndTriageTest.php`
  - added scenario for cooldown skip and telemetry emission verification

### Update - PHASE3-OPS-RUN-SUMMARY-DIGEST-COMPLETE-2026-02-19
- Added standardized run summary digest to ops monitor execution:
  - `app/Console/Commands/NajmHodaOpsMonitor.php`
  - emits event: `najm_hoda.ops.run.summary`
  - summary includes:
    - `run_id`
    - health status + key metrics
    - incident/escalation counters
    - dry-run marker
- Added cache snapshot for latest ops monitor run:
  - key: `najm_hoda:ops:last_run_summary`
  - TTL configured via `runtime.ops.monitor.summary_ttl_minutes`
- Added config key:
  - `config/najm-hoda.php`
  - `runtime.ops.monitor.summary_ttl_minutes`
- Expanded tests:
  - `tests/Feature/NajmHoda/OpsHealthMonitorAndTriageTest.php`
  - added command-level verification for summary event emission and cached digest

### Update - PHASE3-ADMIN-OPS-DIGEST-FEED-COMPLETE-2026-02-19
- Added ops summary history persistence in monitor command:
  - `app/Console/Commands/NajmHodaOpsMonitor.php`
  - cache key:
    - `najm_hoda:ops:run_summary_history`
  - capped by configurable history size
- Added admin-facing digest endpoint:
  - `app/Http/Controllers/Admin/NajmHodaController.php`
  - method: `getOpsDigest(Request $request)`
  - returns:
    - `last_summary`
    - `history`
    - `recent_ops_events`
- Added admin route for digest feed:
  - `routes/web.php`
  - route name: `admin.najm-hoda.ops.digest`
- Added config key:
  - `config/najm-hoda.php`
  - `runtime.ops.monitor.summary_history_size`
- Expanded tests:
  - `tests/Feature/NajmHoda/OpsHealthMonitorAndTriageTest.php`
  - added verification for cached summary history ordering and size cap

### Update - PHASE3-OPS-RETENTION-CLEANUP-COMPLETE-2026-02-19
- Added runtime retention service for ops cache artifacts:
  - `app/Services/NajmHoda/Runtime/NajmHodaOpsRetentionService.php`
  - prune responsibilities:
    - summary history cap enforcement
    - stale telemetry index key cleanup
  - emits:
    - `najm_hoda.ops.retention.pruned`
- Integrated retention pass into ops monitor command:
  - `app/Console/Commands/NajmHodaOpsMonitor.php`
  - new option:
    - `--skip-retention`
- Added DI registration:
  - `app/Providers/NajmHodaServiceProvider.php`
- Added runtime retention config keys:
  - `config/najm-hoda.php`
  - keys:
    - `runtime.ops.retention.telemetry_index_retention_hours`
    - `runtime.ops.retention.telemetry_index_max_size`
- Added tests:
  - `tests/Feature/NajmHoda/OpsRetentionServiceTest.php`
  - validates summary trim + stale telemetry key pruning
### Update - PHASE4-KICKOFF-2026-02-20
- Phase 3 task list is fully completed (`P3-T01` to `P3-T15`).
- Started Phase 4 with a dedicated backlog document:
  - `docs/NAJM_HODA_PHASE4_TASKS.fa.md`
- Marked `P4-T01` (Autonomous Goal Loop Skeleton) as `in_progress` to begin implementation.
### Update - PHASE4-AUTONOMOUS-GOAL-LOOP-SKELETON-COMPLETE-2026-02-20
- Implemented autonomous goal loop skeleton service:
  - `app/Services/NajmHoda/Runtime/NajmHodaAutonomousGoalLoopService.php`
  - produces context summary + low-risk plan + cached runtime snapshot
  - emits event: `najm_hoda.autonomy.goal_loop.executed`
- Added CLI command and scheduler integration:
  - `app/Console/Commands/NajmHodaGoalLoop.php`
  - command: `najm-hoda:goal-loop`
  - scheduled in `app/Console/Kernel.php` every ten minutes
- Registered DI and config:
  - `app/Providers/NajmHodaServiceProvider.php`
  - `config/najm-hoda.php` (`runtime.autonomy.*`)
- Added tests:
  - `tests/Feature/NajmHoda/AutonomousGoalLoopTest.php`

### Update - PHASE4-CAPABILITY-REGISTRY-CONTRACTS-COMPLETE-2026-02-20
- Implemented capability registry for autonomous actions:
  - `app/Services/NajmHoda/Runtime/NajmHodaCapabilityRegistry.php`
  - contract resolution, required-input validation, and trace events:
    - `najm_hoda.autonomy.contract.accepted`
    - `najm_hoda.autonomy.contract.rejected`
- Integrated action-contract enforcement into goal loop planning:
  - `app/Services/NajmHoda/Runtime/NajmHodaAutonomousGoalLoopService.php`
  - plans are now created only via registry contract checks.
- Registered new runtime service in DI:
  - `app/Providers/NajmHodaServiceProvider.php`
- Added autonomy capability contracts to config:
  - `config/najm-hoda.php` (`runtime.autonomy.capabilities.*`)
- Added and updated tests:
  - `tests/Feature/NajmHoda/CapabilityRegistryTest.php`
  - `tests/Feature/NajmHoda/AutonomousGoalLoopTest.php`

### Update - PHASE4-SAFETY-GATE-V2-COMPLETE-2026-02-20
- Implemented autonomy Safety Gate v2 (policy + budget + scope):
  - `app/Services/NajmHoda/Runtime/NajmHodaAutonomySafetyGate.php`
  - blocks by:
    - risk policy
    - allowlist/blocklist policy
    - max actions budget per run
    - goal/action scope mismatch
- Integrated safety enforcement into autonomous goal planning:
  - `app/Services/NajmHoda/Runtime/NajmHodaAutonomousGoalLoopService.php`
  - blocked actions emit:
    - `najm_hoda.autonomy.safety.blocked`
    - `najm_hoda.autonomy.plan_item.blocked`
- Registered service and configuration:
  - `app/Providers/NajmHodaServiceProvider.php`
  - `config/najm-hoda.php` (`runtime.autonomy.safety.*`)
- Added tests:
  - `tests/Feature/NajmHoda/AutonomySafetyGateTest.php`
  - updated `tests/Feature/NajmHoda/AutonomousGoalLoopTest.php`

### Update - PHASE4-HUMAN-ESCALATION-WORKFLOW-COMPLETE-2026-02-20
- Implemented human approval workflow service for autonomy actions:
  - `app/Services/NajmHoda/Runtime/NajmHodaAutonomyApprovalService.php`
  - features:
    - pending approval queue
    - SLA deadline tracking
    - admin decision (approve/reject)
    - reason capture for decisions
    - runtime trace events for request/decision
- Integrated workflow into autonomous goal loop:
  - `app/Services/NajmHoda/Runtime/NajmHodaAutonomousGoalLoopService.php`
  - apply-mode and medium/high-risk actions now route to human approval
  - fallback-to-propose mode applied when configured
- Registered runtime service and config:
  - `app/Providers/NajmHodaServiceProvider.php`
  - `config/najm-hoda.php` (`runtime.autonomy.human_escalation.*`)
- Added admin endpoints for queue + decision:
  - `GET admin/najm-hoda/autonomy/approvals`
  - `POST admin/najm-hoda/autonomy/approvals/{approvalId}/decision`
  - files:
    - `app/Http/Controllers/Admin/NajmHodaController.php`
    - `routes/web.php`
- Added tests:
  - `tests/Feature/NajmHoda/AutonomyApprovalServiceTest.php`
  - updated `tests/Feature/NajmHoda/AutonomousGoalLoopTest.php`

### Update - PHASE4-OBSERVABILITY-GRAPH-COMPLETE-2026-02-20
- Implemented cross-module observability graph service:
  - `app/Services/NajmHoda/Runtime/NajmHodaObservabilityGraphService.php`
  - unified snapshot includes:
    - runtime reliability signals
    - chat activity signal
    - group counts
    - Najm Hoda assignment backlog/overdue signals
  - caches latest snapshot and emits:
    - `najm_hoda.autonomy.observability.snapshot`
- Integrated observability context into autonomous goal loop:
  - `app/Services/NajmHoda/Runtime/NajmHodaAutonomousGoalLoopService.php`
  - planning now consumes graph context instead of raw event-only summary
- Registered service in DI and config:
  - `app/Providers/NajmHodaServiceProvider.php`
  - `config/najm-hoda.php` (`runtime.autonomy.observability.*`)
- Added tests:
  - `tests/Feature/NajmHoda/ObservabilityGraphServiceTest.php`
  - updated `tests/Feature/NajmHoda/AutonomousGoalLoopTest.php`

### Update - PHASE4-PROACTIVE-RECOMMENDATION-ENGINE-COMPLETE-2026-02-20
- Implemented proactive recommendation engine for autonomous planning:
  - `app/Services/NajmHoda/Runtime/NajmHodaProactiveRecommendationService.php`
  - generates explainable recommendations with confidence score and action hints
  - emits runtime event:
    - `najm_hoda.autonomy.recommendations.generated`
- Integrated recommendations into goal-loop context and plan:
  - `app/Services/NajmHoda/Runtime/NajmHodaAutonomousGoalLoopService.php`
  - output now includes `recommendations`
  - plan input carries top recommendation signal for decision traceability
- Registered service and config:
  - `app/Providers/NajmHodaServiceProvider.php`
  - `config/najm-hoda.php` (`runtime.autonomy.recommendations.*`)
- Added tests:
  - `tests/Feature/NajmHoda/ProactiveRecommendationServiceTest.php`
  - updated `tests/Feature/NajmHoda/AutonomousGoalLoopTest.php`

### Update - PHASE4-OPERATOR-ACTION-EXECUTOR-V2-COMPLETE-2026-02-20
- Implemented operator action executor v2 with reliability guards:
  - `app/Services/NajmHoda/Runtime/NajmHodaOperatorActionExecutorV2.php`
  - features:
    - low-risk apply-only enforcement
    - retry loop
    - action cooldown
    - idempotency keying for duplicate prevention
    - structured runtime events for executed/skipped/failed intents
- Integrated executor into autonomous goal loop:
  - `app/Services/NajmHoda/Runtime/NajmHodaAutonomousGoalLoopService.php`
  - run result now includes:
    - `execution_results`
- Registered service and config:
  - `app/Providers/NajmHodaServiceProvider.php`
  - `config/najm-hoda.php` (`runtime.autonomy.executor.*`)
- Added tests:
  - `tests/Feature/NajmHoda/OperatorActionExecutorV2Test.php`
  - updated `tests/Feature/NajmHoda/AutonomousGoalLoopTest.php`

### Update - PHASE4-ADMIN-CONTROL-SURFACE-COMPLETE-2026-02-20
- Implemented autonomy control surface service:
  - `app/Services/NajmHoda/Runtime/NajmHodaAutonomyControlService.php`
  - capabilities:
    - pause/resume autonomy loop
    - force mode override (`apply` / `propose`)
    - blocked-actions override
    - control-state trace events
- Integrated controls into runtime execution:
  - `app/Services/NajmHoda/Runtime/NajmHodaAutonomousGoalLoopService.php`
  - goal loop now:
    - halts when paused
    - applies admin overrides to planned actions
- Added admin APIs for control operations:
  - `GET admin/najm-hoda/autonomy/controls`
  - `POST admin/najm-hoda/autonomy/controls`
  - files:
    - `app/Http/Controllers/Admin/NajmHodaController.php`
    - `routes/web.php`
- Added lightweight controls to admin ops page:
  - `resources/views/admin/najm-hoda/ops-digest.blade.php`
- Added tests:
  - `tests/Feature/NajmHoda/AutonomyControlServiceTest.php`
  - updated `tests/Feature/NajmHoda/AutonomousGoalLoopTest.php`

### Update - PHASE4-AUDIT-NARRATIVE-REPLAYABILITY-COMPLETE-2026-02-20
- Implemented autonomy audit service for decision-to-execution trace:
  - `app/Services/NajmHoda/Runtime/NajmHodaAutonomyAuditService.php`
  - capabilities:
    - trace recording for each loop run
    - historical trace retrieval
    - replay by `run_id`
    - audit events for record/replay
- Integrated audit recording into goal loop:
  - `app/Services/NajmHoda/Runtime/NajmHodaAutonomousGoalLoopService.php`
  - records both completed and paused runs
- Registered service and config:
  - `app/Providers/NajmHodaServiceProvider.php`
  - `config/najm-hoda.php` (`runtime.autonomy.audit.*`)
- Added admin APIs for audit trace and replay:
  - `GET admin/najm-hoda/autonomy/audit`
  - `POST admin/najm-hoda/autonomy/audit/{runId}/replay`
  - files:
    - `app/Http/Controllers/Admin/NajmHodaController.php`
    - `routes/web.php`
- Added tests:
  - `tests/Feature/NajmHoda/AutonomyAuditServiceTest.php`
  - updated `tests/Feature/NajmHoda/AutonomousGoalLoopTest.php`

### Update - PHASE4-RELIABILITY-MATRIX-INITIAL-2026-02-20
- Started Phase 4 reliability matrix work with focused governance scenarios:
  - `tests/Feature/NajmHoda/AutonomyReliabilityMatrixTest.php`
  - current covered scenarios:
    - paused autonomy halts execution and is audit-traced
    - force-propose override prevents apply execution path
- Validation:
  - `AutonomyReliabilityMatrixTest`: 2 passed

### Update - PHASE4-RELIABILITY-MATRIX-CHAOS-COMPLETE-2026-02-20
- Added explicit chaos scenarios for autonomy runtime:
  - `tests/Feature/NajmHoda/AutonomyChaosScenariosTest.php`
  - covered:
    - executor retry exhaustion and fail event path
    - audit replay consistency for plan/execution shape
- Revalidated reliability matrix:
  - `tests/Feature/NajmHoda/AutonomyReliabilityMatrixTest.php`
  - pause/override governance scenarios remain passing
- Phase 4 reliability target considered complete with matrix + chaos baseline.

### Update - PHASE5-KICKOFF-2026-02-20
- Reviewed transformation roadmap and confirmed next defined phase is:
  - `Phase 5: Governance & Hardening`
- Added detailed Phase 5 task breakdown:
  - `docs/NAJM_HODA_PHASE5_TASKS.fa.md`
- Marked `P5-T01` as `in_progress` for immediate execution start.

### Update - PHASE5-GOVERNANCE-KPI-BASELINE-COMPLETE-2026-02-20
- Implemented governance KPI baseline catalog service:
  - `app/Services/NajmHoda/Runtime/NajmHodaGovernanceKpiCatalogService.php`
  - formalized KPI formulas and SLO thresholds for phase-5 governance.
- Added governance baseline config as Source of Truth:
  - `config/najm-hoda.php`
  - path: `runtime.autonomy.governance.kpis.*`
- Registered service in provider:
  - `app/Providers/NajmHodaServiceProvider.php`
- Added admin endpoint for baseline retrieval:
  - `GET admin/najm-hoda/autonomy/governance/baseline`
  - files:
    - `app/Http/Controllers/Admin/NajmHodaController.php`
    - `routes/web.php`
- Added governance baseline document:
  - `docs/NAJM_HODA_PHASE5_GOVERNANCE_BASELINE.fa.md`
- Added tests:
  - `tests/Feature/NajmHoda/GovernanceKpiCatalogServiceTest.php`

### Update - PHASE5-GOVERNANCE-METRICS-AGGREGATOR-COMPLETE-2026-02-20
- Implemented governance metrics aggregation service:
  - `app/Services/NajmHoda/Runtime/NajmHodaGovernanceMetricsAggregatorService.php`
  - snapshot output includes:
    - governance metrics
    - SLO evaluation status (`ok` / `warning` / `breach` / `no_data`)
    - 24h-window event-based counters
- Registered service in DI:
  - `app/Providers/NajmHodaServiceProvider.php`
- Added governance snapshot runtime config:
  - `config/najm-hoda.php`
  - path: `runtime.autonomy.governance.{window_hours,event_limit,snapshot_ttl_minutes}`
- Added admin endpoint for governance snapshot:
  - `GET admin/najm-hoda/autonomy/governance/snapshot`
  - files:
    - `app/Http/Controllers/Admin/NajmHodaController.php`
    - `routes/web.php`
- Added tests:
  - `tests/Feature/NajmHoda/GovernanceMetricsAggregatorServiceTest.php`

### Update - PHASE5-ADMIN-GOVERNANCE-DASHBOARD-COMPLETE-2026-02-20
- Added governance dashboard page for admin panel:
  - `resources/views/admin/najm-hoda/governance-dashboard.blade.php`
  - features:
    - KPI/SLO status table
    - summary cards (breach/warning/event count/success rate)
    - time-window selector (`1h/6h/24h/72h`)
- Added governance page endpoint:
  - `GET admin/najm-hoda/autonomy/governance`
  - files:
    - `app/Http/Controllers/Admin/NajmHodaController.php`
    - `routes/web.php`
- Reused governance APIs already available:
  - `GET admin/najm-hoda/autonomy/governance/baseline`
  - `GET admin/najm-hoda/autonomy/governance/snapshot`

### Update - PHASE5-AI-COST-LEDGER-BUDGET-GUARD-COMPLETE-2026-02-20
- Implemented autonomy AI cost ledger service:
  - `app/Services/NajmHoda/Runtime/NajmHodaAutonomyCostLedgerService.php`
  - features:
    - daily/monthly cost totals
    - per-action cost estimates
    - budget-guard check before spend
    - cost audit events
- Integrated budget guard into operator executor:
  - `app/Services/NajmHoda/Runtime/NajmHodaOperatorActionExecutorV2.php`
  - apply actions are blocked when budget is exceeded.
- Registered cost ledger in DI:
  - `app/Providers/NajmHodaServiceProvider.php`
- Added runtime cost config:
  - `config/najm-hoda.php`
  - path: `runtime.autonomy.costs.*`
- Added admin cost status endpoint:
  - `GET admin/najm-hoda/autonomy/costs/status`
  - files:
    - `app/Http/Controllers/Admin/NajmHodaController.php`
    - `routes/web.php`
- Updated governance dashboard to show daily AI cost against budget:
  - `resources/views/admin/najm-hoda/governance-dashboard.blade.php`
- Added tests:
  - `tests/Feature/NajmHoda/AutonomyCostLedgerServiceTest.php`
  - updated `tests/Feature/NajmHoda/OperatorActionExecutorV2Test.php`

### Update - PHASE5-DECISION-POLICY-DRIFT-DETECTOR-COMPLETE-2026-02-21
- Completed drift detector wiring for governance:
  - `app/Services/NajmHoda/Runtime/NajmHodaDecisionPolicyDriftService.php`
  - `app/Providers/NajmHodaServiceProvider.php`
  - `config/najm-hoda.php` (`runtime.autonomy.governance.drift.*`)
- Added admin drift report endpoint:
  - `GET admin/najm-hoda/autonomy/governance/drift`
  - files:
    - `app/Http/Controllers/Admin/NajmHodaController.php`
    - `routes/web.php`
- Added tests:
  - `tests/Feature/NajmHoda/DecisionPolicyDriftServiceTest.php`
- Updated phase tracker:
  - `P5-T05` marked `done`
  - `P5-T06` marked `in_progress`

### Update - PHASE5-RUNBOOK-REGISTRY-READINESS-COMPLETE-2026-02-21
- Implemented runbook registry and readiness service:
  - `app/Services/NajmHoda/Runtime/NajmHodaRunbookRegistryService.php`
  - capabilities:
    - normalized runbook registry retrieval
    - readiness scoring and status (`ready` / `warning` / `breach`)
    - runbook readiness telemetry event
- Registered service and exposed admin APIs:
  - `app/Providers/NajmHodaServiceProvider.php`
  - `GET admin/najm-hoda/autonomy/runbooks`
  - `GET admin/najm-hoda/autonomy/runbooks/readiness`
  - files:
    - `app/Http/Controllers/Admin/NajmHodaController.php`
    - `routes/web.php`
- Added runbook runtime config baseline:
  - `config/najm-hoda.php`
  - path: `runtime.autonomy.runbooks.*`
- Added tests:
  - `tests/Feature/NajmHoda/RunbookRegistryServiceTest.php`
- Updated phase tracker:
  - `P5-T06` marked `done`
  - `P5-T07` marked `in_progress`

### Update - PHASE5-GLOBAL-KILL-SWITCH-COMPLETE-2026-02-21
- Implemented fail-safe global kill switch for autonomy runtime:
  - `app/Services/NajmHoda/Runtime/NajmHodaAutonomyControlService.php`
  - features:
    - `activateKillSwitch` / `deactivateKillSwitch`
    - bounded duration with expiry handling
    - deterministic active-state checks via `isKillSwitchActive`
    - telemetry events for activation/deactivation
- Enforced kill switch in autonomous execution path:
  - `app/Services/NajmHoda/Runtime/NajmHodaAutonomousGoalLoopService.php`
    - loop halts with `status=kill_switched` and audit trace
  - `app/Services/NajmHoda/Runtime/NajmHodaOperatorActionExecutorV2.php`
    - apply actions are skipped with `global_kill_switch_active`
- Extended admin control surface:
  - `app/Http/Controllers/Admin/NajmHodaController.php`
  - control actions added:
    - `activate_kill_switch`
    - `deactivate_kill_switch`
  - controls payload now includes `kill_switch` state
- Wired dependencies and config:
  - `app/Providers/NajmHodaServiceProvider.php`
  - `config/najm-hoda.php` (`runtime.autonomy.kill_switch.*`)
- Updated ops digest admin UI controls:
  - `resources/views/admin/najm-hoda/ops-digest.blade.php`
- Added/updated tests:
  - `tests/Feature/NajmHoda/AutonomyControlServiceTest.php`
  - `tests/Feature/NajmHoda/OperatorActionExecutorV2Test.php`
  - `tests/Feature/NajmHoda/AutonomousGoalLoopTest.php`
- Updated phase tracker:
  - `P5-T07` marked `done`
  - `P5-T08` marked `in_progress`

### Update - PHASE5-ALERTING-SLA-GUARD-COMPLETE-2026-02-21
- Implemented governance alerting and SLA guard service:
  - `app/Services/NajmHoda/Runtime/NajmHodaGovernanceAlertingService.php`
  - features:
    - KPI warning/breach alert generation from governance snapshot
    - human-approval SLA overdue guard (`approval_sla_overdue_threshold`)
    - alert cooldown and bounded per-run emission
    - alert history persistence in cache
    - optional admin notifications
- Registered service in DI:
  - `app/Providers/NajmHodaServiceProvider.php`
- Added admin APIs:
  - `POST admin/najm-hoda/autonomy/governance/alerts/evaluate`
  - `GET admin/najm-hoda/autonomy/governance/alerts/history`
  - files:
    - `app/Http/Controllers/Admin/NajmHodaController.php`
    - `routes/web.php`
- Added runtime config:
  - `config/najm-hoda.php`
  - path: `runtime.autonomy.governance.alerting.*`
- Added tests:
  - `tests/Feature/NajmHoda/GovernanceAlertingServiceTest.php`
- Regression checks passed:
  - `GovernanceMetricsAggregatorServiceTest`
  - `AutonomyApprovalServiceTest`
- Updated phase tracker:
  - `P5-T08` marked `done`
  - `P5-T09` marked `in_progress`

### Update - PHASE5-GAMEDAY-CHAOS-AUTOMATION-COMPLETE-2026-02-21
- Implemented autonomy GameDay chaos drill service with deterministic pass/fail report:
  - `app/Services/NajmHoda/Runtime/NajmHodaAutonomyGameDayService.php`
  - covered scenarios:
    - `kill_switch_blocks_goal_loop`
    - `pause_blocks_goal_loop`
    - `replay_consistency`
    - `approval_sla_alert_guard`
  - includes control-state rollback after drills to avoid operational drift.
- Added CLI command for periodic or on-demand drills:
  - `app/Console/Commands/NajmHodaGameDay.php`
  - command: `najm-hoda:gameday`
  - options:
    - `--scenario=*`
    - `--dry-run`
    - `--history=N`
- Registered command and runtime service wiring:
  - `app/Console/Kernel.php`
  - `app/Providers/NajmHodaServiceProvider.php`
- Added admin APIs for GameDay execution and history:
  - `POST admin/najm-hoda/autonomy/gameday/run`
  - `GET admin/najm-hoda/autonomy/gameday/history`
  - files:
    - `app/Http/Controllers/Admin/NajmHodaController.php`
    - `routes/web.php`
- Added runtime config:
  - `config/najm-hoda.php`
  - path: `runtime.autonomy.gameday.*`
- Added tests:
  - `tests/Feature/NajmHoda/AutonomyGameDayServiceTest.php`
- Updated phase tracker:
  - `P5-T09` marked `done`
  - `P5-T10` marked `in_progress`

### Update - PHASE5-SECURITY-HARDENING-INITIAL-2026-02-21
- Started security hardening for autonomy surface (`P5-T10`) with first enforcement layer:
  - Added dedicated autonomy rate limiters:
    - `najm-hoda-autonomy-read`
    - `najm-hoda-autonomy-write`
  - file:
    - `app/Providers/RouteServiceProvider.php`
- Applied dedicated throttle middleware to sensitive autonomy endpoints:
  - approvals decision
  - controls update
  - audit replay
  - governance alert evaluate
  - gameday run
  - files:
    - `routes/web.php`
- Tightened entry-policy enforcement for autonomy write actions:
  - `app/Http/Controllers/Admin/NajmHodaController.php`
  - switched write-path checks from no-rate-limit mode to enforced mode.
- Added audit tamper detection:
  - trace integrity hash is recorded and verified during replay
  - tampered traces are rejected and telemetry is emitted
  - files:
    - `app/Services/NajmHoda/Runtime/NajmHodaAutonomyAuditService.php`
    - `config/najm-hoda.php` (`runtime.autonomy.audit.integrity.*`)
- Added tests for tamper-check:
  - `tests/Feature/NajmHoda/AutonomyAuditServiceTest.php`
- `P5-T10` remains `in_progress` for final RBAC/coverage hardening pass.

### Update - PHASE5-SECURITY-HARDENING-COMPLETE-2026-02-21
- Completed final RBAC hardening pass for autonomy surface with low-risk backward compatibility:
  - Split autonomy routes into read/write permission domains.
  - Kept fallback authorization to existing permission (`najm-hoda.manage-settings`) to prevent operational lockout.
  - files:
    - `routes/web.php`
- Added dedicated autonomy permission slugs for progressive least-privilege rollout:
  - `najm-hoda.autonomy.read`
  - `najm-hoda.autonomy.write`
  - `najm-hoda.autonomy.gameday`
  - file:
    - `database/seeders/RolePermissionSeeder.php`
- Security model after this change:
  - Existing admins continue to function through fallback permission.
  - New least-privilege roles can be introduced without route refactor.
- Updated phase tracker:
  - `P5-T10` marked `done`
  - `P5-T11` marked `in_progress`

### Update - PHASE5-COMPLIANCE-EVIDENCE-PACK-COMPLETE-2026-02-21
- Implemented compliance evidence pack service for autonomy auditability:
  - `app/Services/NajmHoda/Runtime/NajmHodaComplianceEvidenceService.php`
  - package sections:
    - `audit_traces`
    - `approvals`
    - `governance_alerts`
    - `gameday_reports`
    - `runtime_events`
  - includes summary counters and `integrity_hash` for export payload.
- Extended approval service for evidence collection:
  - added `history(limit, status?)`
  - file: `app/Services/NajmHoda/Runtime/NajmHodaAutonomyApprovalService.php`
- Added admin compliance APIs:
  - `GET admin/najm-hoda/autonomy/compliance/evidence`
  - `GET admin/najm-hoda/autonomy/compliance/evidence/export`
  - files:
    - `app/Http/Controllers/Admin/NajmHodaController.php`
    - `routes/web.php`
- Added runtime config for compliance export sizing/window:
  - `config/najm-hoda.php` (`runtime.autonomy.compliance.*`)
- Added focused test:
  - `tests/Feature/NajmHoda/ComplianceEvidenceServiceTest.php`
- Validation:
  - php lint passed for touched files
  - route registration verified for compliance endpoints
  - `ComplianceEvidenceServiceTest` passed
- Updated phase tracker:
  - `P5-T11` marked `done`
  - `P5-T12` marked `in_progress`

### Update - PHASE5-PRODUCTION-READINESS-GO-NO-GO-COMPLETE-2026-02-21
- Implemented production readiness review service for autonomy Go/No-Go decision:
  - `app/Services/NajmHoda/Runtime/NajmHodaProductionReadinessService.php`
  - decision output:
    - `go`
    - `conditional_go`
    - `no_go`
  - readiness checks:
    - governance KPI statuses (warning/breach thresholds)
    - policy drift status
    - runbook readiness + required rollback runbooks
    - approval queue pressure (pending/overdue)
    - GameDay pass-rate over required cycles
    - compliance evidence integrity and minimum coverage
- Added admin APIs for readiness preview/export:
  - `GET admin/najm-hoda/autonomy/readiness/review`
  - `GET admin/najm-hoda/autonomy/readiness/review/export`
  - files:
    - `app/Http/Controllers/Admin/NajmHodaController.php`
    - `routes/web.php`
- Added readiness runtime configuration:
  - `config/najm-hoda.php`
  - path: `runtime.autonomy.readiness.*`
- Registered services in DI container:
  - `app/Providers/NajmHodaServiceProvider.php`
  - `NajmHodaComplianceEvidenceService`
  - `NajmHodaProductionReadinessService`
- Added tests:
  - `tests/Feature/NajmHoda/ProductionReadinessServiceTest.php`
  - covers `no_go` and `go` paths.
- Validation:
  - php lint passed on touched files
  - readiness routes registered and protected by autonomy-read middleware
  - `ProductionReadinessServiceTest` passed
- Updated phase tracker:
  - `P5-T12` marked `done`
