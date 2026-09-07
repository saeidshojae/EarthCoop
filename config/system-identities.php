<?php

return [
    'support' => [
        'email' => env('EARTHCOOP_SUPPORT_IDENTITY_EMAIL', 'support@earthcoop.ir'),
        'first_name' => 'تیم پشتیبانی',
        'last_name' => 'EarthCoop',
        'mail_from_name' => env('EARTHCOOP_SUPPORT_FROM_NAME', 'تیم پشتیبانی EarthCoop'),
    ],

    'management' => [
        'email' => env('EARTHCOOP_MANAGEMENT_IDENTITY_EMAIL', 'management@earthcoop.ir'),
        'first_name' => 'تیم مدیریت',
        'last_name' => 'EarthCoop',
        'mail_from_name' => env('EARTHCOOP_MANAGEMENT_FROM_NAME', 'تیم مدیریت EarthCoop'),
    ],
];
