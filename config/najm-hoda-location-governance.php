<?php

return [
    'capability' => [
        'name' => 'Location Governance Proposal Review',
        'enabled' => true,
        'version' => 1,
        'risk' => 'medium',
        'mode' => 'propose',
        'human_approval_required' => true,
        'required_input' => ['proposal_id'],
        'optional_input' => ['recommendation', 'duplicate_candidate_id', 'distinct_verifiers'],
        'output' => ['recommendation', 'rationale', 'duplicate_candidate_id', 'anomalies'],
    ],
    'allowed_action' => 'review_location_governance_proposal',
    'goal_scope' => ['review_location_governance'],
    'blocked_sensitive_actions' => [
        'approve_location_governance_proposal',
        'reject_location_governance_proposal',
        'merge_location_governance_proposal',
        'request_location_governance_evidence',
    ],
];
