<?php

$founderApproverIds = array_values(array_filter(array_map(
    static fn (string $value): ?int => ctype_digit(trim($value)) ? (int) trim($value) : null,
    explode(',', (string) env('NAJM_HODA_FOUNDER_APPROVER_USER_IDS', ''))
), static fn (?int $value): bool => $value !== null && $value > 0));

return [
    /*
     | Founder Operations authority policy
     |
     | Modes:
     |   observe            read-only visibility
     |   propose            may prepare a recommendation/draft only
     |   approval_required  execution requires explicit founder approval
     |   delegated_safe     may execute only when delegation is explicitly enabled
     |   forbidden          must never execute through Founder Ops
     */
    'default_mode' => 'forbidden',

    'domains' => [
        'users' => ['actions' => [
            'view_profile_summary' => 'observe', 'draft_support_response' => 'propose',
            'send_support_response' => 'approval_required', 'suspend_user' => 'approval_required', 'delete_user' => 'forbidden',
        ]],
        'support' => ['actions' => [
            'classify_ticket' => 'delegated_safe', 'assign_priority' => 'delegated_safe', 'draft_reply' => 'delegated_safe',
            'send_reply' => 'approval_required', 'close_ticket' => 'approval_required',
        ]],
        'reference_data' => ['actions' => [
            'detect_duplicate' => 'delegated_safe', 'recommend_approval' => 'propose',
            'approve' => 'approval_required', 'reject' => 'approval_required', 'delete' => 'forbidden',
        ]],
        'locations' => ['actions' => [
            'detect_duplicate' => 'delegated_safe', 'recommend_approval' => 'propose',
            'approve' => 'approval_required', 'reject' => 'approval_required', 'delete' => 'forbidden',
        ]],
        'groups' => ['actions' => [
            'summarize_activity' => 'delegated_safe', 'propose_action_item' => 'delegated_safe',
            'change_member_role' => 'approval_required', 'close_group' => 'approval_required', 'delete_group' => 'forbidden',
        ]],
        'governance' => ['actions' => [
            'summarize_election' => 'delegated_safe', 'flag_anomaly' => 'delegated_safe',
            'change_election_rules' => 'approval_required', 'alter_vote' => 'forbidden', 'alter_result' => 'forbidden',
        ]],
        'reports_moderation' => ['actions' => [
            'classify_report' => 'delegated_safe', 'prepare_case_summary' => 'delegated_safe',
            'resolve_report' => 'approval_required', 'sanction_user' => 'approval_required',
        ]],
        'email' => ['actions' => [
            'draft_email' => 'delegated_safe', 'preview_template' => 'delegated_safe',
            'edit_template' => 'approval_required', 'send_email' => 'approval_required', 'bulk_send' => 'approval_required',
        ]],
        'blog' => ['actions' => [
            'draft_post' => 'delegated_safe', 'suggest_edit' => 'delegated_safe',
            'publish_post' => 'approval_required', 'unpublish_post' => 'approval_required', 'delete_post' => 'approval_required',
        ]],
        'content' => ['actions' => [
            'draft_faq_answer' => 'delegated_safe', 'draft_page_update' => 'delegated_safe',
            'publish_content' => 'approval_required', 'delete_content' => 'approval_required',
        ]],
        'notifications' => ['actions' => [
            'draft_announcement' => 'delegated_safe', 'publish_announcement' => 'approval_required',
            'change_global_notification_defaults' => 'approval_required',
        ]],
        'invitations' => ['actions' => [
            'summarize_growth' => 'delegated_safe', 'recommend_request_decision' => 'propose',
            'issue_invitation' => 'approval_required', 'reject_invitation_request' => 'approval_required',
        ]],
        'secretariat' => ['actions' => [
            'draft_correspondence' => 'delegated_safe', 'prepare_follow_up' => 'delegated_safe',
            'register_formal_record' => 'approval_required', 'dispatch_formal_record' => 'approval_required',
            'close_case' => 'approval_required', 'rewrite_history' => 'forbidden',
        ]],
        'stock' => ['actions' => [
            'summarize_auction' => 'delegated_safe', 'flag_settlement_issue' => 'delegated_safe',
            'create_auction' => 'approval_required', 'settle_auction' => 'approval_required',
            'transfer_shares' => 'approval_required', 'alter_ownership_history' => 'forbidden',
        ]],
        'najm_bahar' => ['actions' => [
            'summarize_financial_state' => 'delegated_safe', 'flag_transaction_anomaly' => 'delegated_safe',
            'draft_project_review' => 'delegated_safe', 'approve_project' => 'approval_required',
            'execute_transaction' => 'approval_required', 'change_monetary_policy' => 'approval_required',
            'alter_ledger_history' => 'forbidden',
        ]],
        'admin_settings' => ['actions' => [
            'audit_configuration' => 'delegated_safe', 'recommend_change' => 'propose',
            'change_setting' => 'approval_required', 'change_role_permission' => 'approval_required',
            'disable_audit_controls' => 'forbidden',
        ]],
        'runtime_health' => ['actions' => [
            'collect_health_snapshot' => 'delegated_safe', 'classify_incident' => 'delegated_safe',
            'run_read_only_diagnostic' => 'delegated_safe', 'restart_external_service' => 'approval_required',
            'destroy_data' => 'forbidden',
        ]],
    ],

    // Empty means approval-required actions remain non-executable even if another admin approves them.
    'founder_approval' => [
        'user_ids' => $founderApproverIds,
    ],

    // Delegated-safe actions are disabled globally until the founder explicitly enables delegation.
    'delegation' => [
        'enabled' => false,
        'allowed_domains' => [],
        'allowed_actions' => [],
    ],
];
