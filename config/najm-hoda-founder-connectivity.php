<?php

return [
    /*
     | Executive connectivity evidence
     |
     | A policy entry alone is not proof that Najm Hoda can execute the action.
     | Each adapter below names the canonical service that completes the contract.
     */
    'read_domains' => [
        'users','support','reference_data','locations','groups','governance',
        'reports_moderation','email','blog','content','notifications','invitations',
        'secretariat','stock','najm_bahar','admin_settings','runtime_health',
    ],

    'proposal_adapters' => [
        'users.draft_support_response' => App\Services\NajmHoda\FounderOps\FounderUserSupportResponseService::class,
        'support.draft_reply' => App\Services\Support\TicketReplyDraftService::class,
        'reference_data.recommend_approval' => App\Services\NajmHoda\FounderOps\FounderReferenceApprovalCandidateService::class,
        'locations.recommend_approval' => App\Services\NajmHoda\FounderOps\FounderReferenceApprovalCandidateService::class,
        'reports_moderation.prepare_case_summary' => App\Services\Moderation\ModerationCaseSummaryService::class,
        'email.draft_email' => App\Services\NajmHoda\FounderOps\FounderEmailDraftService::class,
        'blog.draft_post' => App\Services\NajmHoda\FounderOps\FounderContentDraftService::class,
        'notifications.draft_announcement' => App\Services\NajmHoda\FounderOps\FounderAnnouncementDraftService::class,
        'invitations.recommend_request_decision' => App\Services\Invitation\InvitationManagementService::class,
        'secretariat.prepare_follow_up' => App\Modules\Secretariat\Services\SecretariatFollowUpProposalService::class,
    ],

    'approval_adapters' => [
        'users.send_support_response' => App\Services\NajmHoda\FounderOps\FounderUserSupportResponseService::class,
        'users.suspend_user' => App\Services\NajmHoda\FounderOps\FounderUserSuspensionDecisionService::class,
        'support.send_reply' => App\Services\NajmHoda\FounderOps\FounderSupportDraftApprovalService::class,
        'support.close_ticket' => App\Services\NajmHoda\FounderOps\FounderSupportTicketDecisionService::class,
        'reference_data.approve' => App\Services\NajmHoda\FounderOps\FounderReferenceApprovalDecisionService::class,
        'locations.approve' => App\Services\NajmHoda\FounderOps\FounderReferenceApprovalDecisionService::class,
        'reports_moderation.resolve_report' => App\Services\NajmHoda\FounderOps\FounderModerationDecisionService::class,
        'email.send_email' => App\Services\NajmHoda\FounderOps\FounderEmailDecisionService::class,
        'blog.publish_post' => App\Services\NajmHoda\FounderOps\FounderContentDecisionService::class,
        'notifications.publish_announcement' => App\Services\NajmHoda\FounderOps\FounderAnnouncementDecisionService::class,
        'invitations.issue_invitation' => App\Services\NajmHoda\FounderOps\FounderInvitationDecisionService::class,
        'invitations.reject_invitation_request' => App\Services\NajmHoda\FounderOps\FounderInvitationDecisionService::class,
        'secretariat.register_formal_record' => App\Services\NajmHoda\FounderOps\FounderSecretariatDecisionService::class,
        'secretariat.close_case' => App\Services\NajmHoda\FounderOps\FounderSecretariatDecisionService::class,
    ],

    /*
     | Known architectural dependencies
     |
     | These actions are intentionally not counted as ordinary missing adapters.
     | They stay non-executable until the named canonical dependency exists.
     */
    'blocked_actions' => [
        'secretariat.dispatch_formal_record' => [
            'reason' => 'real_transport_not_available',
            'dependency' => 'Secretariat transport outbox + delivery callback/reconciliation',
        ],
        'governance.change_election_rules' => [
            'reason' => 'canonical_election_rules_service_pending',
            'dependency' => 'Permanent-election rule model/service replacing legacy GroupSetting mutation',
        ],
        'notifications.change_global_notification_defaults' => [
            'reason' => 'persisted_global_defaults_missing',
            'dependency' => 'Canonical persisted notification-default policy/state service',
        ],
    ],
];
