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
        'type' => env('AI_PROVIDER', 'openai'), // openai, claude, gemini
        'api_key' => env('AI_API_KEY', env('OPENAI_API_KEY')),
        'organization' => env('AI_ORGANIZATION'),
        'model' => env('AI_MODEL', 'gpt-4-turbo-preview'),
    ],

    /**
     * تنظیمات عوامل (Agents)
     */
    'agents' => [
        'engineer' => [
            'enabled' => env('AGENT_ENGINEER_ENABLED', true),
            'temperature' => 0.2, // کمتر = دقیق‌تر، بیشتر = خلاق‌تر
            'max_tokens' => 4000,
        ],
        'pilot' => [
            'enabled' => env('AGENT_PILOT_ENABLED', true),
            'temperature' => 0.5,
            'max_tokens' => 3000,
        ],
        'steward' => [
            'enabled' => env('AGENT_STEWARD_ENABLED', true),
            'temperature' => 0.7,
            'max_tokens' => 2000,
        ],
        'guide' => [
            'enabled' => env('AGENT_GUIDE_ENABLED', true),
            'temperature' => 0.6,
            'max_tokens' => 3000,
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
        'max_requests_per_minute' => 20,
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
     * تنظیمات Mock Mode (زمانی که API Key نیست)
     */
    'mock_mode' => [
        'enabled' => env('NAJM_HODA_MOCK_MODE', !env('AI_API_KEY')),
        'responses' => [
            'engineer' => 'من مهندس نجم‌هدا هستم. در حالت آزمایشی قرار دارم.',
            'pilot' => 'من خلبان نجم‌هدا هستم. برای عملکرد کامل، API Key نیاز است.',
            'steward' => 'سلام! من مهماندار نجم‌هدا هستم. چطور می‌تونم کمکتون کنم؟',
            'guide' => 'من راهنمای نجم‌هدا هستم. برای ارائه نقشه راه دقیق، API Key مورد نیاز است.',
        ],
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

