<?php

return [
    // Mapping of keywords to role slugs (used to find an operator to assign)
    'triage_rules' => [
        // technical issues
        'خطا' => 'support-tech',
        'مشکل' => 'support-tech',
        'bug' => 'support-tech',
        'خطا در' => 'support-tech',

        // billing / payments
        'پرداخت' => 'support-billing',
        'تراکنش' => 'support-billing',
        'فاکتور' => 'support-billing',

        // account / login
        'ورود' => 'support-account',
        'رمز' => 'support-account',
        'حساب' => 'support-account',

        // urgent
        'فوری' => 'support-priority',
    ],

    // Keywords that indicate high priority
    'priority_high_keywords' => [
        'فوری',
        'urgent',
        'critical',
        'حساس',
    ],

    // Fallback role slug to look for operators if specific role not found
    'fallback_operator_role' => 'support',
];
