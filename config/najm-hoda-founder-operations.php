<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Founder Operations management domains
    |--------------------------------------------------------------------------
    |
    | This catalog is the expansion map for Najm Hoda as EarthCoop's unified
    | management layer. Every current or future domain enters Founder Ops through
    | the same contract: observe -> summarize -> triage -> propose -> act.
    |
    | integration_stage values:
    |   planned  : inventoried, not yet connected
    |   mapped   : data/actions identified, integration incomplete
    |   observed : runtime events or a read model exist
    |   managed  : observed + triage/proposals + safe actions are wired
    |
    */
    'domains' => [
        'users' => [
            'label' => 'Users & Membership',
            'priority' => 10,
            'integration_stage' => 'observed',
            'risk' => 'medium',
            'sources' => ['users', 'auth lifecycle'],
            'event_prefixes' => ['najm_hoda.input.founder.user.'],
            'capabilities' => ['observe', 'summarize', 'triage'],
        ],
        'support' => [
            'label' => 'Support & Tickets',
            'priority' => 10,
            'integration_stage' => 'observed',
            'risk' => 'medium',
            'sources' => ['tickets', 'ticket_comments', 'support chat'],
            'event_prefixes' => ['najm_hoda.input.support.'],
            'capabilities' => ['observe', 'summarize', 'triage'],
        ],
        'reference_data' => [
            'label' => 'Occupational & Experience Reference Data',
            'priority' => 9,
            'integration_stage' => 'observed',
            'risk' => 'medium',
            'sources' => ['occupational_fields', 'experience_fields'],
            'event_prefixes' => ['najm_hoda.input.founder.reference.'],
            'capabilities' => ['observe', 'summarize', 'triage'],
        ],
        'locations' => [
            'label' => 'Location Reference Data',
            'priority' => 9,
            'integration_stage' => 'observed',
            'risk' => 'medium',
            'sources' => ['rurals', 'regions', 'neighborhoods', 'streets', 'alleys'],
            'event_prefixes' => ['najm_hoda.input.founder.reference.'],
            'capabilities' => ['observe', 'summarize', 'triage'],
        ],
        'runtime_health' => [
            'label' => 'Najm Hoda Runtime Health',
            'priority' => 10,
            'integration_stage' => 'managed',
            'risk' => 'low',
            'sources' => ['runtime event bus', 'ops health monitor', 'ops triage'],
            'event_prefixes' => ['najm_hoda.ops.'],
            'capabilities' => ['observe', 'summarize', 'triage', 'propose', 'safe_action'],
        ],
        'groups' => [
            'label' => 'Groups & Community Operations',
            'priority' => 9,
            'integration_stage' => 'mapped',
            'risk' => 'medium',
            'sources' => ['groups', 'group_user', 'group feed', 'group chat'],
            'event_prefixes' => ['najm_hoda.input.group_'],
            'capabilities' => ['observe'],
        ],
        'governance' => [
            'label' => 'Governance & Elections',
            'priority' => 10,
            'integration_stage' => 'mapped',
            'risk' => 'high',
            'sources' => ['elections', 'polls', 'governance module'],
            'event_prefixes' => ['najm_hoda.input.election.', 'najm_hoda.input.poll.'],
            'capabilities' => ['observe'],
        ],
        'secretariat' => [
            'label' => 'Secretariat & Correspondence',
            'priority' => 10,
            'integration_stage' => 'mapped',
            'risk' => 'medium',
            'sources' => ['secretariat module', 'knowledge files'],
            'event_prefixes' => ['najm_hoda.input.secretariat.'],
            'capabilities' => ['observe', 'propose'],
        ],
        'najm_bahar' => [
            'label' => 'Najm Bahar Finance',
            'priority' => 10,
            'integration_stage' => 'mapped',
            'risk' => 'high',
            'sources' => ['NajmBahar models', 'transactions', 'projects', 'salary runs'],
            'event_prefixes' => ['najm_hoda.input.najm_bahar.'],
            'capabilities' => ['observe'],
        ],
        'email' => [
            'label' => 'Email & System Mail Configuration',
            'priority' => 9,
            'integration_stage' => 'observed',
            'risk' => 'high',
            'sources' => ['EmailTemplate', 'Admin\\EmailController', 'Admin\\SystemEmailController'],
            'event_prefixes' => ['najm_hoda.input.email.'],
            'capabilities' => ['observe'],
        ],
        'blog' => [
            'label' => 'Blog & Editorial Operations',
            'priority' => 8,
            'integration_stage' => 'observed',
            'risk' => 'medium',
            'sources' => ['app/Modules/Blog', 'blog posts', 'comments', 'categories', 'tags'],
            'event_prefixes' => ['najm_hoda.input.blog.'],
            'capabilities' => ['observe'],
        ],
        'stock' => [
            'label' => 'Stock, Auctions & Settlement',
            'priority' => 10,
            'integration_stage' => 'observed',
            'risk' => 'high',
            'sources' => ['app/Modules/Stock', 'auctions', 'settlement', 'share ownership'],
            'event_prefixes' => ['najm_hoda.input.stock.'],
            'capabilities' => ['observe'],
        ],
        'content' => [
            'label' => 'Pages, Knowledge Base & Published Content',
            'priority' => 7,
            'integration_stage' => 'mapped',
            'risk' => 'medium',
            'sources' => ['pages', 'kb_articles', 'faq_questions', 'content observers'],
            'event_prefixes' => ['najm_hoda.input.content.'],
            'capabilities' => ['observe'],
        ],
        'notifications' => [
            'label' => 'Notifications & Announcements',
            'priority' => 7,
            'integration_stage' => 'planned',
            'risk' => 'medium',
            'sources' => ['notifications', 'notification_settings', 'announcements'],
            'event_prefixes' => ['najm_hoda.input.notification.'],
            'capabilities' => [],
        ],
        'reports_moderation' => [
            'label' => 'Reports, Moderation & Reputation',
            'priority' => 8,
            'integration_stage' => 'planned',
            'risk' => 'high',
            'sources' => ['reports', 'reported messages', 'reputation'],
            'event_prefixes' => ['najm_hoda.input.moderation.'],
            'capabilities' => [],
        ],
        'invitations' => [
            'label' => 'Invitations & Growth Operations',
            'priority' => 6,
            'integration_stage' => 'planned',
            'risk' => 'medium',
            'sources' => ['invitation codes', 'invitation logs'],
            'event_prefixes' => ['najm_hoda.input.invitation.'],
            'capabilities' => [],
        ],
        'admin_settings' => [
            'label' => 'System & Admin Configuration',
            'priority' => 9,
            'integration_stage' => 'planned',
            'risk' => 'high',
            'sources' => ['system settings', 'group settings', 'realtime settings', 'roles', 'permissions'],
            'event_prefixes' => ['najm_hoda.input.admin_settings.'],
            'capabilities' => [],
        ],
    ],

    'stage_order' => ['planned', 'mapped', 'observed', 'managed'],
];
