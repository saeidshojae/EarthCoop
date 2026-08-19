<?php

return [
    /*
     * Founder Operations authority levels:
     * observe            = read/inspect only
     * propose            = prepare recommendation/draft only
     * approval_required  = executable only after explicit founder approval
     * delegated_safe     = may execute only when an explicit delegation grant exists
     * forbidden          = Founder Ops must never execute
     *
     * Unknown actions fail closed as forbidden.
     */
    'default_level' => 'forbidden',

    'domains' => [
        'users' => [
            'view' => 'observe',
            'summarize' => 'observe',
            'draft_response' => 'propose',
            'tag_for_followup' => 'delegated_safe',
            'suspend' => 'approval_required',
            'delete' => 'forbidden',
        ],
        'support' => [
            'view' => 'observe',
            'summarize' => 'observe',
            'classify' => 'delegated_safe',
            'draft_reply' => 'propose',
            'assign' => 'delegated_safe',
            'send_reply' => 'approval_required',
            'close_ticket' => 'approval_required',
        ],
        'reference_data' => [
            'view' => 'observe',
            'detect_duplicate' => 'propose',
            'approve' => 'approval_required',
            'reject' => 'approval_required',
            'delete' => 'forbidden',
        ],
        'locations' => [
            'view' => 'observe',
            'detect_duplicate' => 'propose',
            'approve' => 'approval_required',
            'reject' => 'approval_required',
            'delete' => 'forbidden',
        ],
        'groups' => [
            'view' => 'observe',
            'summarize' => 'observe',
            'draft_manager_message' => 'propose',
            'create_followup' => 'delegated_safe',
            'change_membership_role' => 'approval_required',
            'close_group' => 'approval_required',
            'delete_group' => 'forbidden',
        ],
        'governance' => [
            'view' => 'observe',
            'summarize' => 'observe',
            'flag_anomaly' => 'delegated_safe',
            'draft_notice' => 'propose',
            'change_election_config' => 'approval_required',
            'start_election' => 'approval_required',
            'close_election' => 'approval_required',
            'alter_vote_or_result' => 'forbidden',
        ],
        'reports_moderation' => [
            'view' => 'observe',
            'classify' => 'delegated_safe',
            'draft_resolution' => 'propose',
            'escalate' => 'delegated_safe',
            'resolve' => 'approval_required',
            'sanction_user' => 'approval_required',
            'erase_report_history' => 'forbidden',
        ],
        'notifications' => [
            'view' => 'observe',
            'draft_announcement' => 'propose',
            'schedule_internal_notice' => 'approval_required',
            'publish_announcement' => 'approval_required',
        ],
        'email' => [
            'view' => 'observe',
            'draft_template' => 'propose',
            'draft_message' => 'propose',
            'activate_template' => 'approval_required',
            'send_email' => 'approval_required',
            'bulk_send' => 'approval_required',
        ],
        'blog' => [
            'view' => 'observe',
            'draft_post' => 'propose',
            'seo_suggest' => 'propose',
            'publish' => 'approval_required',
            'unpublish' => 'approval_required',
            'delete' => 'approval_required',
        ],
        'content' => [
            'view' => 'observe',
            'draft_page' => 'propose',
            'draft_faq_answer' => 'propose',
            'publish_page' => 'approval_required',
            'publish_faq' => 'approval_required',
            'delete_formal_content' => 'forbidden',
        ],
        'invitations' => [
            'view' => 'observe',
            'summarize' => 'observe',
            'approve_request' => 'approval_required',
            'reject_request' => 'approval_required',
            'revoke_code' => 'approval_required',
        ],
        'admin_settings' => [
            'view' => 'observe',
            'diff' => 'observe',
            'recommend_change' => 'propose',
            'change_noncritical_setting' => 'approval_required',
            'change_security_or_financial_setting' => 'approval_required',
            'disable_audit_or_safety_controls' => 'forbidden',
        ],
        'secretariat' => [
            'view' => 'observe',
            'summarize' => 'observe',
            'draft_record' => 'propose',
            'draft_correspondence' => 'propose',
            'create_followup_reminder' => 'delegated_safe',
            'register_record' => 'approval_required',
            'dispatch' => 'approval_required',
            'close_case' => 'approval_required',
            'rewrite_history' => 'forbidden',
        ],
        'najm_bahar' => [
            'view' => 'observe',
            'summarize' => 'observe',
            'flag_anomaly' => 'delegated_safe',
            'draft_review' => 'propose',
            'approve_project' => 'approval_required',
            'reject_project' => 'approval_required',
            'execute_transaction' => 'approval_required',
            'change_monetary_policy' => 'approval_required',
            'rewrite_ledger' => 'forbidden',
            'mint_or_create_value_outside_policy' => 'forbidden',
        ],
        'stock' => [
            'view' => 'observe',
            'summarize' => 'observe',
            'flag_anomaly' => 'delegated_safe',
            'draft_auction_notice' => 'propose',
            'schedule_auction' => 'approval_required',
            'settle_auction' => 'approval_required',
            'transfer_shares' => 'approval_required',
            'change_ownership_history' => 'forbidden',
        ],
        'runtime_health' => [
            'view' => 'observe',
            'summarize' => 'observe',
            'collect_diagnostics' => 'delegated_safe',
            'retry_read_only_probe' => 'delegated_safe',
            'restart_service' => 'approval_required',
            'disable_monitoring' => 'forbidden',
        ],
    ],
];
