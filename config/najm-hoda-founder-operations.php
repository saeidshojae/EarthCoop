<?php

return [
    /* Founder Operations management-domain catalog.
     * Pipeline: observe -> summarize -> triage -> propose -> act.
     * Stages: planned, mapped, observed, managed.
     */
    'domains' => [
        'users' => ['label'=>'Users & Membership','priority'=>10,'integration_stage'=>'observed','risk'=>'medium','sources'=>['users','auth lifecycle'],'event_prefixes'=>['najm_hoda.input.founder.user.'],'capabilities'=>['observe','summarize','triage']],
        'support' => ['label'=>'Support & Tickets','priority'=>10,'integration_stage'=>'observed','risk'=>'medium','sources'=>['tickets','ticket_comments','support chat'],'event_prefixes'=>['najm_hoda.input.support.'],'capabilities'=>['observe','summarize','triage']],
        'reference_data' => ['label'=>'Occupational & Experience Reference Data','priority'=>9,'integration_stage'=>'observed','risk'=>'medium','sources'=>['occupational_fields','experience_fields'],'event_prefixes'=>['najm_hoda.input.founder.reference.'],'capabilities'=>['observe','summarize','triage']],
        'locations' => ['label'=>'Location Reference Data','priority'=>9,'integration_stage'=>'observed','risk'=>'medium','sources'=>['rurals','regions','neighborhoods','streets','alleys'],'event_prefixes'=>['najm_hoda.input.founder.reference.'],'capabilities'=>['observe','summarize','triage']],
        'runtime_health' => ['label'=>'Najm Hoda Runtime Health','priority'=>10,'integration_stage'=>'managed','risk'=>'low','sources'=>['runtime event bus','ops health monitor','ops triage'],'event_prefixes'=>['najm_hoda.ops.'],'capabilities'=>['observe','summarize','triage','propose','safe_action']],
        'groups' => ['label'=>'Groups & Community Operations','priority'=>9,'integration_stage'=>'observed','risk'=>'medium','sources'=>['groups','group_user','group feed','group chat'],'event_prefixes'=>['najm_hoda.input.group_','najm_hoda.input.groups.'],'capabilities'=>['observe','summarize','triage']],
        'governance' => ['label'=>'Governance & Elections','priority'=>10,'integration_stage'=>'observed','risk'=>'high','sources'=>['elections','polls','governance module'],'event_prefixes'=>['najm_hoda.input.group_election_','najm_hoda.input.governance.'],'capabilities'=>['observe','summarize','triage']],
        'secretariat' => ['label'=>'Secretariat & Correspondence','priority'=>10,'integration_stage'=>'observed','risk'=>'medium','sources'=>['secretariat records','cases','dispatches','attachment scans'],'event_prefixes'=>['najm_hoda.input.secretariat.'],'capabilities'=>['observe','summarize','triage','propose']],
        'najm_bahar' => ['label'=>'Najm Bahar Finance','priority'=>10,'integration_stage'=>'observed','risk'=>'high','sources'=>['NajmBahar models','transactions','projects','reviews','scheduled transactions'],'event_prefixes'=>['najm_hoda.input.najm_bahar.'],'capabilities'=>['observe','summarize','triage']],
        'email' => ['label'=>'Email & System Mail Configuration','priority'=>9,'integration_stage'=>'observed','risk'=>'high','sources'=>['EmailTemplate','Admin\\EmailController','Admin\\SystemEmailController'],'event_prefixes'=>['najm_hoda.input.email.'],'capabilities'=>['observe']],
        'blog' => ['label'=>'Blog & Editorial Operations','priority'=>8,'integration_stage'=>'observed','risk'=>'medium','sources'=>['app/Modules/Blog','blog posts','comments','categories','tags'],'event_prefixes'=>['najm_hoda.input.blog.'],'capabilities'=>['observe','summarize','triage']],
        'stock' => ['label'=>'Stock, Auctions & Settlement','priority'=>10,'integration_stage'=>'observed','risk'=>'high','sources'=>['app/Modules/Stock','auctions','settlement','share ownership'],'event_prefixes'=>['najm_hoda.input.stock.'],'capabilities'=>['observe','summarize','triage']],
        'content' => ['label'=>'Pages, Knowledge Base & Published Content','priority'=>7,'integration_stage'=>'observed','risk'=>'medium','sources'=>['pages','kb_articles','faq_questions','content observers'],'event_prefixes'=>['najm_hoda.input.content.'],'capabilities'=>['observe','summarize','triage']],
        'notifications' => ['label'=>'Notifications & Announcements','priority'=>7,'integration_stage'=>'observed','risk'=>'medium','sources'=>['notifications','notification_settings','announcements'],'event_prefixes'=>['najm_hoda.input.notification.'],'capabilities'=>['observe','summarize','triage']],
        'reports_moderation' => ['label'=>'Reports, Moderation & Reputation','priority'=>8,'integration_stage'=>'observed','risk'=>'high','sources'=>['reports','reported messages','reputation'],'event_prefixes'=>['najm_hoda.input.moderation.'],'capabilities'=>['observe','summarize','triage']],
        'invitations' => ['label'=>'Invitations & Growth Operations','priority'=>6,'integration_stage'=>'observed','risk'=>'medium','sources'=>['invitations','invitation codes'],'event_prefixes'=>['najm_hoda.input.invitation.'],'capabilities'=>['observe','summarize','triage']],
        'admin_settings' => ['label'=>'System & Admin Configuration','priority'=>9,'integration_stage'=>'observed','risk'=>'high','sources'=>['system settings','group settings','realtime settings','roles','permissions'],'event_prefixes'=>['najm_hoda.input.admin_settings.'],'capabilities'=>['observe','summarize','triage']],
    ],
    'stage_order' => ['planned','mapped','observed','managed'],
];
