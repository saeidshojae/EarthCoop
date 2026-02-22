# Ù„Ø§Ú¯ Ø§Ø¬Ø±Ø§ÛŒ Ø¨Ø±Ù†Ø§Ù…Ù‡ ØªØ­ÙˆÙ„ Ù†Ø¬Ù… Ù‡Ø¯Ø§

## 2026-02-19

### Ø§Ù†Ø¬Ø§Ù… Ø´Ø¯
- Ø§ÛŒØ¬Ø§Ø¯ Ø³Ù†Ø¯ roadmap:
  - `docs/NAJM_HODA_TRANSFORMATION_ROADMAP.fa.md`
- Ø§ÛŒØ¬Ø§Ø¯ Ø¨Ø±Ù†Ø§Ù…Ù‡ Sprint 1:
  - `docs/NAJM_HODA_SPRINT1_EXECUTION_PLAN.fa.md`
- Ø§ÛŒØ¬Ø§Ø¯ Ø±ÛŒØ²ØªØ³Ú© Ù‡Ø§ÛŒ ÙØ§Ø² 0:
  - `docs/NAJM_HODA_PHASE0_TASKS.fa.md`
- Ø´Ø±ÙˆØ¹ Ø§Ø¬Ø±Ø§ÛŒ ÙØ§Ø² 0 (Feature Flag Enforcement):
  - Ú¯Ø§Ø±Ø¯ `NAJM_HODA_ENABLED` Ø¯Ø± API Ù†Ø¬Ù… Ù‡Ø¯Ø§
  - Ú¯Ø§Ø±Ø¯ `NAJM_HODA_ENABLED` Ø¯Ø± listener Ú¯Ø±ÙˆÙ‡ÛŒ Ù†Ø¬Ù… Ù‡Ø¯Ø§
  - Ú¯Ø§Ø±Ø¯ `NAJM_HODA_ENABLED` Ø¯Ø± commandÙ‡Ø§ÛŒ Ø§ØµÙ„ÛŒ Ù†Ø¬Ù… Ù‡Ø¯Ø§
  - Ú¯Ø§Ø±Ø¯ `NAJM_HODA_ENABLED` Ø¯Ø± admin chat endpoint
- Ø§Ø¶Ø§ÙÙ‡ Ø´Ø¯Ù† audit log Ø¨Ø±Ø§ÛŒ Ù…Ø³ÛŒØ±Ù‡Ø§ÛŒ blocked Ø¯Ø± Ø­Ø§Ù„Øª disabled
- Ø´Ø±ÙˆØ¹ Ø§Ø±Ø²ÛŒØ§Ø¨ÛŒ mojibake Ùˆ Ø«Ø¨Øª Ú¯Ø²Ø§Ø±Ø´ Ù…Ø±Ø­Ù„Ù‡ Ø§ÛŒ:
  - `docs/NAJM_HODA_MOJIBAKE_ASSESSMENT_PHASE0.fa.md`
- Ø§ØµÙ„Ø§Ø­ Ù‡Ø¯ÙÙ…Ù†Ø¯ mojibake Ø¯Ø± Ù…Ù‚Ø§Ø¯ÛŒØ± Ù¾ÛŒØ´ ÙØ±Ø¶ Ù†Ø§Ù… Ø¨Ø§Øª Ú¯Ø±ÙˆÙ‡ÛŒ:
  - `app/Services/NajmHoda/NajmHodaGroupAssistantService.php`
