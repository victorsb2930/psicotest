<?php

return [
    'metrics' => [
        'limit' => 30,
        'period_seconds' => 60,
        'retry_after_seconds' => 15,
    ],
    'heartbeat' => [
        'min_interval_seconds' => 8,
        'burst_leeway' => 2,
        'window' => [
            'limit' => 120,
            'period_seconds' => 900,
        ],
        'retry_after_seconds' => 5,
    ],
    'logging' => [
        'violation_sample_every' => 5,
    ],
];
