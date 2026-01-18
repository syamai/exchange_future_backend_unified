<?php
return [
    'chunk_limit_checkpoint' => env('CHUNK_LIMIT_CHECKPOINT', 100),
    'referrer_client_level' => [
        0 => [
            'tradeRange' => ['min' => 0],
            'volume' => 0,
            'rate' => 0,
            'label' => 'Basic'
        ],
        1 => [
            'tradeRange' => ['min' => 1],
            'volume' => 500000,
            'rate' => 20,
            'label' => 'Bronze'
        ],
        2 => [
            'tradeRange' => ['min' => 11],
            'volume' => 1000000,
            'rate' => 25,
            'label' => 'Silver'
        ],
        3 => [
            'tradeRange' => ['min' => 26],
            'volume' => 2000000,
            'rate' => 30,
            'label' => 'Gold'
        ],
        4 => [
            'tradeRange' => ['min' => 51],
            'volume' => 3000000,
            'rate' => 50,
            'label' => 'Platinum'
        ],
        5 =>  [
            'tradeRange' => ['min' => 101],
            'volume' => 5000000,
            'rate' => 60,
            'label' => 'Diamond'
        ]
    ],
    'referrer_message' => [
        'register' => 'New referral registration',
        'co_received' => 'Commission received',
        'tier_up' => 'Tier upgrade',
        'tier_down' => 'Tier downgrade',
        'tier_created' => 'Tier registration'
    ]
];