- ØªÚ©Ù…ÛŒÙ„ Ø¨Ø§Ø²Ø·Ø±Ø§Ø­ÛŒ Ù…Ø³ÛŒØ± ØªÙ†Ø¸ÛŒÙ…Ø§Øª Auto-Fixer:
  - Ø­Ø°Ù ÙˆØ§Ø¨Ø³ØªÚ¯ÛŒ `saveAutoFixerSettings` Ø¨Ù‡ cache
  - Ø°Ø®ÛŒØ±Ù‡ ØªÙ†Ø¸ÛŒÙ…Ø§Øª Ø¯Ø± `.env` Ùˆ Ø§Ø¹Ù…Ø§Ù„ runtime config
  - Ø­Ø°Ù `rand()` Ø§Ø² `testAutoFixer` Ùˆ Ø¬Ø§ÛŒÚ¯Ø²ÛŒÙ†ÛŒ Ø¨Ø§ ØªØ³Øª ÙˆØ§Ù‚Ø¹ÛŒ Ø¨Ø± Ù¾Ø§ÛŒÙ‡ scan summary
  - Ø­Ø°Ù ÙˆØ§Ø¨Ø³ØªÚ¯ÛŒ `cleanBackups` Ø¨Ù‡ cache Ùˆ Ø§ØªØµØ§Ù„ Ø¨Ù‡ config Ù¾Ø§ÛŒØ¯Ø§Ø±

### Ø±Ø§Ø³ØªÛŒ Ø¢Ø²Ù…Ø§ÛŒÛŒ
- syntax check Ø¨Ø±Ø§ÛŒ ÙØ§ÛŒÙ„ Ù‡Ø§ÛŒ ØªØºÛŒÛŒØ± Ø¯Ø§Ø¯Ù‡ Ø´Ø¯Ù‡ Ø§Ù†Ø¬Ø§Ù… Ø´Ø¯ Ùˆ Ø®Ø·Ø§ÛŒ syntax Ú¯Ø²Ø§Ø±Ø´ Ù†Ø´Ø¯.

### ÙˆØ¶Ø¹ÛŒØª ÙØ¹Ù„ÛŒ
- `P0-T01`: Ø§Ù†Ø¬Ø§Ù… Ø´Ø¯ (feature flag Ø±ÙˆÛŒ entrypointÙ‡Ø§ÛŒ Ø§ØµÙ„ÛŒ enforce Ø´Ø¯)
- `P0-T02`: Ø§Ù†Ø¬Ø§Ù… Ø´Ø¯ (disabled response Ø§Ø³ØªØ§Ù†Ø¯Ø§Ø±Ø¯ Ø¨Ø§ Ú©Ø¯ `NAJM_HODA_DISABLED` Ø§Ø¹Ù…Ø§Ù„ Ø´Ø¯)
- `P0-T03`: Ø§Ù†Ø¬Ø§Ù… Ø´Ø¯ (Ù¾Ø§Ú©Ø³Ø§Ø²ÛŒ mojibake Ø¯Ø± ÙØ§ÛŒÙ„ Ù‡Ø§ÛŒ Ù‡Ø³ØªÙ‡ Ø§ÛŒ ØªÚ©Ù…ÛŒÙ„ Ø´Ø¯)
- `P0-T04`: Ø§Ù†Ø¬Ø§Ù… Ø´Ø¯ (runtime config Ø­Ø³Ø§Ø³ Ù¾Ø§ÛŒØ¯Ø§Ø± Ø´Ø¯)
- `P0-T05`: Ø§Ù†Ø¬Ø§Ù… Ø´Ø¯ (Ù„Ø§Ú¯ blocked Ø¯Ø± Ù…Ø³ÛŒØ±Ù‡Ø§ÛŒ Ø§ØµÙ„ÛŒ Ø§ÙØ²ÙˆØ¯Ù‡ Ø´Ø¯)
- `P0-T06`: Ø§Ù†Ø¬Ø§Ù… Ø´Ø¯ (smoke check Ù…Ø³ÛŒØ±Ù‡Ø§ÛŒ Ú©Ù„ÛŒØ¯ÛŒ Ùˆ syntax validation Ø§Ù†Ø¬Ø§Ù… Ø´Ø¯)


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

### Update - PHASE6-INITIALIZATION-2026-02-21
- Started Phase 6 planning and execution track (`Universal Autonomy Integration`).
- Added detailed Phase 6 task document:
  - `docs/NAJM_HODA_PHASE6_TASKS.fa.md`
