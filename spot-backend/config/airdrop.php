<?php

return [
    'total_amal' => env('TOTAL_AMAL', 126000000),
    'min_hold_amal' => env('MIN_HOLD_AMAL', 100000),
    'percision_round' => env('PERCISION_ROUND', 0.000000001),
    'waiting_time_unlock' => 0,    // minutes - waiting time between Lock Balance Job and Unlock Job
    'airdrop_setting_live_time_cache' => 7200, // seconds
    'email' => 'amanpuri@gmail.com', //email of user who paid airdrop
    'unlock_percent_for_special_type' => 100, // Unlock percent for AMAL from buying in Salepoint
    'period_for_special_type' => 30, // Period for AMAL from buying in Salepoint
    'start_unlock' => '2020-03-26', // Starting day unlock AMAL YYYY/MM/DD
    'start_unlock_admin_type' => env('DIVIDEND_ENABLE_ADMIN_TYPE', 0), // Starting Unlock Perpetual, 0 is off
    'enable_special_type_unlock' => env('DIVIDEND_ENABLE_SPECIAL_TYPE', 0), // Convert all type unlock to special before open margin //enable airdrop
    'enable_admin_type_unlock' => env('DIVIDEND_ENABLE_ADMIN_TYPE', 0) // enable dividend (auto+manual)
];
