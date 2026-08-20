<?php

return [
    /*
     | Executive connectivity evidence
     |
     | This file is intentionally explicit. A policy entry alone is not proof that
     | Najm Hoda can execute the action. Each adapter below names the canonical
     | service that completes the action contract.
     */
    'read_domains' => [
        'users','support','reference_data','locations','groups','governance',
        'reports_moderation','email','blog','content','notifications','invitations',
        'secretariat','stock','najm_bahar','admin_settings','runtime_health',
    ],

    'proposal_adapters' => [
        'support.draft_reply' => App\Services\Support\TicketReplyDraftService::class,
        'reports_moderation.prepare_case_summary' => App\Services\Moderation\ModerationCaseSummaryService::class,
        'secretariat.prepare_follow_up' => App\Modules\Secretariat\Services\SecretariatFollowUpProposalService::class,
        'email.draft_email' => App\Services\NajmHoda\FounderOps\FounderEmailDraftService::class,
        'blog.draft_post' => App\Services\NajmHoda\FounderOps\FounderContentDraftService::class,
    ],

    'approval_adapters' => [
        'support.send_reply' => App\Services\NajmHoda\FounderOps\FounderSupportDraftApprovalService::class,
        'reference_data.approve' => App\Services\NajmHoda\FounderOps\FounderReferenceApprovalDecisionService::class,
        'locations.approve' => App\Services\NajmHoda\FounderOps\FounderReferenceApprovalDecisionService::class,
        'reports_moderation.resolve_report' => App\Services\NajmHoda\FounderOps\FounderModerationDecisionService::class,
        'email.send_email' => App\Services\NajmHoda\FounderOps\FounderEmailDecisionService::class,
        'blog.publish_post' => App\Services\NajmHoda\FounderOps\FounderContentDecisionService::class,
    ],
];