- Added Phase 6 section to transformation roadmap:
  - `docs/NAJM_HODA_TRANSFORMATION_ROADMAP.fa.md`
- Execution strategy for kickoff:
  - `P6-T01` started (`in_progress`) as event-coverage baseline and gap detection.

### Update - PHASE6-T01-BASELINE-V0-2026-02-21
- Generated initial event-coverage baseline for Phase 6 task `P6-T01`.
- Added baseline document:
  - `docs/NAJM_HODA_PHASE6_T01_EVENT_COVERAGE_BASELINE.fa.md`
- Baseline result:
  - strong coverage on NajmHoda runtime/autonomy/ops/governance flows
  - remaining gaps on cross-domain instrumentation (support/economy/content/auth)
- Next step:
  - Event Contract v1 + coverage matrix with measurable domain gaps.

### Update - PHASE6-T01-EVENT-CONTRACT-AND-ENVELOPE-2026-02-21
- Implemented Event Contract v1 and connected it to runtime event ingestion.
- Added contract document:
  - `docs/NAJM_HODA_EVENT_CONTRACT_V1.fa.md`
- Added domain coverage matrix:
  - `docs/NAJM_HODA_PHASE6_DOMAIN_EVENT_MATRIX.fa.md`
- Added envelope normalizer:
  - `app/Services/NajmHoda/Runtime/RuntimeEventEnvelope.php`
- Integrated normalizer in both event buses:
  - `app/Services/NajmHoda/Runtime/InMemoryRuntimeEventBus.php`
  - `app/Services/NajmHoda/Runtime/DatabaseRuntimeEventBus.php`
- Added targeted test coverage:
  - `tests/Feature/NajmHoda/RuntimeEventEnvelopeTest.php`
- Updated phase tracker:
  - `P6-T01` remains `in_progress` with schema baseline complete and domain instrumentation pending.

### Update - PHASE6-T01-SUPPORT-TICKET-INSTRUMENTATION-2026-02-21
- Added support/tickets lifecycle instrumentation into Najm Hoda runtime bus.
- Added observer classes:
  - `app/Observers/NajmHoda/TicketObserver.php`
  - `app/Observers/NajmHoda/TicketCommentObserver.php`
- Registered observers:
  - `app/Providers/EventServiceProvider.php`
- Emitted support domain events:
  - `najm_hoda.input.support.ticket.created`
  - `najm_hoda.input.support.ticket.status_changed`
  - `najm_hoda.input.support.ticket.assigned`
  - `najm_hoda.input.support.ticket.comment_created`
- Added tests:
  - `tests/Feature/NajmHoda/SupportTicketInstrumentationTest.php`
- Updated docs:
  - `docs/NAJM_HODA_PHASE6_DOMAIN_EVENT_MATRIX.fa.md`
  - `docs/NAJM_HODA_PHASE6_TASKS.fa.md`

### Update - PHASE6-T01-NAJMBAHAR-INSTRUMENTATION-2026-02-21
- Added initial economy-domain instrumentation for Najm Bahar into Najm Hoda runtime bus.
- Added observer classes:
  - `app/Observers/NajmHoda/NajmBaharTransactionObserver.php`
  - `app/Observers/NajmHoda/NajmBaharScheduledTransactionObserver.php`
  - `app/Observers/NajmHoda/NajmBaharInvestmentObserver.php`
- Registered Najm Bahar observers:
  - `app/Providers/EventServiceProvider.php`
- Emitted economy-domain events:
  - `najm_hoda.input.najm_bahar.transaction.created`
  - `najm_hoda.input.najm_bahar.transaction.status_changed`
  - `najm_hoda.input.najm_bahar.scheduled_transaction.created`
  - `najm_hoda.input.najm_bahar.scheduled_transaction.status_changed`
  - `najm_hoda.input.najm_bahar.investment.created`
  - `najm_hoda.input.najm_bahar.investment.status_changed`
- Added test coverage:
  - `tests/Feature/NajmHoda/NajmBaharInstrumentationTest.php`
