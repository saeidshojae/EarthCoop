<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | This option controls the default broadcaster that will be used by the
    | framework when an event needs to be broadcast. You may set this to
    | any of the connections defined in the "connections" array below.
    |
    | Supported: "pusher", "ably", "redis", "log", "null"
    |
    */

    'default' => env('BROADCAST_CONNECTION', env('BROADCAST_DRIVER', 'null')),

    'pusher' => [
        'driver' => 'pusher',
        'key' => env('REVERB_APP_KEY', env('PUSHER_APP_KEY')),
        'secret' => env('REVERB_APP_SECRET', env('PUSHER_APP_SECRET')),
        'app_id' => env('REVERB_APP_ID', env('PUSHER_APP_ID')),
        'options' => [
            'cluster' => env('PUSHER_APP_CLUSTER', 'mt1'),
            'host' => env('REVERB_HOST', env('PUSHER_HOST', 'api-' . env('PUSHER_APP_CLUSTER', 'mt1') . '.pusher.com')),
            'port' => env('REVERB_PORT', env('PUSHER_PORT', 443)),
            'scheme' => env('REVERB_SCHEME', env('PUSHER_SCHEME', 'https')),
            'encrypted' => env('REVERB_SCHEME', env('PUSHER_SCHEME', 'https')) === 'https',
            'useTLS' => env('REVERB_SCHEME', env('PUSHER_SCHEME', 'https')) === 'https',
        ],
    ],

    'connections' => [

        'pusher' => [
            'driver' => 'pusher',
            'key' => env('REVERB_APP_KEY', env('PUSHER_APP_KEY')),
            'secret' => env('REVERB_APP_SECRET', env('PUSHER_APP_SECRET')),
            'app_id' => env('REVERB_APP_ID', env('PUSHER_APP_ID')),
            'options' => [
                'host' => env('REVERB_HOST', env('PUSHER_HOST', 'api-' . env('PUSHER_APP_CLUSTER', 'mt1') . '.pusher.com')),
                'port' => env('REVERB_PORT', env('PUSHER_PORT', 443)),
                'scheme' => env('REVERB_SCHEME', env('PUSHER_SCHEME', 'https')),
                'encrypted' => env('REVERB_SCHEME', env('PUSHER_SCHEME', 'https')) === 'https',
                'useTLS' => env('REVERB_SCHEME', env('PUSHER_SCHEME', 'https')) === 'https',
            ],
            'client_options' => [
                // Guzzle client options: https://docs.guzzlephp.org/en/stable/request-options.html
                // Keep the polling fallback responsive when the local realtime
                // process is temporarily unavailable.
                'connect_timeout' => (float) env('GROUP_CHAT_BROADCAST_CONNECT_TIMEOUT', 0.35),
                'timeout' => (float) env('GROUP_CHAT_BROADCAST_TIMEOUT', 0.75),
            ],
        ],

        'ably' => [
            'driver' => 'ably',
            'key' => env('ABLY_KEY'),
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
