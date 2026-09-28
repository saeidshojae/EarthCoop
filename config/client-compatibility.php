<?php

return [
    'android' => [
        'minimum_version' => env('ANDROID_MINIMUM_VERSION', '1.0.0'),
        'latest_version' => env('ANDROID_LATEST_VERSION', '1.0.0'),
    ],
    'ios' => [
        'minimum_version' => env('IOS_MINIMUM_VERSION', '1.0.0'),
        'latest_version' => env('IOS_LATEST_VERSION', '1.0.0'),
    ],
];
