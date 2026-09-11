<?php

return [
    // Dark-launch switches. Canonical location/governance runtime stays disabled
    // until its corresponding migration phase is explicitly enabled.
    'runtime_enabled' => filter_var(env('LOCATION_GOVERNANCE_RUNTIME_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'registration_enabled' => filter_var(env('LOCATION_GOVERNANCE_REGISTRATION_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'groups_enabled' => filter_var(env('LOCATION_GOVERNANCE_GROUPS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'elections_enabled' => filter_var(env('LOCATION_GOVERNANCE_ELECTIONS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'projects_enabled' => filter_var(env('LOCATION_GOVERNANCE_PROJECTS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    // Production-readiness target and immutable release evidence. These values
    // are read-only inputs to location-governance:readiness; the command never
    // imports data, changes flags, or mutates the database.
    'target_country' => strtoupper((string) env('LOCATION_GOVERNANCE_TARGET_COUNTRY', 'IR')),
    'target_schema' => (string) env('LOCATION_GOVERNANCE_TARGET_SCHEMA', 'ir-reference-v1'),
    'target_dataset_source' => (string) env('LOCATION_GOVERNANCE_TARGET_DATASET_SOURCE', 'earthcoop-reference'),
    'target_dataset_version' => (string) env('LOCATION_GOVERNANCE_TARGET_DATASET_VERSION', 'v1'),
    'validation_sha' => env('LOCATION_GOVERNANCE_VALIDATION_SHA'),
    'uat_evidence' => env('LOCATION_GOVERNANCE_UAT_EVIDENCE'),

    // Distinct supporters make a crowdsourced location ready for human review;
    // reaching this threshold never auto-approves the proposal.
    'location_proposal_verification_threshold' => max(1, (int) env('LOCATION_GOVERNANCE_PROPOSAL_VERIFICATION_THRESHOLD', 10)),
];
