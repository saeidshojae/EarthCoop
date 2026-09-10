<?php

return [
    // Dark-launch switches. Canonical location/governance runtime stays disabled
    // until its corresponding migration phase is explicitly enabled.
    'runtime_enabled' => filter_var(env('LOCATION_GOVERNANCE_RUNTIME_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'registration_enabled' => filter_var(env('LOCATION_GOVERNANCE_REGISTRATION_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'groups_enabled' => filter_var(env('LOCATION_GOVERNANCE_GROUPS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'elections_enabled' => filter_var(env('LOCATION_GOVERNANCE_ELECTIONS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'projects_enabled' => filter_var(env('LOCATION_GOVERNANCE_PROJECTS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
];
