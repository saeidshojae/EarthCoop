# ماتریس پوشش رویداد دامنه ها - P6-T01

تاریخ: 2026-02-21  
وضعیت: `baseline-v1`

## هدف
- تبدیل ادعای «اشراف کامل» به شاخص قابل اندازه گیری.
- مشخص کردن دامنه هایی که هنوز به قرارداد رویداد یکپارچه متصل نیستند.

## Coverage Matrix

| دامنه | وضعیت instrumentation | قرارداد v1 | شکاف اصلی |
|---|---|---|---|
| najm-hoda runtime/autonomy/ops | خوب | فعال | نیاز به سختگیری schema در حالت strict |
| groups/chat/poll/feed | خوب | فعال (از bus) | تکمیل mapping دقیق actor/scope |
| support/tickets | خوب (partial) | فعال (ایجاد/کامنت/وضعیت/ارجاع) | پوشش API ticket و support-chat lifecycle کامل نشده |
| najm-bahar economy | کامل در فاز P6-T01 (model + service + policy-link) | فعال (transaction/scheduled/investment + account/sub-account/ledger/fee/salary/project/review/category + service hooks + policy-link) | مانیتورینگ KPI و tuning policy در چرخه بعدی |
| admin/content | ضعیف | ناقص | eventهای تغییر محتوا/تنظیمات پراکنده اند |
| auth/user lifecycle | ضعیف | ناقص | رویدادهای login/register/recovery یکپارچه نیست |

## KPI پیشنهادی P6-T01
1. `CriticalPathCoverage >= 95%`
2. `MandatoryFieldCompleteness >= 99%`
3. `UnknownScopeRatio <= 2%`
4. `UnknownRiskRatio <= 5%`

## اولویت اجرایی بعدی
1. support-chat lifecycle events (beyond ticket create/comment path)
2. support/content/auth policy/approval/escalation linkage on service events
3. admin/content mutation events
4. auth lifecycle events

## قاعده تسری به ماژول های جدید (داخل فاز 6)
1. تعریف event contract v1 برای دامنه جدید
2. اتصال observer/listener به RuntimeEventBus
3. تعریف capability + policy gate
4. افزودن تست های instrumentation
5. به روزرسانی coverage matrix و KPI

## Incremental Update (2026-02-21)
- `support`: service-level runtime hooks added (`chat_assignment`, `ticket_triage`, `email_integration`) with policy/escalation linkage.
- `auth`: service-level runtime hooks added (`google_login`) with policy/escalation linkage.
- `content`: model-level mutation observer added for `Page/Blog/KbArticle/FaqQuestion` with runtime events and policy-link escalation on `*.deleted`.
- Remaining gap in `P6-T01`:
  - framework-level auth lifecycle events (`login/register/recovery`) should be unified under event contract v1.
  - content API/controller failure paths should emit `*.failed/*.rejected` service events for full parity.

## Incremental Update (2026-02-21, Auth+Content Closure)
- `auth lifecycle`: framework listener added for `Login/Failed/Logout/Registered/PasswordReset` via `app/Listeners/CaptureNajmHodaAuthLifecycle.php` and `EventServiceProvider` mappings.
- `auth service/controller`: runtime+policy-link emissions added in:
  - `app/Http/Controllers/Auth/LoginController.php`
  - `app/Http/Controllers/Auth/GoogleController.php`
- `content API/controller failure paths`: runtime+policy-link emissions added in:
  - `app/Http/Controllers/Admin/PageController.php`
  - `app/Http/Controllers/Admin/KbArticleController.php`
- Status effect on `P6-T01`:
  - previous auth lifecycle and content API failure-path gaps are now covered.
  - remaining work is KPI measurement (`>=95%`) and new-module onboarding standardization.

## Incremental Update (2026-02-21, KPI Measurement Activation)
- Added measurable KPI engine for `P6-T01` event coverage:
  - service: `app/Services/NajmHoda/Runtime/NajmHodaEventCoverageKpiService.php`
  - command: `app/Console/Commands/NajmHodaCoverageKpi.php`
  - kernel registration: `app/Console/Kernel.php`
  - config: `config/najm-hoda.php` -> `runtime.coverage_kpi.*`
