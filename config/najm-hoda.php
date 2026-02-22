<?php

return [
    /*
    |--------------------------------------------------------------------------
    | نجم‌هدا - نرم‌افزار جامع مدیریت هوشمند
    |--------------------------------------------------------------------------
    |
    | تنظیمات سیستم هوش مصنوعی نجم‌هدا
    |
    */

    /**
     * فعال/غیرفعال کردن کل سیستم
     */
    'enabled' => env('NAJM_HODA_ENABLED', true),

    /**
     * حالت Mock - برای تست بدون API واقعی
     */
    'mock_mode' => env('NAJM_HODA_MOCK_MODE', false),

    /**
     * ارائه‌دهنده سرویس AI
     */
    'provider' => [
        'type' => env('AI_PROVIDER', 'openai'), // openai, openrouter, claude, gemini
        'api_key' => env('AI_API_KEY', env('OPENAI_API_KEY')),
        'organization' => env('AI_ORGANIZATION'),
        'model' => env('AI_MODEL', 'gpt-4-turbo-preview'),
        'base_url' => env('AI_BASE_URL', 'https://api.openai.com/v1'),
        'openrouter' => [
            'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
            'site_url' => env('OPENROUTER_SITE_URL'),
            'app_name' => env('OPENROUTER_APP_NAME', 'NewEarthCoop'),
        ],
    ],

    /**
     * تنظیمات عوامل (Agents)
     */
    'agents' => [
        'engineer' => [
            'enabled' => env('AGENT_ENGINEER_ENABLED', true),
            'temperature' => env('AGENT_ENGINEER_TEMPERATURE', 0.2), // کمتر = دقیق‌تر، بیشتر = خلاق‌تر
            'max_tokens' => env('AGENT_ENGINEER_MAX_TOKENS', 4000),
        ],
        'pilot' => [
            'enabled' => env('AGENT_PILOT_ENABLED', true),
            'temperature' => env('AGENT_PILOT_TEMPERATURE', 0.5),
            'max_tokens' => env('AGENT_PILOT_MAX_TOKENS', 3000),
        ],
        'steward' => [
            'enabled' => env('AGENT_STEWARD_ENABLED', true),
            'temperature' => env('AGENT_STEWARD_TEMPERATURE', 0.7),
            'max_tokens' => env('AGENT_STEWARD_MAX_TOKENS', 2000),
            'source_priorities' => [
                'kb_articles' => env('STEWARD_PRIORITY_KB', 10),
                'knowledge_files' => env('STEWARD_PRIORITY_FILES', 8),
                'faq_questions' => env('STEWARD_PRIORITY_FAQ', 7),
                'blog_posts' => env('STEWARD_PRIORITY_BLOG', 5),
            ],
        ],
        'guide' => [
            'enabled' => env('AGENT_GUIDE_ENABLED', true),
            'temperature' => env('AGENT_GUIDE_TEMPERATURE', 0.6),
            'max_tokens' => env('AGENT_GUIDE_MAX_TOKENS', 3000),
        ],
        'architect' => [
            'enabled' => env('AGENT_ARCHITECT_ENABLED', true),
            'temperature' => env('AGENT_ARCHITECT_TEMPERATURE', 0.4),
            'max_tokens' => env('AGENT_ARCHITECT_MAX_TOKENS', 4000),
        ],
    ],

    /**
     * مسیر فایل‌های دانش پروژه (Knowledge Base)
     */
    'knowledge_base_path' => storage_path('najm-hoda/knowledge'),

    /**
     * Cache
     */
    'cache' => [
        'enabled' => env('NAJM_HODA_CACHE_ENABLED', true),
        'ttl' => 3600, // 1 hour
        'prefix' => 'najm_hoda_',
    ],

    /**
     * محدودیت استفاده (Rate Limiting)
     */
    'rate_limit' => [
        'enabled' => env('NAJM_HODA_RATE_LIMIT_ENABLED', true),
        'max_requests_per_minute' => env('NAJM_HODA_RATE_LIMIT_MAX_REQUESTS', 20),
        'max_requests_per_hour' => 100,
        'max_requests_per_day' => 500,
    ],

    /**
     * ردیابی هزینه
     */
    'cost_tracking' => [
        'enabled' => true,
        'cost_per_1k_tokens' => [
            'gpt-4-turbo-preview' => 0.01,
            'gpt-4' => 0.03,
            'gpt-3.5-turbo' => 0.0015,
            'claude-3-opus-20240229' => 0.015,
            'claude-3-sonnet-20240229' => 0.003,
        ],
    ],

    /**
     * اقدامات خودکار
     * 
     * توجه: فعال‌سازی با احتیاط!
     */
    'auto_actions' => [
        'code_generation' => env('NAJM_HODA_AUTO_CODE_GEN', false),
        'code_deployment' => env('NAJM_HODA_AUTO_DEPLOY', false),
        'database_changes' => env('NAJM_HODA_AUTO_DB_CHANGES', false),
        'user_responses' => env('NAJM_HODA_AUTO_USER_RESPONSES', true),
    ],

    /**
     * لاگ‌گذاری
     */
    'logging' => [
        'enabled' => true,
        'channel' => 'najm_hoda',
        'level' => env('NAJM_HODA_LOG_LEVEL', 'info'),
        'log_inputs' => true,
        'log_outputs' => true,
    ],

    'runtime' => [
        'entry_policy' => [
            'rate_limit' => [
                'max_requests_per_minute' => env('NAJM_HODA_ENTRY_RATE_MAX_PER_MINUTE', 120),
                'max_chat_requests_per_minute' => env('NAJM_HODA_ENTRY_RATE_CHAT_MAX_PER_MINUTE', 30),
            ],
        ],
        'event_bus' => [
            'driver' => env('NAJM_HODA_RUNTIME_EVENT_BUS_DRIVER', 'database'), // database | in_memory
            'max_events' => env('NAJM_HODA_RUNTIME_EVENT_BUS_MAX_EVENTS', 500),
            'retention_days' => env('NAJM_HODA_RUNTIME_EVENT_RETENTION_DAYS', 14),
            'prune_interval_seconds' => env('NAJM_HODA_RUNTIME_EVENT_PRUNE_INTERVAL_SECONDS', 300),
        ],
        'domain_policy_link' => [
            'enabled' => env('NAJM_HODA_DOMAIN_POLICY_LINK_ENABLED', true),
            'request_approval_on_failures' => env('NAJM_HODA_DOMAIN_POLICY_LINK_REQUEST_APPROVAL_ON_FAILURES', true),
            'approval_risk_levels' => ['medium', 'high'],
        ],
        'coverage_kpi' => [
            'window_hours' => env('NAJM_HODA_COVERAGE_KPI_WINDOW_HOURS', 24),
            'event_limit' => env('NAJM_HODA_COVERAGE_KPI_EVENT_LIMIT', 5000),
            'snapshot_ttl_minutes' => env('NAJM_HODA_COVERAGE_KPI_SNAPSHOT_TTL_MINUTES', 180),
            'history_size' => env('NAJM_HODA_COVERAGE_KPI_HISTORY_SIZE', 200),
            'probe' => [
                'enabled' => env('NAJM_HODA_COVERAGE_KPI_PROBE_ENABLED', true),
            ],
            'heartbeat' => [
                'enabled' => env('NAJM_HODA_COVERAGE_KPI_HEARTBEAT_ENABLED', true),
            ],
            'sustainment' => [
                'required_consecutive_ok' => env('NAJM_HODA_COVERAGE_KPI_SUSTAIN_REQUIRED', 3),
                'require_without_probe' => env('NAJM_HODA_COVERAGE_KPI_SUSTAIN_WITHOUT_PROBE', true),
            ],
            'critical_families' => [
                'najm_hoda.input.support.service.',
                'najm_hoda.input.auth.service.',
                'najm_hoda.input.content.service.',
                'najm_hoda.input.najm_bahar.service.',
                'najm_hoda.input.group_',
            ],
            'mandatory_fields' => [
                'request_id',
                'correlation_id',
                'actor_id',
                'scope',
                'risk',
                'event_version',
                'emitted_at',
            ],
            'unknown_scopes' => ['unknown', 'global'],
            'unknown_risks' => ['unknown'],
            'thresholds' => [
                'critical_path_coverage_min' => 0.95,
                'mandatory_field_completeness_min' => 0.99,
                'unknown_scope_ratio_max' => 0.02,
                'unknown_risk_ratio_max' => 0.05,
            ],
        ],
        'multi_horizon_goals' => [
            'window_hours' => env('NAJM_HODA_MULTI_GOALS_WINDOW_HOURS', 24),
            'event_limit' => env('NAJM_HODA_MULTI_GOALS_EVENT_LIMIT', 2000),
            'snapshot_ttl_minutes' => env('NAJM_HODA_MULTI_GOALS_SNAPSHOT_TTL_MINUTES', 180),
            'thresholds' => [
                'critical_path_coverage_min' => env('NAJM_HODA_MULTI_GOALS_CRITICAL_COVERAGE_MIN', 0.95),
                'unknown_risk_ratio_max' => env('NAJM_HODA_MULTI_GOALS_UNKNOWN_RISK_MAX', 0.02),
            ],
            'review' => [
                'max_backlog_growth' => env('NAJM_HODA_MULTI_GOALS_REVIEW_MAX_BACKLOG_GROWTH', 1),
                'max_high_priority_growth' => env('NAJM_HODA_MULTI_GOALS_REVIEW_MAX_HIGH_GROWTH', 0),
            ],
        ],
        'safety' => [
            'rate_limit' => [
                'max_actions_per_minute' => env('NAJM_HODA_RUNTIME_RATE_MAX_PER_MINUTE', 60),
                'max_actions_per_action_per_minute' => env('NAJM_HODA_RUNTIME_RATE_MAX_PER_ACTION_PER_MINUTE', 20),
            ],
            'circuit_breaker' => [
                'failure_threshold' => env('NAJM_HODA_RUNTIME_CB_FAILURE_THRESHOLD', 5),
                'failure_window_seconds' => env('NAJM_HODA_RUNTIME_CB_FAILURE_WINDOW', 600),
                'cooldown_seconds' => env('NAJM_HODA_RUNTIME_CB_COOLDOWN_SECONDS', 300),
            ],
        ],
        'ops' => [
            'monitor' => [
                'window_minutes' => env('NAJM_HODA_OPS_WINDOW_MINUTES', 15),
                'recent_limit' => env('NAJM_HODA_OPS_RECENT_LIMIT', 400),
                'summary_ttl_minutes' => env('NAJM_HODA_OPS_SUMMARY_TTL_MINUTES', 180),
                'summary_history_size' => env('NAJM_HODA_OPS_SUMMARY_HISTORY_SIZE', 50),
            ],
            'retention' => [
                'telemetry_index_retention_hours' => env('NAJM_HODA_OPS_TELEMETRY_INDEX_RETENTION_HOURS', 72),
                'telemetry_index_max_size' => env('NAJM_HODA_OPS_TELEMETRY_INDEX_MAX_SIZE', 5000),
            ],
            'thresholds' => [
                'warning_error_rate_percent' => env('NAJM_HODA_OPS_WARNING_ERROR_RATE_PERCENT', 15),
                'critical_error_rate_percent' => env('NAJM_HODA_OPS_CRITICAL_ERROR_RATE_PERCENT', 35),
                'warning_unresolved_requests' => env('NAJM_HODA_OPS_WARNING_UNRESOLVED_REQUESTS', 4),
                'critical_unresolved_requests' => env('NAJM_HODA_OPS_CRITICAL_UNRESOLVED_REQUESTS', 10),
            ],
            'triage' => [
                'auto_playbook_enabled' => env('NAJM_HODA_OPS_AUTO_PLAYBOOK_ENABLED', true),
                'degraded_ttl_seconds' => env('NAJM_HODA_OPS_DEGRADED_TTL_SECONDS', 900),
                'entry_rate_multiplier_base' => env('NAJM_HODA_OPS_ENTRY_RATE_MULTIPLIER_BASE', 1.0),
                'entry_rate_multiplier_warning' => env('NAJM_HODA_OPS_ENTRY_RATE_MULTIPLIER_WARNING', 0.8),
                'entry_rate_multiplier_critical' => env('NAJM_HODA_OPS_ENTRY_RATE_MULTIPLIER_CRITICAL', 0.5),
            ],
            'playbooks' => [
                'enforce_low_risk_only' => env('NAJM_HODA_OPS_PLAYBOOK_ENFORCE_LOW_RISK_ONLY', true),
                'max_actions_per_run' => env('NAJM_HODA_OPS_PLAYBOOK_MAX_ACTIONS_PER_RUN', 5),
                'default_action_cooldown_seconds' => env('NAJM_HODA_OPS_PLAYBOOK_DEFAULT_ACTION_COOLDOWN_SECONDS', 0),
                'action_cooldowns' => [
                    'set_degraded_mode' => env('NAJM_HODA_OPS_PLAYBOOK_COOLDOWN_SET_DEGRADED_MODE', 60),
                    'clear_degraded_mode' => env('NAJM_HODA_OPS_PLAYBOOK_COOLDOWN_CLEAR_DEGRADED_MODE', 60),
                    'set_entry_rate_multiplier_base' => env('NAJM_HODA_OPS_PLAYBOOK_COOLDOWN_SET_ENTRY_RATE_MULTIPLIER_BASE', 60),
                    'set_entry_rate_multiplier_warning' => env('NAJM_HODA_OPS_PLAYBOOK_COOLDOWN_SET_ENTRY_RATE_MULTIPLIER_WARNING', 60),
                    'set_entry_rate_multiplier_critical' => env('NAJM_HODA_OPS_PLAYBOOK_COOLDOWN_SET_ENTRY_RATE_MULTIPLIER_CRITICAL', 60),
                ],
                'plan' => [
                    'healthy' => ['clear_degraded_mode', 'set_entry_rate_multiplier_base'],
                    'warning' => ['set_degraded_mode', 'set_entry_rate_multiplier_warning'],
                    'critical' => ['set_degraded_mode', 'set_entry_rate_multiplier_critical'],
                ],
                'catalog' => [
                    'clear_degraded_mode' => [
                        'enabled' => true,
                        'risk' => 'low',
                    ],
                    'set_degraded_mode' => [
                        'enabled' => true,
                        'risk' => 'low',
                    ],
                    'set_entry_rate_multiplier_base' => [
                        'enabled' => true,
                        'risk' => 'low',
                    ],
                    'set_entry_rate_multiplier_warning' => [
                        'enabled' => true,
                        'risk' => 'low',
                    ],
                    'set_entry_rate_multiplier_critical' => [
                        'enabled' => true,
                        'risk' => 'low',
                    ],
                ],
            ],
            'escalation' => [
                'enabled' => env('NAJM_HODA_OPS_ESCALATION_ENABLED', true),
                'notify_admins' => env('NAJM_HODA_OPS_ESCALATION_NOTIFY_ADMINS', true),
                'cooldown_seconds' => env('NAJM_HODA_OPS_ESCALATION_COOLDOWN_SECONDS', 900),
                'max_incidents_per_run' => env('NAJM_HODA_OPS_ESCALATION_MAX_PER_RUN', 3),
            ],
        ],
        'autonomy' => [
            'enabled' => env('NAJM_HODA_AUTONOMY_ENABLED', true),
            'context_limit' => env('NAJM_HODA_AUTONOMY_CONTEXT_LIMIT', 200),
            'plan_ttl_minutes' => env('NAJM_HODA_AUTONOMY_PLAN_TTL_MINUTES', 180),
            'max_goals_per_run' => env('NAJM_HODA_AUTONOMY_MAX_GOALS_PER_RUN', 5),
            'allow_apply_low_risk' => env('NAJM_HODA_AUTONOMY_ALLOW_APPLY_LOW_RISK', false),
            'default_goals' => [
                'stabilize_operations',
                'improve_user_experience',
            ],
            'thresholds' => [
                'warning_error_rate_percent' => env('NAJM_HODA_AUTONOMY_WARNING_ERROR_RATE_PERCENT', 15),
                'warning_unresolved_requests' => env('NAJM_HODA_AUTONOMY_WARNING_UNRESOLVED_REQUESTS', 4),
            ],
            'capabilities' => [
                'run_ops_monitor' => [
                    'name' => 'Operations Monitor Trigger',
                    'enabled' => true,
                    'version' => 1,
                    'risk' => 'low',
                    'mode' => 'propose',
                    'required_input' => ['health_status'],
                    'optional_input' => ['error_rate_percent', 'unresolved_requests', 'goal_count'],
                    'output' => ['plan_ref', 'trace_id'],
                ],
                'propose_engagement_recommendations' => [
                    'name' => 'Engagement Recommendation Proposal',
                    'enabled' => true,
                    'version' => 1,
                    'risk' => 'low',
                    'mode' => 'propose',
                    'required_input' => ['goal_count'],
                    'optional_input' => ['health_status', 'error_rate_percent', 'unresolved_requests', 'recommendation_count', 'top_recommendation_key', 'top_recommendation_confidence'],
                    'output' => ['recommendations', 'confidence', 'rationale'],
                ],
                'set_ticket_needs_review' => [
                    'name' => 'Set Ticket Needs Review',
                    'enabled' => true,
                    'version' => 1,
                    'risk' => 'low',
                    'mode' => 'apply',
                    'required_input' => ['ticket_id'],
                    'optional_input' => ['target_status'],
                    'output' => ['ticket_id', 'previous_status', 'target_status'],
                ],
                'rollback_ops_monitor' => [
                    'name' => 'Operations Monitor Rollback',
                    'enabled' => true,
                    'version' => 1,
                    'risk' => 'low',
                    'mode' => 'apply',
                    'required_input' => ['origin_action', 'origin_run_id'],
                    'optional_input' => ['origin_input'],
                    'output' => ['rollback_trace'],
                ],
                'rollback_engagement_recommendations' => [
                    'name' => 'Engagement Recommendation Rollback',
                    'enabled' => true,
                    'version' => 1,
                    'risk' => 'low',
                    'mode' => 'apply',
                    'required_input' => ['origin_action', 'origin_run_id'],
                    'optional_input' => ['origin_input'],
                    'output' => ['rollback_trace'],
                ],
            ],
            'safety' => [
                'enabled' => env('NAJM_HODA_AUTONOMY_SAFETY_ENABLED', true),
                'max_actions_per_run' => env('NAJM_HODA_AUTONOMY_SAFETY_MAX_ACTIONS_PER_RUN', 3),
                'allowed_risk_levels' => ['low'],
                'blocked_actions' => [],
                'allowed_actions' => [
                    'run_ops_monitor',
                    'propose_engagement_recommendations',
                    'set_ticket_needs_review',
                    'rollback_ops_monitor',
                    'rollback_engagement_recommendations',
                ],
                'action_goal_scope' => [
                    'run_ops_monitor' => ['stabilize_operations'],
                    'propose_engagement_recommendations' => ['improve_user_experience'],
                    'set_ticket_needs_review' => ['stabilize_operations'],
                    'rollback_ops_monitor' => ['stabilize_operations'],
                    'rollback_engagement_recommendations' => ['stabilize_operations', 'improve_user_experience'],
                ],
            ],
            'human_escalation' => [
                'enabled' => env('NAJM_HODA_AUTONOMY_HUMAN_ESCALATION_ENABLED', true),
                'notify_admins' => env('NAJM_HODA_AUTONOMY_HUMAN_ESCALATION_NOTIFY_ADMINS', true),
                'require_approval_risk_levels' => ['medium', 'high'],
                'require_approval_for_apply_mode' => env('NAJM_HODA_AUTONOMY_HUMAN_ESCALATION_APPLY_MODE', true),
                'fallback_to_propose' => env('NAJM_HODA_AUTONOMY_HUMAN_ESCALATION_FALLBACK_PROPOSE', true),
                'sla_minutes' => env('NAJM_HODA_AUTONOMY_HUMAN_ESCALATION_SLA_MINUTES', 30),
                'retention_minutes' => env('NAJM_HODA_AUTONOMY_HUMAN_ESCALATION_RETENTION_MINUTES', 10080),
                'max_requests_history' => env('NAJM_HODA_AUTONOMY_HUMAN_ESCALATION_MAX_HISTORY', 500),
            ],
            'observability' => [
                'event_limit' => env('NAJM_HODA_AUTONOMY_OBSERVABILITY_EVENT_LIMIT', 300),
                'window_hours' => env('NAJM_HODA_AUTONOMY_OBSERVABILITY_WINDOW_HOURS', 24),
                'snapshot_ttl_minutes' => env('NAJM_HODA_AUTONOMY_OBSERVABILITY_SNAPSHOT_TTL_MINUTES', 180),
            ],
            'recommendations' => [
                'enabled' => env('NAJM_HODA_AUTONOMY_RECOMMENDATIONS_ENABLED', true),
                'max_items' => env('NAJM_HODA_AUTONOMY_RECOMMENDATIONS_MAX_ITEMS', 5),
                'min_confidence' => env('NAJM_HODA_AUTONOMY_RECOMMENDATIONS_MIN_CONFIDENCE', 0.4),
            ],
            'executor' => [
                'enabled' => env('NAJM_HODA_AUTONOMY_EXECUTOR_ENABLED', true),
                'max_retries' => env('NAJM_HODA_AUTONOMY_EXECUTOR_MAX_RETRIES', 1),
                'idempotency_ttl_minutes' => env('NAJM_HODA_AUTONOMY_EXECUTOR_IDEMPOTENCY_TTL_MINUTES', 60),
                'default_action_cooldown_seconds' => env('NAJM_HODA_AUTONOMY_EXECUTOR_DEFAULT_COOLDOWN_SECONDS', 60),
                'action_cooldowns' => [
                    'run_ops_monitor' => env('NAJM_HODA_AUTONOMY_EXECUTOR_COOLDOWN_RUN_OPS_MONITOR', 60),
                    'propose_engagement_recommendations' => env('NAJM_HODA_AUTONOMY_EXECUTOR_COOLDOWN_RECOMMENDATIONS', 120),
                    'prioritize_overdue_action_items' => env('NAJM_HODA_AUTONOMY_EXECUTOR_COOLDOWN_PRIORITIZE_ITEMS', 120),
                    'set_ticket_needs_review' => env('NAJM_HODA_AUTONOMY_EXECUTOR_COOLDOWN_SET_TICKET_REVIEW', 60),
                    'rollback_ops_monitor' => env('NAJM_HODA_AUTONOMY_EXECUTOR_COOLDOWN_ROLLBACK_OPS_MONITOR', 30),
                    'rollback_engagement_recommendations' => env('NAJM_HODA_AUTONOMY_EXECUTOR_COOLDOWN_ROLLBACK_RECOMMENDATIONS', 30),
                ],
            ],
            'orchestrator' => [
                'enabled' => env('NAJM_HODA_AUTONOMY_ORCHESTRATOR_ENABLED', true),
                'max_steps_per_chain' => env('NAJM_HODA_AUTONOMY_ORCHESTRATOR_MAX_STEPS', 3),
                'compensation' => [
                    'fallback_to_capability_rollback' => env('NAJM_HODA_AUTONOMY_ORCHESTRATOR_COMP_FALLBACK', true),
                ],
                'rollback_map' => [
                    'run_ops_monitor' => 'rollback_ops_monitor',
                    'propose_engagement_recommendations' => 'rollback_engagement_recommendations',
                    'set_ticket_needs_review' => 'rollback_engagement_recommendations',
                ],
            ],
            'permissioning_v2' => [
                'enabled' => env('NAJM_HODA_PERMISSIONING_V2_ENABLED', true),
                'enforce_apply_requires_delegation' => env('NAJM_HODA_PERMISSIONING_V2_ENFORCE_APPLY', false),
                'default_expiry_minutes' => env('NAJM_HODA_PERMISSIONING_V2_DEFAULT_EXPIRY_MINUTES', 1440),
                'retention_minutes' => env('NAJM_HODA_PERMISSIONING_V2_RETENTION_MINUTES', 10080),
                'max_delegation_history' => env('NAJM_HODA_PERMISSIONING_V2_MAX_HISTORY', 2000),
            ],
            'policy_learning' => [
                'retention_minutes' => env('NAJM_HODA_POLICY_LEARNING_RETENTION_MINUTES', 10080),
                'review_ttl_minutes' => env('NAJM_HODA_POLICY_LEARNING_REVIEW_TTL_MINUTES', 720),
                'override_ttl_minutes' => env('NAJM_HODA_POLICY_LEARNING_OVERRIDE_TTL_MINUTES', 180),
                'max_history' => env('NAJM_HODA_POLICY_LEARNING_MAX_HISTORY', 300),
                'max_recommendations_history' => env('NAJM_HODA_POLICY_LEARNING_MAX_RECOMMENDATIONS_HISTORY', 500),
            ],
            'codeops' => [
                'window_hours' => env('NAJM_HODA_CODEOPS_CANARY_WINDOW_HOURS', 24),
                'canary_phases' => [5, 25, 50, 100],
                'max_warnings_for_progress' => env('NAJM_HODA_CODEOPS_CANARY_MAX_WARNINGS', 1),
                'rollback_pause_minutes' => env('NAJM_HODA_CODEOPS_CANARY_ROLLBACK_PAUSE_MINUTES', 30),
                'retention_minutes' => env('NAJM_HODA_CODEOPS_CANARY_RETENTION_MINUTES', 20160),
                'history_size' => env('NAJM_HODA_CODEOPS_CANARY_HISTORY_SIZE', 500),
            ],
            'evaluation' => [
                'window_hours' => env('NAJM_HODA_EVAL_WINDOW_HOURS', 24),
                'audit_limit' => env('NAJM_HODA_EVAL_AUDIT_LIMIT', 200),
                'retention_minutes' => env('NAJM_HODA_EVAL_RETENTION_MINUTES', 20160),
                'history_size' => env('NAJM_HODA_EVAL_HISTORY_SIZE', 180),
                'alerts_history_size' => env('NAJM_HODA_EVAL_ALERTS_HISTORY_SIZE', 500),
                'notify_admins' => env('NAJM_HODA_EVAL_NOTIFY_ADMINS', true),
                'thresholds' => [
                    'decision_quality_min' => env('NAJM_HODA_EVAL_DECISION_QUALITY_MIN', 0.75),
                    'decision_quality_warning_below' => env('NAJM_HODA_EVAL_DECISION_QUALITY_WARN_BELOW', 0.65),
                    'safety_failure_rate_max' => env('NAJM_HODA_EVAL_SAFETY_FAILURE_MAX', 0.20),
                    'safety_failure_rate_delta_max' => env('NAJM_HODA_EVAL_SAFETY_FAILURE_DELTA_MAX', 0.10),
                    'drift_delta_warning_above' => env('NAJM_HODA_EVAL_DRIFT_DELTA_WARN_ABOVE', 0.01),
                ],
            ],
            'audit' => [
                'history_size' => env('NAJM_HODA_AUTONOMY_AUDIT_HISTORY_SIZE', 500),
                'retention_minutes' => env('NAJM_HODA_AUTONOMY_AUDIT_RETENTION_MINUTES', 10080),
                'integrity' => [
                    'enabled' => env('NAJM_HODA_AUTONOMY_AUDIT_INTEGRITY_ENABLED', true),
                    'secret' => env('NAJM_HODA_AUTONOMY_AUDIT_INTEGRITY_SECRET'),
                ],
            ],
            'kill_switch' => [
                'enabled' => env('NAJM_HODA_AUTONOMY_KILL_SWITCH_ENABLED', true),
                'max_minutes' => env('NAJM_HODA_AUTONOMY_KILL_SWITCH_MAX_MINUTES', 10080),
            ],
            'costs' => [
                'daily_budget' => env('NAJM_HODA_AUTONOMY_COST_DAILY_BUDGET', 5.0),
                'monthly_budget' => env('NAJM_HODA_AUTONOMY_COST_MONTHLY_BUDGET', 100.0),
                'default_action_cost' => env('NAJM_HODA_AUTONOMY_COST_DEFAULT_ACTION', 0.001),
                'max_daily_ledger_entries' => env('NAJM_HODA_AUTONOMY_COST_MAX_DAILY_LEDGER_ENTRIES', 2000),
                'action_estimates' => [
                    'run_ops_monitor' => env('NAJM_HODA_AUTONOMY_COST_RUN_OPS_MONITOR', 0.002),
                    'propose_engagement_recommendations' => env('NAJM_HODA_AUTONOMY_COST_PROPOSE_RECOMMENDATIONS', 0.003),
                    'prioritize_overdue_action_items' => env('NAJM_HODA_AUTONOMY_COST_PRIORITIZE_ITEMS', 0.0015),
                    'set_ticket_needs_review' => env('NAJM_HODA_AUTONOMY_COST_SET_TICKET_REVIEW', 0.0015),
                    'rollback_ops_monitor' => env('NAJM_HODA_AUTONOMY_COST_ROLLBACK_OPS_MONITOR', 0.001),
                    'rollback_engagement_recommendations' => env('NAJM_HODA_AUTONOMY_COST_ROLLBACK_RECOMMENDATIONS', 0.001),
                ],
            ],
            'runbooks' => [
                'min_required_checklist_items' => env('NAJM_HODA_RUNBOOK_MIN_CHECKLIST_ITEMS', 4),
                'registry' => [
                    [
                        'id' => 'incident_response',
                        'title' => 'Incident Response',
                        'owner' => 'SRE',
                        'version' => '1.0.0',
                        'status' => 'active',
                        'updated_at' => '2026-02-21',
                        'checklist' => [
                            'Confirm incident severity and blast radius.',
                            'Pause high-risk autonomy actions if needed.',
                            'Create and assign escalation ticket.',
                            'Broadcast status update to operations channel.',
                        ],
                    ],
                    [
                        'id' => 'degraded_mode',
                        'title' => 'Degraded Mode Operations',
                        'owner' => 'Platform',
                        'version' => '1.0.0',
                        'status' => 'active',
                        'updated_at' => '2026-02-21',
                        'checklist' => [
                            'Switch autonomy to propose-only mode.',
                            'Disable non-critical background jobs.',
                            'Increase health-check cadence.',
                            'Track MTTR and customer impact.',
                        ],
                    ],
                    [
                        'id' => 'override_control',
                        'title' => 'Override Control',
                        'owner' => 'Operations',
                        'version' => '1.0.0',
                        'status' => 'active',
                        'updated_at' => '2026-02-21',
                        'checklist' => [
                            'Record override reason and expected duration.',
                            'Limit blocked actions to scoped set.',
                            'Assign approver for override exit.',
                            'Review audit trace before clearance.',
                        ],
                    ],
                    [
                        'id' => 'recovery_validation',
                        'title' => 'Recovery Validation',
                        'owner' => 'Engineering',
                        'version' => '1.0.0',
                        'status' => 'active',
                        'updated_at' => '2026-02-21',
                        'checklist' => [
                            'Run post-incident health monitor.',
                            'Replay critical autonomy traces.',
                            'Confirm no open policy drift breach.',
                            'Publish recovery summary and next actions.',
                        ],
                    ],
                ],
            ],
            'gameday' => [
                'report_ttl_minutes' => env('NAJM_HODA_GAMEDAY_REPORT_TTL_MINUTES', 10080),
                'history_size' => env('NAJM_HODA_GAMEDAY_HISTORY_SIZE', 30),
            ],
            'compliance' => [
                'window_hours' => env('NAJM_HODA_COMPLIANCE_WINDOW_HOURS', 24),
                'audit_limit' => env('NAJM_HODA_COMPLIANCE_AUDIT_LIMIT', 200),
                'approval_limit' => env('NAJM_HODA_COMPLIANCE_APPROVAL_LIMIT', 200),
                'alerts_limit' => env('NAJM_HODA_COMPLIANCE_ALERTS_LIMIT', 200),
                'gameday_limit' => env('NAJM_HODA_COMPLIANCE_GAMEDAY_LIMIT', 50),
                'events_limit' => env('NAJM_HODA_COMPLIANCE_EVENTS_LIMIT', 500),
            ],
            'readiness' => [
                'window_hours' => env('NAJM_HODA_READINESS_WINDOW_HOURS', 24),
                'governance' => [
                    'max_breach_kpis' => env('NAJM_HODA_READINESS_MAX_BREACH_KPIS', 0),
                    'max_warning_kpis' => env('NAJM_HODA_READINESS_MAX_WARNING_KPIS', 2),
                ],
                'approvals' => [
                    'max_pending' => env('NAJM_HODA_READINESS_APPROVALS_MAX_PENDING', 25),
                    'max_overdue' => env('NAJM_HODA_READINESS_APPROVALS_MAX_OVERDUE', 0),
                ],
                'gameday' => [
                    'history_limit' => env('NAJM_HODA_READINESS_GAMEDAY_HISTORY_LIMIT', 10),
                    'min_cycles' => env('NAJM_HODA_READINESS_GAMEDAY_MIN_CYCLES', 2),
                    'min_pass_rate' => env('NAJM_HODA_READINESS_GAMEDAY_MIN_PASS_RATE', 1.0),
                ],
                'evidence' => [
                    'min_audit_traces' => env('NAJM_HODA_READINESS_EVIDENCE_MIN_AUDIT_TRACES', 1),
                    'min_runtime_events' => env('NAJM_HODA_READINESS_EVIDENCE_MIN_RUNTIME_EVENTS', 1),
                ],
                'rollback' => [
                    'required_runbooks' => [
                        'incident_response',
                        'degraded_mode',
                        'override_control',
                        'recovery_validation',
                    ],
                ],
            ],
            'governance' => [
                'window_hours' => env('NAJM_HODA_GOVERNANCE_WINDOW_HOURS', 24),
                'event_limit' => env('NAJM_HODA_GOVERNANCE_EVENT_LIMIT', 3000),
                'snapshot_ttl_minutes' => env('NAJM_HODA_GOVERNANCE_SNAPSHOT_TTL_MINUTES', 180),
                'alerting' => [
                    'enabled' => env('NAJM_HODA_GOVERNANCE_ALERTING_ENABLED', true),
                    'notify_admins' => env('NAJM_HODA_GOVERNANCE_ALERTING_NOTIFY_ADMINS', true),
                    'cooldown_minutes' => env('NAJM_HODA_GOVERNANCE_ALERTING_COOLDOWN_MINUTES', 30),
                    'max_alerts_per_run' => env('NAJM_HODA_GOVERNANCE_ALERTING_MAX_ALERTS_PER_RUN', 20),
                    'max_history' => env('NAJM_HODA_GOVERNANCE_ALERTING_MAX_HISTORY', 500),
                    'approval_sla_overdue_threshold' => env('NAJM_HODA_GOVERNANCE_ALERTING_APPROVAL_SLA_OVERDUE_THRESHOLD', 1),
                ],
                'drift' => [
                    'window_hours' => env('NAJM_HODA_GOVERNANCE_DRIFT_WINDOW_HOURS', 24),
                    'event_limit' => env('NAJM_HODA_GOVERNANCE_DRIFT_EVENT_LIMIT', 3000),
                ],
                'kpis' => [
                    'auto_action_success_rate' => [
                        'target_min' => 0.95,
                        'warning_below' => 0.90,
                    ],
                    'autonomy_coverage_rate' => [
                        'target_min' => 0.60,
                        'warning_below' => 0.50,
                    ],
                    'mttr_reduction_rate' => [
                        'target_min' => 0.30,
                        'warning_below' => 0.20,
                    ],
                    'rollback_unwanted_rate' => [
                        'target_max' => 0.02,
                        'warning_above' => 0.03,
                    ],
                    'user_satisfaction_score' => [
                        'target_min' => 0.80,
                        'warning_below' => 0.75,
                    ],
                    'human_approval_latency_minutes' => [
                        'target_max' => 30,
                        'warning_above' => 45,
                    ],
                    'policy_drift_rate' => [
                        'target_max' => 0.01,
                        'warning_above' => 0.02,
                    ],
                ],
            ],
        ],
    ],

    /**
     * Webhooks (برای اعلان‌ها)
     */
    'webhooks' => [
        'on_critical_health' => env('NAJM_HODA_WEBHOOK_CRITICAL'),
        'on_code_generation' => env('NAJM_HODA_WEBHOOK_CODE_GEN'),
        'on_error' => env('NAJM_HODA_WEBHOOK_ERROR'),
    ],

    /**
     * تنظیمات Widget چت
     */
    'widget' => [
        'enabled' => env('NAJM_HODA_WIDGET_ENABLED', true),
        'position' => 'bottom-left', // bottom-left, bottom-right
        'theme' => 'auto', // light, dark, auto
        'show_for_guests' => true,
        'show_for_users' => true,
        'show_for_admins' => true,
    ],

    /**
     * تنظیمات Dashboard
     */
    'dashboard' => [
        'enabled' => env('NAJM_HODA_DASHBOARD_ENABLED', true),
        'route_prefix' => 'najm-hoda',
        'middleware' => ['web', 'auth', 'admin'],
    ],

    /**
     * پاسخ‌های Mock (برای نمایش متن‌های ثابت)
     */
    'mock_responses' => [
        'engineer' => 'من مهندس نجم‌هدا هستم. در حالت آزمایشی قرار دارم.',
        'pilot' => 'من خلبان نجم‌هدا هستم. برای عملکرد کامل، API Key نیاز است.',
        'steward' => 'سلام! من مهماندار نجم‌هدا هستم. چطور می‌تونم کمکتون کنم؟',
        'guide' => 'من راهنمای نجم‌هدا هستم. برای ارائه نقشه راه دقیق، API Key مورد نیاز است.',
    ],

    /**
     * تنظیمات امنیتی
     */
    'security' => [
        'require_authentication' => true,
        'allowed_ips' => env('NAJM_HODA_ALLOWED_IPS', '*'), // '*' = همه، یا آرایه IP ها
        'encrypt_conversations' => false,
        'sanitize_inputs' => true,
    ],

    /**
     * تنظیمات Conversation
     */
    'conversation' => [
        'max_messages_per_conversation' => 100,
        'auto_archive_after_days' => 30,
        'delete_old_conversations' => false,
    ],

    /**
     * تنظیمات دستیار گروهی نجم‌هدا
     *
     * نقش هدف: منشی/دبیر/راهنما در گروه‌ها
     */
    'group_assistant' => [
        'enabled' => env('NAJM_HODA_GROUP_ASSISTANT_ENABLED', true),
        'bot_email' => env('NAJM_HODA_GROUP_BOT_EMAIL', 'najm-hoda-bot@local.invalid'),
        'bot_first_name' => env('NAJM_HODA_GROUP_BOT_FIRST_NAME', 'نجم'),
        'bot_last_name' => env('NAJM_HODA_GROUP_BOT_LAST_NAME', 'هدا'),

        // 3 = مدیر گروه (طبق منطق فعلی پروژه)
        'default_group_role' => env('NAJM_HODA_GROUP_DEFAULT_ROLE', 3),
        'assistant_role' => env('NAJM_HODA_GROUP_ASSISTANT_ROLE', 'secretary'),
        'default_agent' => env('NAJM_HODA_GROUP_DEFAULT_AGENT', 'steward'),

        // disabled | mention_only | mention_or_question | always
        'auto_reply_mode' => env('NAJM_HODA_GROUP_AUTO_REPLY_MODE', 'mention_or_question'),

        // local | hybrid | global
        'knowledge_scope' => env('NAJM_HODA_GROUP_KNOWLEDGE_SCOPE', 'hybrid'),
        'meeting_mode_enabled' => env('NAJM_HODA_GROUP_MEETING_MODE', true),
        'allow_proactive_guidance' => env('NAJM_HODA_GROUP_PROACTIVE_GUIDANCE', true),
        'allow_private_messages' => env('NAJM_HODA_GROUP_ALLOW_PRIVATE_MESSAGES', true),
        // direct | request
        'private_message_mode' => env('NAJM_HODA_GROUP_PRIVATE_MESSAGE_MODE', 'direct'),

        'max_replies_per_hour' => env('NAJM_HODA_GROUP_MAX_REPLIES_PER_HOUR', 12),
        'min_reply_interval_seconds' => env('NAJM_HODA_GROUP_MIN_REPLY_INTERVAL_SECONDS', 90),

        'trigger_keywords' => [
            'نجم هدا',
            'نجم‌هدا',
            'نجمهدا',
            '@najmhoda',
            'najm hoda',
            'najmhoda',
            'منشی',
            'دبیر',
            'صورتجلسه',
            'صورت جلسه',
        ],

        'action_items' => [
            'enabled' => env('NAJM_HODA_GROUP_ACTION_ITEMS_ENABLED', true),
            'max_items' => env('NAJM_HODA_GROUP_ACTION_ITEMS_MAX_ITEMS', 8),
        ],

        'action_executor' => [
            'enabled' => env('NAJM_HODA_GROUP_ACTION_EXECUTOR_ENABLED', true),
            'propose_before_execute' => env('NAJM_HODA_GROUP_ACTION_PROPOSE_BEFORE_EXECUTE', false),
            'dry_run' => env('NAJM_HODA_GROUP_ACTION_DRY_RUN', false),
            'allow_create_post' => env('NAJM_HODA_GROUP_ACTION_ALLOW_CREATE_POST', true),
            'allow_create_poll' => env('NAJM_HODA_GROUP_ACTION_ALLOW_CREATE_POLL', true),
            'allow_create_comment' => env('NAJM_HODA_GROUP_ACTION_ALLOW_CREATE_COMMENT', true),
            'allow_react_message' => env('NAJM_HODA_GROUP_ACTION_ALLOW_REACT_MESSAGE', true),
            'allow_react_post' => env('NAJM_HODA_GROUP_ACTION_ALLOW_REACT_POST', true),
            'allow_react_comment' => env('NAJM_HODA_GROUP_ACTION_ALLOW_REACT_COMMENT', true),
            'max_actions_per_hour' => env('NAJM_HODA_GROUP_ACTION_MAX_PER_HOUR', 6),
            // 2: inspector, 3: manager
            'permitted_roles' => [2, 3],
        ],
    ],

    /**
     * تنظیمات Auto-Fixer (کمک خلبان هوشمند)
     */
    'auto_fixer' => [
        // فعال/غیرفعال کل سیستم Auto-Fixer
        'enabled' => env('NAJM_HODA_AUTO_FIXER_ENABLED', false),

        // سطح اتوماسیون: off, safe, moderate, aggressive
        'level' => env('NAJM_HODA_AUTO_FIXER_LEVEL', 'safe'),

        // حداکثر تعداد رفع در هر اجرا
        'max_fixes_per_run' => env('NAJM_HODA_AUTO_FIXER_MAX_FIXES', 10),

        // آیا نیاز به تأیید دستی دارد؟
        'require_approval' => env('NAJM_HODA_AUTO_FIXER_REQUIRE_APPROVAL', true),

        // مدت نگهداری Backup (روز)
        'backup_retention_days' => env('NAJM_HODA_AUTO_FIXER_BACKUP_RETENTION', 30),

        // فایل‌های مستثنی از Auto-Fix
        'excluded_patterns' => [
            'vendor/*',
            'node_modules/*',
            'storage/*',
            'bootstrap/cache/*',
            '.env',
            '*.blade.php', // فعلاً Blade را نمی‌زنیم
        ],

        // اولویت‌بندی انواع مشکلات برای رفع خودکار
        'fix_priorities' => [
            'Long Line' => 1,          // بالاترین اولویت
            'Commented Code' => 1,
            'Debug Code' => 1,
            'Inefficient Count' => 2,
            'Query in Loop' => 2,
            'N+1 Query' => 3,
            'SQL Injection' => 4,      // کمترین اولویت - نیاز به بررسی دقیق
            'XSS' => 4,
        ],
    ],
];

