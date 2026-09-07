<?php

return [
    // Default weights for events that have a verified runtime award path.
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
        'poll_created' => 5,
        'poll_participated' => 2,
        'elected_inspector' => 50,
        'elected_manager' => 100,
        'professional_referral_completed' => 10,
    ],

    // Policy defaults are used only when a rule is first bootstrapped into the database
    // or when no DB rule exists yet. Existing DB rules remain authoritative.
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

    // Caps are awarded points in the rolling previous 24 hours, not event counts.
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
