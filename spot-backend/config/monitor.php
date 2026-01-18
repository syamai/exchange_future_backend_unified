<?php 
return [
    'memory' => [
        'threshold' => env('MEMORY_THRESHOLD', 128), //MB
        'enable_send_alert' => env('ENABLE_SEND_MEMORY_ALERT', true),
    ],
    'prometheus' => [
        'enabled' => env('PROMETHEUS_ENABLED', false),
    ],
];