<?php

return [
    'timezone' => env('DUTY_ROSTER_TIMEZONE', 'Asia/Manila'),
    'online_timeout_minutes' => env('DUTY_ROSTER_ONLINE_TIMEOUT_MINUTES', 3),

    // Oct 30 starts with Jeb (BCP-0003), then Peter (BCP-0001), then Cecilio (BCP-0002).
    'rotation_start_date' => env('DUTY_ROSTER_START_DATE', '2026-10-30'),
    'rotation_start_guard' => env('DUTY_ROSTER_START_GUARD', 'BCP-0003'),
    'rotation_start_hour' => env('DUTY_ROSTER_START_HOUR', 8),
    'rotation_guards' => [
        'BCP-0001',
        'BCP-0002',
        'BCP-0003',
    ],
    'fixed_evening_guards' => [
        'BCP-0004',
    ],
];