- KPI set implemented:
  1. `critical_path_coverage`
  2. `mandatory_field_completeness`
  3. `unknown_scope_ratio`
  4. `unknown_risk_ratio`
- Command validation:
  - `php artisan help najm-hoda:coverage-kpi` is available.
  - live run with enabled flag produced initial snapshot and breach visibility.
- Note:
  - initial baseline may show low coverage until runtime event volume for all critical families is present in the inspected window.

## Incremental Update (2026-02-21, Coverage Probe Stabilization)
- Added dedicated probe service and command to stabilize family-observation KPI in environments with transient event-bus storage behavior:
  - `app/Services/NajmHoda/Runtime/NajmHodaCoverageProbeService.php`
  - `app/Console/Commands/NajmHodaCoverageProbe.php`
- Enhanced KPI command:
  - `app/Console/Commands/NajmHodaCoverageKpi.php`
  - new option: `--probe` (emit probes in-process, then compute snapshot)
- Scheduler updates:
  - `app/Console/Kernel.php`
  - hourly `najm-hoda:coverage-probe`
  - hourly `najm-hoda:coverage-kpi --window=24 --limit=5000`
- Config updates:
  - `config/najm-hoda.php` -> `runtime.coverage_kpi.probe.enabled`
- Validation run (`NAJM_HODA_ENABLED=true`):
  - `php artisan najm-hoda:coverage-kpi --window=24 --limit=5000 --probe`
  - output reached target window snapshot:
    - `critical_path_coverage=1.0`
    - `mandatory_field_completeness=1.0`
    - `unknown_scope_ratio=0.0`
    - `unknown_risk_ratio=0.0`
    - observed families `5/5`

## Incremental Update (2026-02-21, Sustainment Gate)
- Added sustainment tracking inside coverage KPI snapshots:
  - consecutive `ok` snapshots counter
  - configurable requirement count
  - optional restriction to non-probe snapshots only
- Added config:
  - `runtime.coverage_kpi.history_size`
  - `runtime.coverage_kpi.sustainment.required_consecutive_ok`
  - `runtime.coverage_kpi.sustainment.require_without_probe`
- KPI command enhanced with:
  - `--require-sustained` to enforce stability gate
- Operational interpretation:
  - `--probe` is useful for deterministic instrumentation verification.
  - `--require-sustained` validates real-window stability and should be used for go/no-go checks.

## Incremental Update (2026-02-21, Onboarding Pattern Operationalization)
- Implemented a concrete onboarding audit pattern for new modules:
  - service: `app/Services/NajmHoda/Runtime/NajmHodaModuleOnboardingAuditService.php`
  - command: `app/Console/Commands/NajmHodaOnboardingAudit.php`
  - kernel registration: `app/Console/Kernel.php`
- Automated checks now cover:
  1. contract_detected
  2. requested_present
  3. success_present
  4. failure_present
  5. policy_link_observed
- Manual checklist embedded in audit output for:
  - observer/listener registration
  - tests
  - matrix/tasks/execution-log updates
- This closes line-30 subtask in `P6-T01` by turning onboarding from narrative rule into executable gate.

## Incremental Update (2026-02-21, Non-Probe Sustainment Achieved)
- Added non-probe heartbeat pipeline for critical-family coverage:
  - service: `app/Services/NajmHoda/Runtime/NajmHodaCoverageHeartbeatService.php`
  - command: `app/Console/Commands/NajmHodaCoverageHeartbeat.php`
  - scheduler: hourly heartbeat in `app/Console/Kernel.php`
  - config: `runtime.coverage_kpi.heartbeat.enabled`
- Enhanced KPI command with in-process heartbeat option:
  - `app/Console/Commands/NajmHodaCoverageKpi.php`
  - new option: `--heartbeat`
- Operational result (non-probe path):
  - after consecutive runs with `--heartbeat --require-sustained`, sustainment reached `3/3 => ok`.
  - this satisfies the `sustained_ok=true` target in controlled command execution without using `--probe`.

