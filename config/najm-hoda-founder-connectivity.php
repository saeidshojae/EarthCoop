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
        'content.draft_faq_answer' => App\Services\Content\ContentManagementService::class,
        'content.draft_page_update' => App\Services\Content\ContentManagementService::class,
        'notifications.draft_announcement' => App\Services\NajmHoda\FounderOps\FounderAnnouncementDraftService::class,
        'invitations.recommend_request_decision' => App\Services\Invitation\InvitationManagementService::class,
        'secretariat.draft_correspondence' => App\Services\NajmHoda\FounderOps\FounderSecretariatCorrespondenceDraftService::class,
        'secretariat.prepare_follow_up' => App\Modules\Secretariat\Services\SecretariatFollowUpProposalService::class,
        'admin_settings.recommend_change' => App\Services\NajmHoda\FounderOps\FounderAdminSettingRecommendationService::class,
    ],

    'approval_adapters' => [
        'users.send_support_response' => App\Services\NajmHoda\FounderOps\FounderUserSupportResponseService::class,
        'users.suspend_user' => App\Services\NajmHoda\FounderOps\FounderUserSuspensionDecisionService::class,
        'support.send_reply' => App\Services\NajmHoda\FounderOps\FounderSupportDraftApprovalService::class,
        'support.close_ticket' => App\Services\NajmHoda\FounderOps\FounderSupportTicketDecisionService::class,
        'reference_data.approve' => App\Services\NajmHoda\FounderOps\FounderReferenceApprovalDecisionService::class,
        'locations.approve' => App\Services\NajmHoda\FounderOps\FounderReferenceApprovalDecisionService::class,
        'groups.change_member_role' => App\Services\NajmHoda\FounderOps\FounderGroupDecisionService::class,
        'reports_moderation.resolve_report' => App\Services\NajmHoda\FounderOps\FounderModerationDecisionService::class,
        'email.edit_template' => App\Services\NajmHoda\FounderOps\FounderEmailTemplateDecisionService::class,
        'email.send_email' => App\Services\NajmHoda\FounderOps\FounderEmailDecisionService::class,
        'email.bulk_send' => App\Services\NajmHoda\FounderOps\FounderEmailDecisionService::class,
        'blog.publish_post' => App\Services\NajmHoda\FounderOps\FounderContentDecisionService::class,
        'blog.delete_post' => App\Services\NajmHoda\FounderOps\FounderBlogLifecycleDecisionService::class,
        'content.publish_content' => App\Services\NajmHoda\FounderOps\FounderContentLifecycleDecisionService::class,
        'content.delete_content' => App\Services\NajmHoda\FounderOps\FounderContentLifecycleDecisionService::class,
        'notifications.publish_announcement' => App\Services\NajmHoda\FounderOps\FounderAnnouncementDecisionService::class,
        'invitations.issue_invitation' => App\Services\NajmHoda\FounderOps\FounderInvitationDecisionService::class,
        'invitations.reject_invitation_request' => App\Services\NajmHoda\FounderOps\FounderInvitationDecisionService::class,
        'secretariat.register_formal_record' => App\Services\NajmHoda\FounderOps\FounderSecretariatDecisionService::class,
        'secretariat.close_case' => App\Services\NajmHoda\FounderOps\FounderSecretariatDecisionService::class,
        'stock.settle_auction' => App\Services\NajmHoda\FounderOps\FounderStockDecisionService::class,
        'najm_bahar.approve_project' => App\Services\NajmHoda\FounderOps\FounderNajmBaharProjectDecisionService::class,
        'najm_bahar.execute_transaction' => App\Services\NajmHoda\FounderOps\FounderNajmBaharTransactionDecisionService::class,
        'najm_bahar.change_monetary_policy' => App\Services\NajmHoda\FounderOps\FounderNajmBaharMonetaryPolicyDecisionService::class,
        'admin_settings.change_setting' => App\Services\NajmHoda\FounderOps\FounderAdminSettingDecisionService::class,
    ],

    /*
     | Known architectural dependencies
     |
     | These actions are intentionally not counted as ordinary missing adapters.
     | They stay non-executable until the named canonical dependency exists.
     */
    'blocked_actions' => [
        'reference_data.reject' => [
            'reason' => 'persisted_rejection_state_missing',
            'dependency' => 'Canonical rejected state/lifecycle for occupational and experience candidates distinct from forbidden deletion',
        ],
        'locations.reject' => [
            'reason' => 'persisted_rejection_state_missing',
            'dependency' => 'Canonical rejected state/lifecycle for location candidates distinct from forbidden deletion',
        ],
        'groups.close_group' => [
            'reason' => 'canonical_group_lifecycle_missing',
            'dependency' => 'Audited group closure transition defining membership, election, chat and representation consequences',
        ],
        'runtime_health.restart_external_service' => [
            'reason' => 'external_control_plane_missing',
            'dependency' => 'Authenticated external-service control plane with bounded restart target allowlist and post-action health verification',
        ],
        'reports_moderation.sanction_user' => [
            'reason' => 'canonical_sanction_lifecycle_missing',
            'dependency' => 'Typed sanction policy and persisted sanction lifecycle distinct from generic account suspension',
        ],
        'admin_settings.change_role_permission' => [
            'reason' => 'canonical_role_permission_boundary_missing',
            'dependency' => 'Central role/permission command boundary with protected-role invariants and audit trail',
        ],
        'blog.unpublish_post' => [
            'reason' => 'publication_state_missing',
            'dependency' => 'Canonical persisted blog publication-state lifecycle distinct from hard deletion',
        ],
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
        'stock.create_auction' => [
            'reason' => 'typed_supply_intent_missing',
            'dependency' => 'Canonical auction-creation command with explicit treasury/holder supply ownership reservation and seller identity',
        ],
        'stock.transfer_shares' => [
            'reason' => 'canonical_transfer_boundary_missing',
            'dependency' => 'Canonical share-transfer/trade boundary that cannot bypass secondary-market Active Bahar settlement or ownership audit',
        ],
    ],
];
