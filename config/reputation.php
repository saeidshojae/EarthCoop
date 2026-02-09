<?php

return [
    // Default weights for events
    'weights' => [
        'email_verified' => 50,
        'profile_completed' => 30,
        'post_created' => 10,
        'post_upvoted' => 5,
        'comment_created' => 2,
        'comment_upvoted' => 1,
        'bid_placed' => 1,
        'bid_won' => 20,
        'successful_settlement' => 30,
        'report_received' => -10,
        'bid_canceled' => -15,
        'fraud' => -100,
        // Profile-related optional fields
        'profile_photo_uploaded' => 10,
        'social_links_added' => 5,
        'documents_uploaded' => 20,
        'bio_added' => 5,

        // Group polls & elections
        'poll_created' => 5,
        'poll_participated' => 2,
        'election_participated' => 5,
        'election_candidate' => 10,
        'elected_inspector' => 50,
        'elected_manager' => 100,
    ],

    // Tier thresholds
    'tiers' => [
        'Bronze' => 0,
        'Silver' => 200,
        'Gold' => 1000,
        'Platinum' => 5000,
    ],

    // Daily caps to prevent abuse (per action-key)
    'daily_caps' => [
        'post_upvoted' => 50,
        'comment_upvoted' => 100,
        'bid_placed' => 500,
        // limit poll participation abuse
        'poll_participated' => 100,
    ],

    // Decay settings (monthly percentage to remove)
    'decay' => [
        'enabled' => false,
        'monthly_percent' => 5,
    ],
];
