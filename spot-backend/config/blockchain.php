<?php

/**
 * Created by PhpStorm.
 * Date: 4/16/19
 * Time: 9:25 AM
 */

return [
    'network' => env('MIX_BLOCKCHAIN_NETWORK'),
    'sb_url' => env('SB_URL'),
    'sb_token' => env('SB_TOKEN'),
    'key_coin_marketcap' => env('API_KEY_COINMARKET'),
    'eos_url' => env('URL_EOS_WALLET_SERVICE'),
    'usdt_url' => env('URL_USDT_WALLET_SERVICE'),
    'not_convert_currencies' => env('NOT_CONVERT_CURRENCIES', ['eos', 'xrp']),
    'api_wallet' => env('API_WALLET'),
    'port_wallet' => env('PORT_WALLET'),
    'x_api_key_wallet' => env('X_API_KEY_WALLET'),

    'wallet_id' => [
        'eth' => env('SOTATEK_ETH_WALLET_ID'),
        'btc' => env('SOTATEK_BTC_WALLET_ID'),
        'amal' => env('SOTATEK_AML_WALLET_ID'),
        'xrp' => env('SOTATEK_XRP_WALLET_ID'),
        'bch' => env('SOTATEK_BCH_WALLET_ID'),
        'eos' => env('SOTATEK_EOS_WALLET_ID'),
        'ada' => env('SOTATEK_ADA_WALLET_ID'),
        'ltc' => env('SOTATEK_LTC_WALLET_ID'),
        'usdt' => env('SOTATEK_USDT_WALLET_ID'),
    ],
    'google_recaptcha_uri' => env('GOOGLE_RECAPTCHA_URI'),
    'google_recaptcha_secret' => env('GOOGLE_RECAPTCHA_SECRET'),

    'auth' => [
        'exchange' => [
            'username' => env('EXCHANGE_USERNAME'),
            'password' => env('EXCHANGE_PASSWORD'),
        ],
        'wallet' => [
            'username' => env('WALLET_USERNAME'),
            'password' => env('WALLET_USERNAME'),
        ]
    ]
];