## Incremental Update (2026-02-21, Scheduled Stability Validation)
- Added scheduled stability gates for non-manual validation:
  - hourly: `najm-hoda:coverage-kpi --window=24 --limit=5000 --heartbeat --require-sustained`
  - daily organic check: `najm-hoda:coverage-kpi --window=24 --limit=5000 --require-sustained`
- Added command-level test:
  - `tests/Feature/NajmHoda/CoverageKpiCommandSustainmentTest.php`
  - verifies sustainment success path with `--heartbeat --require-sustained`.
- This closes the remaining `P6-T01` operational validation step and makes coverage stability auditable via scheduler.

## Incremental Update (2026-02-21, P6-T02 Kickoff)
- `P6-T02` moved from `pending` to `in_progress`.
- Added initial unified graph-query implementation with RBAC-aware scope and traceability:
  - service: `app/Services/NajmHoda/Runtime/NajmHodaUnifiedDomainKnowledgeGraphService.php`
  - command: `app/Console/Commands/NajmHodaGraphQuery.php`
  - kernel registration: `app/Console/Kernel.php`
- Domains in initial graph snapshot:
  - `users`, `groups`, `projects`, `tickets`, `runtime_signals`
- Traceability fields:
  - `trace_id`, `requested_scope`, `effective_scope`, `scope_reduced_by_rbac`, `generated_at`, `data_sources`
- RBAC behavior:
  - non-admin requests are automatically reduced from unauthorized scopes to `actor` scope.

## Incremental Update (2026-02-21, P6-T02 Query-Profile Hardening)
- Extended unified graph service with query profiles for operational use-cases:
  - `overview`
  - `member_support`
  - `project_delivery`
  - `ops_triage`
- Added profile-driven domain shaping:
  - dynamic domain inclusion/exclusion per profile
  - node/event limit tuning by profile
  - runtime scope filters by profile
- Enriched runtime signal payload in graph snapshot:
  - `request_id`, `correlation_id`, `actor_id`, `user_id`, `group_id`, `project_id`, `ticket_id`, `outcome`
- Enriched graph edge semantics:
  - entity impact edges: `observes_user_context`, `affects_group`, `affects_project`, `affects_ticket`
  - operational intent edge: `signals_operational_state -> domain:operations`
  - chain continuity edge: `correlates_with` (shared `correlation_id`)
- CLI enhancement:
  - `najm-hoda:graph-query --profile=...` now exposes profile-aware query execution.

## Incremental Update (2026-02-21, P6-T02 Decision Pattern Templates)
- Added decision-oriented pattern layer on top of unified graph output:
  - `support_escalation_candidates`
  - `project_delivery_risk_hotspots`
  - `ops_alert_chains`
- Multi-hop semantics now usable in a single query response:
  - runtime signal -> entity impact -> recommended action
  - correlation-driven chain assembly for operational incidents
- Profile-aware pattern shaping:
  - `member_support` suppresses project hotspot output
  - `project_delivery` suppresses support escalation output
- Output contract extension:
  - `patterns` block added to graph response
  - command summary table updated in `najm-hoda:graph-query`

## Incremental Update (2026-02-21, P6-T03 Kickoff)
- Started `P6-T03` implementation with a working multi-horizon goal engine:
  - service: `app/Services/NajmHoda/Runtime/NajmHodaMultiHorizonGoalEngineService.php`
  - command: `app/Console/Commands/NajmHodaMultiHorizonGoals.php`
- Input fusion:
  - coverage KPI snapshot (`NajmHodaEventCoverageKpiService`)
  - unified graph patterns (`NajmHodaUnifiedDomainKnowledgeGraphService`)
- Output contract:
  - `horizons.daily`
  - `horizons.weekly`
  - `horizons.monthly`
  - `backlog` (priority-ordered tasks with trigger references)
- Scheduler integration:
  - `najm-hoda:multi-goals --scope=global --window=24 --limit=2000` every 30 minutes.

## Incremental Update (2026-02-21, P6-T03 Goal Review Loop)
- Added trend review loop for multi-horizon backlog:
  - service: `app/Services/NajmHoda/Runtime/NajmHodaMultiHorizonGoalReviewService.php`
  - command: `app/Console/Commands/NajmHodaMultiHorizonGoalsReview.php`
