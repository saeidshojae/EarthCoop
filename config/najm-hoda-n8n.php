<?php

return [
    'enabled' => env('NAJM_HODA_N8N_ENABLED', false),
    'base_url' => rtrim((string) env('NAJM_HODA_N8N_BASE_URL', ''), '/'),
    'health_path' => env('NAJM_HODA_N8N_HEALTH_PATH', '/healthz'),
    'dispatch_path' => env('NAJM_HODA_N8N_DISPATCH_PATH', '/webhook/najm-hoda'),
    'shared_secret' => env('NAJM_HODA_N8N_SHARED_SECRET'),
    'timeout_seconds' => (int) env('NAJM_HODA_N8N_TIMEOUT_SECONDS', 8),
    'max_payload_bytes' => (int) env('NAJM_HODA_N8N_MAX_PAYLOAD_BYTES', 32768),

    // Milestone 1 is deliberately read-only/propose-only. Apply-capable workflows
    // must not be added here until approval, permissioning and GameDay evidence exist.
    'allowed_workflows' => [
        'ops.health.read' => 'read_only',
        'support.triage.propose' => 'propose_only',
        'content.brief.propose' => 'propose_only',
    ],
];
