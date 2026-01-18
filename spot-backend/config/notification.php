<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Infomation of Line Chanel ID
    |--------------------------------------------------------------------------
    |
    | Include Client_ID & Client_Secret
    |
    */


    'line' => [
        'client_id' => env('LINE_CLIENT_ID', null),
        'client_secret' => env('LINE_CLIENT_SECRET', null),

    ],
];
