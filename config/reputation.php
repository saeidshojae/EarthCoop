<?php

return [
    // Default weights for events
    'weights' => [
        'email_verified' => 50,
        'profile_completed' => 30,
        // Latest launch policy: a completed invitation earns 100 participation points.
        'invite_member' => 100,
        'membership_fee_paid' => 12,
        'post_created' => 10,
        'post_liked' => 1,
        'post_upvoted' => 5,
        'comment_created' => 2,
        'comment_liked' => 1,
        'comment_upvoted' => 1,
        'bid_placed' => 1,
        'bid_won' => 20,
        'successful_settlement' => 30,
        'report_received' => -10,
        'bid_canceled' => -15,
        'fraud' => -100,
        'profile_photo_uploaded' => 10,
        'social_links_added' => 5,
        'documents_uploaded' => 20,
        'bio_added' => 5,

        // Group polls, systemic election outcomes & verified governance outcomes.
        'poll_created' => 5,
        'poll_participated' => 2,
        'elected_inspector' => 50,
        'elected_manager' => 100,
        'professional_referral_completed' => 10,
    ],

    // Policy defaults are used only when a rule is first bootstrapped into the database.
    // Existing database rules remain authoritative and are never overwritten by config.
    'policy_defaults' => [
        'invite_member' => [
            'dimension' => 'participation',
            'convertible' => true,
            'repeat_policy' => 'once_per_context',
        ],
        'membership_fee_paid' => [
            'dimension' => 'participation',
            'convertible' => true,
            'repeat_policy' => 'once_per_context',
        ],
        'profile_photo_uploaded' => [
            'dimension' => 'participation',
            'convertible' => false,
            'repeat_policy' => 'once_per_context',
        ],
        'social_links_added' => [
            'dimension' => 'participation',
            'convertible' => false,
            'repeat_policy' => 'once_per_context',
        ],
        'documents_uploaded' => [
            'dimension' => 'participation',
            'convertible' => false,
            'repeat_policy' => 'once_per_context',
        ],
        'bio_added' => [
            'dimension' => 'participation',
            'convertible' => false,
            'repeat_policy' => 'once_per_context',
        ],
        'report_received' => [
            'dimension' => 'reliability',
            'convertible' => false,
            'repeat_policy' => 'once_per_context',
        ],
        'bid_canceled' => [
            'dimension' => 'reliability',
            'convertible' => false,
            'repeat_policy' => 'once_per_context',
        ],
        'fraud' => [
            'dimension' => 'reliability',
            'convertible' => false,
            'repeat_policy' => 'once_per_context',
        ],
        'post_liked' => [
            'dimension' => 'participation',
            'convertible' => true,
            'repeat_policy' => 'once_per_context',
        ],
        'post_upvoted' => [
            'dimension' => 'participation',
            'convertible' => true,
            'repeat_policy' => 'once_per_context',
        ],
        'comment_liked' => [
            'dimension' => 'participation',
            'convertible' => true,
            'repeat_policy' => 'once_per_context',
        ],
        'comment_upvoted' => [
            'dimension' => 'participation',
            'convertible' => true,
            'repeat_policy' => 'once_per_context',
        ],
        'poll_created' => [
            'dimension' => 'participation',
            'convertible' => true,
            'repeat_policy' => 'once_per_context',
        ],
        'poll_participated' => [
            'dimension' => 'participation',
            'convertible' => true,
            'repeat_policy' => 'once_per_context',
        ],
        'elected_manager' => [
            'dimension' => 'participation',
            'convertible' => false,
            'repeat_policy' => 'once_per_context',
        ],
        'elected_inspector' => [
            'dimension' => 'participation',
            'convertible' => false,
            'repeat_policy' => 'once_per_context',
        ],
        'professional_referral_completed' => [
            'dimension' => 'participation',
            'convertible' => false,
            'repeat_policy' => 'once_per_context',
        ],
    ],

    'tiers' => [
        'Bronze' => 0,
        'Silver' => 200,
        'Gold' => 1000,
        'Platinum' => 5000,
    ],

    // Daily caps are awarded points over a rolling day, not event counts.
    'daily_caps' => [
        'post_created' => 50,
        'comment_created' => 20,
        'post_liked' => 20,
        'post_upvoted' => 50,
        'comment_liked' => 20,
        'comment_upvoted' => 100,
        'bid_placed' => 500,
        'poll_created' => 25,
        'poll_participated' => 100,
        'professional_referral_completed' => 50,
    ],

    'decay' => [
        'enabled' => false,
        'monthly_percent' => 5,
    ],
];