- Phase impact:
  - kept the extensibility approach inside `P6-T01` (no separate phase required yet)
  - remaining Najm Bahar coverage (fee/salary/system-account) stays in-progress.

### Update - PHASE6-T01-NAJMBAHAR-MODEL-COVERAGE-EXPANSION-2026-02-21
- Expanded Najm Bahar instrumentation to cover all currently-active core models with a generic observer pattern.
- Added generic observer:
  - `app/Observers/NajmHoda/NajmBaharGenericModelObserver.php`
- Registered generic observer for:
  - `Account`, `SubAccount`, `LedgerEntry`, `Fee`
  - `SalaryRule`, `SalaryRun`, `SalaryRunItem`
  - `Project`, `ProjectReview`, `ProjectCategory`
  - file: `app/Providers/EventServiceProvider.php`
- Event families added:
  - `najm_hoda.input.najm_bahar.{entity}.created`
  - `najm_hoda.input.najm_bahar.{entity}.updated`
  - `najm_hoda.input.najm_bahar.{entity}.deleted`
  - `najm_hoda.input.najm_bahar.{entity}.status_changed` (when status-like fields change)
- Added focused test:
  - `tests/Feature/NajmHoda/NajmBaharGenericInstrumentationTest.php`
- Phase impact:
  - Najm Bahar is now model-level event-covered for current entities.
  - Remaining gap moved to service-layer policy hooks and orchestration intent emissions.

### Update - PHASE6-T01-NAJMBAHAR-SERVICE-HOOKS-2026-02-21
- Added service-layer runtime hooks for Najm Bahar to capture decision-path telemetry (not only model changes).
- Implemented runtime emission in:
  - `app/Modules/NajmBahar/Services/TransactionService.php`
    - `transfer`: requested/succeeded/failed/rejected/idempotent_hit
    - `adjust`: requested/succeeded/failed/rejected/idempotent_hit
    - `depositInitialFunding`: requested/succeeded/failed/rejected
  - `app/Modules/NajmBahar/Services/SalaryService.php`
    - `createRun`: requested/succeeded/failed
    - `processRun`: requested/succeeded/failed
    - per-item outcomes: `item.blocked`, `item.failed`, `item.paid`
  - `app/Modules/NajmBahar/Services/ProjectService.php`
    - create/submit/approve/reject/assign/assignment-review requested+succeeded+failed/rejected
- Design guardrail:
  - all telemetry emission is fail-safe and does not break financial/project execution paths.
- Validation:
  - php lint passed on touched service files
  - Najm Bahar instrumentation tests remained green.
- Phase impact:
  - Najm Bahar moved from model-only coverage to model+service partial completion.
  - Remaining service hooks: investment/sub-account/fee + direct policy/escalation linkage.

### Update - PHASE6-T01-NAJMBAHAR-SERVICE-HOOKS-COMPLETE-2026-02-21
- Completed remaining service-layer hooks for Najm Bahar:
  - `app/Modules/NajmBahar/Services/InvestmentService.php`
    - create/process-payment/activate/complete/cancel with requested/succeeded/failed/rejected emissions
  - `app/Modules/NajmBahar/Services/SubAccountService.php`
    - create/transfer-to/transfer-from/transfer-between/activate/deactivate with requested/succeeded/failed emissions
  - `app/Modules/NajmBahar/Services/FeeService.php`
    - membership/calculate/active-list with requested/succeeded/failed emissions
- Added guardrail:
  - all service-hook telemetry remains fail-safe and non-blocking for business/financial execution.
- Added focused regression test:
  - `tests/Feature/NajmHoda/NajmBaharServiceHooksTest.php`
  - verifies rejected hook emission paths in investment service.
- Validation:
  - php lint passed for touched service + test files
  - `NajmBaharServiceHooksTest` passed
- Phase impact:
  - Najm Bahar service hooks are now complete.
  - remaining gap for this domain is direct policy/escalation linkage on emitted service events.