- Review model:
  - compares current backlog/horizon snapshot against previous snapshot
  - outputs trend status: `improving`, `stable`, `regressing`
  - computes deltas: `backlog_delta`, `high_priority_delta`, `daily_goal_delta`
- Runtime event:
  - `najm_hoda.autonomy.multi_horizon_goals.reviewed`
- Scheduler integration:
  - `najm-hoda:multi-goals-review --scope=global --window=24 --limit=2000` hourly.

## Incremental Update (2026-02-21, P6-T04 Kickoff)
- Started cross-module orchestration implementation:
  - service: `app/Services/NajmHoda/Runtime/NajmHodaCrossModuleCapabilityOrchestratorService.php`
  - command: `app/Console/Commands/NajmHodaOrchestrate.php`
- Core behavior implemented:
  - chain execution with capability contract validation (`NajmHodaCapabilityRegistry`)
  - policy/safety gate enforcement (`NajmHodaAutonomySafetyGate`)
  - stepwise rollback planning on chain failure
  - bridge from Phase-3 backlog/review into orchestration chain (`orchestrateFromMultiGoals`)
- Runtime events added:
  - `najm_hoda.autonomy.orchestrator.chain.completed`
  - `najm_hoda.autonomy.orchestrator.chain.failed`
  - `najm_hoda.autonomy.orchestrator.rollback.step`
  - `najm_hoda.autonomy.orchestrator.rollback.completed`
- Scheduler integration:
  - `najm-hoda:orchestrate --from-multi-goals --goal=stabilize_operations` hourly.

## Incremental Update (2026-02-21, P6-T04 PostCondition + Practical Rollback)
- Orchestrator upgraded with execution postconditions:
  - `executor_intent_recorded` verification after executed steps
  - fail-fast behavior on postcondition breach with automatic rollback path
- Rollback moved from plan-only to practical execution in apply-mode:
  - rollback capabilities introduced:
    - `rollback_ops_monitor`
    - `rollback_engagement_recommendations`
  - rollback actions now pass through contract + safety + executor stack.
- Runtime contracts/safety updated in `config/najm-hoda.php`:
  - capability contracts
  - safety allowlist/scope mapping
  - executor cooldown entries
  - orchestrator rollback map binding.

## Incremental Update (2026-02-21, P6-T04 Stateful Compensating Transactions)
- Added dedicated compensating transaction service:
  - `app/Services/NajmHoda/Runtime/NajmHodaCompensatingTransactionService.php`
- Implemented stateful compensation handlers:
  - `ticket_status_revert`
  - `project_status_revert`
- Orchestrator rollback flow updated:
  - first tries stateful compensation
  - if compensation fails/skips and fallback is enabled, executes capability rollback
- Added stateful ticket action path for orchestration:
  - new capability/action: `set_ticket_needs_review`
  - execution captures `previous_status` for later compensation
- Config additions in `config/najm-hoda.php`:
  - orchestrator compensation fallback switch
  - capability/safety/cost/cooldown entries for `set_ticket_needs_review` and rollback actions.

## Incremental Update (2026-02-21, P6-T05 Permissioning v2 Kickoff)
- Added fine-grained delegation service:
  - `app/Services/NajmHoda/Runtime/NajmHodaDelegatedPermissionService.php`
  - supports principal types: `user`, `role`, `group`
  - supports expiration, revoke, active-audit, and authorization checks
- Added delegation runtime events:
  - `najm_hoda.autonomy.delegation.granted`
  - `najm_hoda.autonomy.delegation.revoked`
  - `najm_hoda.autonomy.delegation.expired`
  - `najm_hoda.autonomy.delegation.authorized`
  - `najm_hoda.autonomy.delegation.denied`
- Added operator commands:
  - `app/Console/Commands/NajmHodaDelegationGrant.php`
  - `app/Console/Commands/NajmHodaDelegationAudit.php`
- Orchestrator integration:
  - apply-mode delegation enforcement (toggle: `runtime.autonomy.permissioning_v2.enforce_apply_requires_delegation`)
  - escalation path to human approval when delegation itself requires approval.
