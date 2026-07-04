<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Group Chat Runtime Flags
    |--------------------------------------------------------------------------
    |
    | This file controls how group chat transport works at runtime.
    | "auto" uses websocket when available and falls back to polling.
    | Keep defaults conservative for intranet / limited-connectivity setups.
    |
    */
    'enabled' => env('GROUP_CHAT_ENABLED', true),

    // auto | polling | websocket
    'transport' => env('GROUP_CHAT_TRANSPORT', 'auto'),

    'fallback_to_polling' => env('GROUP_CHAT_FALLBACK_TO_POLLING', true),

    // If true, chat broadcast events are dispatched after HTTP response is sent.
    // This keeps message/post/poll submit APIs responsive even when broadcaster is slow.
    'defer_broadcasts' => env('GROUP_CHAT_DEFER_BROADCASTS', true),

    // Server-side latency instrumentation for key chat write endpoints.
    'api_timing' => [
        'enabled' => env('GROUP_CHAT_API_TIMING_ENABLED', true),
        'log' => env('GROUP_CHAT_API_TIMING_LOG', false),
        'slow_ms' => (int) env('GROUP_CHAT_API_TIMING_SLOW_MS', 1200),
    ],

    // Safe default for high-load groups and unstable networks.
    'polling_interval_ms' => (int) env('GROUP_CHAT_POLLING_INTERVAL_MS', 1800),

    // Hard bounds to avoid accidental overload from misconfiguration.
    'polling_min_interval_ms' => 1000,
    'polling_max_interval_ms' => 10000,

    // Cache TTL for private channel auth membership checks.
    'channel_auth_cache_ttl_seconds' => (int) env('GROUP_CHAT_CHANNEL_AUTH_CACHE_TTL_SECONDS', 30),

    // Feature toggles for staged rollout (non-breaking migration path).
    'features' => [
        'message_no_reload' => env('GROUP_CHAT_FEATURE_MESSAGE_NO_RELOAD', true),
        'reaction_no_reload' => env('GROUP_CHAT_FEATURE_REACTION_NO_RELOAD', true),
        'post_no_reload' => env('GROUP_CHAT_FEATURE_POST_NO_RELOAD', true),
        'poll_no_reload' => env('GROUP_CHAT_FEATURE_POLL_NO_RELOAD', true),
        'realtime_messages' => env('GROUP_CHAT_FEATURE_REALTIME_MESSAGES', true),
        'realtime_reactions' => env('GROUP_CHAT_FEATURE_REALTIME_REACTIONS', true),
        'realtime_posts' => env('GROUP_CHAT_FEATURE_REALTIME_POSTS', true),
        'realtime_polls' => env('GROUP_CHAT_FEATURE_REALTIME_POLLS', true),
    ],
];