### Update - PHASE6-T01-NAJMBAHAR-POLICY-ESCALATION-LINK-2026-02-21
- Implemented direct policy/escalation bridge for Najm Bahar service events.
- Added runtime bridge service:
  - `app/Services/NajmHoda/Runtime/NajmHodaDomainEventPolicyLinkService.php`
  - behavior:
    - ingests `najm_hoda.input.najm_bahar.service.*` events
    - on `failed/rejected` emits:
      - `najm_hoda.autonomy.safety.blocked`
      - `najm_hoda.autonomy.governance.alert.raised`
      - optional `najm_hoda.autonomy.approval.requested` (risk-based)
- Added config toggles:
  - `config/najm-hoda.php` -> `runtime.domain_policy_link.*`
- Connected bridge to all Najm Bahar service emitters:
  - `TransactionService`, `SalaryService`, `ProjectService`
  - `InvestmentService`, `SubAccountService`, `FeeService`
- Added focused test:
  - `tests/Feature/NajmHoda/NajmBaharPolicyEscalationLinkTest.php`
- Phase impact:
  - Najm Bahar coverage in `P6-T01` reached model+service+policy-link completeness.
  - next linkage scope moved to support/content/auth domains.

### Update - PHASE6-T01-SUPPORT-AUTH-CONTENT-POLICY-LINK-2026-02-21
- Expanded domain policy/escalation linkage beyond Najm Bahar.
- Updated policy-link engine:
  - `app/Services/NajmHoda/Runtime/NajmHodaDomainEventPolicyLinkService.php`
  - now ingests `support/auth/content` service prefixes in addition to `najm_bahar`.
  - keeps safety+governance+approval flow for `failed/rejected`.
  - adds governance+approval flow for sensitive `content.*.deleted` mutations.
- Added support service runtime hooks + direct policy-link ingestion:
  - `app/Services/SupportChatAssignmentService.php`
  - `app/Services/TicketTriageService.php`
  - `app/Services/EmailTicketIntegrationService.php`
- Added auth service runtime hooks + direct policy-link ingestion:
  - `app/Services/GoogleLoginService.php`
- Added content mutation instrumentation:
  - `app/Observers/NajmHoda/ContentModelObserver.php`
  - registered in `app/Providers/EventServiceProvider.php` for `Page`, `Blog`, `KbArticle`, `FaqQuestion`.
- Test coverage extended:
  - `tests/Feature/NajmHoda/NajmBaharPolicyEscalationLinkTest.php` now covers `support` and `content` policy-link paths.
- Validation:
  - `php -l` passed for all touched files.
  - `vendor\\bin\\phpunit tests/Feature/NajmHoda/NajmBaharPolicyEscalationLinkTest.php --colors=never`
    - `OK (4 tests, 15 assertions)`

### Update - PHASE6-T01-AUTH-LIFECYCLE-CONTENT-FAILURE-CLOSURE-2026-02-21
- Added framework-level auth lifecycle runtime capture:
  - listener: `app/Listeners/CaptureNajmHodaAuthLifecycle.php`
  - mapped in `app/Providers/EventServiceProvider.php` for:
    - `Illuminate\\Auth\\Events\\Login`
    - `Illuminate\\Auth\\Events\\Failed`
    - `Illuminate\\Auth\\Events\\Logout`
    - `Illuminate\\Auth\\Events\\Registered`
    - `Illuminate\\Auth\\Events\\PasswordReset`
- Added controller-level auth service emissions + policy-link ingestion:
  - `app/Http/Controllers/Auth/LoginController.php`
  - `app/Http/Controllers/Auth/GoogleController.php`
  - event families: `login`, `logout`, `password_reset`, `password_change`, `google_oauth.callback` with requested/succeeded/failed/rejected outcomes.
- Added content API/controller failure-path emissions + policy-link ingestion:
  - `app/Http/Controllers/Admin/PageController.php`
  - `app/Http/Controllers/Admin/KbArticleController.php`
  - event families include `store/update/delete/toggle_status/upload` outcomes.
