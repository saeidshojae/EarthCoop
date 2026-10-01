<?php

return [
    'enabled' => (bool) env('DEPLOYMENT_CONSOLE_ENABLED', false),
    'secret' => env('DEPLOYMENT_CONSOLE_SECRET'),

    'vendor_package_directory' => storage_path('deployment/vendor-packages'),
    'vendor_manifest' => storage_path('deployment/vendor-packages/manifest.json'),
    'vendor_installed_state' => storage_path('deployment/vendor-packages/installed.json'),
    'vendor_staging_directory' => storage_path('deployment/vendor-staging'),
    'vendor_live_directory' => base_path('vendor'),
    'composer_lock_path' => base_path('composer.lock'),
    'vendor_backup_retention' => 1,
];
