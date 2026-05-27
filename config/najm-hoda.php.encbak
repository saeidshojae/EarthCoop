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