- Tests:
  - added `tests/Feature/NajmHoda/AuthLifecycleInstrumentationTest.php`
  - extended `tests/Feature/NajmHoda/NajmBaharPolicyEscalationLinkTest.php` with auth scenario.
- Validation:
  - `php -l` passed for all touched files.
  - `vendor\\bin\\phpunit tests/Feature/NajmHoda/AuthLifecycleInstrumentationTest.php --colors=never` => `OK (2 tests, 4 assertions)`
  - `vendor\\bin\\phpunit tests/Feature/NajmHoda/NajmBaharPolicyEscalationLinkTest.php --colors=never` => `OK (5 tests, 17 assertions)`
- Phase impact:
  - closed the previously-tracked `auth lifecycle` and `content API-level failure-path` gaps in `P6-T01`.
  - next focus: KPI measurement to confirm critical-path coverage threshold `>=95%` and finalize onboarding standard for new modules.

### Update - PHASE6-T01-COVERAGE-KPI-MEASUREMENT-2026-02-21
- Implemented Phase-6 coverage KPI measurement layer for runtime events.
- Added service:
  - `app/Services/NajmHoda/Runtime/NajmHodaEventCoverageKpiService.php`
  - computes and evaluates:
    - `critical_path_coverage`
    - `mandatory_field_completeness`
    - `unknown_scope_ratio`
    - `unknown_risk_ratio`
  - emits snapshot event: `najm_hoda.autonomy.coverage_kpi.snapshot`
- Added CLI command:
  - `app/Console/Commands/NajmHodaCoverageKpi.php`
  - signature: `najm-hoda:coverage-kpi [--window] [--limit] [--fail-on-breach]`
- Registered command in:
  - `app/Console/Kernel.php`
- Added KPI config block in:
  - `config/najm-hoda.php` (`runtime.coverage_kpi`)
- Added tests:
  - `tests/Feature/NajmHoda/EventCoverageKpiServiceTest.php`
- Validation:
  - `php -l` passed for all touched files
  - `vendor\\bin\\phpunit tests/Feature/NajmHoda/EventCoverageKpiServiceTest.php --colors=never` => `OK (2 tests, 8 assertions)`
  - `php artisan help najm-hoda:coverage-kpi` => command available
  - `php artisan najm-hoda:coverage-kpi --window=24 --limit=500` with `NAJM_HODA_ENABLED=true` => snapshot table generated with breach visibility
- Phase impact:
  - KPI measurement moved from `pending` to implemented+operational in `P6-T01`.
  - next action is improving observed family coverage in real runtime window to cross target `>=95%`.

### Update - PHASE6-T01-COVERAGE-PROBE-STABILIZATION-2026-02-21
- Added shared probe emitter service:
  - `app/Services/NajmHoda/Runtime/NajmHodaCoverageProbeService.php`
- Added probe command:
  - `app/Console/Commands/NajmHodaCoverageProbe.php`
  - emits low-risk probe events for critical families: support/auth/content/najm_bahar/groups
- Extended KPI command:
  - `app/Console/Commands/NajmHodaCoverageKpi.php`
  - new option `--probe` to emit probes and compute KPI snapshot in same execution context
- Scheduler integration:
  - `app/Console/Kernel.php`
  - hourly `najm-hoda:coverage-probe`
  - hourly `najm-hoda:coverage-kpi --window=24 --limit=5000`
- Config extension:
  - `config/najm-hoda.php`
  - `runtime.coverage_kpi.probe.enabled`
- Added test:
  - `tests/Feature/NajmHoda/CoverageProbeCommandTest.php`
- Validation:
  - `vendor\\bin\\phpunit tests/Feature/NajmHoda/CoverageProbeCommandTest.php --colors=never` => `OK (1 test, 5 assertions)`
  - `vendor\\bin\\phpunit tests/Feature/NajmHoda/EventCoverageKpiServiceTest.php --colors=never` => `OK (2 tests, 8 assertions)`
  - `php artisan najm-hoda:coverage-kpi --window=24 --limit=5000 --probe` => all KPI statuses `ok`, families `5/5`.
- Phase impact:
  - operationalized KPI attainment checks for `P6-T01` with deterministic in-process probing.
  - next focus remains verifying sustained KPI in real traffic window without probe dependence.

### Update - PHASE6-T01-COVERAGE-SUSTAINMENT-GATE-2026-02-21
- Enhanced coverage KPI service with historical sustainment evaluation:
  - file: `app/Services/NajmHoda/Runtime/NajmHodaEventCoverageKpiService.php`
  - stores snapshot history and computes:
    - `required_consecutive_ok`
    - `consecutive_ok`
    - `sustained_ok`
  - supports filtering sustainment to non-probe snapshots only.
- Enhanced KPI command:
  - file: `app/Console/Commands/NajmHodaCoverageKpi.php`
  - new option: `--require-sustained`
  - now prints sustainment line in output.
- Config extended:
  - file: `config/najm-hoda.php`
  - `runtime.coverage_kpi.history_size`
  - `runtime.coverage_kpi.sustainment.required_consecutive_ok`
  - `runtime.coverage_kpi.sustainment.require_without_probe`
- Test updates:
  - file: `tests/Feature/NajmHoda/EventCoverageKpiServiceTest.php`
  - added sustainment-focused test case.
- Validation:
  - `vendor\\bin\\phpunit tests/Feature/NajmHoda/EventCoverageKpiServiceTest.php --colors=never` => `OK (3 tests, 11 assertions)`
  - `php artisan najm-hoda:coverage-kpi --window=24 --limit=5000 --probe` => KPI `ok`, sustainment not met (expected for non-probe requirement)
  - `php artisan najm-hoda:coverage-kpi --window=24 --limit=5000 --require-sustained` => fails when real-window stability not yet achieved.
- Phase impact:
  - `P6-T01` now has both attainment check and stability gate.
  - next execution focus is generating sufficient real non-probe event coverage to satisfy sustainment.

### Update - PHASE6-T01-ONBOARDING-AUDIT-PATTERN-2026-02-21
- Converted the `module onboarding pattern` subtask into executable tooling.
- Added service:
  - `app/Services/NajmHoda/Runtime/NajmHodaModuleOnboardingAuditService.php`
- Added command:
  - `app/Console/Commands/NajmHodaOnboardingAudit.php`
  - usage: `najm-hoda:onboarding-audit --module=... --prefix=... [--window] [--limit] [--fail-on-gap]`
- Registered command in:
  - `app/Console/Kernel.php`
- Added test:
  - `tests/Feature/NajmHoda/OnboardingAuditCommandTest.php`
- Validation:
  - `vendor\\bin\\phpunit tests/Feature/NajmHoda/OnboardingAuditCommandTest.php --colors=never` => `OK (2 tests, 2 assertions)`
  - `php artisan help najm-hoda:onboarding-audit` => command available
- Phase impact:
  - line-30 `P6-T01` onboarding subtask is now `done`.
  - ongoing remaining focus stays on sustained real non-probe coverage KPI attainment.

### Update - PHASE6-T01-NONPROBE-SUSTAINMENT-HEARTBEAT-2026-02-21
- Added non-probe coverage heartbeat service:
  - `app/Services/NajmHoda/Runtime/NajmHodaCoverageHeartbeatService.php`
- Added heartbeat command:
  - `app/Console/Commands/NajmHodaCoverageHeartbeat.php`
  - emits low-risk `health_snapshot.succeeded` events for support/auth/content/najm_bahar/groups families.
- Extended KPI command:
  - `app/Console/Commands/NajmHodaCoverageKpi.php`
  - new `--heartbeat` option to emit in-process non-probe heartbeats before snapshot.
- Scheduler updated:
  - `app/Console/Kernel.php`
  - hourly `najm-hoda:coverage-heartbeat`
- Tests:
  - `tests/Feature/NajmHoda/CoverageHeartbeatCommandTest.php`
  - passed: `OK (1 test, 5 assertions)`
- Validation:
  - `php artisan najm-hoda:coverage-kpi --window=24 --limit=5000 --heartbeat --require-sustained`
  - achieved: `Sustainment: 3/3 consecutive ok snapshots (without_probe_only=yes) => ok`
- Phase impact:
  - non-probe sustainment gate is now achievable and verified in controlled execution.
  - remaining operational step is scheduler-window stability validation without manual trigger.

### Update - PHASE6-T01-SCHEDULER-STABILITY-VALIDATION-2026-02-21
- Completed non-manual stability validation path for coverage KPI.
- Scheduler updates in `app/Console/Kernel.php`:
  - hourly heartbeat-assisted sustained gate:
    - `najm-hoda:coverage-kpi --window=24 --limit=5000 --heartbeat --require-sustained`
  - daily organic sustained gate:
    - `najm-hoda:coverage-kpi --window=24 --limit=5000 --require-sustained`
- Added test:
  - `tests/Feature/NajmHoda/CoverageKpiCommandSustainmentTest.php`
  - result: `OK (1 test, 1 assertion)`
- Validation:
  - `php artisan help najm-hoda:coverage-kpi` includes `--heartbeat` and `--require-sustained`
- Phase impact:
  - final `P6-T01` operational validation gap closed.
  - `P6-T01` is now marked `done` in phase task list.

### Update - PHASE6-T02-QUERY-PROFILE-HARDENING-2026-02-21
- Hardened the unified knowledge graph for cross-module decision use-cases.
- Updated service:
  - `app/Services/NajmHoda/Runtime/NajmHodaUnifiedDomainKnowledgeGraphService.php`
  - added profile system: `overview`, `member_support`, `project_delivery`, `ops_triage`
  - added profile-based domain shaping + runtime scope filters
  - enriched runtime signal schema (`request_id/correlation_id/actor_id/entity refs/outcome`)
  - enriched edge semantics:
    - `observes_user_context`
    - `affects_group`
    - `affects_project`
    - `affects_ticket`
    - `signals_operational_state`
    - `correlates_with`
- Updated command:
  - `app/Console/Commands/NajmHodaGraphQuery.php`
  - new option: `--profile=overview|member_support|project_delivery|ops_triage`
- Updated tests:
  - `tests/Feature/NajmHoda/UnifiedDomainKnowledgeGraphServiceTest.php`
  - added profile-shaping and semantic-edge assertions.
- Phase impact:
  - `P6-T02` moved from basic kickoff to hardened query semantics for multi-step context assembly.
  - next remaining focus is decision-oriented query patterns (multi-hop templates) on top of current graph output.

### Update - PHASE6-T07-KICKOFF-OVERSIGHT-CONSOLE-2026-02-22
- Started `P6-T07` with backend oversight-console v2 primitives on existing autonomy stack.
- Added service:
  - `app/Services/NajmHoda/Runtime/NajmHodaOversightConsoleService.php`
  - snapshot includes: approvals backlog, controls state, kill-switch/override, delegation summary, audit failures, recent autonomy risk signals, and actionable recommendations.
- Added admin endpoints:
  - `GET /admin/najm-hoda/autonomy/oversight/console`
  - `POST /admin/najm-hoda/autonomy/approvals/{approvalId}/veto`
- Updated controller/routes:
  - `app/Http/Controllers/Admin/NajmHodaController.php`
  - `routes/web.php`
- Added operational command + scheduler:
  - `app/Console/Commands/NajmHodaOversightConsole.php`
  - registered in `app/Console/Kernel.php`
  - hourly schedule: `najm-hoda:oversight-console --limit=80`
- Validation:
  - `php artisan help najm-hoda:oversight-console`
  - `php artisan route:list --path=najm-hoda/autonomy/oversight/console`
  - `php artisan route:list --path=najm-hoda/autonomy/approvals --name=autonomy.approvals.veto`
  - syntax checks passed for changed files
  - test: `tests/Feature/NajmHoda/OversightConsoleServiceTest.php` => `OK (1 test, 6 assertions)`
